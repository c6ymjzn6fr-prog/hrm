<?php
use App\Core\Auth;

$pageTitle = $title ?? 'HT MEDIA';
$loggedInUser = Auth::user();
$flashMessages = consumeFlash();
$menuItems = [
    ['dashboard', 'Tổng quan', 'dashboard', 'view', 'bi-speedometer2'],
    ['recruitment', 'Tuyển dụng', 'recruitment', 'view', 'bi-person-plus'],
    ['applicants', 'Ứng viên', 'recruitment', 'manage', 'bi-person-lines-fill'],
    ['employees', 'Hồ sơ nhân viên', 'employees', 'view', 'bi-person-badge'],
    ['probation', 'Thử việc', 'probation', 'view', 'bi-hourglass-split'],
    ['attendance', 'Chấm công', 'attendance', 'view', 'bi-calendar-check'],
    ['leave', 'Nghỉ phép', 'leave', 'view', 'bi-calendar2-minus'],
    ['overtime', 'Tăng ca', 'overtime', 'view', 'bi-clock-history'],
    ['payroll', 'Tính lương', 'payroll', 'view', 'bi-cash-stack'],
    ['reviews', 'Đánh giá', 'reviews', 'view', 'bi-clipboard2-check'],
    ['salary_changes', 'Tăng lương', 'salary_changes', 'view', 'bi-graph-up-arrow'],
    ['transfers', 'Điều chuyển', 'transfers', 'view', 'bi-arrow-left-right'],
    ['resignations', 'Nghỉ việc', 'resignations', 'view', 'bi-person-x'],
    ['reports', 'Báo cáo', 'reports', 'view', 'bi-bar-chart'],
    ['accounts', 'Quản lý tài khoản', 'accounts', 'manage', 'bi-people-gear'],
];
$activeRoute = (string) ($_GET['route'] ?? $_GET['r'] ?? 'dashboard');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | HT MEDIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(APP_BASE_URL) ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="<?= $loggedInUser === null ? 'auth-page' : '' ?>">
<?php if ($loggedInUser !== null): ?>
    <header class="topbar navbar navbar-dark px-3">
        <a class="navbar-brand fw-semibold" href="<?= e(url('dashboard')) ?>">
            <i class="bi bi-people-fill me-2"></i>HT MEDIA <span class="brand-subtitle">Quản lý nhân lực</span>
        </a>
        <div class="d-flex align-items-center gap-3 text-white">
            <span class="d-none d-sm-inline"><?= e($loggedInUser['HoTen']) ?></span>
            <?php if (count(Auth::roles()) > 1): ?>
                <?php
                $roleNames = [
                    'giamdoc' => 'Giám đốc', 'nhansu' => 'Nhân sự', 'ketoan' => 'Kế toán',
                    'admin' => 'Quản trị', 'quanly' => 'Trưởng bộ phận', 'nhanvien' => 'Nhân viên',
                ];
                ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= e($roleNames[Auth::activeRole() ?? ''] ?? Auth::activeRole() ?? '') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach (Auth::roles() as $role): ?>
                            <li><a class="dropdown-item" href="<?= e(url('dashboard') . '&role=' . rawurlencode($role)) ?>"><?= e($roleNames[$role] ?? $role) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <span class="badge text-bg-light"><?= e(Auth::activeRole() ?? $loggedInUser['VaiTro']) ?></span>
            <?php endif; ?>
            <form method="post" action="<?= e(url('logout')) ?>" class="m-0">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <button class="btn btn-sm btn-outline-light" type="submit" aria-label="Đăng xuất">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="d-none d-md-inline ms-1">Đăng xuất</span>
                </button>
            </form>
        </div>
    </header>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-label">MENU CHÍNH</div>
            <?php foreach ($menuItems as [$route, $label, $feature, $action, $icon]): ?>
                <?php
                $personalModules = ['employees', 'probation', 'attendance', 'payroll', 'reviews', 'reports'];
                $allowed = Auth::can($feature, $action)
                    || ($route === 'dashboard' && Auth::can($feature, 'view'))
                    || (in_array($feature, $personalModules, true) && Auth::can($feature, 'personal'))
                    || (in_array($feature, ['leave', 'overtime'], true) && Auth::can($feature, 'personal_request'))
                    || ($feature === 'resignations' && Auth::can($feature, 'personal_request'))
                    || ($feature === 'attendance' && Auth::can($feature, 'personal_manage'));
                ?>
                <?php if ($allowed): ?>
                    <a class="sidebar-link <?= $activeRoute === $route ? 'active' : '' ?>"
                       href="<?= e(url($route)) ?>" title="<?= e($label) ?>">
                        <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </aside>
        <main class="main-content">
            <?php foreach ($flashMessages as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($message['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
                </div>
            <?php endforeach; ?>
            <?php require $viewFile; ?>
        </main>
    </div>
<?php else: ?>
    <main class="auth-container">
        <?php require $viewFile; ?>
    </main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
