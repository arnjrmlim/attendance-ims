<?php /** Background Job Logs */
$pagination = pagination_meta($total, $page, $perPage);
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-cpu me-2"></i>Background Job Logs</h4>
        <small class="text-muted">Execution history of all cron / scheduled tasks</small>
    </div>
    <a href="<?= url('system/health') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Health Dashboard
    </a>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Job Name</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Finished</th>
                    <th>Duration</th>
                    <th>Output</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No job logs yet.</td></tr>
                <?php else: foreach ($rows as $row):
                    $duration = '';
                    if ($row['started_at'] && $row['finished_at']) {
                        $secs = strtotime($row['finished_at']) - strtotime($row['started_at']);
                        $duration = $secs . 's';
                    }
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($row['job_name']) ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'success' ? 'success' : ($row['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                        <td><small><?= e(date('M d, Y H:i:s', strtotime($row['started_at']))) ?></small></td>
                        <td><small><?= $row['finished_at'] ? e(date('M d, Y H:i:s', strtotime($row['finished_at']))) : '—' ?></small></td>
                        <td><small><?= $duration ?: '—' ?></small></td>
                        <td>
                            <small class="text-muted" title="<?= e($row['output'] ?? '') ?>">
                                <?= e(mb_strimwidth($row['output'] ?? '', 0, 80, '…')) ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($row['error']): ?>
                                <small class="text-danger" title="<?= e($row['error']) ?>">
                                    <?= e(mb_strimwidth($row['error'], 0, 60, '…')) ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted">—</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['pages'] > 1): ?>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $pagination['offset'] + 1 ?>–<?= min($pagination['offset'] + $perPage, $total) ?> of <?= $total ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $pagination['pages']; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
