<?php
declare(strict_types=1);

namespace App\Core;

use DomainException;
use PDO;

final class Workflow
{
    /** @var array<string,list<string>>|null */
    private static ?array $configuration = null;

    public static function assertNextStep(PDO $db, string $process, string $objectId, string $actorId): string
    {
        $steps = self::steps($process);
        [$requesterId, $departmentId] = self::context($db, $process, $objectId);
        if ($requesterId !== null && hash_equals($requesterId, $actorId)) {
            throw new DomainException('Người gửi không được tự phê duyệt yêu cầu của mình.');
        }

        if ($requesterId !== null && $departmentId !== null && in_array('truong_bp', $steps, true)) {
            $head = $db->prepare('SELECT MaTruongBP FROM PHONGBAN WHERE MaPB = :department');
            $head->execute(['department' => $departmentId]);
            if ($head->fetchColumn() === $requesterId) {
                $steps = array_values(array_filter($steps, static fn (string $step): bool => $step !== 'truong_bp'));
            }
        }
        if ($steps === []) {
            $steps = self::steps($process . '_fallback');
        }

        $history = $db->prepare(
            "SELECT Buoc, KetQua FROM PHEDUYET
             WHERE LoaiQuyTrinh = :process AND MaDoiTuong = :object_id
             ORDER BY MaPD"
        );
        $history->execute(['process' => $process, 'object_id' => $objectId]);
        $approved = [];
        foreach ($history->fetchAll() as $entry) {
            if ($entry['KetQua'] === 'Từ chối') {
                throw new DomainException('Yêu cầu đã bị từ chối; không thể tiếp tục duyệt.');
            }
            if ($entry['KetQua'] === 'Đã duyệt') {
                $approved[(string) $entry['Buoc']] = true;
            }
        }

        $nextStep = null;
        foreach ($steps as $step) {
            if (!isset($approved[$step])) {
                $nextStep = $step;
                break;
            }
        }
        if ($nextStep === null) {
            throw new DomainException('Quy trình đã được phê duyệt đủ các bước.');
        }
        if (!self::actorCanApprove($db, $nextStep, $departmentId, $actorId)) {
            throw new DomainException('Vai trò của bạn không phải người duyệt ở bước hiện tại (' . $nextStep . ').');
        }

        return $nextStep;
    }

    public static function recordDecision(
        PDO $db,
        string $process,
        string $objectId,
        string $step,
        string $actorId,
        string $decision,
        string $reason = ''
    ): void {
        if (!in_array($decision, ['Đã duyệt', 'Từ chối'], true)
            || ($decision === 'Từ chối' && trim($reason) === '')) {
            throw new DomainException('Khi từ chối yêu cầu, bắt buộc nhập lý do.');
        }
        $context = self::context($db, $process, $objectId);
        $insert = $db->prepare(
            'INSERT INTO PHEDUYET
                (LoaiQuyTrinh, MaDoiTuong, Buoc, NguoiGui, NguoiDuyet, KetQua, LyDo, ThoiGian)
             VALUES (:process, :object_id, :step, :requester, :approver, :decision, :reason, CURRENT_TIMESTAMP)'
        );
        $insert->execute([
            'process' => $process,
            'object_id' => $objectId,
            'step' => $step,
            'requester' => $context[0],
            'approver' => $actorId,
            'decision' => $decision,
            'reason' => trim($reason) !== '' ? trim($reason) : null,
        ]);
    }

    /** Ghi nhận lần gửi hồ sơ để lưu đầy đủ lịch sử quy trình. */
    public static function recordSubmission(
        PDO $db,
        string $process,
        string $objectId,
        string $requesterId,
        array $data = []
    ): void {
        $steps = self::steps($process);
        if ($steps === []) {
            throw new DomainException('Quy trình chưa cấu hình bước phê duyệt.');
        }
        $insert = $db->prepare(
            "INSERT INTO PHEDUYET
                (LoaiQuyTrinh, MaDoiTuong, Buoc, NguoiGui, KetQua, DuLieu, ThoiGian)
             VALUES (:process, :object_id, :step, :requester, 'Đã gửi', :data, CURRENT_TIMESTAMP)"
        );
        $insert->execute([
            'process' => $process,
            'object_id' => $objectId,
            'step' => $steps[0],
            'requester' => $requesterId,
            'data' => $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return list<string> */
    public static function steps(string $process): array
    {
        if (self::$configuration === null) {
            $configuration = require APP_ROOT . '/config/workflow.php';
            if (!is_array($configuration)) {
                throw new \RuntimeException('Cấu hình quy trình phê duyệt không hợp lệ.');
            }
            self::$configuration = $configuration;
        }
        $steps = self::$configuration[$process] ?? null;
        if (!is_array($steps)) {
            throw new DomainException('Không tìm thấy quy trình phê duyệt: ' . $process);
        }
        return $steps;
    }

    /** @return array{0:?string,1:?string} */
    private static function context(PDO $db, string $process, string $objectId): array
    {
        [$sql, $column] = match ($process) {
            'nghi_phep' => ['SELECT np.MaNV AS requester, nv.MaPB AS department FROM NGHIPHEP np INNER JOIN NHANVIEN nv ON nv.MaNV=np.MaNV WHERE np.MaNP=:id', 'requester'],
            'tang_ca' => ['SELECT tc.MaNV AS requester, nv.MaPB AS department FROM TANGCA tc INNER JOIN NHANVIEN nv ON nv.MaNV=tc.MaNV WHERE tc.MaTC=:id', 'requester'],
            'de_xuat_tuyen_dung' => ['SELECT DeXuatBoi AS requester, MaPB AS department FROM TUYENDUNG WHERE MaTD=:id', 'requester'],
            'danh_gia_thu_viec' => ['SELECT NULL AS requester, nv.MaPB AS department FROM THUVIEC tv INNER JOIN NHANVIEN nv ON nv.MaNV=tv.MaNV WHERE tv.MaTV=:id', 'requester'],
            'tang_luong' => ['SELECT hd.DeXuatBoi AS requester, nv.MaPB AS department FROM HOPDONG hd INNER JOIN NHANVIEN nv ON nv.MaNV=hd.MaNV WHERE hd.MaHD=:id', 'requester'],
            'dieu_chuyen' => ['SELECT dc.NguoiDeXuat AS requester, dc.BoPhanCu AS department FROM DIEUCHUYEN dc WHERE dc.MaDC=:id', 'requester'],
            'nghi_viec' => ['SELECT COALESCE(nv.NguoiDeXuat, nv.MaNV) AS requester, emp.MaPB AS department FROM NGHIVIEC nv INNER JOIN NHANVIEN emp ON emp.MaNV=nv.MaNV WHERE nv.MaNViec=:id', 'requester'],
            'bang_luong' => ['SELECT NguoiTao AS requester, (SELECT MaPB FROM NHANVIEN WHERE MaNV=LUONG.MaNV) AS department FROM LUONG WHERE MaLuong=:id', 'requester'],
            'dieu_chinh_cong' => ['SELECT nv.MaNV AS requester, nv.MaPB AS department FROM CHAMCONG cc INNER JOIN NHANVIEN nv ON nv.MaNV=cc.MaNV WHERE cc.MaCC=:id', 'requester'],
            default => throw new DomainException('Không hỗ trợ đối tượng của quy trình này.'),
        };
        $query = $db->prepare($sql);
        $query->execute(['id' => $objectId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new DomainException('Không tìm thấy hồ sơ cần phê duyệt.');
        }
        return [
            is_string($row[$column]) ? $row[$column] : null,
            is_string($row['department']) ? $row['department'] : null,
        ];
    }

    private static function actorCanApprove(PDO $db, string $step, ?string $departmentId, string $actorId): bool
    {
        if ($step === 'truong_bp') {
            if ($departmentId === null) {
                return false;
            }
            $query = $db->prepare('SELECT MaTruongBP FROM PHONGBAN WHERE MaPB = :department');
            $query->execute(['department' => $departmentId]);
            return $query->fetchColumn() === $actorId;
        }
        return Auth::hasRole($step);
    }
}
