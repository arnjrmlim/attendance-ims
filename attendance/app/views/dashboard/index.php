<div class="page-head">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <div class="text-muted">Welcome back, <?= e(current_user()['full_name']) ?>.</div>
    </div>
</div>

<?php if (has_role('employee')): ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="text-muted">Pending Leave</div><div class="metric-value"><?= (int) $stats['pending_leaves'] ?></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="text-muted">Pending Corrections</div><div class="metric-value"><?= (int) $stats['pending_corrections'] ?></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="text-muted">Leave Balance</div><div class="fw-semibold"><?= e($stats['leave_balance']) ?></div></div></div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ([
            'Present' => $stats['present'], 'Absent' => $stats['absent'], 'Late' => $stats['late'], 'On Leave' => $stats['on_leave'],
            'Pending Corrections' => $stats['pending_corrections'], 'Pending Leaves' => $stats['pending_leaves'], 'Attendance %' => $stats['attendance_percentage'] . '%'
        ] as $label => $value): ?>
            <div class="col-sm-6 col-lg-3"><div class="metric-card"><div class="text-muted"><?= e($label) ?></div><div class="metric-value"><?= e($value) ?></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <a href="<?= url('email-logs') ?>" class="text-decoration-none">
                <div class="metric-card">
                    <div class="text-muted"><i class="bi bi-envelope-exclamation"></i> Failed / Queued Emails</div>
                    <div class="metric-value"><?= (int) ($stats['failed_emails'] ?? 0) ?></div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="<?= url('backups') ?>" class="text-decoration-none">
                <div class="metric-card">
                    <div class="text-muted"><i class="bi bi-hdd-stack"></i> Last Backup</div>
                    <?php $lb = $stats['last_backup'] ?? null; ?>
                    <div class="fw-semibold">
                        <?php if ($lb): ?>
                            <span class="badge status-badge <?= $lb['status'] === 'success' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= e($lb['status']) ?></span>
                            <div class="small text-muted"><?= e($lb['created_at']) ?></div>
                        <?php else: ?>
                            <span class="text-muted">No backups yet</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="<?= url('system/health') ?>" class="text-decoration-none">
                <div class="metric-card">
                    <div class="text-muted"><i class="bi bi-heart-pulse"></i> System Health</div>
                    <div class="fw-semibold"><span class="badge text-bg-success status-badge">View Status</span></div>
                </div>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($announcements)): ?>
    <div class="panel p-3 mb-4">
        <h2 class="h5"><i class="bi bi-megaphone"></i> Announcements</h2>
        <?php foreach (array_slice($announcements, 0, 5) as $a): ?>
            <div class="border-bottom py-2">
                <div class="fw-semibold"><?= e($a['title']) ?></div>
                <div class="small text-muted"><?= e($a['created_at'] ?? '') ?></div>
            </div>
        <?php endforeach; ?>
        <a href="<?= url('announcements') ?>" class="small">View all announcements &raquo;</a>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel p-3">
            <h2 class="h5">Recent Attendance</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Date</th><th>Employee</th><th>Status</th><th>Time In</th><th>Time Out</th></tr></thead>
                    <tbody>
                    <?php foreach (($stats['recent_attendance'] ?? $stats['history'] ?? []) as $row): ?>
                        <tr>
                            <td><?= e($row['attendance_date']) ?></td>
                            <td><?= e($row['employee_name'] ?? current_user()['full_name']) ?></td>
                            <td><span class="badge text-bg-secondary status-badge"><?= e($row['day_status']) ?></span></td>
                            <td><?= e($row['time_in'] ?? '') ?></td>
                            <td><?= e($row['time_out'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-3">
            <h2 class="h5">Recent Notifications</h2>
            <?php foreach ($notifications as $note): ?>
                <div class="border-bottom py-2">
                    <div class="fw-semibold"><?= e($note['title']) ?></div>
                    <div class="small text-muted"><?= e($note['message']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$notifications): ?><div class="text-muted">No notifications yet.</div><?php endif; ?>
        </div>
    </div>
</div>
