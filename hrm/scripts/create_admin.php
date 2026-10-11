<?php
declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/core/Database.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}

$employeeId = prompt('Mã nhân viên đã được tạo trong NHANVIEN: ');
$username = prompt('Tên đăng nhập quản trị: ');
$password = prompt('Mật khẩu (tối thiểu 12 ký tự): ');

if ($employeeId === '' || $username === '' || strlen($password) < 12) {
    fwrite(STDERR, "Thông tin không hợp lệ. Mật khẩu phải có ít nhất 12 ký tự.\n");
    exit(1);
}

$db = Database::connection();
$employeeQuery = $db->prepare('SELECT MaNV FROM NHANVIEN WHERE MaNV = :id');
$employeeQuery->execute(['id' => $employeeId]);
if ($employeeQuery->fetchColumn() === false) {
    fwrite(STDERR, "Không tìm thấy mã nhân viên đã nhập.\n");
    exit(1);
}

$duplicateQuery = $db->prepare(
    'SELECT COUNT(*) FROM TAIKHOAN WHERE MaNV = :employee_id OR TenDangNhap = :username'
);
$duplicateQuery->execute(['employee_id' => $employeeId, 'username' => $username]);
if ((int) $duplicateQuery->fetchColumn() > 0) {
    fwrite(STDERR, "Nhân viên hoặc tên đăng nhập đã có tài khoản.\n");
    exit(1);
}

$accountId = 'TK' . strtoupper(bin2hex(random_bytes(4)));
$insert = $db->prepare(
    'INSERT INTO TAIKHOAN (MaTK, MaNV, TenDangNhap, MatKhau, VaiTro, TrangThai)
     VALUES (:id, :employee_id, :username, :password, :role, :status)'
);
$insert->execute([
    'id' => $accountId,
    'employee_id' => $employeeId,
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'admin',
    'status' => 'Hoạt động',
]);

fwrite(STDOUT, "Đã tạo tài khoản quản trị {$username} cho nhân viên {$employeeId}.\n");
