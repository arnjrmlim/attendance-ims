<?php

declare(strict_types=1);

namespace App\Services;

final class ReportService
{
    public function rows(array $filters): array
    {
        return (new AttendanceService())->monitor($filters);
    }

    public function totals(array $rows): array
    {
        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'undertime' => 0];
        foreach ($rows as $row) {
            $status = $row['day_status'] ?? '';
            if (isset($totals[$status])) {
                $totals[$status]++;
            }
            if (!empty($row['is_late'])) {
                $totals['late']++;
            }
            if (!empty($row['undertime_minutes'])) {
                $totals['undertime']++;
            }
        }
        return $totals;
    }
}
