<?php /** System Health Dashboard */ ?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-heart-pulse me-2"></i>System Health</h4>
        <small class="text-muted">Generated at <?= e($report['generated_at']) ?></small>
    </div>
    <a href="<?= url('system/health') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </a>
</div>

<!-- Alerts -->
<?php if (!empty($report['alerts'])): ?>
    <?php foreach ($report['alerts'] as $alert): ?>
        <div class="alert alert-<?= e($alert['level']) ?> py-2 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <?= e($alert['message']) ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle-fill me-2"></i>All systems healthy.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- PHP & Server -->
    <div class="col-lg-4">
        <div class="panel p-4 h-100">
            <h6 class="fw-semibold mb-3"><i class="bi bi-code-slash me-2 text-muted"></i>PHP & Server</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-muted">PHP Version</td><td class="fw-semibold"><?= e($report['php_version']) ?></td></tr>
                <tr><td class="text-muted">Memory Limit</td><td><?= e($report['php_memory_limit']) ?></td></tr>
                <tr><td class="text-muted">Max Execution</td><td><?= e($report['php_max_exec']) ?>s</td></tr>
                <tr><td class="text-muted">Web Server</td><td><?= e($report['server_software']) ?></td></tr>
                <tr><td class="text-muted">System Uptime</td><td><?= e($report['server_uptime']) ?></td></tr>
                <tr><td class="text-muted">Active Sessions</td><td><?= (int)$report['active_sessions'] ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Database -->
    <div class="col-lg-4">
        <div class="panel p-4 h-100">
            <h6 class="fw-semibold mb-3"><i class="bi bi-database me-2 text-muted"></i>Database</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-muted">Version</td><td class="fw-semibold"><?= e($report['db_version']) ?></td></tr>
                <tr><td class="text-muted">Size</td><td><?= e($report['db_size_human']) ?></td></tr>
                <tr><td class="text-muted">Tables</td><td><?= (int)$report['db_table_count'] ?></td></tr>
                <tr>
                    <td class="text-muted">Last Backup</td>
                    <td>
                        <?php if ($report['last_backup']): ?>
                            <?= e(date('M d, Y H:i', strtotime($report['last_backup']['created_at']))) ?>
                            <small class="text-muted">(<?= e($report['last_backup']['filename']) ?>)</small>
                        <?php else: ?>
                            <span class="text-danger">None</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <div class="mt-2">
                <a href="<?= url('backups') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-database-fill-down me-1"></i>Manage Backups
                </a>
            </div>
        </div>
    </div>

    <!-- Storage -->
    <div class="col-lg-4">
        <div class="panel p-4 h-100">
            <h6 class="fw-semibold mb-3"><i class="bi bi-hdd me-2 text-muted"></i>Disk Storage</h6>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">Disk Usage</small>
                    <small class="fw-semibold"><?= $report['disk_percent'] ?>%</small>
                </div>
                <?php
                $pct  = min(100, (float)$report['disk_percent']);
                $cls  = $pct > 90 ? 'danger' : ($pct > 75 ? 'warning' : 'success');
                ?>
                <div class="progress" style="height:10px">
                    <div class="progress-bar bg-<?= $cls ?>" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-muted">Total</td><td><?= e($report['disk_total']) ?></td></tr>
                <tr><td class="text-muted">Used</td><td><?= e($report['disk_used']) ?></td></tr>
                <tr><td class="text-muted">Free</td><td><?= e($report['disk_free']) ?></td></tr>
            </table>
        </div>
    </div>
</div>

<!-- Pending Queues -->
<div class="row g-3 mb-4">
    <?php
    $queues = [
        ['label' => 'Pending Manual Attendance', 'count' => $report['pending_manual'],      'icon' => 'bi-person-badge',   'color' => 'warning', 'url' => 'manual-attendance'],
        ['label' => 'Pending Corrections',        'count' => $report['pending_corrections'], 'icon' => 'bi-pencil-square',  'color' => 'info',    'url' => 'corrections'],
        ['label' => 'Pending Leaves',             'count' => $report['pending_leaves'],      'icon' => 'bi-calendar-check', 'color' => 'primary', 'url' => 'leaves'],
        ['label' => 'Failed Emails',              'count' => $report['failed_emails'],       'icon' => 'bi-envelope-x',     'color' => 'danger',  'url' => 'email-logs?status=failed'],
        ['label' => 'Failed Jobs (7d)',           'count' => $report['failed_jobs'],         'icon' => 'bi-cpu',            'color' => 'danger',  'url' => 'system/job-logs'],
    ];
    ?>
    <?php foreach ($queues as $q): ?>
        <div class="col-sm-4 col-md-2-4">
            <a href="<?= url($q['url']) ?>" class="metric-card d-flex align-items-center gap-3 text-decoration-none">
                <div class="rounded-circle p-2 bg-<?= $q['color'] ?> bg-opacity-10">
                    <i class="bi <?= $q['icon'] ?> text-<?= $q['color'] ?> fs-5"></i>
                </div>
                <div>
                    <div class="metric-value" style="font-size:1.4rem"><?= (int)$q['count'] ?></div>
                    <div class="text-muted small"><?= e($q['label']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Recent Background Jobs -->
<div class="panel mb-4">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0"><i class="bi bi-cpu me-2"></i>Recent Background Jobs</h6>
        <a href="<?= url('system/job-logs') ?>" class="btn btn-outline-secondary btn-sm">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Job</th><th>Status</th><th>Started</th><th>Finished</th><th>Output</th></tr>
            </thead>
            <tbody>
                <?php if (empty($report['recent_jobs'])): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No job logs yet.</td></tr>
                <?php else: foreach ($report['recent_jobs'] as $job): ?>
                    <tr>
                        <td><?= e($job['job_name']) ?></td>
                        <td>
                            <span class="badge bg-<?= $job['status'] === 'success' ? 'success' : ($job['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($job['status']) ?>
                            </span>
                        </td>
                        <td><small><?= e(date('M d H:i', strtotime($job['started_at']))) ?></small></td>
                        <td><small><?= $job['finished_at'] ? e(date('M d H:i', strtotime($job['finished_at']))) : '—' ?></small></td>
                        <td><small class="text-muted" title="<?= e($job['output'] ?? '') ?>"><?= e(mb_strimwidth($job['output'] ?? '', 0, 60, '…')) ?></small></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Email status -->
<?php if ($report['last_email']): ?>
<div class="panel p-3">
    <h6 class="fw-semibold mb-2"><i class="bi bi-envelope me-2"></i>Last Sent Email</h6>
    <small class="text-muted">
        To: <strong><?= e($report['last_email']['recipient']) ?></strong> —
        Subject: <?= e($report['last_email']['subject']) ?> —
        Sent: <?= e(date('M d, Y H:i', strtotime($report['last_email']['sent_at']))) ?>
    </small>
</div>
<?php endif; ?>
