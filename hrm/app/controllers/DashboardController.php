<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Dashboard;

final class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $requestedRole = trim((string) ($_GET['role'] ?? ''));
        if ($requestedRole !== '' && !Auth::setActiveRole($requestedRole)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Vai trò không thuộc tài khoản']);
            return;
        }
        $role = Auth::activeRole() ?? Auth::landingRole() ?? 'nhanvien';
        if ($role === 'admin') {
            $this->authorize('accounts', 'manage');
        } else {
            $this->authorize('reports', Auth::hasRole('nhanvien') && !Auth::can('reports', 'view') ? 'personal' : 'view');
        }
        $data = (new Dashboard(Database::connection()))->roleData($role, Auth::user());
        $user = Auth::user();
        $this->render('dashboard/index', [
            'title' => 'Tổng quan - ' . $this->roleTitle($role),
            'user' => $user,
            'role' => $role,
            'roles' => Auth::roles(),
            'cards' => $data['cards'],
            'departments' => $data['departments'],
            'notifications' => $data['notifications'],
            'pendingApprovals' => $data['pending'],
            'attendanceToday' => $data['attendanceToday'] ?? null,
            'personal' => $role === 'nhanvien',
        ]);
    }

    private function roleTitle(string $role): string
    {
        return [
            'giamdoc' => 'Giám đốc',
            'nhansu' => 'Nhân sự',
            'ketoan' => 'Kế toán',
            'admin' => 'Quản trị hệ thống',
            'quanly' => 'Trưởng bộ phận',
            'nhanvien' => 'Nhân viên',
        ][$role] ?? $role;
    }
}
