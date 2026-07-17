<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DirectoryService;
use App\Services\ReportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

final class ReportController extends BaseController
{
    public function index(): void
    {
        require_role(['administrator', 'hr']);
        $rows = (new ReportService())->rows($_GET);
        $directory = new DirectoryService();
        $this->render('reports/index', [
            'title' => 'Reports',
            'rows' => $rows,
            'totals' => (new ReportService())->totals($rows),
            'employees' => $directory->employees(),
            'departments' => $directory->departments(),
            'branches' => $directory->branches(),
            'shifts' => $directory->shifts(),
            'period' => date_range_label($_GET['start_date'] ?? null, $_GET['end_date'] ?? null),
        ]);
    }

    public function export(): void
    {
        require_role(['administrator', 'hr']);
        $format = strtolower((string) ($_GET['format'] ?? 'csv'));
        $rows = (new ReportService())->rows($_GET);
        
        if ($format === 'pdf') {
            header('Content-Type: text/html; charset=utf-8');
            echo '<script>window.print()</script>';
            $this->index();
            return;
        }

        if ($format === 'xlsx') {
            $this->exportExcel($rows);
            return;
        }

        // CSV export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance-report.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['Employee No', 'Employee', 'Department', 'Branch', 'Date', 'Status', 'Time In', 'Time Out', 'Late', 'Undertime', 'Hours']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['employee_number'],
                $row['employee_name'],
                $row['department_name'],
                $row['branch_name'],
                $row['display_date'],
                $row['day_status'],
                $row['time_in'],
                $row['time_out'],
                $row['late_minutes'],
                $row['undertime_minutes'],
                $row['total_hours'],
            ]);
        }
        fclose($out);
    }

    private function exportExcel(array $rows): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Helper function to convert column number to letter
        $getColumnLetter = function($col) {
            $letter = '';
            while ($col > 0) {
                $col--;
                $letter = chr(65 + ($col % 26)) . $letter;
                $col = floor($col / 26);
            }
            return $letter;
        };

        // Set headers
        $headers = [
            'Employee No',
            'Employee',
            'Department',
            'Branch',
            'Date',
            'Status',
            'Leave Type',
            'Leave Duration',
            'Time In',
            'Break Out',
            'Break In',
            'Time Out',
            'Overtime In',
            'Overtime Out',
            'Late (Minutes)',
            'Break (Minutes)',
            'Overtime (Minutes)',
            'Undertime (Minutes)',
            'Hours Worked'
        ];

        $col = 1;
        foreach ($headers as $header) {
            $coordinate = $getColumnLetter($col) . '1';
            $cell = $sheet->getCell($coordinate);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        // Freeze header row
        $sheet->freezePane('A2');

        // Add data rows
        $row = 2;
        foreach ($rows as $data) {
            $sheet->setCellValue($getColumnLetter(1) . $row, $data['employee_number']);
            $sheet->setCellValue($getColumnLetter(2) . $row, $data['employee_name']);
            $sheet->setCellValue($getColumnLetter(3) . $row, $data['department_name']);
            $sheet->setCellValue($getColumnLetter(4) . $row, $data['branch_name']);
            $sheet->setCellValue($getColumnLetter(5) . $row, $data['display_date']);
            $sheet->setCellValue($getColumnLetter(6) . $row, $data['day_status']);
            $sheet->setCellValue($getColumnLetter(7) . $row, $data['leave_type'] ?? '');
            $sheet->setCellValue($getColumnLetter(8) . $row, $data['leave_duration'] ? $data['leave_duration'] . ' days' : '');
            $sheet->setCellValue($getColumnLetter(9) . $row, $data['time_in'] ?? '');
            $sheet->setCellValue($getColumnLetter(10) . $row, $data['break_out'] ?? '');
            $sheet->setCellValue($getColumnLetter(11) . $row, $data['break_in'] ?? '');
            $sheet->setCellValue($getColumnLetter(12) . $row, $data['time_out'] ?? '');
            $sheet->setCellValue($getColumnLetter(13) . $row, $data['overtime_in'] ?? '');
            $sheet->setCellValue($getColumnLetter(14) . $row, $data['overtime_out'] ?? '');
            $sheet->setCellValue($getColumnLetter(15) . $row, $data['late_minutes'] ?? '');
            $sheet->setCellValue($getColumnLetter(16) . $row, $data['break_minutes'] ?? '');
            $sheet->setCellValue($getColumnLetter(17) . $row, $data['overtime_minutes'] ?? '');
            $sheet->setCellValue($getColumnLetter(18) . $row, $data['undertime_minutes'] ?? '');
            $sheet->setCellValue($getColumnLetter(19) . $row, $data['total_hours'] ?? '');
            $row++;
        }

        // Auto-size columns
        foreach (range(1, 19) as $col) {
            $sheet->getColumnDimension($getColumnLetter($col))->setAutoSize(true);
        }

        // Generate Excel file
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="attendance-report.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}
