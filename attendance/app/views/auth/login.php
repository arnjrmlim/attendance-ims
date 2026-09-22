<section class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="panel p-4" style="width:min(420px,100%)">
        <div class="text-center mb-4">
            <?php
            $cfg = new \App\Services\SettingsService();
            $logo = $cfg->getCompanyLogo();
            $companyName = $cfg->getCompanyName();
            ?>
            <img src="<?= asset_url($logo) ?>" width="80" height="80" alt="IMS">
            <h1 class="h4 mt-3"><?= e($companyName) ?></h1>
            <p class="text-muted small mb-0">Attendance Management Portal</p>
        </div>
        <form method="post" action="<?= url('login') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input class="form-control" name="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <input class="form-control" type="password" name="password" id="passwordInput" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>
            <button class="btn btn-primary w-100">Sign in</button>
        </form>
    </div>
</section>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
});
</script>
