<?php /** Announcement create/edit form */
$a = $announcement ?? null;
$isEdit = $a !== null;
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0">
            <i class="bi bi-megaphone me-2"></i><?= $isEdit ? 'Edit Announcement' : 'New Announcement' ?>
        </h4>
    </div>
    <a href="<?= url('announcements') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="post" action="<?= $isEdit ? url('announcements/update') : url('announcements') ?>">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
            <?php endif; ?>

            <div class="panel p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="title" required
                           value="<?= e($a['title'] ?? '') ?>"
                           placeholder="Announcement title">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="body" rows="5" required
                              placeholder="Announcement content…"><?= e($a['body'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="draft"     <?= ($a['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($a['status'] ?? '') === 'published'       ? 'selected' : '' ?>>Published</option>
                            <option value="archived"  <?= ($a['status'] ?? '') === 'archived'        ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Target Audience</label>
                        <select class="form-select" name="target_type" id="targetType">
                            <option value="all"        <?= ($a['target_type'] ?? 'all') === 'all'        ? 'selected' : '' ?>>Everyone</option>
                            <option value="department" <?= ($a['target_type'] ?? '') === 'department'    ? 'selected' : '' ?>>Department</option>
                            <option value="employee"   <?= ($a['target_type'] ?? '') === 'employee'      ? 'selected' : '' ?>>Individual Employee</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="targetIdWrap" style="display:none">
                        <label class="form-label" id="targetIdLabel">Select Target</label>
                        <select class="form-select" name="target_id" id="targetId">
                            <option value="">— Select —</option>
                            <optgroup label="Departments" id="deptOptions">
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= e($dept['id']) ?>"
                                            data-type="department"
                                            <?= ($a['target_type'] ?? '') === 'department' && ($a['target_id'] ?? '') === $dept['id'] ? 'selected' : '' ?>>
                                        <?= e($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Employees" id="empOptions">
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= e($emp['id']) ?>"
                                            data-type="employee"
                                            <?= ($a['target_type'] ?? '') === 'employee' && ($a['target_id'] ?? '') === $emp['id'] ? 'selected' : '' ?>>
                                        <?= e($emp['employee_number'] . ' — ' . $emp['first_name'] . ' ' . $emp['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Publish Date/Time</label>
                        <input type="datetime-local" class="form-control" name="publish_at"
                               value="<?= $a && $a['publish_at'] ? e(date('Y-m-d\TH:i', strtotime($a['publish_at']))) : '' ?>">
                        <small class="text-muted">Leave blank to publish immediately</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expiry Date/Time</label>
                        <input type="datetime-local" class="form-control" name="expire_at"
                               value="<?= $a && $a['expire_at'] ? e(date('Y-m-d\TH:i', strtotime($a['expire_at']))) : '' ?>">
                        <small class="text-muted">Hide automatically after this time</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-center pt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="pinned" id="pinned"
                                   <?= !empty($a['pinned']) && $a['pinned'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pinned">
                                <i class="bi bi-pin-fill text-warning me-1"></i>Pin to top
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?= $isEdit ? 'Update' : 'Create' ?> Announcement
                    </button>
                    <a href="<?= url('announcements') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const targetType = document.getElementById('targetType');
const targetIdWrap = document.getElementById('targetIdWrap');
const deptOptions = document.getElementById('deptOptions');
const empOptions  = document.getElementById('empOptions');
const targetIdLabel = document.getElementById('targetIdLabel');

function updateTargetVisibility() {
    const val = targetType.value;
    if (val === 'all') {
        targetIdWrap.style.display = 'none';
    } else {
        targetIdWrap.style.display = '';
        deptOptions.style.display = val === 'department' ? '' : 'none';
        empOptions.style.display  = val === 'employee'   ? '' : 'none';
        targetIdLabel.textContent  = val === 'department' ? 'Select Department' : 'Select Employee';
    }
}

targetType.addEventListener('change', updateTargetVisibility);
updateTargetVisibility();
</script>
