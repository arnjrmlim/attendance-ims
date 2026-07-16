<div class="page-head"><div><h1 class="h3 mb-1">Notifications</h1><div class="text-muted">Unread alerts, approvals, rejections and security events.</div></div></div>
<div class="panel p-3">
<?php foreach ($rows as $row): ?>
    <div class="d-flex align-items-start justify-content-between border-bottom py-3 <?= $row['is_read'] ? '' : 'bg-light' ?>">
        <div><span class="badge text-bg-<?= e($row['type']) ?>"><?= e($row['type']) ?></span><h2 class="h6 mt-2 mb-1"><?= e($row['title']) ?></h2><div class="text-muted"><?= e($row['message']) ?></div><div class="small text-muted"><?= e($row['created_at']) ?></div></div>
        <div class="btn-group"><?php if (!$row['is_read']): ?><form method="post" action="<?= url('notifications/read') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-sm btn-outline-primary">Read</button></form><?php endif; ?><form method="post" action="<?= url('notifications/delete') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div>
    </div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="text-center text-muted py-5">No notifications found.</div><?php endif; ?>
</div>
