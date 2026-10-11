<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Workflow;
use DomainException;
use PDO;

final class Recruitment
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $query = $this->db->prepare(
            'SELECT t.*, pb.TenPB, proposer.HoTen AS NguoiDeXuat, approver.HoTen AS NguoiDuyet,
                    (SELECT COUNT(*) FROM UNGVIEN u WHERE u.MaTD = t.MaTD) AS SoUngVien
             FROM TUYENDUNG t
             LEFT JOIN PHONGBAN pb ON pb.MaPB = t.MaPB
             LEFT JOIN NHANVIEN proposer ON proposer.MaNV = t.DeXuatBoi
             LEFT JOIN NHANVIEN approver ON approver.MaNV = t.NguoiPheDuyet
             ORDER BY t.NgayDang DESC, t.MaTD DESC'
        );
        $query->execute();

        return $query->fetchAll();
    }

    public function create(
        string $jobId,
        string $position,
        int $quantity,
        string $departmentId,
        string $requirements,
        string $proposerId
    ): void
    {
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare(
                "INSERT INTO TUYENDUNG
                    (MaTD, ViTri, SoLuong, MaPB, YeuCau, NgayDang, TrangThai, DeXuatBoi)
                 VALUES (:id, :position, :quantity, :department, :requirements, CURRENT_DATE(), 'Chờ phê duyệt', :proposer)"
            );
            $query->execute([
                'id' => $jobId,
                'position' => $position,
                'quantity' => $quantity,
                'department' => $departmentId,
                'requirements' => $requirements !== '' ? $requirements : null,
                'proposer' => $proposerId,
            ]);
            Workflow::recordSubmission($this->db, 'de_xuat_tuyen_dung', $jobId, $proposerId, [
                'ViTri' => $position,
                'SoLuong' => $quantity,
                'MaPB' => $departmentId,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function approve(string $jobId, string $approverId): bool
    {
        $this->db->beginTransaction();
        try {
            $step = Workflow::assertNextStep($this->db, 'de_xuat_tuyen_dung', $jobId, $approverId);
            $query = $this->db->prepare(
                "UPDATE TUYENDUNG
                 SET TrangThai = 'Đã duyệt', NguoiPheDuyet = :approver, NgayPheDuyet = CURRENT_DATE()
                 WHERE MaTD = :id AND TrangThai = 'Chờ phê duyệt'"
            );
            $query->execute(['approver' => $approverId, 'id' => $jobId]);
            if ($query->rowCount() !== 1) {
                throw new DomainException('Đề xuất không còn ở trạng thái chờ phê duyệt.');
            }
            Workflow::recordDecision($this->db, 'de_xuat_tuyen_dung', $jobId, $step, $approverId, 'Đã duyệt');
            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function reject(string $jobId, string $approverId, string $reason): bool
    {
        $this->db->beginTransaction();
        try {
            $step = Workflow::assertNextStep($this->db, 'de_xuat_tuyen_dung', $jobId, $approverId);
            $query = $this->db->prepare(
                "UPDATE TUYENDUNG
                 SET TrangThai = 'Từ chối', NguoiPheDuyet = :approver, NgayPheDuyet = CURRENT_DATE()
                 WHERE MaTD = :id AND TrangThai = 'Chờ phê duyệt'"
            );
            $query->execute(['approver' => $approverId, 'id' => $jobId]);
            if ($query->rowCount() !== 1) {
                throw new DomainException('Đề xuất không còn ở trạng thái chờ phê duyệt.');
            }
            Workflow::recordDecision($this->db, 'de_xuat_tuyen_dung', $jobId, $step, $approverId, 'Từ chối', $reason);
            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function publish(string $jobId): bool
    {
        $query = $this->db->prepare(
            "UPDATE TUYENDUNG SET TrangThai = 'Đang tuyển'
             WHERE MaTD = :id AND TrangThai = 'Đã duyệt'"
        );
        $query->execute(['id' => $jobId]);

        return $query->rowCount() === 1;
    }

    /** @return list<array{MaTD:string,ViTri:string}> */
    public function openJobs(): array
    {
        $query = $this->db->prepare(
            "SELECT MaTD, ViTri FROM TUYENDUNG
             WHERE TrangThai = 'Đang tuyển' ORDER BY ViTri"
        );
        $query->execute();

        return $query->fetchAll();
    }
}
