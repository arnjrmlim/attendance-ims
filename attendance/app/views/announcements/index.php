<?php /** Announcements List */
$isAdminHr = has_role(['administrator', 'hr']);
$pagination = pagination_meta($total, $page, $perPage);
$statusColors = ['published' => 'success', 'draft' => 'secondary', 'archived' => 'dark'];
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-megaphone me-2"></i>Announcements</h4>
        <small class="text-muted">
            <?= $isAdminHr ? 'Create and manage announcements' : 'Company and branch announcements' ?>
        </small>
    </div>
    <?php if ($isAdminHr): ?>
        <a href="<?= url('announcements/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> New Announcement
        </a>
    <?php endif; ?>
</div>

<!-- Filters (admin/HR only) -->
<?php if ($isAdminHr): ?>
<form class="panel p-3 mb-3" method="get" action="<?= url('announcements') ?>">
    <div class="row g-2">
        <div class="col-sm-4">
            <input class="form-control form-control-sm" name="q" placeholder="Search title…" value="<?= e($filters['q']) ?>">
        </div>
        <div class="col-sm-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">All statuses</option>
                <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft"     <?= $filters['status'] === 'draft'     ? 'selected' : '' ?>>Draft</option>
                <option value="archived"  <?= $filters['status'] === 'archived'  ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>
        <div class="col-sm-auto">
            <button class="btn btn-sm btn-secondary"><i class="bi bi-search"></i> Filter</button>
            <a href="<?= url('announcements') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- Announcements as cards -->
<div class="row g-3">
    <?php if (empty($rows)): ?>
        <div class="col-12">
            <div class="panel p-5 text-center text-muted">
                <i class="bi bi-megaphone fs-1 mb-3 d-block opacity-25"></i>
                No announcements found.
            </div>
        </div>
    <?php else: foreach ($rows as $row): ?>
        <div class="col-md-6 col-lg-4">
            <div class="panel p-4 h-100 d-flex flex-column <?= $row['pinned'] ? 'border-warning' : '' ?>">
                <?php if ($row['pinned']): ?>
                    <div class="mb-2"><span class="badge bg-warning text-dark"><i class="bi bi-pin-fill me-1"></i>Pinned</span></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-semibold mb-0"><?= e($row['title']) ?></h6>
                    <span class="badge bg-<?= $statusColors[$row['status']] ?? 'secondary' ?> ms-2">
                        <?= ucfirst($row['status']) ?>
                    </span>
                </div>
                <div class="text-muted small mb-3 flex-grow-1">
                    <?= nl2br(e(mb_strimwidth(strip_tags($row['body']), 0, 180, '…'))) ?>
                </div>
                <div class="d-flex justify-content-between align-items-end">
                    <small class="text-muted">
                        <?= $row['author_name'] ? 'By ' . e($row['author_name']) . ' · ' : '' ?>
                        <?= e(date('M d, Y', strtotime($row['created_at']))) ?>
                        <?php if ($row['expire_at'] && strtotime($row['expire_at']) < time()): ?>
                            <span class="badge bg-secondary ms-1">Expired</span>
                        <?php endif; ?>
                    </small>
                    <?php if ($isAdminHr): ?>
                        <div class="d-flex gap-1">
                            <a href="<?= url('announcements/edit?id=' . urlencode($row['id'])) ?>"
                               class="btn btn-xs btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($row['status'] === 'draft'): ?>
                                <form method="post" action="<?= url('announcements/publish') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <button class="btn btn-xs btn-outline-success" title="Publish">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($row['status'] !== 'archived'): ?>
                                <form method="post" action="<?= url('announcements/archive') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <button class="btn btn-xs btn-outline-dark"
                                            data-confirm="Archive this announcement?"
                                            title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php if ($pagination['pages'] > 1): ?>
<nav class="mt-3 d-flex justify-content-center">
    <ul class="pagination pagination-sm">
        <?php for ($p = 1; $p <= $pagination['pages']; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($filters['status']) ?>&q=<?= urlencode($filters['q']) ?>">
                    <?= $p ?>
                </a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
