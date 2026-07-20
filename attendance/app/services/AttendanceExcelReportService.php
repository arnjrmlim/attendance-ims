<?php

/**
 * AttendanceExcelReportService
 *
 * Single source of truth for all Excel attendance exports.
 *
 * Used by:
 *   - ReportController::export()  (Reports → Attendance Monitoring → Download Excel)
 *   - EmailScheduleService        (email attachment)
 *   - Safe-Testing mode dry runs
 *
 * Both callers receive an identical file with the same columns, formatting,
 * and data because they both route through generateForPeriod().
 *
 * Sheets:
 *   1. Daily Attendance  — one row per employee per day (19 columns, matches
 *                          the monitoring page export exactly)
 *   2. Period Summary    — one row per employee with totals for the period
 *   3. Statistics        — aggregate numbers for the period
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

final class AttendanceExcelReportService
{
    private string $tempDir;

    // Header colour — same blue used in the monitoring UI
    private const HEADER_BG  = '1A56DB';
    private const HEADER_FG  = 'FFFFFF';

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ams_reports';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0775, true);
        }
    }

    // ----------------------------------------------------------------
    // Public entry points
    // ----------------------------------------------------------------

    /**
     * Generate an Excel file for an arbitrary date range.
     *
     * Used by ReportController for the "Download Excel" button.
     * Accepts the same $filters array that AttendanceService::monitor() uses
     * (start_date, end_date, employee_id, department_id, branch_id, q …).
     *
     * @param  array  $filters   GET/filter params forwarded from the report page
     * @param  string $periodLabel  Human-readable label for the Statistics sheet
     * @return string|null  Absolute path to the .xlsx file, or null on failure
     */
    public function generateFromFilters(array $filters, string $periodLabel = ''): ?string
    {
        $dateFrom    = $filters['start_date'] ?? date('Y-m-01');
        $dateTo      = $filters['end_date']   ?? date('Y-m-d');
        $periodLabel = $periodLabel ?: "{$dateFrom} to {$dateTo}";

        $rows = (new AttendanceService())->monitor($filters);
        return $this->buildExcel($rows, $dateFrom, $dateTo, $periodLabel);
    }

    /**
     * Generate an Excel file for an explicit period (used by EmailScheduleService).
     *
     * @param  string $dateFrom     YYYY-MM-DD period start
     * @param  string $dateTo       YYYY-MM-DD period end
     * @param  string $periodLabel  e.g. "July 2026 (1–15)"
     * @return string|null
     */
    public function generateForPeriod(string $dateFrom, string $dateTo, string $periodLabel): ?string
    {
        $filters = [
            'start_date' => $dateFrom,
            'end_date'   => $dateTo,
        ];
        $rows = (new AttendanceService())->monitor($filters);
        return $this->buildExcel($rows, $dateFrom, $dateTo, $periodLabel);
    }

    /**
     * Legacy entry point kept for backward compatibility.
     * Generates a full-month report anchored on $reportDate.
     */
    public function generate(string $reportDate): ?string
    {
        $dateFrom = date('Y-m-01', strtotime($reportDate));
        $dateTo   = date('Y-m-t',  strtotime($reportDate));
        $label    = date('F Y',    strtotime($reportDate));
        return $this->generateForPeriod($dateFrom, $dateTo, $label);
    }

    // ----------------------------------------------------------------
    // Core builder
    // ----------------------------------------------------------------

    /**
     * Build the multi-sheet .xlsx from pre-fetched monitor rows.
     *
     * @param  array  $rows        Output of AttendanceService::monitor()
     * @param  string $dateFrom
     * @param  string $dateTo
     * @param  string $periodLabel
     * @return string|null
     */
    public function buildExcel(
        array  $rows,
        string $dateFrom,
        string $dateTo,
        string $periodLabel
    ): ?string {
        try {
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                error_log('PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet');
                return null;
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            $this->buildDailySheet($spreadsheet, $rows);
            $this->buildSummarySheet($spreadsheet, $rows);
            $this->buildStatisticsSheet($spreadsheet, $dateFrom, $dateTo, $periodLabel);

            $spreadsheet->setActiveSheetIndex(0);

            // Descriptive filename:  Attendance_Report_2026-07_01-15.xlsx
            $filename = $this->buildFilename($dateFrom, $dateTo);
            $filepath = $this->tempDir . DIRECTORY_SEPARATOR . $filename;

            (new Xlsx($spreadsheet))->save($filepath);
            return $filepath;

        } catch (\Throwable $e) {
            error_log('AttendanceExcelReportService: ' . $e->getMessage());
            return null;
        }
    }

    // ----------------------------------------------------------------
    // Sheet 1 — Daily Attendance (matches monitoring export exactly)
    // ----------------------------------------------------------------

    private function buildDailySheet(Spreadsheet $ss, array $rows): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Daily Attendance');

        $headers = [
            'Employee No',
            'First Name', 'Middle Name', 'Last Name', 'Full Name',
            'Department', 'Branch',
            'Date', 'Day', 'Status',
            'Leave Type', 'Leave Duration',
            'Time In', 'Break Out', 'Break In', 'Time Out',
            'Overtime In', 'Overtime Out',
            'Late (min)', 'Break (min)', 'Overtime (min)', 'Undertime (min)',
            'Hours Worked', 'Shift',
        ];

        $this->writeHeaderRow($sheet, $headers);
        $sheet->freezePane('A2');

        $r = 2;
        foreach ($rows as $row) {
            $date = $row['attendance_date'] ?? $row['display_date'] ?? '';
            $day  = $date ? date('D', strtotime($date)) : '';   // Mon, Tue …

            $sheet->setCellValue("A{$r}", $row['employee_number']  ?? '');
            $sheet->setCellValue("B{$r}", $row['first_name']       ?? '');
            $sheet->setCellValue("C{$r}", $row['middle_name']      ?? '');
            $sheet->setCellValue("D{$r}", $row['last_name']        ?? '');
            $sheet->setCellValue("E{$r}", $row['employee_name']    ?? '');
            $sheet->setCellValue("F{$r}", $row['department_name']  ?? '');
            $sheet->setCellValue("G{$r}", $row['branch_name']      ?? '');
            $sheet->setCellValue("H{$r}", $date);
            $sheet->setCellValue("I{$r}", $day);
            $sheet->setCellValue("J{$r}", $row['day_status']       ?? '');
            $sheet->setCellValue("K{$r}", $row['leave_type']       ?? '');
            $sheet->setCellValue("L{$r}", $row['leave_duration']
                ? $row['leave_duration'] . ' day(s)' : '');
            $sheet->setCellValue("M{$r}", $this->fmtTime($row['time_in']      ?? null));
            $sheet->setCellValue("N{$r}", $this->fmtTime($row['break_out']    ?? null));
            $sheet->setCellValue("O{$r}", $this->fmtTime($row['break_in']     ?? null));
            $sheet->setCellValue("P{$r}", $this->fmtTime($row['time_out']     ?? null));
            $sheet->setCellValue("Q{$r}", $this->fmtTime($row['overtime_in']  ?? null));
            $sheet->setCellValue("R{$r}", $this->fmtTime($row['overtime_out'] ?? null));
            $sheet->setCellValue("S{$r}", (int) ($row['late_minutes']      ?? 0));
            $sheet->setCellValue("T{$r}", (int) ($row['break_minutes']     ?? 0));
            $sheet->setCellValue("U{$r}", (int) ($row['overtime_minutes']  ?? 0));
            $sheet->setCellValue("V{$r}", (int) ($row['undertime_minutes'] ?? 0));
            $sheet->setCellValue("W{$r}", $row['total_hours'] !== null
                ? round((float) $row['total_hours'], 2) : '');
            $sheet->setCellValue("X{$r}", $row['shift_name']       ?? '');
            $r++;
        }

        $lastRow = max($r - 1, 1);
        // Format date column (now H)
        $sheet->getStyle("H2:H{$lastRow}")
              ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        // Format decimal hours (now W)
        $sheet->getStyle("W2:W{$lastRow}")
              ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        $this->autoSize($sheet, 'X');
    }

    // ----------------------------------------------------------------
    // Sheet 2 — Period Summary (one row per employee)
    // ----------------------------------------------------------------

    private function buildSummarySheet(Spreadsheet $ss, array $rows): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Period Summary');

        $headers = [
            'Employee No',
            'First Name', 'Middle Name', 'Last Name', 'Full Name',
            'Department', 'Branch', 'Shift',
            'Days Present', 'Days Late', 'Days Absent', 'Days Leave',
            'Total Hours', 'Total Late (min)', 'Total Undertime (min)',
            'Total Overtime (min)', 'Total Break (min)',
        ];
        $this->writeHeaderRow($sheet, $headers);
        $sheet->freezePane('A2');

        // Aggregate by employee
        $byEmp = [];
        foreach ($rows as $row) {
            $eid = $row['employee_number'] ?? '';
            if (!isset($byEmp[$eid])) {
                $byEmp[$eid] = [
                    'employee_number'  => $row['employee_number']  ?? '',
                    'first_name'       => $row['first_name']       ?? '',
                    'middle_name'      => $row['middle_name']      ?? '',
                    'last_name'        => $row['last_name']        ?? '',
                    'employee_name'    => $row['employee_name']    ?? '',
                    'department_name'  => $row['department_name']  ?? '',
                    'branch_name'      => $row['branch_name']      ?? '',
                    'shift_name'       => $row['shift_name']       ?? '',
                    'present'          => 0,
                    'late'             => 0,
                    'absent'           => 0,
                    'leave'            => 0,
                    'total_hours'      => 0.0,
                    'late_min'         => 0,
                    'undertime_min'    => 0,
                    'overtime_min'     => 0,
                    'break_min'        => 0,
                ];
            }
            $status = $row['day_status'] ?? '';
            if ($status === 'present')   { $byEmp[$eid]['present']++; }
            if ($status === 'absent')    { $byEmp[$eid]['absent']++;  }
            if ($status === 'leave')     { $byEmp[$eid]['leave']++;   }
            if (!empty($row['is_late'])) { $byEmp[$eid]['late']++;    }
            $byEmp[$eid]['total_hours']   += (float) ($row['total_hours']       ?? 0);
            $byEmp[$eid]['late_min']      += (int)   ($row['late_minutes']      ?? 0);
            $byEmp[$eid]['undertime_min'] += (int)   ($row['undertime_minutes'] ?? 0);
            $byEmp[$eid]['overtime_min']  += (int)   ($row['overtime_minutes']  ?? 0);
            $byEmp[$eid]['break_min']     += (int)   ($row['break_minutes']     ?? 0);
        }

        $r = 2;
        foreach ($byEmp as $emp) {
            $sheet->setCellValue("A{$r}", $emp['employee_number']);
            $sheet->setCellValue("B{$r}", $emp['first_name']);
            $sheet->setCellValue("C{$r}", $emp['middle_name']);
            $sheet->setCellValue("D{$r}", $emp['last_name']);
            $sheet->setCellValue("E{$r}", $emp['employee_name']);
            $sheet->setCellValue("F{$r}", $emp['department_name']);
            $sheet->setCellValue("G{$r}", $emp['branch_name']);
            $sheet->setCellValue("H{$r}", $emp['shift_name']);
            $sheet->setCellValue("I{$r}", $emp['present']);
            $sheet->setCellValue("J{$r}", $emp['late']);
            $sheet->setCellValue("K{$r}", $emp['absent']);
            $sheet->setCellValue("L{$r}", $emp['leave']);
            $sheet->setCellValue("M{$r}", round($emp['total_hours'], 2));
            $sheet->setCellValue("N{$r}", $emp['late_min']);
            $sheet->setCellValue("O{$r}", $emp['undertime_min']);
            $sheet->setCellValue("P{$r}", $emp['overtime_min']);
            $sheet->setCellValue("Q{$r}", $emp['break_min']);
            $r++;
        }

        $lastRow = max($r - 1, 1);
        $sheet->getStyle("M2:M{$lastRow}")
              ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        $this->autoSize($sheet, 'Q');
    }

    // ----------------------------------------------------------------
    // Sheet 3 — Statistics
    // ----------------------------------------------------------------

    private function buildStatisticsSheet(
        Spreadsheet $ss,
        string $dateFrom,
        string $dateTo,
        string $periodLabel
    ): void {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Statistics');

        $db   = Database::connection();
        $stmt = $db->prepare(
            "SELECT
                COUNT(DISTINCT s.employee_id)                               AS total_employees,
                SUM(CASE WHEN s.day_status = 'present' THEN 1 ELSE 0 END)  AS total_present,
                SUM(CASE WHEN s.is_late    = 1          THEN 1 ELSE 0 END)  AS total_late,
                SUM(CASE WHEN s.day_status = 'absent'  THEN 1 ELSE 0 END)  AS total_absent,
                SUM(CASE WHEN s.day_status = 'leave'   THEN 1 ELSE 0 END)  AS total_leave,
                COUNT(s.id)                                                 AS total_records,
                ROUND(SUM(COALESCE(s.late_minutes, 0))       / 60, 2)      AS total_late_hours,
                ROUND(SUM(COALESCE(s.undertime_minutes, 0))  / 60, 2)      AS total_undertime_hours,
                ROUND(SUM(COALESCE(s.overtime_minutes, 0))   / 60, 2)      AS total_overtime_hours
             FROM attendance_summary s
             WHERE s.attendance_date BETWEEN ? AND ?"
        );
        $stmt->execute([$dateFrom, $dateTo]);
        $st = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $totalRecords = (int) ($st['total_records'] ?? 0);
        $totalPresent = (int) ($st['total_present'] ?? 0);
        $pct          = $totalRecords > 0
            ? round($totalPresent / $totalRecords * 100, 2)
            : 0;

        $rows = [
            ['Report Period',             $periodLabel],
            ['Date From',                 $dateFrom],
            ['Date To',                   $dateTo],
            ['Generated',                 date('Y-m-d H:i:s')],
            ['', ''],
            ['Employees with Records',    (int) ($st['total_employees']  ?? 0)],
            ['Total Records',             $totalRecords],
            ['Attendance Rate',           $pct . '%'],
            ['', ''],
            ['Present Days',              $totalPresent],
            ['Late Days',                 (int) ($st['total_late']         ?? 0)],
            ['Absent Days',               (int) ($st['total_absent']       ?? 0)],
            ['Leave Days',                (int) ($st['total_leave']        ?? 0)],
            ['', ''],
            ['Total Late Hours',          (float) ($st['total_late_hours']      ?? 0)],
            ['Total Undertime Hours',     (float) ($st['total_undertime_hours'] ?? 0)],
            ['Total Overtime Hours',      (float) ($st['total_overtime_hours']  ?? 0)],
        ];

        $r = 1;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$r}", $label);
            $sheet->setCellValue("B{$r}", $value);
            if ($label !== '') {
                $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            }
            $r++;
        }

        $last = $r - 1;
        $sheet->getStyle("A1:B{$last}")->applyFromArray([
            'borders' => ['allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'D1D5DB'],
            ]],
        ]);
        foreach (['A', 'B'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function writeHeaderRow($sheet, array $headers): void
    {
        $col = 1;
        foreach ($headers as $h) {
            $coord = $this->colLetter($col) . '1';
            $sheet->setCellValue($coord, $h);
            $sheet->getStyle($coord)->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => self::HEADER_FG],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::HEADER_BG],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ]);
            $col++;
        }
    }

    private function autoSize($sheet, string $lastCol): void
    {
        $lastOrd = ord(strtoupper($lastCol));
        for ($o = ord('A'); $o <= $lastOrd; $o++) {
            $sheet->getColumnDimension(chr($o))->setAutoSize(true);
        }
    }

    /** Convert a 1-based column number to Excel letter (A, B, … Z, AA …). */
    private function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n      = (int) floor($n / 26);
        }
        return $letter;
    }

    /**
     * Format a datetime/time value for display.
     * Returns 'HH:MM' from 'YYYY-MM-DD HH:MM:SS' or 'HH:MM:SS'.
     */
    private function fmtTime(?string $v): string
    {
        if (!$v) { return ''; }
        // Strip date portion if present
        if (strlen($v) > 8 && str_contains($v, ' ')) {
            $v = trim(explode(' ', $v)[1]);
        }
        // Return HH:MM only
        return substr($v, 0, 5);
    }

    /**
     * Build a descriptive filename.
     * e.g.  Attendance_Report_2026-07_01-15.xlsx
     *        Attendance_Report_2026-07_16-31.xlsx
     */
    private function buildFilename(string $dateFrom, string $dateTo): string
    {
        // Extract year-month and day ranges
        $ym      = date('Y-m', strtotime($dateFrom));  // 2026-07
        $dayFrom = date('d',   strtotime($dateFrom));  // 01
        $dayTo   = date('d',   strtotime($dateTo));    // 15

        return "Attendance_Report_{$ym}_{$dayFrom}-{$dayTo}.xlsx";
    }
}
