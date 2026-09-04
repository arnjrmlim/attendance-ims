<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Corrections Report</title>
    <style>
        @media print {
            body { font-family: Arial, sans-serif; font-size: 12pt; }
            .no-print { display: none !important; }
            .print-header { border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
            .print-filters { margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; }
            .print-filter-item { margin-bottom: 5px; }
            .print-filter-label { font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; font-weight: bold; }
            tr { page-break-inside: avoid; }
            .status-Approved { color: green; font-weight: bold; }
            .status-Rejected { color: red; font-weight: bold; }
            .status-Cancelled { color: gray; font-weight: bold; }
            .status-Pending { color: orange; font-weight: bold; }
        }
        @media screen {
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
            .print-header { border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
            .print-filters { margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; }
            .print-filter-item { margin-bottom: 5px; }
            .print-filter-label { font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .no-print { margin-top: 20px; }
            .status-Approved { color: green; font-weight: bold; }
            .status-Rejected { color: red; font-weight: bold; }
            .status-Cancelled { color: gray; font-weight: bold; }
            .status-Pending { color: orange; font-weight: bold; }
        }
    </style>
</head>
<body>
<?php
$cfg = new \App\Services\SettingsService();
$companyLogo = $cfg->getCompanyLogo();
$companyName = $cfg->getCompanyName();
?>
    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= asset_url($companyLogo) ?>" width="52" height="52" alt="IMS">
            <div>
                <h2 class="h4 mb-0"><?= e($companyName) ?></h2>
                <div class="text-muted">Generated <?= e(date('Y-m-d H:i')) ?></div>
            </div>
        </div>
        <div class="text-end text-muted">Attendance Corrections Report</div>
    </div>

    <?php if (!empty($filters)): ?>
    <div class="print-filters">
        <h3>Applied Filters</h3>
        <?php if (!empty($filters['status'])): ?>
        <div class="print-filter-item">
            <span class="print-filter-label">Status:</span>
            <?= e($filters['status']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($filters['correction_type'])): ?>
        <div class="print-filter-item">
            <span class="print-filter-label">Correction Type:</span>
            <?= e($filters['correction_type']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($filters['start_date'])): ?>
        <div class="print-filter-item">
            <span class="print-filter-label">Start Date:</span>
            <?= e($filters['start_date']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($filters['end_date'])): ?>
        <div class="print-filter-item">
            <span class="print-filter-label">End Date:</span>
            <?= e($filters['end_date']) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($filters['q'])): ?>
        <div class="print-filter-item">
            <span class="print-filter-label">Search:</span>
            <?= e($filters['q']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
    <p>No attendance corrections found matching the current filters.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <?php if ($isAdminHr): ?>
                <th>Employee</th>
                <?php endif; ?>
                <th>Date</th>
                <th>Type</th>
                <th>Requested Times</th>
                <th>Status</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): 
                $requestedTimes = [];
                if (!empty($row['requested_time_in'])) $requestedTimes[] = $row['requested_time_in'];
                if (!empty($row['requested_time_out'])) $requestedTimes[] = $row['requested_time_out'];
                $requestedTimesStr = implode(' / ', $requestedTimes) ?: '—';
            ?>
            <tr>
                <?php if ($isAdminHr): ?>
                <td><?= e($row['employee_name'] ?? '—') ?></td>
                <?php endif; ?>
                <td><?= e($row['attendance_date'] ?? '—') ?></td>
                <td><?= e($row['correction_type'] ?? '—') ?></td>
                <td><?= e($requestedTimesStr) ?></td>
                <td class="status-<?= e($row['status'] ?? '') ?>"><?= e($row['status'] ?? '—') ?></td>
                <td><?= e(mb_strimwidth($row['reason'] ?? '', 0, 50, '…')) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 20px;"><strong>Total Records:</strong> <?= count($rows) ?></p>
    <?php endif; ?>

    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Print</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; margin-left: 10px;">Close</button>
    </div>

    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 100);
        });

        window.addEventListener('afterprint', function() {
            window.close();
        });
    </script>
</body>
</html>
