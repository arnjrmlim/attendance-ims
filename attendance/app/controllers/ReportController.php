<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DirectoryService;
use App\Services\ReportService;

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
        $extension = $format === 'xlsx' ? 'xls' : 'csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance-report.' . $extension . '"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['Employee No', 'Employee', 'Department', 'Branch', 'Date', 'Status', 'Time In', 'Time Out', 'Late', 'Undertime', 'Hours']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['employee_number'],
                $row['employee_name'],
                $row['department_name'],
                $row['branch_name'],
                $row['attendance_date'],
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
}
