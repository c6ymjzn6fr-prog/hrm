<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\RecordId;
use App\Core\Scope;
use App\Core\Workflow;
use DateTimeImmutable;
use DomainException;
use PDO;

final class PersonnelOperations
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function employees(): array
    {
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $query = $this->db->prepare(
            "SELECT nv.MaNV, nv.HoTen, nv.NgayVaoLam, nv.MaPB, nv.MaCV, nv.TrangThai,
                    pb.TenPB, cv.TenCV
             FROM NHANVIEN nv
             INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
             INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV
             WHERE nv.TrangThai <> 'Nghỉ việc' AND (" . $scopeSql . ")
             ORDER BY nv.HoTen"
        );
        $query->execute($scopeParams);

        return $query->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function reviews(?string $employeeId = null): array
    {
        $sql = 'SELECT dg.*, nv.HoTen, nv.MaNV FROM DANHGIA dg
                INNER JOIN NHANVIEN nv ON nv.MaNV = dg.MaNV';
        [$scopeSql, $params] = Scope::nhanVien('nv');
        $sql .= ' WHERE (' . $scopeSql . ')';
        if ($employeeId !== null) {
            $sql .= ' AND dg.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $query = $this->db->prepare($sql . ' ORDER BY dg.NgayDanhGia DESC, nv.HoTen LIMIT 300');
        $query->execute($params);

        return $query->fetchAll();
    }

    public function createReview(
        string $employeeId,
        string $date,
        string $outcome,
        string $rating,
        string $note
    ): void {
        if (!$this->validDate($date) || $date > date('Y-m-d')
            || mb_strlen($outcome, 'UTF-8') < 2 || mb_strlen($outcome, 'UTF-8') > 4000
            || mb_strlen($rating, 'UTF-8') < 2 || mb_strlen($rating, 'UTF-8') > 50
            || mb_strlen($note, 'UTF-8') > 4000) {
            throw new DomainException('Ngày hoặc nội dung đánh giá không hợp lệ.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $query = $this->db->prepare(
            'INSERT INTO DANHGIA (MaDG, MaNV, NgayDanhGia, KetQua, XepLoai, NhanXet)
             VALUES (:id, :employee_id, :date, :outcome, :rating, :note)'
        );
        $query->execute([
            'id' => RecordId::make('DG'),
            'employee_id' => $employeeId,
            'date' => $date,
            'outcome' => $outcome,
            'rating' => $rating,
            'note' => $note !== '' ? $note : null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function salaryChanges(?string $employeeId = null): array
    {
        $sql = 'SELECT hd.MaHD, hd.MaNV, hd.NgayBatDau AS NgayHieuLuc, hd.MucLuong, hd.TrangThai,
                       hd.DeXuatBoi, hd.LyDoDieuChinh, hd.NguoiPheDuyet, hd.NgayPheDuyet,
                       nv.HoTen, dx.HoTen AS TenNguoiDeXuat, duy.HoTen AS TenNguoiPheDuyet
                FROM HOPDONG hd
                INNER JOIN NHANVIEN nv ON nv.MaNV = hd.MaNV
                LEFT JOIN NHANVIEN dx ON dx.MaNV = hd.DeXuatBoi
                LEFT JOIN NHANVIEN duy ON duy.MaNV = hd.NguoiPheDuyet
                WHERE hd.TrangThai IN (\'Chờ duyệt tăng lương\', \'Hiệu lực\', \'Hết hiệu lực\')';
        [$scopeSql, $params] = Scope::nhanVien('nv');
        $sql .= ' AND (' . $scopeSql . ')';
        if ($employeeId !== null) {
            $sql .= ' AND hd.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $query = $this->db->prepare($sql . ' ORDER BY hd.NgayBatDau DESC, nv.HoTen LIMIT 500');
        $query->execute($params);

        return $query->fetchAll();
    }

    public function proposeSalary(
        string $employeeId,
        float $newSalary,
        string $effectiveDate,
        string $reason,
        string $requesterId
    ): void {
        if ($newSalary <= 0 || $newSalary > 999999999999.99 || !$this->validDate($effectiveDate)
            || $effectiveDate < date('Y-m-d') || mb_strlen($reason, 'UTF-8') < 3
            || mb_strlen($reason, 'UTF-8') > 2000) {
            throw new DomainException('Mức lương, ngày hiệu lực hoặc lý do đề xuất không hợp lệ.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $this->db->beginTransaction();
        try {
            $current = $this->db->prepare(
                "SELECT MaHD, LoaiHD, NgayBatDau, NgayKetThuc, MucLuong
                 FROM HOPDONG WHERE MaNV = :employee_id AND TrangThai = 'Hiệu lực'
                   AND NgayBatDau <= :effective_date
                   AND (NgayKetThuc IS NULL OR NgayKetThuc >= :effective_end)
                 ORDER BY NgayBatDau DESC LIMIT 1 FOR UPDATE"
            );
            $current->execute([
                'employee_id' => $employeeId,
                'effective_date' => $effectiveDate,
                'effective_end' => $effectiveDate,
            ]);
            $contract = $current->fetch();
            if ($contract === false) {
                throw new DomainException('Nhân viên chưa có hợp đồng hiệu lực vào ngày đề xuất.');
            }
            if ((float) $newSalary <= (float) $contract['MucLuong']) {
                throw new DomainException('Tăng lương mới phải cao hơn mức lương hợp đồng hiện tại.');
            }
            if ($effectiveDate <= (string) $contract['NgayBatDau']) {
                throw new DomainException('Ngày hiệu lực phải sau ngày bắt đầu hợp đồng hiện tại để lưu lịch sử.');
            }
            $pending = $this->db->prepare(
                "SELECT COUNT(*) FROM HOPDONG
                 WHERE MaNV = :employee_id AND TrangThai = 'Chờ duyệt tăng lương'"
            );
            $pending->execute(['employee_id' => $employeeId]);
            if ((int) $pending->fetchColumn() > 0) {
                throw new DomainException('Nhân viên đã có đề xuất tăng lương đang chờ duyệt.');
            }

            $contractId = RecordId::make('HD');
            $insert = $this->db->prepare(
                "INSERT INTO HOPDONG
                    (MaHD, MaNV, LoaiHD, NgayBatDau, NgayKetThuc, MucLuong, TrangThai,
                     DeXuatBoi, LyDoDieuChinh)
                 VALUES (:id, :employee_id, :type, :effective_date, :contract_end, :salary,
                         'Chờ duyệt tăng lương', :requester_id, :reason)"
            );
            $insert->execute([
                'id' => $contractId,
                'employee_id' => $employeeId,
                'type' => $contract['LoaiHD'],
                'effective_date' => $effectiveDate,
                'contract_end' => $contract['NgayKetThuc'],
                'salary' => $newSalary,
                'requester_id' => $requesterId,
                'reason' => $reason,
            ]);
            Workflow::recordSubmission($this->db, 'tang_luong', $contractId, $requesterId, [
                'MaNV' => $employeeId,
                'MucLuong' => $newSalary,
                'NgayBatDau' => $effectiveDate,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function approveSalary(string $contractId, string $decision, string $directorId, string $reason = ''): void
    {
        if (!in_array($decision, ['Đã duyệt', 'Từ chối'], true)) {
            throw new DomainException('Quyết định tăng lương không hợp lệ.');
        }
        $owner = $this->db->prepare('SELECT MaNV FROM HOPDONG WHERE MaHD = :id');
        $owner->execute(['id' => $contractId]);
        $employeeId = $owner->fetchColumn();
        if (!is_string($employeeId)) {
            throw new DomainException('Không tìm thấy đề xuất tăng lương.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $this->db->beginTransaction();
        try {
            $proposalQuery = $this->db->prepare(
                "SELECT * FROM HOPDONG WHERE MaHD = :id AND TrangThai = 'Chờ duyệt tăng lương' FOR UPDATE"
            );
            $proposalQuery->execute(['id' => $contractId]);
            $proposal = $proposalQuery->fetch();
            if ($proposal === false) {
                throw new DomainException('Không tìm thấy đề xuất tăng lương đang chờ duyệt.');
            }
            $step = Workflow::assertNextStep($this->db, 'tang_luong', $contractId, $directorId);
            if ($decision === 'Từ chối') {
                if (trim($reason) === '') {
                    throw new DomainException('Khi từ chối đề xuất tăng lương, bắt buộc nhập lý do.');
                }
                $reject = $this->db->prepare(
                    "UPDATE HOPDONG SET TrangThai = 'Từ chối', NguoiPheDuyet = :director_id,
                        NgayPheDuyet = CURRENT_DATE() WHERE MaHD = :id"
                );
                $reject->execute(['director_id' => $directorId, 'id' => $contractId]);
                Workflow::recordDecision(
                    $this->db,
                    'tang_luong',
                    $contractId,
                    $step,
                    $directorId,
                    'Từ chối',
                    $reason
                );
                $this->db->commit();
                return;
            }

            $oldQuery = $this->db->prepare(
                "SELECT MaHD, NgayBatDau FROM HOPDONG
                 WHERE MaNV = :employee_id AND TrangThai = 'Hiệu lực'
                   AND NgayBatDau < :effective_date
                   AND (NgayKetThuc IS NULL OR NgayKetThuc >= :effective_end)
                 ORDER BY NgayBatDau DESC LIMIT 1 FOR UPDATE"
            );
            $oldQuery->execute([
                'employee_id' => $proposal['MaNV'],
                'effective_date' => $proposal['NgayBatDau'],
                'effective_end' => $proposal['NgayBatDau'],
            ]);
            $old = $oldQuery->fetch();
            if ($old === false) {
                throw new DomainException('Không còn hợp đồng hiện tại để chốt lịch sử mức lương.');
            }
            $oldContract = $this->db->prepare(
                "UPDATE HOPDONG SET NgayKetThuc = DATE_SUB(:effective_date, INTERVAL 1 DAY),
                        TrangThai = 'Hết hiệu lực'
                 WHERE MaHD = :id"
            );
            $oldContract->execute(['effective_date' => $proposal['NgayBatDau'], 'id' => $old['MaHD']]);
            $approve = $this->db->prepare(
                "UPDATE HOPDONG SET TrangThai = 'Hiệu lực', NguoiPheDuyet = :director_id,
                        NgayPheDuyet = CURRENT_DATE()
                 WHERE MaHD = :id AND TrangThai = 'Chờ duyệt tăng lương'"
            );
            $approve->execute(['director_id' => $directorId, 'id' => $contractId]);
            Workflow::recordDecision($this->db, 'tang_luong', $contractId, $step, $directorId, 'Đã duyệt');
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function transfers(): array
    {
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $query = $this->db->prepare(
            'SELECT dc.*, nv.HoTen, nv.MaPB AS PhongBanHienTai, nv.MaCV AS ChucVuHienTai,
                    pbcu.TenPB AS TenBoPhanCu, pbmoi.TenPB AS TenBoPhanMoi,
                    cvcu.TenCV AS TenViTriCu, cvmoi.TenCV AS TenViTriMoi
             FROM DIEUCHUYEN dc
             INNER JOIN NHANVIEN nv ON nv.MaNV = dc.MaNV
             INNER JOIN PHONGBAN pbcu ON pbcu.MaPB = dc.BoPhanCu
             INNER JOIN PHONGBAN pbmoi ON pbmoi.MaPB = dc.BoPhanMoi
             INNER JOIN CHUCVU cvcu ON cvcu.MaCV = dc.ViTriCu
             INNER JOIN CHUCVU cvmoi ON cvmoi.MaCV = dc.ViTriMoi
             WHERE (' . $scopeSql . ')
             ORDER BY CASE dc.TrangThai WHEN \'Chờ phê duyệt\' THEN 0 ELSE 1 END, dc.NgayDC DESC LIMIT 300'
        );
        $query->execute($scopeParams);

        return $query->fetchAll();
    }

    public function createTransfer(
        string $employeeId,
        string $newDepartment,
        string $newPosition,
        string $date,
        string $reason,
        string $requesterId
    ): void {
        if (!$this->validDate($date) || $date < date('Y-m-d')
            || mb_strlen($reason, 'UTF-8') < 3 || mb_strlen($reason, 'UTF-8') > 2000) {
            throw new DomainException('Ngày điều chuyển hoặc lý do không hợp lệ.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('SELECT MaPB, MaCV FROM NHANVIEN WHERE MaNV = :id FOR UPDATE');
            $query->execute(['id' => $employeeId]);
            $employee = $query->fetch();
            if ($employee === false) {
                throw new DomainException('Không tìm thấy nhân viên.');
            }
            if ($employee['MaPB'] === $newDepartment) {
                throw new DomainException('Phòng ban mới phải khác phòng ban hiện tại.');
            }
            $pending = $this->db->prepare(
                "SELECT COUNT(*) FROM DIEUCHUYEN WHERE MaNV = :id AND TrangThai = 'Chờ phê duyệt'"
            );
            $pending->execute(['id' => $employeeId]);
            if ((int) $pending->fetchColumn() > 0) {
                throw new DomainException('Nhân viên đã có đề nghị điều chuyển đang chờ duyệt.');
            }
            $transferId = RecordId::make('DC');
            $insert = $this->db->prepare(
                "INSERT INTO DIEUCHUYEN
                    (MaDC, MaNV, NgayDC, BoPhanCu, BoPhanMoi, ViTriCu, ViTriMoi, LyDo, TrangThai, NguoiDeXuat)
                 VALUES (:id, :employee_id, :date, :old_department, :new_department, :old_position,
                         :new_position, :reason, 'Chờ phê duyệt', :requester_id)"
            );
            $insert->execute([
                'id' => $transferId,
                'employee_id' => $employeeId,
                'date' => $date,
                'old_department' => $employee['MaPB'],
                'new_department' => $newDepartment,
                'old_position' => $employee['MaCV'],
                'new_position' => $newPosition,
                'reason' => $reason,
                'requester_id' => $requesterId,
            ]);
            Workflow::recordSubmission($this->db, 'dieu_chuyen', $transferId, $requesterId, [
                'MaNV' => $employeeId,
                'BoPhanMoi' => $newDepartment,
                'ViTriMoi' => $newPosition,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function decideTransfer(string $transferId, string $decision, string $directorId, string $reason = ''): void
    {
        if (!in_array($decision, ['Đã duyệt', 'Từ chối'], true)) {
            throw new DomainException('Quyết định điều chuyển không hợp lệ.');
        }
        $owner = $this->db->prepare('SELECT MaNV FROM DIEUCHUYEN WHERE MaDC = :id');
        $owner->execute(['id' => $transferId]);
        $employeeId = $owner->fetchColumn();
        if (!is_string($employeeId)) {
            throw new DomainException('Không tìm thấy đề nghị điều chuyển.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare(
                "SELECT * FROM DIEUCHUYEN WHERE MaDC = :id AND TrangThai = 'Chờ phê duyệt' FOR UPDATE"
            );
            $query->execute(['id' => $transferId]);
            $transfer = $query->fetch();
            if ($transfer === false) {
                throw new DomainException('Không tìm thấy đề nghị điều chuyển đang chờ duyệt.');
            }
            $step = Workflow::assertNextStep($this->db, 'dieu_chuyen', $transferId, $directorId);
            if ($decision === 'Từ chối' && trim($reason) === '') {
                throw new DomainException('Khi từ chối đề nghị điều chuyển, bắt buộc nhập lý do.');
            }
            if ($decision === 'Đã duyệt') {
                $employee = $this->db->prepare('SELECT MaPB, MaCV FROM NHANVIEN WHERE MaNV = :id FOR UPDATE');
                $employee->execute(['id' => $transfer['MaNV']]);
                $current = $employee->fetch();
                if ($current === false || $current['MaPB'] !== $transfer['BoPhanCu']
                    || $current['MaCV'] !== $transfer['ViTriCu']) {
                    throw new DomainException('Thông tin nhân viên đã đổi kể từ lúc đề xuất; vui lòng tạo đề nghị mới.');
                }
                $updateEmployee = $this->db->prepare(
                    'UPDATE NHANVIEN SET MaPB = :department, MaCV = :position WHERE MaNV = :id'
                );
                $updateEmployee->execute([
                    'department' => $transfer['BoPhanMoi'],
                    'position' => $transfer['ViTriMoi'],
                    'id' => $transfer['MaNV'],
                ]);
            }
            $update = $this->db->prepare(
                'UPDATE DIEUCHUYEN SET TrangThai = :status, NguoiPheDuyet = :director_id,
                    NgayPheDuyet = CURRENT_DATE() WHERE MaDC = :id'
            );
            $update->execute(['status' => $decision, 'director_id' => $directorId, 'id' => $transferId]);
            Workflow::recordDecision(
                $this->db,
                'dieu_chuyen',
                $transferId,
                $step,
                $directorId,
                $decision,
                $reason
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function resignations(?string $employeeId = null): array
    {
        $sql = 'SELECT nvx.*, nv.HoTen, nv.MaNV,
                       CASE
                           WHEN pb.MaTruongBP = COALESCE(nvx.NguoiDeXuat, nv.MaNV)
                             OR EXISTS (
                                 SELECT 1 FROM PHEDUYET pd
                                 WHERE pd.LoaiQuyTrinh = \'nghi_viec\' AND pd.MaDoiTuong = nvx.MaNViec
                                   AND pd.Buoc = \'truong_bp\' AND pd.KetQua = \'Đã duyệt\'
                             ) THEN \'giamdoc\'
                           ELSE \'truong_bp\'
                       END AS BuocTiepTheo,
                       (SELECT hd.LoaiHD FROM HOPDONG hd WHERE hd.MaNV = nv.MaNV
                        ORDER BY hd.NgayBatDau DESC LIMIT 1) AS LoaiHD
                FROM NGHIVIEC nvx INNER JOIN NHANVIEN nv ON nv.MaNV = nvx.MaNV
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB';
        [$scopeSql, $params] = Scope::nhanVien('nv');
        $sql .= ' WHERE (' . $scopeSql . ')';
        if ($employeeId !== null) {
            $sql .= ' AND nvx.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $query = $this->db->prepare($sql . ' ORDER BY nvx.NgayDeNghi DESC LIMIT 300');
        $query->execute($params);

        return $query->fetchAll();
    }

    public function createResignation(
        string $employeeId,
        string $departureDate,
        string $reason,
        string $requesterId
    ): void
    {
        if (!$this->validDate($departureDate) || mb_strlen($reason, 'UTF-8') < 3
            || mb_strlen($reason, 'UTF-8') > 2000) {
            throw new DomainException('Ngày nghỉ hoặc lý do nghỉ việc không hợp lệ.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $this->db->beginTransaction();
        try {
            $employee = $this->db->prepare("SELECT TrangThai FROM NHANVIEN WHERE MaNV = :id FOR UPDATE");
            $employee->execute(['id' => $employeeId]);
            $status = $employee->fetchColumn();
            if ($status === false || $status === 'Nghỉ việc') {
                throw new DomainException('Nhân viên không tồn tại hoặc đã nghỉ việc.');
            }
            $activeRequest = $this->db->prepare(
                "SELECT COUNT(*) FROM NGHIVIEC
                 WHERE MaNV = :id AND TrangThai NOT IN ('Lưu hồ sơ', 'Từ chối')"
            );
            $activeRequest->execute(['id' => $employeeId]);
            if ((int) $activeRequest->fetchColumn() > 0) {
                throw new DomainException('Nhân viên đã có đề nghị nghỉ việc đang xử lý.');
            }
            $contractQuery = $this->db->prepare(
                "SELECT LoaiHD, NgayBatDau, NgayKetThuc FROM HOPDONG
                 WHERE MaNV = :id AND TrangThai = 'Hiệu lực'
                   AND NgayBatDau <= CURRENT_DATE()
                   AND (NgayKetThuc IS NULL OR NgayKetThuc >= CURRENT_DATE())
                 ORDER BY NgayBatDau DESC LIMIT 1"
            );
            $contractQuery->execute(['id' => $employeeId]);
            $contract = $contractQuery->fetch();
            if ($contract === false) {
                throw new DomainException('Thiếu hợp đồng hiệu lực để kiểm tra thời hạn báo trước.');
            }
            $minimumDate = $this->minimumDepartureDate($contract);
            if ($departureDate < $minimumDate) {
                throw new DomainException('Thời hạn báo trước chưa đủ. Ngày nghỉ sớm nhất theo hợp đồng là '
                    . date('d/m/Y', strtotime($minimumDate)) . '.');
            }
            $requestId = RecordId::make('NV');
            $insert = $this->db->prepare(
                "INSERT INTO NGHIVIEC
                    (MaNViec, MaNV, NgayDeNghi, NgayNghi, LyDo, TrangThai, NguoiDeXuat)
                 VALUES (:id, :employee_id, CURRENT_DATE(), :departure_date, :reason, 'Tiếp nhận', :requester)"
            );
            $insert->execute([
                'id' => $requestId,
                'employee_id' => $employeeId,
                'departure_date' => $departureDate,
                'reason' => $reason,
                'requester' => $requesterId,
            ]);
            Workflow::recordSubmission($this->db, 'nghi_viec', $requestId, $requesterId, [
                'MaNV' => $employeeId,
                'NgayNghi' => $departureDate,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function advanceResignation(
        string $requestId,
        string $action,
        string $actorId,
        array $roles,
        ?string $payrollMonth = null,
        string $reason = ''
    ): void
    {
        $owner = $this->db->prepare('SELECT MaNV FROM NGHIVIEC WHERE MaNViec = :id');
        $owner->execute(['id' => $requestId]);
        $employeeId = $owner->fetchColumn();
        if (!is_string($employeeId)) {
            throw new DomainException('Không tìm thấy hồ sơ nghỉ việc.');
        }
        Scope::requireEmployee($this->db, $employeeId);

        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('SELECT * FROM NGHIVIEC WHERE MaNViec = :id FOR UPDATE');
            $query->execute(['id' => $requestId]);
            $request = $query->fetch();
            if ($request === false) {
                throw new DomainException('Không tìm thấy hồ sơ nghỉ việc.');
            }
            if ($action === 'tiếp nhận') {
                $this->requireRole($roles, ['nhansu']);
                $this->expectStage($request, 'Tiếp nhận');
                $this->setResignationStage($requestId, 'Phê duyệt');
            } elseif ($action === 'phê duyệt') {
                $this->expectStage($request, 'Phê duyệt');
                $step = Workflow::assertNextStep($this->db, 'nghi_viec', $requestId, $actorId);
                Workflow::recordDecision($this->db, 'nghi_viec', $requestId, $step, $actorId, 'Đã duyệt');
                if ($step === 'giamdoc') {
                    $approve = $this->db->prepare(
                        "UPDATE NGHIVIEC SET TrangThai = 'Bàn giao', NguoiPheDuyet = :actor,
                            NgayPheDuyet = CURRENT_DATE() WHERE MaNViec = :id"
                    );
                    $approve->execute(['actor' => $actorId, 'id' => $requestId]);
                }
            } elseif ($action === 'từ chối') {
                $this->expectStage($request, 'Phê duyệt');
                $step = Workflow::assertNextStep($this->db, 'nghi_viec', $requestId, $actorId);
                if (trim($reason) === '') {
                    throw new DomainException('Khi từ chối đề nghị nghỉ việc, bắt buộc nhập lý do.');
                }
                Workflow::recordDecision($this->db, 'nghi_viec', $requestId, $step, $actorId, 'Từ chối', $reason);
                $this->setResignationStage($requestId, 'Từ chối');
            } elseif ($action === 'bàn giao') {
                $this->requireRole($roles, ['nhansu']);
                $this->expectStage($request, 'Bàn giao');
                $update = $this->db->prepare(
                    "UPDATE NGHIVIEC SET BanGiao = 1, TrangThai = 'Chốt công/lương',
                        NgayBanGiao = CURRENT_DATE() WHERE MaNViec = :id AND BanGiao = 0"
                );
                $update->execute(['id' => $requestId]);
            } elseif ($action === 'chốt lương') {
                $this->requireRole($roles, ['ketoan']);
                $this->expectStage($request, 'Chốt công/lương');
                if ((int) $request['BanGiao'] !== 1) {
                    throw new DomainException('Chưa bàn giao đầy đủ; không thể chốt lương.');
                }
                if ($payrollMonth === null || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $payrollMonth) !== 1
                    || $payrollMonth . '-01' > (string) $request['NgayNghi']) {
                    throw new DomainException('Chọn kỳ lương đã phê duyệt và không sau ngày nghỉ việc.');
                }
                $payroll = $this->db->prepare(
                    "SELECT 1 FROM LUONG
                     WHERE MaNV = :employee_id AND Nam = :year AND Thang = :month AND TrangThai = 'Đã duyệt'
                     LIMIT 1"
                );
                $payroll->execute([
                    'employee_id' => $request['MaNV'],
                    'year' => (int) substr($payrollMonth, 0, 4),
                    'month' => (int) substr($payrollMonth, 5, 2),
                ]);
                if ($payroll->fetchColumn() === false) {
                    throw new DomainException('Chưa có phiếu lương đã được phê duyệt cho kỳ được chọn.');
                }
                $update = $this->db->prepare(
                    'UPDATE NGHIVIEC SET LuongDaChot = 1, NgayChotLuong = CURRENT_DATE()
                     WHERE MaNViec = :id AND BanGiao = 1 AND LuongDaChot = 0'
                );
                $update->execute(['id' => $requestId]);
            } elseif ($action === 'lưu hồ sơ') {
                $this->requireRole($roles, ['nhansu']);
                $this->expectStage($request, 'Chốt công/lương');
                if ((int) $request['BanGiao'] !== 1 || (int) $request['LuongDaChot'] !== 1) {
                    throw new DomainException('Chỉ được lưu hồ sơ sau khi bàn giao và chốt lương.');
                }
                $update = $this->db->prepare(
                    "UPDATE NGHIVIEC SET TrangThai = 'Lưu hồ sơ', NgayLuuHoSo = CURRENT_DATE()
                     WHERE MaNViec = :id AND BanGiao = 1 AND LuongDaChot = 1"
                );
                $update->execute(['id' => $requestId]);
                $employee = $this->db->prepare(
                    "UPDATE NHANVIEN SET TrangThai = 'Nghỉ việc' WHERE MaNV = :employee_id"
                );
                $employee->execute(['employee_id' => $request['MaNV']]);
                $account = $this->db->prepare(
                    "UPDATE TAIKHOAN SET TrangThai = 'Ngưng hoạt động' WHERE MaNV = :employee_id"
                );
                $account->execute(['employee_id' => $request['MaNV']]);
            } else {
                throw new DomainException('Bước quy trình nghỉ việc không hợp lệ.');
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $contract */
    private function minimumDepartureDate(array $contract): string
    {
        $type = mb_strtolower((string) $contract['LoaiHD'], 'UTF-8');
        if (str_contains($type, 'không xác định')) {
            return (new DateTimeImmutable())->modify('+45 days')->format('Y-m-d');
        }
        if ($contract['NgayKetThuc'] !== null) {
            $duration = (int) (new DateTimeImmutable((string) $contract['NgayBatDau']))
                ->diff(new DateTimeImmutable((string) $contract['NgayKetThuc']))->days + 1;
            if ($duration < 365) {
                $date = new DateTimeImmutable();
                $businessDays = 0;
                while ($businessDays < 3) {
                    $date = $date->modify('+1 day');
                    if ((int) $date->format('N') <= 5) {
                        $businessDays++;
                    }
                }
                return $date->format('Y-m-d');
            }
        }

        return (new DateTimeImmutable())->modify('+30 days')->format('Y-m-d');
    }

    /** @param array<string,mixed> $request */
    private function expectStage(array $request, string $stage): void
    {
        if ($request['TrangThai'] !== $stage) {
            throw new DomainException('Hồ sơ không ở bước "' . $stage . '" của quy trình.');
        }
    }

    private function setResignationStage(string $requestId, string $stage): void
    {
        $update = $this->db->prepare('UPDATE NGHIVIEC SET TrangThai = :stage WHERE MaNViec = :id');
        $update->execute(['stage' => $stage, 'id' => $requestId]);
    }

    /** @param list<string> $allowed */
    private function requireRole(array $roles, array $allowed): void
    {
        if (array_intersect($roles, $allowed) === []) {
            throw new DomainException('Vai trò hiện tại không được xử lý bước này.');
        }
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false && $parsed->format('Y-m-d') === $date
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
