<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class AccountAdmin
{
    private const ROLES = ['admin', 'nhansu', 'quanly', 'ketoan', 'nhanvien', 'giamdoc'];
    private const MATRIX = [
        'Quản lý tài khoản' => ['admin' => 'Toàn quyền'],
        'Tuyển dụng' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Đề xuất', 'giamdoc' => 'Phê duyệt'],
        'Hồ sơ nhân viên' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Xem', 'nhanvien' => 'Cá nhân', 'giamdoc' => 'Xem'],
        'Thử việc' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Toàn quyền', 'nhanvien' => 'Cá nhân', 'giamdoc' => 'Xem'],
        'Chấm công' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Toàn quyền', 'ketoan' => 'Toàn quyền', 'nhanvien' => 'Cá nhân', 'giamdoc' => 'Xem'],
        'Tính lương' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Xem', 'ketoan' => 'Toàn quyền', 'nhanvien' => 'Phiếu cá nhân', 'giamdoc' => 'Phê duyệt'],
        'Đánh giá' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Toàn quyền', 'nhanvien' => 'Cá nhân', 'giamdoc' => 'Toàn quyền'],
        'Tăng lương / điều chuyển' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Đề xuất', 'giamdoc' => 'Phê duyệt'],
        'Nghỉ việc' => ['admin' => 'Toàn quyền', 'nhansu' => 'Toàn quyền', 'quanly' => 'Đề xuất', 'ketoan' => 'Chốt lương', 'nhanvien' => 'Đề nghị cá nhân', 'giamdoc' => 'Phê duyệt'],
        'Báo cáo' => ['admin' => 'Xem', 'nhansu' => 'Xem', 'quanly' => 'Xem', 'ketoan' => 'Xem', 'nhanvien' => 'Cá nhân', 'giamdoc' => 'Xem'],
    ];

    public function __construct(private PDO $db)
    {
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return self::ROLES;
    }

    /** @return array<string,array<string,string>> */
    public static function permissionMatrix(): array
    {
        return self::MATRIX;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query(
            'SELECT tk.MaTK, tk.MaNV, tk.TenDangNhap, tk.VaiTro, tk.TrangThai, tk.SoLanSai, tk.KhoaDen, nv.HoTen
             FROM TAIKHOAN tk INNER JOIN NHANVIEN nv ON nv.MaNV = tk.MaNV
             ORDER BY tk.TenDangNhap'
        )->fetchAll();
    }

    /** @return list<array{MaNV:string,HoTen:string}> */
    public function employeesWithoutAccount(): array
    {
        return $this->db->query(
            "SELECT nv.MaNV, nv.HoTen FROM NHANVIEN nv
             LEFT JOIN TAIKHOAN tk ON tk.MaNV = nv.MaNV
             WHERE tk.MaTK IS NULL AND nv.TrangThai <> 'Nghỉ việc'
             ORDER BY nv.HoTen"
        )->fetchAll();
    }

    public function create(string $accountId, string $employeeId, string $username, string $passwordHash, string $role): bool
    {
        if (!in_array($role, self::ROLES, true)) {
            return false;
        }
        $exists = $this->db->prepare(
            "SELECT COUNT(*) FROM NHANVIEN nv
             WHERE nv.MaNV = :employee_id AND nv.TrangThai <> 'Nghỉ việc'
               AND NOT EXISTS (
                   SELECT 1 FROM TAIKHOAN tk
                   WHERE tk.MaNV = nv.MaNV OR tk.TenDangNhap = :username
               )"
        );
        $exists->execute(['username' => $username, 'employee_id' => $employeeId]);
        if ((int) $exists->fetchColumn() !== 1) {
            return false;
        }
        $insert = $this->db->prepare(
            "INSERT INTO TAIKHOAN (MaTK, MaNV, TenDangNhap, MatKhau, VaiTro, TrangThai)
             VALUES (:id, :employee_id, :username, :password_hash, :role, 'Hoạt động')"
        );
        $insert->execute([
            'id' => $accountId,
            'employee_id' => $employeeId,
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
        ]);

        return true;
    }

    public function update(string $accountId, string $role, string $status): bool
    {
        if (!in_array($role, self::ROLES, true) || !in_array($status, ['Hoạt động', 'Ngưng hoạt động'], true)) {
            return false;
        }
        $exists = $this->db->prepare('SELECT 1 FROM TAIKHOAN WHERE MaTK = :id');
        $exists->execute(['id' => $accountId]);
        if ($exists->fetchColumn() === false) {
            return false;
        }
        $query = $this->db->prepare(
            'UPDATE TAIKHOAN SET VaiTro = :role, TrangThai = :status,
                SoLanSai = IF(:reset_failures = 1, 0, SoLanSai),
                KhoaDen = IF(:clear_lock = 1, NULL, KhoaDen)
             WHERE MaTK = :id'
        );
        $query->execute([
            'role' => $role,
            'status' => $status,
            'reset_failures' => $status === 'Hoạt động' ? 1 : 0,
            'clear_lock' => $status === 'Hoạt động' ? 1 : 0,
            'id' => $accountId,
        ]);

        return true;
    }
}
