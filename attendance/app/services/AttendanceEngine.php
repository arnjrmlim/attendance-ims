<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * AttendanceEngine
 *
 * Pure calculation layer.  Every write path that touches attendance
 * (manual entry, QR, RFID, admin direct, correction approval) must call
 * rebuildSummary() after inserting / updating / deleting raw attendance rows.
 *
 * Official value rules (applied per employee per date):
 *   time_in      → MIN(time_recorded)   earliest tap wins
 *   break_out    → MIN(time_recorded)   earliest break start wins
 *   break_in     → MAX(time_recorded)   latest break end wins
 *   time_out     → MAX(time_recorded)   latest tap wins
 *   overtime_in  → MIN(time_recorded)   earliest OT start wins
 *   overtime_out → MAX(time_recorded)   latest OT end wins
 *
 * lunch_out / lunch_in (legacy) are treated as aliases for
 * break_out / break_in respectively.
 *
 * Calculated fields:
 *   total_hours      = regular worked time (time_in → time_out minus break)
 *   break_minutes    = break_in − break_out  (0 if either is missing)
 *   overtime_minutes = overtime_out − overtime_in (0 if either is missing)
 *   late_minutes     = time_in − shift_start  after grace period
 *   undertime_minutes= (required_hours − actual_hours) * 60  when < required
 *   is_late          = late_minutes > 0
 */
final class AttendanceEngine
{
    // ----------------------------------------------------------------
    // Public constants — canonical type names used throughout the app
    // ----------------------------------------------------------------

    public const TYPE_TIME_IN     = 'time_in';
    public const TYPE_BREAK_OUT   = 'break_out';
    public const TYPE_BREAK_IN    = 'break_in';
    public const TYPE_TIME_OUT    = 'time_out';
    public const TYPE_OT_IN       = 'overtime_in';
    public const TYPE_OT_OUT      = 'overtime_out';

    /** All valid types in the order they appear in a full attendance day. */
    public const VALID_TYPES = [
        self::TYPE_TIME_IN,
        self::TYPE_BREAK_OUT,
        self::TYPE_BREAK_IN,
        self::TYPE_TIME_OUT,
        self::TYPE_OT_IN,
        self::TYPE_OT_OUT,
    ];

    /** Human-readable labels for each type. */
    public const LABELS = [
        self::TYPE_TIME_IN   => 'Time In',
        self::TYPE_BREAK_OUT => 'Break Out',
        self::TYPE_BREAK_IN  => 'Break In',
        self::TYPE_TIME_OUT  => 'Time Out',
        self::TYPE_OT_IN     => 'Overtime In',
        self::TYPE_OT_OUT    => 'Overtime Out',
        // Legacy aliases
        'lunch_out'          => 'Break Out',
        'lunch_in'           => 'Break In',
    ];

    /**
     * Map legacy lunch_* types to their canonical equivalents so that
     * existing rows are processed correctly by the engine.
     */
    public const LEGACY_MAP = [
        'lunch_out' => self::TYPE_BREAK_OUT,
        'lunch_in'  => self::TYPE_BREAK_IN,
    ];

    // ----------------------------------------------------------------
    // Core rebuild entry-point
    // ----------------------------------------------------------------

    /**
     * Recalculate and persist attendance_summary for one employee on one date.
     *
     * Call this after ANY insert / update / delete on the `attendance` table
     * for the given employee + date.
     *
     * @param string $employeeId UUID
     * @param string $date       Y-m-d
     */
    public function rebuildSummary(string $employeeId, string $date): void
    {
        // Ensure the schema is up to date before any read or write
        $this->ensureSchema();

        $db = Database::connection();

        // ── 1. Load all raw attendance rows for this employee + date ──
        $stmt = $db->prepare(
            "SELECT attendance_type, time_recorded
               FROM attendance
              WHERE employee_id    = ?
                AND attendance_date = ?
              ORDER BY time_recorded ASC"
        );
        $stmt->execute([$employeeId, $date]);
        $rows = $stmt->fetchAll();

        // ── 2. Derive official timestamps using earliest/latest rules ──
        $official = $this->deriveOfficial($rows);

        // ── 3. If no rows at all, remove the summary row and return ───
        if (empty($rows)) {
            $db->prepare(
                "DELETE FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
            )->execute([$employeeId, $date]);
            return;
        }

        // ── 4. Load employee's shift for metric calculation ───────────
        $shift = $this->getShift($employeeId);

        // ── 5. Compute derived metrics ────────────────────────────────
        $metrics = $this->computeMetrics($official, $shift, $date);

        // ── 6. Upsert attendance_summary ──────────────────────────────
        $this->upsertSummary($employeeId, $date, $official, $metrics);
    }

    /**
     * Insert a single raw attendance row then immediately rebuild the summary.
     * This is the canonical write path for QR / RFID / manual taps.
     *
     * @param string      $employeeId
     * @param string      $date        Y-m-d
     * @param string      $datetime    Y-m-d H:i:s
     * @param string      $type        One of VALID_TYPES (or legacy alias)
     * @param string      $method      pin | qr_code | rfid | manual
     * @param string|null $recordedBy  user UUID if admin/manual
     * @param string|null $deviceName
     * @param string|null $ipAddress
     * @return string  UUID of the new attendance row
     */
    public function recordTap(
        string  $employeeId,
        string  $date,
        string  $datetime,
        string  $type,
        string  $method      = 'manual',
        ?string $recordedBy  = null,
        ?string $deviceName  = null,
        ?string $ipAddress   = null
    ): string {
        $type = $this->canonicalType($type);
        
        // Check if employee has approved leave for this date
        $stmt = Database::connection()->prepare(
            "SELECT id, leave_type FROM leave_requests 
             WHERE employee_id = ? 
             AND status = 'Approved' 
             AND ? BETWEEN start_date AND end_date"
        );
        $stmt->execute([$employeeId, $date]);
        $leave = $stmt->fetch();
        
        if ($leave) {
            throw new \InvalidArgumentException(
                "You already have an approved {$leave['leave_type']} for {$date}. Attendance cannot be recorded for dates covered by approved leave."
            );
        }
        
        $id   = uuid_v4();

        Database::connection()->prepare(
            "INSERT INTO attendance
             (id, employee_id, attendance_date, time_recorded, attendance_type,
              method, device_name, ip_address, recorded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$id, $employeeId, $date, $datetime, $type,
                    $method, $deviceName, $ipAddress, $recordedBy]);

        $this->rebuildSummary($employeeId, $date);
        return $id;
    }

    /**
     * Delete a single raw attendance row and rebuild the summary.
     */
    public function deleteTap(string $attendanceId): void
    {
        $db   = Database::connection();
        $stmt = $db->prepare("SELECT employee_id, attendance_date FROM attendance WHERE id = ?");
        $stmt->execute([$attendanceId]);
        $row  = $stmt->fetch();
        if (!$row) {
            return;
        }
        $db->prepare("DELETE FROM attendance WHERE id = ?")->execute([$attendanceId]);
        $this->rebuildSummary($row['employee_id'], $row['attendance_date']);
    }

    /**
     * Fetch all raw attendance rows for a date, grouped by type, in chronological
     * order — used for the "Attendance History" display.
     *
     * @return list<array{type:string,label:string,time_recorded:string,method:string}>
     */
    public function getHistory(string $employeeId, string $date): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT attendance_type, time_recorded, method, device_name, ip_address
               FROM attendance
              WHERE employee_id    = ?
                AND attendance_date = ?
              ORDER BY time_recorded ASC"
        );
        $stmt->execute([$employeeId, $date]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): array {
            $canonical = $this->canonicalType($row['attendance_type']);
            return [
                'type'          => $canonical,
                'label'         => self::LABELS[$canonical] ?? $canonical,
                'time_recorded' => $row['time_recorded'],
                'method'        => $row['method'],
                'device_name'   => $row['device_name'],
                'ip_address'    => $row['ip_address'],
            ];
        }, $rows);
    }

    /**
     * Return the official (computed) summary for one employee on one date.
     * Reads from attendance_summary — always consistent after rebuildSummary().
     */
    public function getOfficialSummary(string $employeeId, string $date): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM attendance_summary
              WHERE employee_id    = ?
                AND attendance_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetch() ?: null;
    }

    // ----------------------------------------------------------------
    // Derivation logic
    // ----------------------------------------------------------------

    /**
     * From a list of raw rows, derive the single official value for each type.
     *
     * Rules:
     *   time_in      MIN
     *   break_out    MIN  (also absorbs lunch_out)
     *   break_in     MAX  (also absorbs lunch_in)
     *   time_out     MAX
     *   overtime_in  MIN
     *   overtime_out MAX
     *
     * @param  list<array{attendance_type:string,time_recorded:string}> $rows
     * @return array<string,string|null>  keys are canonical type names
     */
    public function deriveOfficial(array $rows): array
    {
        $buckets = [
            self::TYPE_TIME_IN   => [],
            self::TYPE_BREAK_OUT => [],
            self::TYPE_BREAK_IN  => [],
            self::TYPE_TIME_OUT  => [],
            self::TYPE_OT_IN     => [],
            self::TYPE_OT_OUT    => [],
        ];

        foreach ($rows as $row) {
            $canonical = $this->canonicalType($row['attendance_type']);
            if (array_key_exists($canonical, $buckets)) {
                $buckets[$canonical][] = $row['time_recorded'];
            }
        }

        return [
            self::TYPE_TIME_IN   => $this->pick($buckets[self::TYPE_TIME_IN],   'min'),
            self::TYPE_BREAK_OUT => $this->pick($buckets[self::TYPE_BREAK_OUT], 'min'),
            self::TYPE_BREAK_IN  => $this->pick($buckets[self::TYPE_BREAK_IN],  'max'),
            self::TYPE_TIME_OUT  => $this->pick($buckets[self::TYPE_TIME_OUT],  'max'),
            self::TYPE_OT_IN     => $this->pick($buckets[self::TYPE_OT_IN],     'min'),
            self::TYPE_OT_OUT    => $this->pick($buckets[self::TYPE_OT_OUT],    'max'),
        ];
    }

    // ----------------------------------------------------------------
    // Metric computation
    // ----------------------------------------------------------------

    /**
     * Compute derived attendance metrics from official timestamps + shift data.
     *
     * @param  array<string,string|null> $official  output of deriveOfficial()
     * @param  array|false               $shift     row from shifts table (or false)
     * @param  string                    $date      Y-m-d (needed to anchor shift times)
     * @return array<string,mixed>
     */
    public function computeMetrics(array $official, array|false $shift, string $date): array
    {
        $timeIn   = $official[self::TYPE_TIME_IN]   ?? null;
        $breakOut = $official[self::TYPE_BREAK_OUT] ?? null;
        $breakIn  = $official[self::TYPE_BREAK_IN]  ?? null;
        $timeOut  = $official[self::TYPE_TIME_OUT]  ?? null;
        $otIn     = $official[self::TYPE_OT_IN]     ?? null;
        $otOut    = $official[self::TYPE_OT_OUT]    ?? null;

        // ── Break duration ────────────────────────────────────────────
        $breakMinutes = 0;
        if ($breakOut && $breakIn) {
            $diff = (int) round((strtotime($breakIn) - strtotime($breakOut)) / 60);
            $breakMinutes = max(0, $diff);
        }

        // ── Overtime duration ─────────────────────────────────────────
        $overtimeMinutes = 0;
        if ($otIn && $otOut) {
            $diff = (int) round((strtotime($otOut) - strtotime($otIn)) / 60);
            $overtimeMinutes = max(0, $diff);
        }

        // ── Total regular hours ───────────────────────────────────────
        $totalHours = null;
        if ($timeIn && $timeOut) {
            $grossSeconds  = strtotime($timeOut) - strtotime($timeIn);
            $netSeconds    = $grossSeconds - ($breakMinutes * 60);
            $totalHours    = round(max(0, $netSeconds) / 3600, 2);
        }

        // ── Late / undertime  (requires shift) ───────────────────────
        $lateMinutes      = 0;
        $undertimeMinutes = 0;
        $isLate           = false;

        if ($shift && $timeIn) {
            $shiftStart  = strtotime($date . ' ' . $shift['time_in']);
            $gracePeriod = (int) ($shift['grace_period_minutes'] ?? 15);
            $tapIn       = strtotime($timeIn);

            if ($tapIn > $shiftStart) {
                $rawLate  = (int) round(($tapIn - $shiftStart) / 60);
                $lateMinutes = max(0, $rawLate - $gracePeriod);
                $isLate      = $lateMinutes > 0;
            }

            // Undertime: net hours < required hours
            if ($totalHours !== null) {
                $required = (float) ($shift['required_hours'] ?? 8.0);
                if ($totalHours < $required) {
                    $undertimeMinutes = (int) round(($required - $totalHours) * 60);
                }
            }
        }

        // ── Day status ────────────────────────────────────────────────
        $dayStatus = $timeIn ? 'present' : 'absent';

        return [
            'break_minutes'    => $breakMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'total_hours'      => $totalHours,
            'late_minutes'     => $lateMinutes,
            'undertime_minutes'=> $undertimeMinutes,
            'is_late'          => $isLate ? 1 : 0,
            'day_status'       => $dayStatus,
        ];
    }

    // ----------------------------------------------------------------
    // Persistence
    // ----------------------------------------------------------------

    /**
     * Ensure all attendance_summary columns introduced by the break/OT
     * enhancement exist.  Safe to call on every write — the INFORMATION_SCHEMA
     * check is cheap and the ALTER runs at most once per column per DB.
     */
    private function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $db = Database::connection();

        // Columns that must exist on attendance_summary
        $needed = [
            'break_out'     => "ALTER TABLE `attendance_summary` ADD COLUMN `break_out`     DATETIME         DEFAULT NULL   AFTER `time_in`",
            'break_in'      => "ALTER TABLE `attendance_summary` ADD COLUMN `break_in`      DATETIME         DEFAULT NULL   AFTER `break_out`",
            'overtime_in'   => "ALTER TABLE `attendance_summary` ADD COLUMN `overtime_in`   DATETIME         DEFAULT NULL   AFTER `time_out`",
            'overtime_out'  => "ALTER TABLE `attendance_summary` ADD COLUMN `overtime_out`  DATETIME         DEFAULT NULL   AFTER `overtime_in`",
            'break_minutes' => "ALTER TABLE `attendance_summary` ADD COLUMN `break_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `overtime_out`",
        ];

        foreach ($needed as $col => $ddl) {
            $exists = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'attendance_summary'
                    AND COLUMN_NAME  = '{$col}'"
            )->fetchColumn();

            if ($exists === 0) {
                $db->exec($ddl);
            }
        }

        // Also ensure the attendance_type ENUM includes all 6 types
        // (check for 'break_out' in the ENUM values as proxy)
        $enumRow = $db->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'attendance'
                AND COLUMN_NAME  = 'attendance_type'"
        )->fetchColumn();

        if ($enumRow && strpos($enumRow, 'break_out') === false) {
            $db->exec(
                "ALTER TABLE `attendance`
                 MODIFY COLUMN `attendance_type`
                 ENUM('time_in','break_out','break_in','time_out',
                      'overtime_in','overtime_out','lunch_out','lunch_in') NOT NULL"
            );
            // Drop the old unique index that prevents multiple taps of the same type
            $idxExists = (int) $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'attendance'
                    AND INDEX_NAME   = 'uq_attendance_type'"
            )->fetchColumn();
            if ($idxExists > 0) {
                $db->exec("ALTER TABLE `attendance` DROP INDEX `uq_attendance_type`");
            }
        }

        // Fix manual_attendance_requests.request_type ENUM if break/OT types are missing
        $marEnum = $db->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'manual_attendance_requests'
                AND COLUMN_NAME  = 'request_type'"
        )->fetchColumn();

        if ($marEnum && strpos((string) $marEnum, 'break_out') === false) {
            $db->exec(
                "ALTER TABLE `manual_attendance_requests`
                 MODIFY COLUMN `request_type`
                 ENUM('time_in','break_out','break_in','time_out','overtime_in','overtime_out') NOT NULL"
            );
        }
    }

    private function upsertSummary(
        string $employeeId,
        string $date,
        array  $official,
        array  $metrics
    ): void {
        // Self-heal: add any missing columns before the first write
        $this->ensureSchema();

        $db = Database::connection();

        // Check if row already exists
        $stmt = $db->prepare(
            "SELECT id FROM attendance_summary WHERE employee_id = ? AND attendance_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        $existing = $stmt->fetchColumn();

        $params = [
            'time_in'          => $official[self::TYPE_TIME_IN],
            'break_out'        => $official[self::TYPE_BREAK_OUT],
            'break_in'         => $official[self::TYPE_BREAK_IN],
            'time_out'         => $official[self::TYPE_TIME_OUT],
            'overtime_in'      => $official[self::TYPE_OT_IN],
            'overtime_out'     => $official[self::TYPE_OT_OUT],
            // keep legacy columns in sync
            'lunch_out'        => $official[self::TYPE_BREAK_OUT],
            'lunch_in'         => $official[self::TYPE_BREAK_IN],
            'total_hours'      => $metrics['total_hours'],
            'break_minutes'    => $metrics['break_minutes'],
            'late_minutes'     => $metrics['late_minutes'],
            'undertime_minutes'=> $metrics['undertime_minutes'],
            'overtime_minutes' => $metrics['overtime_minutes'],
            'is_late'          => $metrics['is_late'],
            'day_status'       => $metrics['day_status'],
        ];

        if ($existing) {
            $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($params)));
            $params['employee_id']     = $employeeId;
            $params['attendance_date'] = $date;
            $db->prepare(
                "UPDATE attendance_summary SET {$sets}, updated_at = NOW()
                  WHERE employee_id = :employee_id AND attendance_date = :attendance_date"
            )->execute($params);
        } else {
            $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($params)));
            $phs  = implode(', ', array_map(fn($k) => ":{$k}", array_keys($params)));
            $params['id']             = uuid_v4();
            $params['employee_id']    = $employeeId;
            $params['attendance_date'] = $date;
            $db->prepare(
                "INSERT INTO attendance_summary (id, employee_id, attendance_date, {$cols})
                 VALUES (:id, :employee_id, :attendance_date, {$phs})"
            )->execute($params);
        }
    }

    // ----------------------------------------------------------------
    // Shift helper
    // ----------------------------------------------------------------

    private function getShift(string $employeeId): array|false
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.time_in, s.time_out, s.grace_period_minutes,
                    s.required_hours, s.lunch_break_minutes
               FROM shifts s
               INNER JOIN employees e ON e.shift_id = s.id
              WHERE e.id = ?"
        );
        $stmt->execute([$employeeId]);
        return $stmt->fetch();
    }

    // ----------------------------------------------------------------
    // Internal helpers
    // ----------------------------------------------------------------

    /**
     * Normalize a raw attendance_type value (including legacy aliases) to
     * its canonical form.
     */
    public function canonicalType(string $type): string
    {
        return self::LEGACY_MAP[$type] ?? $type;
    }

    /**
     * Pick the min or max timestamp from an array of datetime strings.
     * Returns null when the array is empty.
     */
    private function pick(array $timestamps, string $mode): ?string
    {
        if (empty($timestamps)) {
            return null;
        }
        sort($timestamps); // ascending string sort works for Y-m-d H:i:s
        return $mode === 'min' ? $timestamps[0] : $timestamps[count($timestamps) - 1];
    }
}
