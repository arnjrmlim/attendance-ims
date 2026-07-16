<?php /** Database Backups */
$pagination = pagination_meta($total, $page, $perPage);
$typeColors = ['daily' => 'info', 'weekly' => 'primary', 'monthly' => 'success', 'manual' => 'secondary'];
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-database-fill-down me-2"></i>Database Backups</h4>
        <small class="text-muted">Create, download, restore and manage database backups</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#runBackupModal">
        <i class="bi bi-plus-lg"></i> Run Backup Now
    </button>
</div>

<!-- Quick stats -->
<?php
$byStatus = array_count_values(array_column($rows, 'status'));
$totalSize = array_sum(array_column($rows, 'filesize'));
?>
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value text-primary"><?= $total ?></div>
            <div class="text-muted small">Total Backups</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value text-success"><?= $byStatus['success'] ?? 0 ?></div>
            <div class="text-muted small">Successful</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value text-danger"><?= $byStatus['failed'] ?? 0 ?></div>
            <div class="text-muted small">Failed</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="metric-card text-center">
            <div class="metric-value text-info"><?= round($totalSize / 1048576, 1) ?> MB</div>
            <div class="text-muted small">Total Size</div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Trigger</th>
                    <th>Size</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No backups yet. Run your first backup now.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <i class="bi bi-file-earmark-zip text-warning me-1"></i>
                            <span title="<?= e($row['filepath']) ?>">
                                <?= e(mb_strimwidth($row['filename'], 0, 45, '…')) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $typeColors[$row['backup_type']] ?? 'secondary' ?>">
                                <?= ucfirst($row['backup_type']) ?>
                            </span>
                        </td>
                        <td><small><?= ucfirst($row['trigger_type']) ?></small></td>
                        <td>
                            <small><?= $row['filesize'] > 0 ? round($row['filesize'] / 1024, 1) . ' KB' : '—' ?></small>
                        </td>
                        <td><small><?= $row['duration_seconds'] ? $row['duration_seconds'] . 's' : '—' ?></small></td>
                        <td>
                            <?php
                            $sc = match($row['status']) {
                                'success' => 'success', 'failed' => 'danger', default => 'warning'
                            };
                            ?>
                            <span class="badge bg-<?= $sc ?>">
                                <?= ucfirst($row['status']) ?>
                                <?php if ($row['verified']): ?><i class="bi bi-patch-check ms-1"></i><?php endif; ?>
                            </span>
                            <?php if ($row['error_message']): ?>
                                <i class="bi bi-exclamation-circle text-danger ms-1"
                                   title="<?= e($row['error_message']) ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td><small><?= e($row['created_by_name'] ?? 'Cron') ?></small></td>
                        <td><small><?= e(date('M d, Y H:i', strtotime($row['created_at']))) ?></small></td>
                        <td class="no-print">
                            <?php if ($row['status'] === 'success'): ?>
                                <a href="<?= url('backups/download?id=' . urlencode($row['id'])) ?>"
                                   class="btn btn-xs btn-outline-secondary me-1" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button type="button" class="btn btn-xs btn-outline-warning me-1"
                                        data-bs-toggle="modal" data-bs-target="#restoreModal"
                                        data-id="<?= e($row['id']) ?>"
                                        data-file="<?= e($row['filename']) ?>"
                                        title="Restore">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-xs btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-id="<?= e($row['id']) ?>"
                                    data-file="<?= e($row['filename']) ?>"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
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

<!-- Run Backup Modal -->
<div class="modal fade" id="runBackupModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="post" action="<?= url('backups/run') ?>">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-database-fill-down me-2"></i>Run Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Backup Type</label>
                    <select class="form-select" name="type">
                        <option value="manual">Manual</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    <p class="text-muted small mt-2 mb-0">The backup will run immediately. Large databases may take a minute.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-play-fill"></i> Run Now
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= url('backups/restore') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="restoreId">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Restore Database</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2">
                        <strong>This will overwrite the current database.</strong>
                        All data added after this backup will be lost. This cannot be undone.
                    </div>
                    <p>Restore from: <strong id="restoreFile"></strong></p>
                    <p class="mb-0">Are you absolutely sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-arrow-counterclockwise"></i> Yes, Restore
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="post" action="<?= url('backups/delete') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-trash me-2"></i>Delete Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Delete <strong id="deleteFile"></strong>? The file will be permanently removed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('[data-bs-target="#restoreModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('restoreId').value = btn.dataset.id;
        document.getElementById('restoreFile').textContent = btn.dataset.file;
    });
});
document.querySelectorAll('[data-bs-target="#deleteModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteId').value = btn.dataset.id;
        document.getElementById('deleteFile').textContent = btn.dataset.file;
    });
});
</script>
