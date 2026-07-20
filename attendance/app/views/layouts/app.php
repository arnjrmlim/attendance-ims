<?php

$user = current_user();
$title = $title ?? config('name');
$flashSuccess = flash('success');
$flashError = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="<?= url('dashboard') ?>">
            <img src="<?= url('assets/img/logo.svg') ?>" alt="" width="30" height="30">
            AMS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (has_role(['administrator', 'hr'])): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('attendance-monitoring') ?>"><i class="bi bi-clock-history"></i> Monitoring</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?= url('leaves') ?>"><i class="bi bi-calendar2-check"></i> Leaves</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('corrections') ?>"><i class="bi bi-pencil-square"></i> Corrections</a></li>
                <?php if (has_role(['administrator', 'hr'])): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('reports') ?>"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?= url('calendar') ?>"><i class="bi bi-calendar3"></i> Calendar</a></li>
                <?php if (!has_role('administrator')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('manual-attendance/request') ?>"><i class="bi bi-clock"></i> Manual Attendance</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?= url('announcements') ?>"><i class="bi bi-megaphone"></i> Announcements</a></li>
                <?php if (has_role(['administrator', 'hr'])): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('employees') ?>"><i class="bi bi-people"></i> Employees</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= url('holidays') ?>"><i class="bi bi-sun"></i> Holidays</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= url('audit') ?>"><i class="bi bi-shield-check"></i> Audit</a></li>
                    <?php if (!has_role('administrator')): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= url('shifts') ?>"><i class="bi bi-clock"></i> Shifts</a></li>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (has_role('administrator')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-gear"></i> Admin</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url('branches') ?>"><i class="bi bi-building me-1"></i> Branches</a></li>
                        <li><a class="dropdown-item" href="<?= url('departments') ?>"><i class="bi bi-diagram-3 me-1"></i> Departments</a></li>
                        <li><a class="dropdown-item" href="<?= url('shifts') ?>"><i class="bi bi-clock me-1"></i> Shifts</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('backups') ?>"><i class="bi bi-hdd-stack me-1"></i> Backups</a></li>
                        <li><a class="dropdown-item" href="<?= url('email-settings') ?>"><i class="bi bi-envelope-gear me-1"></i> Email Settings</a></li>
                        <li><a class="dropdown-item" href="<?= url('email-logs') ?>"><i class="bi bi-envelope-paper me-1"></i> Email Logs</a></li>
                        <li><a class="dropdown-item" href="<?= url('system/settings') ?>"><i class="bi bi-sliders me-1"></i> System Settings</a></li>
                        <li><a class="dropdown-item" href="<?= url('system/health') ?>"><i class="bi bi-heart-pulse me-1"></i> System Health</a></li>
                        <li><a class="dropdown-item" href="<?= url('system/job-logs') ?>"><i class="bi bi-list-task me-1"></i> Job Logs</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <form class="d-flex me-3" role="search" action="<?= url('search') ?>">
                <input class="form-control form-control-sm" name="q" type="search" placeholder="Search" value="<?= e($_GET['q'] ?? '') ?>">
            </form>
                    <?php /* User dropdown — Notifications + Profile Settings + Logout */ ?>
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= profile_picture_url($user) ?>"
                         alt=""
                         class="rounded-circle"
                         style="width:24px;height:24px;object-fit:cover;">
                    <span class="d-none d-md-inline"><?= e($user['full_name'] ?? $user['username']) ?></span>
                    <?php if (($unreadNotifications ?? 0) > 0): ?>
                        <span class="badge rounded-pill bg-danger" style="font-size:.65rem;"><?= (int) $unreadNotifications ?></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header"><?= e($user['full_name'] ?? $user['username']) ?></h6></li>
                    <li><span class="dropdown-item-text text-muted small"><?= e($user['role_name'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?= url('notifications') ?>">
                            <i class="bi bi-bell me-2"></i> Notifications
                            <?php if (($unreadNotifications ?? 0) > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-1"><?= (int) $unreadNotifications ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= url('profile') ?>">
                            <i class="bi bi-person-gear me-2"></i> Profile Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= url('logout') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="<?= $user ? 'container-fluid py-4' : '' ?>">
    <?php if ($flashSuccess): ?><div class="toast-feedback alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="toast-feedback alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>
    <?php require $viewFile; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
