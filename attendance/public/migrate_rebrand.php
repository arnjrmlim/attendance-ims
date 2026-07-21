<?php
/**
 * IMS Rebranding Migration
 *
 * Updates the app_name value in the settings table from
 * "Attendance Management System" to "Integrated Management Services, Inc."
 *
 * Run once via browser:
 *   http://localhost/attendance-ims/attendance/public/migrate_rebrand.php
 *
 * Safe to run multiple times — idempotent.
 * Delete this file from the server after running.
 */

$config = require dirname(__DIR__) . '/config/database.php';

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $db  = new PDO($dsn, $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
}

$steps = [];

// ── 1. Update app_name ────────────────────────────────────────────────────────
$current = $db->query("SELECT `value` FROM settings WHERE `key` = 'app_name' LIMIT 1")
               ->fetchColumn();

if ($current === false) {
    $db->exec("INSERT INTO settings (`key`, `value`, `type`, `group`, `description`)
               VALUES ('app_name', 'Integrated Management Services, Inc.', 'string', 'general', 'Application name')");
    $steps[] = ['ok', 'Inserted <code>app_name</code> = <strong>Integrated Management Services, Inc.</strong>'];
} elseif ($current === 'Integrated Management Services, Inc.') {
    $steps[] = ['skip', '<code>app_name</code> is already set to <strong>Integrated Management Services, Inc.</strong> — skipped.'];
} else {
    $db->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'app_name'")
       ->execute(['Integrated Management Services, Inc.']);
    $steps[] = ['ok', "Updated <code>app_name</code>: <em>" . htmlspecialchars($current) . "</em> → <strong>Integrated Management Services, Inc.</strong>"];
}

// ── 2. Update company_name if it still holds the old value ───────────────────
$companyName = $db->query("SELECT `value` FROM settings WHERE `key` = 'company_name' LIMIT 1")
                  ->fetchColumn();

if ($companyName !== false && in_array($companyName, ['My Company', 'Attendance Management System'], true)) {
    $db->prepare("UPDATE settings SET `value` = ? WHERE `key` = 'company_name'")
       ->execute(['Integrated Management Services, Inc.']);
    $steps[] = ['ok', "Updated <code>company_name</code>: <em>" . htmlspecialchars($companyName) . "</em> → <strong>Integrated Management Services, Inc.</strong>"];
} elseif ($companyName === 'Integrated Management Services, Inc.') {
    $steps[] = ['skip', '<code>company_name</code> already correct — skipped.'];
} else {
    $steps[] = ['info', '<code>company_name</code> is <em>' . htmlspecialchars((string)$companyName) . '</em> — left unchanged (already customised).'];
}

// ── 3. Verify final values ────────────────────────────────────────────────────
$final = $db->query(
    "SELECT `key`, `value` FROM settings WHERE `key` IN ('app_name','company_name') ORDER BY `key`"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($final as $row) {
    $steps[] = ['info', "Current <code>{$row['key']}</code> = <strong>" . htmlspecialchars($row['value']) . "</strong>"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IMS Rebranding Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width:680px">
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2" style="background:#1e2a6e">
            <span class="fs-5 text-white fw-bold">IMS</span>
            <h5 class="mb-0 text-white">Rebranding Migration</h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($steps as [$type, $msg]):
                    $icon  = ['ok'=>'✅','skip'=>'⏭️','warn'=>'⚠️','info'=>'ℹ️'][$type] ?? '•';
                    $color = ['ok'=>'success','skip'=>'secondary','warn'=>'warning','info'=>'primary'][$type] ?? 'secondary';
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
                Delete <code>public/migrate_rebrand.php</code> from your server.
            </div>
            <a href="index.php?url=dashboard" class="btn btn-primary">
                Go to Dashboard →
            </a>
        </div>
    </div>
</div>
</body>
</html>
