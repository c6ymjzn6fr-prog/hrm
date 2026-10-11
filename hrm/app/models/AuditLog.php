<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class AuditLog
{
    public function __construct(private PDO $db)
    {
    }

    public function record(?string $employeeId, string $action, string $details): void
    {
        $query = $this->db->prepare(
            'INSERT INTO NHATKY (MaNV, HanhDong, ChiTiet, DiaChiIP, ThoiGian)
             VALUES (:employee_id, :action, :details, :ip, CURRENT_TIMESTAMP)'
        );
        $query->execute([
            'employee_id' => $employeeId,
            'action' => mb_substr($action, 0, 100, 'UTF-8'),
            'details' => mb_substr($details, 0, 1000, 'UTF-8'),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? mb_substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $query = $this->db->prepare(
            'SELECT log.MaNhatKy, log.MaNV, COALESCE(nv.HoTen, \'Khách chưa đăng nhập\') AS HoTen,
                    log.HanhDong, log.ChiTiet, log.DiaChiIP, log.ThoiGian
             FROM NHATKY log
             LEFT JOIN NHANVIEN nv ON nv.MaNV = log.MaNV
             ORDER BY log.MaNhatKy DESC LIMIT :limit'
        );
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll();
    }
}
