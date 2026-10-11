<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Scope
{
    /** @return array{0:string,1:array<string,string>} */
    public static function nhanVien(string $alias): array
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $alias) !== 1) {
            throw new \InvalidArgumentException('Bí danh SQL không hợp lệ.');
        }

        $roles = Auth::roles();
        if (array_intersect($roles, ['giamdoc', 'nhansu', 'ketoan']) !== []) {
            return ['1 = 1', []];
        }
        if (in_array('quanly', $roles, true)) {
            $user = Auth::user();
            if ($user === null || $user['ma_pb'] === null) {
                return ['1 = 0', []];
            }

            return [
                '(' . $alias . '.MaPB = :scope_ma_pb OR ' . $alias . '.MaNV = :scope_ma_nv)',
                ['scope_ma_pb' => (string) $user['ma_pb'], 'scope_ma_nv' => (string) $user['ma_nv']],
            ];
        }
        if (in_array('nhanvien', $roles, true)) {
            $user = Auth::user();
            return [
                $alias . '.MaNV = :scope_ma_nv',
                ['scope_ma_nv' => (string) ($user['ma_nv'] ?? '')],
            ];
        }

        return ['1 = 0', []];
    }

    public static function allowsEmployee(PDO $db, string $employeeId): bool
    {
        [$condition, $params] = self::nhanVien('nv');
        $params['scope_target_employee'] = $employeeId;
        $query = $db->prepare(
            'SELECT 1 FROM NHANVIEN nv WHERE nv.MaNV = :scope_target_employee AND (' . $condition . ') LIMIT 1'
        );
        $query->execute($params);

        return $query->fetchColumn() !== false;
    }

    public static function requireEmployee(PDO $db, string $employeeId): void
    {
        if (!self::allowsEmployee($db, $employeeId)) {
            http_response_code(403);
            render('errors/403', ['title' => 'Không có quyền xem dữ liệu nhân viên này']);
            exit;
        }
    }
}
