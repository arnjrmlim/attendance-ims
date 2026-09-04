<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request - <?= e($request['employee_name'] ?? 'Unknown') ?></title>
    <style>
        @media print {
            body { font-family: Arial, sans-serif; font-size: 12pt; }
            .no-print { display: none !important; }
            .print-header { border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
            .print-section { margin-bottom: 20px; }
            .print-label { font-weight: bold; }
            .print-value { margin-bottom: 8px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
        }
        @media screen {
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
            .print-header { border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
            .print-section { margin-bottom: 20px; }
            .print-label { font-weight: bold; }
            .print-value { margin-bottom: 8px; }
            .no-print { margin-top: 20px; }
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
        <div class="text-end text-muted">Leave Request</div>
    </div>

    <div class="print-section">
        <div class="print-value">
            <span class="print-label">Employee:</span>
            <?= e($request['employee_name'] ?? '—') ?>
        </div>
        <div class="print-value">
            <span class="print-label">Leave Type:</span>
            <?= e($request['leave_type'] ?? '—') ?>
        </div>
        <div class="print-value">
            <span class="print-label">Start Date:</span>
            <?= e($request['start_date'] ?? '—') ?>
        </div>
        <div class="print-value">
            <span class="print-label">End Date:</span>
            <?= e($request['end_date'] ?? '—') ?>
        </div>
        <div class="print-value">
            <span class="print-label">Number of Days:</span>
            <?= e($request['number_of_days'] ?? '—') ?>
        </div>
        <div class="print-value">
            <span class="print-label">Status:</span>
            <?= e($request['status'] ?? '—') ?>
        </div>
        <div class="print-value">
            <span class="print-label">Reason:</span>
            <?= e($request['reason'] ?? '—') ?>
        </div>
        <?php if ($isAdminHr): ?>
        <div class="print-value">
            <span class="print-label">Admin Remarks:</span>
            <?= e($request['admin_remarks'] ?? '—') ?>
        </div>
        <?php endif; ?>
        <div class="print-value">
            <span class="print-label">Submitted Date:</span>
            <?= e(date('Y-m-d', strtotime($request['created_at'] ?? ''))) ?>
        </div>
    </div>

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
