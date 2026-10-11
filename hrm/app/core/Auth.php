<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const PERMISSIONS = [
        'dashboard' => [
            'admin' => ['view'], 'nhansu' => ['view'], 'quanly' => ['view'],
            'ketoan' => ['view'], 'nhanvien' => ['view'], 'giamdoc' => ['view'],
        ],
        'accounts' => ['admin' => ['manage']],
        'recruitment' => [
            'nhansu' => ['manage'],
            'quanly' => ['propose'], 'giamdoc' => ['approve'],
        ],
        'employees' => [
            'nhansu' => ['manage'], 'quanly' => ['view'],
            'nhanvien' => ['personal'], 'giamdoc' => ['view'],
        ],
        'probation' => [
            'nhansu' => ['manage'], 'quanly' => ['view'],
            'nhanvien' => ['personal'], 'giamdoc' => ['view'],
        ],
        'attendance' => [
            'nhansu' => ['manage', 'approve'], 'quanly' => ['manage', 'approve'],
            'ketoan' => ['manage'], 'nhanvien' => ['personal_manage', 'personal_request'], 'giamdoc' => ['view'],
        ],
        'leave' => [
            'nhansu' => ['manage', 'approve'], 'quanly' => ['manage', 'approve'],
            'nhanvien' => ['personal_request'], 'giamdoc' => ['view'],
        ],
        'overtime' => [
            'nhansu' => ['manage', 'approve'], 'quanly' => ['manage', 'approve'],
            'ketoan' => ['manage'], 'nhanvien' => ['personal_request'], 'giamdoc' => ['view'],
        ],
        'payroll' => [
            'nhansu' => ['manage'], 'quanly' => ['view'],
            'ketoan' => ['manage'], 'nhanvien' => ['personal'], 'giamdoc' => ['approve'],
        ],
        'reviews' => [
            'nhansu' => ['manage'], 'quanly' => ['manage'],
            'nhanvien' => ['personal'], 'giamdoc' => ['manage'],
        ],
        'salary_changes' => [
            'nhansu' => ['manage'],
            'quanly' => ['propose'], 'giamdoc' => ['approve'],
        ],
        'transfers' => [
            'nhansu' => ['manage'],
            'quanly' => ['propose'], 'giamdoc' => ['approve'],
        ],
        'resignations' => [
            'nhansu' => ['manage'], 'quanly' => ['propose', 'approve'],
            'ketoan' => ['finalize_payroll'], 'nhanvien' => ['personal_request'],
            'giamdoc' => ['approve'],
        ],
        'reports' => [
            'nhansu' => ['view'], 'quanly' => ['view'],
            'ketoan' => ['view'], 'nhanvien' => ['personal'], 'giamdoc' => ['view'],
        ],
    ];

    private const FEATURE_ALIASES = [
        'quản lý tài khoản' => 'accounts',
        'tuyển dụng' => 'recruitment',
        'hồ sơ nhân viên' => 'employees',
        'nhân viên' => 'employees',
        'thử việc' => 'probation',
        'chấm công' => 'attendance',
        'nghỉ phép' => 'leave',
        'tăng ca' => 'overtime',
        'tính lương' => 'payroll',
        'lương' => 'payroll',
        'đánh giá' => 'reviews',
        'tăng lương' => 'salary_changes',
        'điều chuyển' => 'transfers',
        'nghỉ việc' => 'resignations',
        'báo cáo' => 'reports',
    ];

    private const ACTION_ALIASES = [
        'xem' => 'view',
        'quản lý' => 'manage',
        'đề xuất' => 'propose',
        'phê duyệt' => 'approve',
        'cá nhân' => 'personal',
        'quản lý cá nhân' => 'personal_manage',
        'đề nghị cá nhân' => 'personal_request',
        'chốt lương' => 'finalize_payroll',
    ];

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return list<string> */
    public static function roles(): array
    {
        $roles = self::user()['vai_tro'] ?? [];
        return is_array($roles) ? array_values(array_unique(array_filter($roles, 'is_string'))) : [];
    }

    public static function hasRole(string $role): bool
    {
        return in_array($role, self::roles(), true);
    }

    public static function activeRole(): ?string
    {
        $active = $_SESSION['active_role'] ?? null;
        return is_string($active) && self::hasRole($active) ? $active : self::landingRole();
    }

    public static function landingRole(): ?string
    {
        foreach (['giamdoc', 'nhansu', 'ketoan', 'admin', 'quanly', 'nhanvien'] as $role) {
            if (self::hasRole($role)) {
                return $role;
            }
        }
        return null;
    }

    public static function setActiveRole(string $role): bool
    {
        if (!self::hasRole($role)) {
            return false;
        }
        $_SESSION['active_role'] = $role;
        return true;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . url('login'));
            exit;
        }
    }

    public static function can(string $chucNang, string $hanhDong): bool
    {
        $feature = self::FEATURE_ALIASES[mb_strtolower(trim($chucNang), 'UTF-8')] ?? $chucNang;
        $action = self::ACTION_ALIASES[mb_strtolower(trim($hanhDong), 'UTF-8')] ?? $hanhDong;
        foreach (self::roles() as $role) {
            $granted = self::PERMISSIONS[$feature][$role] ?? [];
            if (in_array($action, $granted, true)) {
                return true;
            }
            if ($action === 'view'
                && array_intersect(
                    $granted,
                    ['manage', 'propose', 'approve', 'finalize_payroll', 'personal_manage']
                ) !== []) {
                return true;
            }
        }
        return false;
    }

    public static function requirePermission(string $chucNang, string $hanhDong): void
    {
        self::requireLogin();

        if (!self::can($chucNang, $hanhDong)) {
            http_response_code(403);
            render('errors/403', ['title' => 'Không có quyền truy cập']);
            exit;
        }
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        unset($_SESSION['_csrf_token']);
        $_SESSION['user'] = $user;
        $_SESSION['active_role'] = self::landingRole();
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
    }
}
