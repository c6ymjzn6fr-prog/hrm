<section class="login-card card border-0 shadow">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="login-icon"><i class="bi bi-shield-lock"></i></div>
            <h1 class="h4 fw-bold mt-3 mb-1">Đặt mật khẩu mới</h1>
            <p class="text-secondary mb-0">Mật khẩu phải có ít nhất 12 ký tự.</p>
        </div>
        <?php foreach ($flashMessages as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?> py-2" role="alert"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('reset-password')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu mới</label>
                <input class="form-control form-control-lg" id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
            </div>
            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Xác nhận mật khẩu</label>
                <input class="form-control form-control-lg" id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">
                <i class="bi bi-check2-circle me-2"></i>Cập nhật mật khẩu
            </button>
        </form>
    </div>
</section>
