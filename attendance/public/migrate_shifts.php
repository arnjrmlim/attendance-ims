<?php
/**
 * Shift Management Module — one-time migration script.
 *
 * Adds the `description` and `is_default` columns to the shifts table,
 * backfills the default flag, and seeds "Regular Office Hours" if empty.
 *
 * Run once via browser:
 *   http://localhost/attendance-ims/attendance/public/migrate_shifts.php
 *
 * Safe to run multiple times — all operations are idempotent.
 * Delete this file after running.
 */

$config = require dirname(__DIR__) . '/config/database.php';

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $db  = new PDO($dsn, $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

$steps = [];

// ── 1. Add `description` column ──────────────────────────────────────────────
$hasDesc = (int) $db->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'shifts'
        AND COLUMN_NAME  = 'description'"
)->fetchColumn();

if ($hasDesc === 0) {
    $db->exec("ALTER TABLE `shifts` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `name`");
    $steps[] = ['ok', 'Added <code>description</code> column to <code>shifts</code>.'];
} else {
    $steps[] = ['skip', '<code>description</code> column already exists — skipped.'];
}

// ── 2. Add `is_default` column ───────────────────────────────────────────────
$hasDef = (int) $db->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'shifts'
        AND COLUMN_NAME  = 'is_default'"
)->fetchColumn();

if ($hasDef === 0) {
    $db->exec("ALTER TABLE `shifts` ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");

    $idxExists = (int) $db->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'shifts'
            AND INDEX_NAME   = 'idx_shifts_default'"
    )->fetchColumn();
    if ($idxExists === 0) {
        $db->exec("ALTER TABLE `shifts` ADD KEY `idx_shifts_default` (`is_default`)");
    }

    $steps[] = ['ok', 'Added <code>is_default</code> column (+ index) to <code>shifts</code>.'];
} else {
    $steps[] = ['skip', '<code>is_default</code> column already exists — skipped.'];
}

// ── 3. Seed "Regular Office Hours" if table is empty ────────────────────────
$shiftCount = (int) $db->query("SELECT COUNT(*) FROM shifts")->fetchColumn();

if ($shiftCount === 0) {
    $id = sprintf('%08x-%04x-4%03x-%04x-%012x',
        random_int(0, 0xffffffff), random_int(0, 0xffff),
        random_int(0, 0xfff),
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffffffffffff)
    );
    $db->prepare(
        "INSERT INTO shifts
           (id, name, description, type,
            time_in, time_out,
            lunch_break_start, lunch_break_end, lunch_break_minutes,
            grace_period_minutes, required_hours,
            overnight, status, is_default)
         VALUES
           (?, 'Regular Office Hours',
            'Standard 8 AM \xe2\x80\x93 5 PM office shift with a 1-hour lunch break.',
            'regular', '08:00:00', '17:00:00',
            '12:00:00', '13:00:00', 60,
            15, 8.00, 0, 'active', 1)"
    )->execute([$id]);
    $steps[] = ['ok', 'Seeded default <strong>Regular Office Hours</strong> shift (08:00 AM – 05:00 PM).'];
} else {
    $steps[] = ['info', $shiftCount . ' existing shift(s) found — seed skipped.'];

    // ── 4. Ensure exactly one shift is the default ───────────────────────────
    $defaultCount = (int) $db->query(
        "SELECT COUNT(*) FROM shifts WHERE is_default = 1"
    )->fetchColumn();

    if ($defaultCount === 0) {
        $firstRow = $db->query(
            "SELECT id, name FROM shifts WHERE status = 'active' ORDER BY name LIMIT 1"
        )->fetch();
        if ($firstRow) {
            $db->prepare("UPDATE shifts SET is_default = 1 WHERE id = ?")->execute([$firstRow['id']]);
            $steps[] = ['ok', 'Marked <strong>' . htmlspecialchars($firstRow['name']) . '</strong> as the default shift (none was set).'];
        }
    } elseif ($defaultCount > 1) {
        // Keep the one with the lowest name alphabetically, clear others
        $keepRow = $db->query(
            "SELECT id, name FROM shifts WHERE is_default = 1 ORDER BY name LIMIT 1"
        )->fetch();
        $db->prepare("UPDATE shifts SET is_default = 0 WHERE is_default = 1 AND id != ?")->execute([$keepRow['id']]);
        $steps[] = ['warn', 'Fixed: ' . $defaultCount . ' default shifts found. Kept <strong>' . htmlspecialchars($keepRow['name']) . '</strong>, cleared the rest.'];
    } else {
        $defRow = $db->query("SELECT name FROM shifts WHERE is_default = 1 LIMIT 1")->fetch();
        $steps[] = ['skip', 'Default shift already set: <strong>' . htmlspecialchars($defRow['name']) . '</strong> — skipped.'];
    }
}

// ── 5. Summary counts ────────────────────────────────────────────────────────
$finalCount   = (int) $db->query("SELECT COUNT(*) FROM shifts")->fetchColumn();
$defaultCount = (int) $db->query("SELECT COUNT(*) FROM shifts WHERE is_default = 1")->fetchColumn();
$steps[] = ['info', "Database now has <strong>{$finalCount}</strong> shift(s), <strong>{$defaultCount}</strong> marked as default."];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width:660px">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <i class="fs-5">⚙️</i>
            <h5 class="mb-0">Shift Management — Database Migration</h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($steps as [$type, $msg]):
                    $icon  = ['ok' => '✅', 'skip' => '⏭️', 'warn' => '⚠️', 'info' => 'ℹ️'][$type] ?? '•';
                    $color = ['ok' => 'success', 'skip' => 'secondary', 'warn' => 'warning', 'info' => 'primary'][$type] ?? 'secondary';
                ?>
                <li class="list-group-item d-flex gap-3 align-items-start py-3">
                    <span class="fs-5 lh-1"><?= $icon ?></span>
                    <span class="text-<?= $color ?>"><?= $msg ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card-footer">
            <div class="alert alert-success mb-3">
                <strong>Migration complete.</strong>
                You can delete <code>public/migrate_shifts.php</code> from your server now.
            </div>
            <a href="index.php?url=shifts" class="btn btn-primary">
                Go to Shift Management →
            </a>
        </div>
    </div>
</div>
</body>
</html>
