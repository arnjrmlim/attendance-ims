<?php

/**
 * AttendanceExcelReportService
 *
 * Generates Excel (.xlsx) attendance reports with multiple sheets.
 * Uses PhpSpreadsheet library for professional Excel formatting.
 *
 * Sheets:
 * 1. Attendance Summary - Monthly summary per employee
 * 2. Daily Attendance - Detailed daily records
 * 3. Statistics - Report statistics and metrics
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

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ams_reports';
        
        // Create temp directory if it doesn't exist
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0775, true);
        }
    }

    /**
     * Generate Excel report for a given date.
     *
     * @param string $reportDate The report date (YYYY-MM-DD)
     * @return string|null Path to the generated Excel file, or null on failure
     */
    public function generate(string $reportDate): ?string
    {
        try {
            // Check if PhpSpreadsheet is available
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                error_log('PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
                return null;
            }

            $spreadsheet = new Spreadsheet();
            
            // Remove the default active sheet
            $spreadsheet->removeSheetByIndex(0);

            // Generate sheets
            $this->generateAttendanceSummarySheet($spreadsheet, $reportDate);
            $this->generateDailyAttendanceSheet($spreadsheet, $reportDate);
            $this->generateStatisticsSheet($spreadsheet, $reportDate);

            // Set the first sheet as active
            $spreadsheet->setActiveSheetIndex(0);

            // Save the file
            $filename = 'attendance_report_' . $reportDate . '.xlsx';
            $filepath = $this->tempDir . DIRECTORY_SEPARATOR . $filename;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);

            return $filepath;
        } catch (\Throwable $e) {
            error_log('Failed to generate Excel report: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate the Attendance Summary sheet.
     *
     * @param Spreadsheet $spreadsheet The spreadsheet object
     * @param string $reportDate The report date
     */
    private function generateAttendanceSummarySheet(Spreadsheet $spreadsheet, string $reportDate): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Attendance Summary');

        // Get data
        $db = Database::connection();
        $monthStart = date('Y-m-01', strtotime($reportDate));
        $monthEnd = date('Y-m-t', strtotime($reportDate));

        $stmt = $db->prepare(
            "SELECT 
                e.employee_number,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                d.name AS department,
                b.name AS branch,
                COUNT(s.id) AS total_days,
                SUM(CASE WHEN s.day_status = 'present' THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN s.day_status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                SUM(CASE WHEN s.day_status = 'leave' THEN 1 ELSE 0 END) AS leave_days,
                SUM(s.total_hours) AS total_hours,
                SUM(s.late_minutes) AS total_late_minutes,
                SUM(s.undertime_minutes) AS total_undertime_minutes
             FROM attendance_summary s
             INNER JOIN employees e ON e.id = s.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE s.attendance_date BETWEEN ? AND ?
             GROUP BY e.id
             ORDER BY e.last_name, e.first_name"
        );
        $stmt->execute([$monthStart, $monthEnd]);
        $data = $stmt->fetchAll();

        // Set headers
        $headers = [
            'Employee Number',
            'Employee Name',
            'Department',
            'Branch',
            'Total Days',
            'Present Days',
            'Absent Days',
            'Leave Days',
            'Total Hours',
            'Late Minutes',
            'Undertime Minutes'
        ];

        // Write headers
        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCell([$col, 1]);
            $cell->setValue($header);
            $this->applyHeaderStyle($cell);
            $col++;
        }

        // Write data
        $row = 2;
        foreach ($data as $record) {
            $sheet->setCellValue('A' . $row, $record['employee_number']);
            $sheet->setCellValue('B' . $row, $record['employee_name']);
            $sheet->setCellValue('C' . $row, $record['department'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, $record['branch'] ?? 'N/A');
            $sheet->setCellValue('E' . $row, (int) $record['total_days']);
            $sheet->setCellValue('F' . $row, (int) $record['present_days']);
            $sheet->setCellValue('G' . $row, (int) $record['absent_days']);
            $sheet->setCellValue('H' . $row, (int) $record['leave_days']);
            $sheet->setCellValue('I' . $row, (float) ($record['total_hours'] ?? 0));
            $sheet->setCellValue('J' . $row, (int) ($record['total_late_minutes'] ?? 0));
            $sheet->setCellValue('K' . $row, (int) ($record['total_undertime_minutes'] ?? 0));
            $row++;
        }

        // Apply formatting
        $this->applySheetFormatting($sheet, $row - 1, 11);
        
        // Format hours column as number with 2 decimal places
        $sheet->getStyle('I2:I' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
    }

    /**
     * Generate the Daily Attendance sheet.
     *
     * @param Spreadsheet $spreadsheet The spreadsheet object
     * @param string $reportDate The report date
     */
    private function generateDailyAttendanceSheet(Spreadsheet $spreadsheet, string $reportDate): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Daily Attendance');

        // Get data
        $db = Database::connection();
        $monthStart = date('Y-m-01', strtotime($reportDate));
        $monthEnd = date('Y-m-t', strtotime($reportDate));

        $stmt = $db->prepare(
            "SELECT 
                e.employee_number,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                d.name AS department,
                b.name AS branch,
                s.attendance_date,
                s.day_status AS status,
                s.time_in,
                s.time_out,
                s.total_hours,
                s.late_minutes,
                s.undertime_minutes
             FROM attendance_summary s
             INNER JOIN employees e ON e.id = s.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE s.attendance_date BETWEEN ? AND ?
             ORDER BY s.attendance_date, e.last_name, e.first_name"
        );
        $stmt->execute([$monthStart, $monthEnd]);
        $data = $stmt->fetchAll();

        // Set headers
        $headers = [
            'Employee Number',
            'Employee Name',
            'Department',
            'Branch',
            'Attendance Date',
            'Status',
            'Time In',
            'Time Out',
            'Total Hours',
            'Late Minutes',
            'Undertime Minutes'
        ];

        // Write headers
        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCell([$col, 1]);
            $cell->setValue($header);
            $this->applyHeaderStyle($cell);
            $col++;
        }

        // Write data
        $row = 2;
        foreach ($data as $record) {
            $sheet->setCellValue('A' . $row, $record['employee_number']);
            $sheet->setCellValue('B' . $row, $record['employee_name']);
            $sheet->setCellValue('C' . $row, $record['department'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, $record['branch'] ?? 'N/A');
            $sheet->setCellValue('E' . $row, $record['attendance_date']);
            $sheet->setCellValue('F' . $row, $record['status']);
            $sheet->setCellValue('G' . $row, $record['time_in'] ?? '');
            $sheet->setCellValue('H' . $row, $record['time_out'] ?? '');
            $sheet->setCellValue('I' . $row, (float) ($record['total_hours'] ?? 0));
            $sheet->setCellValue('J' . $row, (int) ($record['late_minutes'] ?? 0));
            $sheet->setCellValue('K' . $row, (int) ($record['undertime_minutes'] ?? 0));
            $row++;
        }

        // Apply formatting
        $this->applySheetFormatting($sheet, $row - 1, 11);
        
        // Format dates as YYYY-MM-DD
        $sheet->getStyle('E2:E' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');
        
        // Format hours column as number with 2 decimal places
        $sheet->getStyle('I2:I' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
    }

    /**
     * Generate the Statistics sheet.
     *
     * @param Spreadsheet $spreadsheet The spreadsheet object
     * @param string $reportDate The report date
     */
    private function generateStatisticsSheet(Spreadsheet $spreadsheet, string $reportDate): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Statistics');

        // Get data
        $db = Database::connection();
        $monthStart = date('Y-m-01', strtotime($reportDate));
        $monthEnd = date('Y-m-t', strtotime($reportDate));

        // Calculate statistics
        $stmt = $db->prepare(
            "SELECT 
                COUNT(DISTINCT e.id) AS total_employees,
                SUM(CASE WHEN s.day_status = 'present' THEN 1 ELSE 0 END) AS total_present,
                SUM(CASE WHEN s.day_status = 'absent' THEN 1 ELSE 0 END) AS total_absent,
                SUM(CASE WHEN s.day_status = 'leave' THEN 1 ELSE 0 END) AS total_leave,
                COUNT(s.id) AS total_records
             FROM attendance_summary s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE s.attendance_date BETWEEN ? AND ?"
        );
        $stmt->execute([$monthStart, $monthEnd]);
        $stats = $stmt->fetch();

        // Calculate attendance percentage
        $totalRecords = (int) $stats['total_records'];
        $totalPresent = (int) $stats['total_present'];
        $attendancePercentage = $totalRecords > 0 
            ? round(($totalPresent / $totalRecords) * 100, 2) 
            : 0;

        // Write statistics
        $statistics = [
            ['Report Date', $reportDate],
            ['Report Period', $monthStart . ' to ' . $monthEnd],
            ['Total Employees', (int) $stats['total_employees']],
            ['Total Present Records', $totalPresent],
            ['Total Absent Records', (int) $stats['total_absent']],
            ['Total Leave Records', (int) $stats['total_leave']],
            ['Total Records', $totalRecords],
            ['Average Attendance Percentage', $attendancePercentage . '%']
        ];

        $row = 1;
        foreach ($statistics as $stat) {
            $sheet->setCellValue('A' . $row, $stat[0]);
            $sheet->setCellValue('B' . $row, $stat[1]);
            
            // Style the label column
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'B') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders
        $sheet->getStyle('A1:B' . ($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
    }

    /**
     * Apply header styling to a cell.
     *
     * @param mixed $cell The cell to style
     */
    private function applyHeaderStyle($cell): void
    {
        $cell->getStyle()->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }

    /**
     * Apply common sheet formatting.
     *
     * @param mixed $sheet The sheet to format
     * @param int $lastRow The last data row
     * @param int $lastCol The last data column
     */
    private function applySheetFormatting($sheet, int $lastRow, int $lastCol): void
    {
        // Auto-size columns
        foreach (range('A', chr(64 + $lastCol)) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze first row
        $sheet->freezePane('A2');

        // Add filters
        $sheet->setAutoFilter('A1:' . chr(64 + $lastCol) . $lastRow);

        // Add borders to data
        $sheet->getStyle('A1:' . chr(64 + $lastCol) . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Center align headers
        $sheet->getStyle('A1:' . chr(64 + $lastCol) . '1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Clean up temporary Excel files older than specified days.
     *
     * @param int $days Number of days to keep files (default: 7)
     */
    public function cleanupOldFiles(int $days = 7): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $cutoff = time() - ($days * 86400); // 86400 seconds per day
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*.xlsx');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
