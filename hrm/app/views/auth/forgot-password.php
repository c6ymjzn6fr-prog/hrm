<section class="login-card card border-0 shadow">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="login-icon"><i class="bi bi-key"></i></div>
            <h1 class="h4 fw-bold mt-3 mb-1">Quên mật khẩu</h1>
            <p class="text-secondary mb-0">Nhập email đã đăng ký với tài khoản của bạn.</p>
        </div>
        <?php foreach ($flashMessages as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?> py-2" role="alert"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('forgot-password')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <div class="mb-4">
                <label for="email" class="form-label">Email</label>
                <input class="form-control form-control-lg" id="email" name="email" type="email" required maxlength="150" autocomplete="email">
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">
                <i class="bi bi-envelope me-2"></i>Gửi liên kết đặt lại
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="<?= e(url('login')) ?>" class="small">Quay lại đăng nhập</a>
        </div>
    </div>
</section>
