<section class="login-card card border-0 shadow">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="login-icon"><i class="bi bi-people-fill"></i></div>
            <h1 class="h4 fw-bold mt-3 mb-1">HT MEDIA</h1>
            <p class="text-secondary mb-0">Hệ thống quản lý nhân lực</p>
        </div>
        <?php foreach ($flashMessages as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?> py-2" role="alert"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('login')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input class="form-control form-control-lg" id="username" name="username" required maxlength="50" autocomplete="username" autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Mật khẩu</label>
                <input class="form-control form-control-lg" id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="<?= e(url('forgot-password')) ?>" class="small">Quên mật khẩu?</a>
        </div>
        <p class="small text-secondary text-center mt-4 mb-0">Công ty TNHH Thương mại Dịch vụ HT MEDIA</p>
    </div>
</section>
