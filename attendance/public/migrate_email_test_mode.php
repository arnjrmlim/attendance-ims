<?php
/**
 * Phase 6 Migration — Safe Testing Mode for Email Schedule.
 *
 * Adds is_test_run and simulated_date columns to the email_logs table.
 * Run once via browser:
 *   http://localhost/attendance-ims/attendance/public/migrate_email_test_mode.php
 *
 * Idempotent — safe to run multiple times.
 * Delete this file after running.
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

// Helper — check if a column exists
$hasCol = function (string $col) use ($db): bool {
    return (int) $db->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'email_logs'
            AND COLUMN_NAME  = '{$col}'"
    )->fetchColumn() > 0;
};

// Helper — check if an index exists
$hasIdx = function (string $idx) use ($db): bool {
    return (int) $db->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'email_logs'
            AND INDEX_NAME   = '{$idx}'"
    )->fetchColumn() > 0;
};

// ── 1. is_test_run ──────────────────────────────────────────────────────────
if (!$hasCol('is_test_run')) {
    $db->exec(
        "ALTER TABLE `email_logs`
         ADD COLUMN `is_test_run` TINYINT(1) NOT NULL DEFAULT 0
         COMMENT '1 = test run, not a real scheduled send'
         AFTER `retry_count`"
    );
    $steps[] = ['ok', 'Added <code>is_test_run</code> column to <code>email_logs</code>.'];
} else {
    $steps[] = ['skip', '<code>is_test_run</code> column already exists — skipped.'];
}

// ── 2. simulated_date ───────────────────────────────────────────────────────
if (!$hasCol('simulated_date')) {
    $db->exec(
        "ALTER TABLE `email_logs`
         ADD COLUMN `simulated_date` DATE DEFAULT NULL
         COMMENT 'Date simulated during a test run'
         AFTER `is_test_run`"
    );
    $steps[] = ['ok', 'Added <code>simulated_date</code> column to <code>email_logs</code>.'];
} else {
    $steps[] = ['skip', '<code>simulated_date</code> column already exists — skipped.'];
}

// ── 3. Index ────────────────────────────────────────────────────────────────
if (!$hasIdx('idx_email_logs_test_run')) {
    $db->exec(
        "ALTER TABLE `email_logs`
         ADD KEY `idx_email_logs_test_run` (`is_test_run`, `created_at`)"
    );
    $steps[] = ['ok', 'Added index <code>idx_email_logs_test_run</code>.'];
} else {
    $steps[] = ['skip', 'Index <code>idx_email_logs_test_run</code> already exists — skipped.'];
}

// ── 4. Verify final schema ──────────────────────────────────────────────────
$cols = $db->query(
    "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
       FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'email_logs'
      ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_ASSOC);

$steps[] = ['info', 'email_logs now has <strong>' . count($cols) . '</strong> columns.'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phase 6 Migration — Email Test Mode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width:680px">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex gap-2 align-items-center">
            <span class="fs-5">⚙️</span>
            <h5 class="mb-0">Phase 6 Migration — Email Schedule Safe Testing Mode</h5>
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
                Delete <code>public/migrate_email_test_mode.php</code> from your server.
            </div>
            <a href="index.php?url=email-schedule/test" class="btn btn-primary me-2">
                Go to Email Schedule Test →
            </a>
            <a href="index.php?url=email-logs" class="btn btn-outline-secondary">
                View Email Logs
            </a>
        </div>
    </div>

    <!-- Column verification table -->
    <div class="card mt-3 shadow-sm">
        <div class="card-header"><h6 class="mb-0">email_logs — current schema</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cols as $i => $col): ?>
                        <tr <?= in_array($col['COLUMN_NAME'], ['is_test_run','simulated_date']) ? 'class="table-success"' : '' ?>>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><code><?= htmlspecialchars($col['COLUMN_NAME']) ?></code></td>
                            <td><small><?= htmlspecialchars($col['COLUMN_TYPE']) ?></small></td>
                            <td><?= $col['IS_NULLABLE'] ?></td>
                            <td><small><?= htmlspecialchars($col['COLUMN_DEFAULT'] ?? 'NULL') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
