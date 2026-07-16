<?php /** Email Logs */
$statusColors = ['sent' => 'success', 'failed' => 'danger', 'queued' => 'warning', 'retrying' => 'info'];
$pagination = pagination_meta($total, $page, $perPage);
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-envelope-open me-2"></i>Email Logs</h4>
        <small class="text-muted">History of all outgoing email deliveries and retry attempts</small>
    </div>
    <a href="<?= url('email-settings') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-gear"></i> Email Settings
    </a>
</div>

<!-- Summary badges -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach (['sent','queued','retrying','failed'] as $s): ?>
        <a href="?status=<?= $s ?>" class="badge text-decoration-none bg-<?= $statusColors[$s] ?> fs-6">
            <?= ucfirst($s) ?>: <?= (int)($summary[$s] ?? 0) ?>
        </a>
    <?php endforeach; ?>
    <a href="<?= url('email-logs') ?>" class="badge bg-secondary text-decoration-none fs-6">
        All: <?= array_sum(array_map('intval', $summary)) ?>
    </a>
</div>

<!-- Filters -->
<form class="panel p-3 mb-3" method="get" action="<?= url('email-logs') ?>">
    <div class="row g-2">
        <div class="col-sm-3">
            <input class="form-control form-control-sm" name="q" placeholder="Search recipient, subject…" value="<?= e($filters['q']) ?>">
        </div>
        <div class="col-sm-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">All statuses</option>
                <?php foreach (['sent','queued','retrying','failed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-2">
            <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filters['dateFrom']) ?>">
        </div>
        <div class="col-sm-2">
            <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filters['dateTo']) ?>">
        </div>
        <div class="col-sm-auto">
            <button class="btn btn-sm btn-secondary"><i class="bi bi-search"></i> Filter</button>
            <a href="<?= url('email-logs') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Retries</th>
                    <th>Sent At</th>
                    <th>Created</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No email logs found.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['recipient']) ?></td>
                        <td>
                            <span title="<?= e($row['body_preview'] ?? '') ?>">
                                <?= e(mb_strimwidth($row['subject'], 0, 55, '…')) ?>
                            </span>
                        </td>
                        <td><small><?= e($row['report_period'] ?? '—') ?></small></td>
                        <td>
                            <span class="badge bg-<?= $statusColors[$row['status']] ?? 'secondary' ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                            <?php if ($row['last_error']): ?>
                                <i class="bi bi-exclamation-circle text-danger ms-1"
                                   title="<?= e($row['last_error']) ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$row['retry_count'] ?></td>
                        <td><small><?= $row['sent_at'] ? e(date('M d, Y H:i', strtotime($row['sent_at']))) : '—' ?></small></td>
                        <td><small><?= e(date('M d, Y H:i', strtotime($row['created_at']))) ?></small></td>
                        <td class="no-print">
                            <?php if (in_array($row['status'], ['failed','queued'], true)): ?>
                                <form method="post" action="<?= url('email-logs/resend') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <button class="btn btn-xs btn-outline-primary" title="Resend">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($row['attachment_path'] && is_file($row['attachment_path'])): ?>
                                <a href="<?= url('email-logs/download?id=' . urlencode($row['id'])) ?>"
                                   class="btn btn-xs btn-outline-secondary ms-1" title="Download attachment">
                                    <i class="bi bi-download"></i>
                                </a>
                            <?php endif; ?>
                            <form method="post" action="<?= url('email-logs/delete') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <button class="btn btn-xs btn-outline-danger ms-1"
                                        data-confirm="Delete this log entry?"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['pages'] > 1): ?>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $perPage, $total) ?> of <?= $total ?>
        </small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $pagination['pages']; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($filters['status']) ?>&q=<?= urlencode($filters['q']) ?>">
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
