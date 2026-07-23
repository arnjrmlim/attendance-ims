<?php
/**
 * System Settings — grouped config editor
 */
$groupLabels = [
    'company'     => ['icon' => 'bi-building',       'label' => 'Company'],
    'system'      => ['icon' => 'bi-gear',             'label' => 'System'],
    'security'    => ['icon' => 'bi-shield-lock',      'label' => 'Security'],
    'reports'     => ['icon' => 'bi-file-earmark-bar-graph', 'label' => 'Reports'],
];
$boolKeys = [
    'maintenance_mode',
    'password_require_upper',
    'password_require_number',
    'password_require_special',
    'report_show_logo',
    'report_show_address',
    'report_show_generated_by',
    'report_show_timestamp',
];
// Remove email group from grouped (managed by EmailSettingsController)
unset($grouped['email']);
?>

<div class="page-head">
    <div>
        <h4 class="fw-semibold mb-0"><i class="bi bi-sliders me-2"></i>System Settings</h4>
        <small class="text-muted">Application-wide configuration</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('email-settings') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-envelope-gear"></i> Email Settings
        </a>
        <a href="<?= url('system/health') ?>" class="btn btn-outline-info btn-sm">
            <i class="bi bi-heart-pulse"></i> Health Dashboard
        </a>
    </div>
</div>

<form method="post" action="<?= url('system/settings') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-4">
        <?php foreach ($groupLabels as $group => $meta): ?>
            <?php if (empty($grouped[$group])): continue; endif; ?>
            <div class="col-12">
                <div class="panel p-4">
                    <h6 class="fw-semibold mb-3">
                        <i class="bi <?= $meta['icon'] ?> me-2 text-muted"></i><?= $meta['label'] ?>
                    </h6>
                    <div class="row g-3">
                        <?php foreach ($grouped[$group] as $row): ?>
                            <?php
                            $key   = $row['key'];
                            $val   = $row['value'];
                            $desc  = $row['description'] ?? '';
                            $type  = $row['type'] ?? 'string';
                            $label = ucwords(str_replace('_', ' ', str_replace($group . '_', '', $key)));
                            ?>
                            <?php if ($key === 'phase3_installed_at' || $key === 'phase2_installed_at'): continue; endif; ?>

                            <?php if ($type === 'boolean' || in_array($key, $boolKeys, true)): ?>
                                <div class="col-md-4">
                                    <div class="form-check form-switch pt-2">
                                        <input class="form-check-input" type="checkbox"
                                               name="<?= e($key) ?>" id="<?= e($key) ?>"
                                               <?= (string)$val === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="<?= e($key) ?>">
                                            <?= e($label) ?>
                                            <?php if ($desc): ?>
                                                <small class="d-block text-muted"><?= e($desc) ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>

                            <?php elseif ($key === 'company_logo'): ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?= e($label) ?></label>
                                    <?php if ($val): ?>
                                        <div class="mb-1">
                                            <?php
                                            $logoPath = str_starts_with($val, 'uploads/') ? $val : 'uploads/' . $val;
                                            ?>
                                            <img src="<?= asset_url($logoPath) ?>" alt="Logo" style="max-height:40px">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control form-control-sm"
                                           name="company_logo" accept=".svg,.png,.jpg,.jpeg">
                                    <small class="text-muted">SVG, PNG or JPG, max 3 MB</small>
                                </div>

                            <?php elseif ($type === 'time'): ?>
                                <div class="col-md-3">
                                    <label class="form-label"><?= e($label) ?></label>
                                    <input type="time" class="form-control form-control-sm"
                                           name="<?= e($key) ?>" value="<?= e($val) ?>">
                                    <?php if ($desc): ?><small class="text-muted"><?= e($desc) ?></small><?php endif; ?>
                                </div>

                            <?php elseif ($type === 'integer'): ?>
                                <div class="col-md-3">
                                    <label class="form-label"><?= e($label) ?></label>
                                    <input type="number" class="form-control form-control-sm"
                                           name="<?= e($key) ?>" value="<?= e($val) ?>" min="0">
                                    <?php if ($desc): ?><small class="text-muted"><?= e($desc) ?></small><?php endif; ?>
                                </div>

                            <?php elseif ($key === 'timezone'): ?>
                                <div class="col-md-4">
                                    <label class="form-label"><?= e($label) ?></label>
                                    <select class="form-select form-select-sm" name="<?= e($key) ?>">
                                        <?php foreach (['Asia/Manila','Asia/Singapore','Asia/Kuala_Lumpur','Asia/Jakarta','UTC','America/New_York','Europe/London'] as $tz): ?>
                                            <option value="<?= $tz ?>" <?= $val === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            <?php elseif ($key === 'backup_path'): ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?= e($label) ?></label>
                                    <input type="text" class="form-control form-control-sm font-monospace"
                                           name="<?= e($key) ?>" value="<?= e($val) ?>"
                                           placeholder="Leave blank for default (attendance/backups/)">
                                    <?php if ($desc): ?><small class="text-muted"><?= e($desc) ?></small><?php endif; ?>
                                </div>

                            <?php else: ?>
                                <div class="col-md-4">
                                    <label class="form-label"><?= e($label) ?></label>
                                    <input type="text" class="form-control form-control-sm"
                                           name="<?= e($key) ?>" value="<?= e($val) ?>">
                                    <?php if ($desc): ?><small class="text-muted"><?= e($desc) ?></small><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Save All Settings
        </button>
        <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
