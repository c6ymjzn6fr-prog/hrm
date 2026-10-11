<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Account
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Lấy tài khoản và nhân viên liên quan để xác thực đăng nhập. */
    public function findForLogin(string $username): ?array
    {
        $statement = $this->db->prepare(
            'SELECT tk.MaTK, tk.MaNV, tk.TenDangNhap, tk.MatKhau, tk.VaiTro,
                    tk.TrangThai, tk.SoLanSai, tk.KhoaDen,
                    (tk.KhoaDen > NOW()) AS DangBiKhoa, nv.HoTen, nv.MaPB,
                    pb.MaTruongBP, (pb.MaTruongBP = nv.MaNV) AS IsTruongBP
             FROM TAIKHOAN tk
             INNER JOIN NHANVIEN nv ON nv.MaNV = tk.MaNV
             INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
             WHERE tk.TenDangNhap = :username
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['username' => $username]);
        $account = $statement->fetch();

        return $account === false ? null : $account;
    }

    public function recordFailure(string $accountId, int $failures, bool $lock): void
    {
        if ($lock) {
            $statement = $this->db->prepare(
                'UPDATE TAIKHOAN
                 SET SoLanSai = :failures, KhoaDen = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                 WHERE MaTK = :id'
            );
        } else {
            $statement = $this->db->prepare(
                'UPDATE TAIKHOAN SET SoLanSai = :failures WHERE MaTK = :id'
            );
        }
        $statement->execute(['failures' => $failures, 'id' => $accountId]);
    }

    public function resetFailures(string $accountId): void
    {
        $statement = $this->db->prepare(
            'UPDATE TAIKHOAN SET SoLanSai = 0, KhoaDen = NULL WHERE MaTK = :id'
        );
        $statement->execute(['id' => $accountId]);
    }

    /** Cấp token cho một email duy nhất đã gắn với tài khoản hoạt động. */
    public function createRecoveryToken(string $email, string $tokenHash): ?string
    {
        $find = $this->db->prepare(
            "SELECT tk.MaTK, nv.Email
             FROM TAIKHOAN tk
             INNER JOIN NHANVIEN nv ON nv.MaNV = tk.MaNV
             WHERE nv.Email = :email AND tk.TrangThai = 'Hoạt động'
             LIMIT 2
             FOR UPDATE"
        );
        $find->execute(['email' => $email]);
        $accounts = $find->fetchAll();

        if (count($accounts) !== 1) {
            return null;
        }

        $update = $this->db->prepare(
            'UPDATE TAIKHOAN
             SET ResetTokenHash = :token_hash,
                 ResetTokenExpiresAt = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE MaTK = :id'
        );
        $update->execute([
            'token_hash' => $tokenHash,
            'id' => $accounts[0]['MaTK'],
        ]);

        return (string) $accounts[0]['Email'];
    }

    public function clearRecoveryToken(string $tokenHash): void
    {
        $statement = $this->db->prepare(
            'UPDATE TAIKHOAN
             SET ResetTokenHash = NULL, ResetTokenExpiresAt = NULL
             WHERE ResetTokenHash = :token_hash'
        );
        $statement->execute(['token_hash' => $tokenHash]);
    }

    public function hasValidRecoveryToken(string $tokenHash): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM TAIKHOAN
             WHERE ResetTokenHash = :token_hash
               AND ResetTokenExpiresAt > NOW()
               AND TrangThai = :status
             LIMIT 1'
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'status' => 'Hoạt động',
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function resetPassword(string $tokenHash, string $passwordHash): bool
    {
        $statement = $this->db->prepare(
            'UPDATE TAIKHOAN
             SET MatKhau = :password_hash,
                 SoLanSai = 0,
                 KhoaDen = NULL,
                 ResetTokenHash = NULL,
                 ResetTokenExpiresAt = NULL
             WHERE ResetTokenHash = :token_hash
               AND ResetTokenExpiresAt > NOW()
               AND TrangThai = :status'
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'token_hash' => $tokenHash,
            'status' => 'Hoạt động',
        ]);

        return $statement->rowCount() === 1;
    }

    public static function connect(): self
    {
        return new self(Database::connection());
    }
}
