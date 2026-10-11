<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecordId;
use App\Models\AccountAdmin;
use App\Models\AuditLog;

final class AccountController extends Controller
{
    public function index(): void
    {
        $this->authorize('accounts', 'manage');
        $model = new AccountAdmin(Database::connection());
        $this->render('accounts/index', [
            'title' => 'Quản trị tài khoản và phân quyền',
            'accounts' => $model->all(),
            'employees' => $model->employeesWithoutAccount(),
            'roles' => AccountAdmin::roles(),
            'permissionMatrix' => AccountAdmin::permissionMatrix(),
            'activityLogs' => (new AuditLog(Database::connection()))->recent(100),
        ]);
    }

    public function create(): void
    {
        $this->authorize('accounts', 'manage');
        $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = trim((string) ($_POST['role'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username) || strlen($password) < 12
            || !in_array($role, AccountAdmin::roles(), true)) {
            flash('error', 'Tên đăng nhập, mật khẩu (tối thiểu 12 ký tự) hoặc vai trò không hợp lệ.');
            $this->redirect('accounts');
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $created = (new AccountAdmin($db))->create(
                RecordId::make('TK'),
                $employeeId,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $role
            );
            if ($created) {
                (new AuditLog($db))->record(
                    Auth::user()['MaNV'],
                    'Tạo tài khoản',
                    'MaNV=' . $employeeId . '; VaiTro=' . $role
                );
            }
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        flash($created ? 'success' : 'error', $created
            ? 'Đã tạo tài khoản. Hãy chuyển mật khẩu khởi tạo cho nhân viên qua kênh an toàn.'
            : 'Nhân viên đã có tài khoản hoặc thông tin không hợp lệ.');
        $this->redirect('accounts');
    }

    public function update(): void
    {
        $this->authorize('accounts', 'manage');
        $accountId = trim((string) ($_POST['account_id'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));
        if (!in_array($role, AccountAdmin::roles(), true)
            || !in_array($status, ['Hoạt động', 'Ngưng hoạt động'], true)) {
            flash('error', 'Vai trò hoặc trạng thái tài khoản không hợp lệ.');
            $this->redirect('accounts');
        }
        if ($accountId === Auth::user()['MaTK']
            && ($status !== 'Hoạt động' || $role !== 'admin')) {
            flash('error', 'Không thể tự khóa hoặc tự hạ quyền tài khoản quản trị đang đăng nhập.');
            $this->redirect('accounts');
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $updated = (new AccountAdmin($db))->update($accountId, $role, $status);
            if ($updated) {
                (new AuditLog($db))->record(
                    Auth::user()['MaNV'],
                    'Cập nhật tài khoản',
                    'MaTK=' . $accountId . '; VaiTro=' . $role . '; TrangThai=' . $status
                );
            }
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        flash($updated ? 'success' : 'error', $updated
            ? 'Đã cập nhật vai trò và trạng thái tài khoản.'
            : 'Không tìm thấy tài khoản để cập nhật.');
        $this->redirect('accounts');
    }
}
