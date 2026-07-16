<?php
/**
 * My Profile – Profile Settings Page
 * Route: GET /profile
 */

// Resolve display avatar: prefer user's profile_picture, fall back to employee photo
$displayAvatar = $profile['profile_picture']
    ?? (($profile['employee_photo'] ?? null) ? $profile['employee_photo'] : null);
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">My Profile</h1>
        <div class="text-muted">Manage your account settings and password</div>
    </div>
</div>

<div class="row g-3">

    <?php /* ── Left: avatar + read-only summary ──────────────────────────── */ ?>
    <div class="col-lg-4">

        <?php /* Profile Picture Card */ ?>
        <div class="panel p-3 text-center mb-3">
            <div class="mb-3">
                <?php if ($displayAvatar): ?>
                    <img src="<?= url('uploads/' . e($displayAvatar)) ?>"
                         alt="Profile Picture"
                         id="avatarPreview"
                         class="rounded-circle border"
                         style="width:150px;height:150px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto"
                         id="avatarPreview"
                         style="width:150px;height:150px;">
                        <i class="bi bi-person text-white" style="font-size:4rem;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <h5 class="mb-0"><?= e($profile['full_name'] ?? $profile['username']) ?></h5>
            <div class="text-muted small mb-1"><?= e($profile['role_name'] ?? '') ?></div>
            <?php if (!empty($profile['position'])): ?>
                <div class="text-muted small mb-3"><?= e($profile['position']) ?></div>
            <?php endif; ?>

            <?php /* Upload form */ ?>
            <form method="post"
                  action="<?= url('profile/picture') ?>"
                  enctype="multipart/form-data"
                  id="pictureUploadForm">
                <?= csrf_field() ?>
                <label for="profilePictureInput" class="btn btn-outline-primary btn-sm w-100 mb-2">
                    <i class="bi bi-camera"></i> <?= $displayAvatar ? 'Replace Picture' : 'Upload Picture' ?>
                </label>
                <input type="file"
                       id="profilePictureInput"
                       name="profile_picture"
                       accept="image/jpeg,image/png,image/webp"
                       class="d-none"
                       onchange="previewAndSubmit(this)">
            </form>

            <?php /* Remove button – only show if picture exists */ ?>
            <?php if (!empty($profile['profile_picture'])): ?>
                <form method="post" action="<?= url('profile/picture/remove') ?>">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="btn btn-outline-danger btn-sm w-100"
                            data-confirm="Remove your profile picture?">
                        <i class="bi bi-trash"></i> Remove Picture
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-2 text-muted" style="font-size:.75rem;">
                Accepted: JPG, PNG, WEBP &bull; Max 2 MB
            </div>
        </div>

        <?php /* Quick-info card */ ?>
        <div class="panel p-3">
            <h6 class="mb-3"><i class="bi bi-info-circle"></i> Account Summary</h6>
            <dl class="row row-cols-1 g-2 mb-0">
                <?php if (!empty($profile['employee_number'])): ?>
                    <div class="col"><dt class="small text-muted">Employee No.</dt><dd><?= e($profile['employee_number']) ?></dd></div>
                <?php endif; ?>
                <div class="col"><dt class="small text-muted">Username</dt><dd><?= e($profile['username']) ?></dd></div>
                <div class="col"><dt class="small text-muted">Role</dt><dd><?= e($profile['role_name'] ?? '-') ?></dd></div>
                <div class="col">
                    <dt class="small text-muted">Account Status</dt>
                    <dd>
                        <?php $stClass = ($profile['user_status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary'; ?>
                        <span class="badge <?= $stClass ?>"><?= e(ucfirst($profile['user_status'] ?? '-')) ?></span>
                    </dd>
                </div>
                <div class="col">
                    <dt class="small text-muted">Last Login</dt>
                    <dd><?= $profile['last_login'] ? date('M j, Y g:i A', strtotime($profile['last_login'])) : 'Never' ?></dd>
                </div>
                <div class="col">
                    <dt class="small text-muted">Password Last Changed</dt>
                    <dd><?= $profile['password_changed_at'] ? date('M j, Y g:i A', strtotime($profile['password_changed_at'])) : 'Never' ?></dd>
                </div>
            </dl>
        </div>

    </div><!-- /col-lg-4 -->

    <?php /* ── Right: personal info + change password ───────────────────── */ ?>
    <div class="col-lg-8">

        <?php /* ── Personal & Employment Info (read-only) ───────────────── */ ?>
        <div class="panel p-3 mb-3">
            <h6 class="mb-3"><i class="bi bi-person"></i> Personal Information</h6>
            <div class="row g-3">
                <div class="col-sm-6">
                    <dt class="small text-muted">Full Name</dt>
                    <dd><?= e(
                        implode(' ', array_filter([
                            $profile['first_name']  ?? null,
                            $profile['middle_name'] ?? null,
                            $profile['last_name']   ?? null,
                            $profile['suffix']      ?? null,
                        ]))
                    ) ?: e($profile['full_name'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Employee No.</dt>
                    <dd><?= e($profile['employee_number'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Branch</dt>
                    <dd><?= e($profile['branch_name'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Department</dt>
                    <dd><?= e($profile['department_name'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Position</dt>
                    <dd><?= e($profile['position'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Email Address</dt>
                    <dd><?= e($profile['employee_email'] ?? $profile['user_email'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Contact Number</dt>
                    <dd><?= e($profile['contact_number'] ?? '-') ?></dd>
                </div>
            </div>
        </div>

        <?php /* ── Account Information (read-only) ────────────────────── */ ?>
        <div class="panel p-3 mb-3">
            <h6 class="mb-3"><i class="bi bi-shield-lock"></i> Account Information</h6>
            <div class="row g-3">
                <div class="col-sm-6">
                    <dt class="small text-muted">Username</dt>
                    <dd><?= e($profile['username']) ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Assigned Role</dt>
                    <dd><?= e($profile['role_name'] ?? '-') ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Account Status</dt>
                    <dd>
                        <span class="badge <?= ($profile['user_status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= e(ucfirst($profile['user_status'] ?? '-')) ?>
                        </span>
                    </dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Last Login</dt>
                    <dd><?= $profile['last_login'] ? date('M j, Y g:i A', strtotime($profile['last_login'])) : 'Never' ?></dd>
                </div>
                <div class="col-sm-6">
                    <dt class="small text-muted">Password Last Changed</dt>
                    <dd><?= $profile['password_changed_at'] ? date('M j, Y g:i A', strtotime($profile['password_changed_at'])) : 'Never' ?></dd>
                </div>
            </div>
        </div>

        <?php /* ── Change Password ──────────────────────────────────────── */ ?>
        <div class="panel p-3">
            <h6 class="mb-1"><i class="bi bi-key"></i> Change Password</h6>
            <p class="text-muted small mb-3">Enter your current password, then choose a new one.</p>

            <form method="post" action="<?= url('profile/password') ?>" id="passwordForm" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password"
                               class="form-control"
                               id="current_password"
                               name="current_password"
                               autocomplete="current-password"
                               required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('current_password',this)" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password"
                               class="form-control"
                               id="new_password"
                               name="new_password"
                               autocomplete="new-password"
                               required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('new_password',this)" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password"
                               class="form-control"
                               id="confirm_password"
                               name="confirm_password"
                               autocomplete="new-password"
                               required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('confirm_password',this)" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div id="confirmMismatch" class="invalid-feedback d-none">
                        The new password and confirmation password do not match.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Change Password
                </button>
            </form>
        </div>

    </div><!-- /col-lg-8 -->
</div><!-- /row -->

<script>
/* Toggle password visibility */
function togglePwd(id, btn) {
    var input = document.getElementById(id);
    var isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
        ? '<i class="bi bi-eye"></i>'
        : '<i class="bi bi-eye-slash"></i>';
}

/* Live client-side confirm-match check (UX only; server validates too) */
document.getElementById('confirm_password').addEventListener('input', function () {
    var newPwd  = document.getElementById('new_password').value;
    var hint    = document.getElementById('confirmMismatch');
    if (this.value && this.value !== newPwd) {
        hint.classList.remove('d-none');
        this.classList.add('is-invalid');
    } else {
        hint.classList.add('d-none');
        this.classList.remove('is-invalid');
    }
});

/* Preview avatar before upload, then auto-submit */
function previewAndSubmit(input) {
    if (!input.files || !input.files[0]) return;

    var file = input.files[0];
    var maxBytes = 2 * 1024 * 1024;

    if (file.size > maxBytes) {
        alert('Profile picture must not exceed 2 MB.');
        input.value = '';
        return;
    }

    var allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        alert('Please select a JPG, PNG, or WEBP image.');
        input.value = '';
        return;
    }

    var reader = new FileReader();
    reader.onload = function (e) {
        var preview = document.getElementById('avatarPreview');
        if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
        } else {
            // Replace the placeholder div with an img
            var img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Profile Picture';
            img.id  = 'avatarPreview';
            img.className = 'rounded-circle border';
            img.style.cssText = 'width:150px;height:150px;object-fit:cover;';
            preview.replaceWith(img);
        }
    };
    reader.readAsDataURL(file);

    // Auto-submit after brief preview delay
    setTimeout(function () {
        document.getElementById('pictureUploadForm').submit();
    }, 400);
}
</script>
