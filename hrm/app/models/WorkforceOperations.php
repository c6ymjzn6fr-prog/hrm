<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\ProbationPolicy;
use App\Core\RecordId;
use App\Core\Scope;
use App\Core\Workflow;
use DateTimeImmutable;
use DomainException;
use PDO;

final class WorkforceOperations
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function probationList(?string $employeeId = null): array
    {
        $sql = 'SELECT tv.MaTV, tv.MaNV, tv.NgayBatDau, tv.NgayKetThuc, tv.KetQua, tv.NhanXet,
                       nv.HoTen, nv.TrangThai, cv.TenCV, hs.TrinhDo
                FROM THUVIEC tv
                INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
                INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV
                LEFT JOIN HOSONHANVIEN hs ON hs.MaNV = nv.MaNV';
        $params = [];
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $sql .= ' WHERE (' . $scopeSql . ')';
        $params += $scopeParams;
        if ($employeeId !== null) {
            $sql .= ' AND tv.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $sql .= ' ORDER BY tv.NgayKetThuc, nv.HoTen';
        $query = $this->db->prepare($sql);
        $query->execute($params);

        return $query->fetchAll();
    }

    public function evaluateProbation(string $probationId, string $result, string $note, string $reviewerId): void
    {
        if (!in_array($result, ['Đạt', 'Không đạt'], true) || mb_strlen($note, 'UTF-8') > 4000) {
            throw new DomainException('Kết quả hoặc nhận xét thử việc không hợp lệ.');
        }
        $owner = $this->db->prepare('SELECT MaNV FROM THUVIEC WHERE MaTV = :id');
        $owner->execute(['id' => $probationId]);
        $employeeId = $owner->fetchColumn();
        if (!is_string($employeeId)) {
            throw new DomainException('Không tìm thấy hồ sơ thử việc.');
        }
        Scope::requireEmployee($this->db, $employeeId);

        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare(
                'SELECT tv.MaNV, tv.NgayBatDau, tv.NgayKetThuc, tv.KetQua, nv.TrangThai,
                        cv.TenCV, hs.TrinhDo
                 FROM THUVIEC tv
                 INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
                 INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV
                 LEFT JOIN HOSONHANVIEN hs ON hs.MaNV = nv.MaNV
                 WHERE tv.MaTV = :id FOR UPDATE'
            );
            $query->execute(['id' => $probationId]);
            $trial = $query->fetch();
            if ($trial === false) {
                throw new DomainException('Không tìm thấy hồ sơ thử việc.');
            }
            if ($trial['KetQua'] !== null) {
                throw new DomainException('Hồ sơ thử việc đã được đánh giá.');
            }
            if ($trial['TrangThai'] !== 'Thử việc') {
                throw new DomainException('Nhân viên không còn ở trạng thái thử việc.');
            }
            $step = Workflow::assertNextStep($this->db, 'danh_gia_thu_viec', $probationId, $reviewerId);

            $duration = ProbationPolicy::durationDays(
                (string) $trial['NgayBatDau'],
                (string) $trial['NgayKetThuc']
            );
            $maximum = ProbationPolicy::maximumDays(
                (string) ($trial['TrinhDo'] ?? ''),
                (string) $trial['TenCV']
            );
            if ($duration === null || $duration > $maximum) {
                throw new DomainException("Thời hạn thử việc vượt mức tối đa {$maximum} ngày theo trình độ/chức vụ.");
            }

            $update = $this->db->prepare(
                'UPDATE THUVIEC SET KetQua = :result, NhanXet = :note WHERE MaTV = :id'
            );
            $update->execute(['result' => $result, 'note' => $note !== '' ? $note : null, 'id' => $probationId]);
            if ($result === 'Đạt') {
                $employee = $this->db->prepare(
                    "UPDATE NHANVIEN SET TrangThai = 'Chính thức' WHERE MaNV = :employee_id AND TrangThai = 'Thử việc'"
                );
                $employee->execute(['employee_id' => $trial['MaNV']]);
                if ($employee->rowCount() !== 1) {
                    throw new DomainException('Không thể chuyển nhân viên sang trạng thái chính thức.');
                }
                Workflow::recordDecision(
                    $this->db,
                    'danh_gia_thu_viec',
                    $probationId,
                    $step,
                    $reviewerId,
                    'Đã duyệt',
                    $result . ($note !== '' ? ': ' . $note : '')
                );
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{summary:array{days:int,complete:int,incomplete:int,overtime:float},today:array<string,mixed>|null,history:list<array<string,mixed>>} */
    public function attendanceData(string $employeeId, string $month): array
    {
        [$start, $end] = $this->monthBounds($month);
        $summary = $this->db->prepare(
            'SELECT COUNT(*) AS days,
                    SUM(CASE WHEN GioVao IS NOT NULL AND GioRa IS NOT NULL THEN 1 ELSE 0 END) AS complete,
                    SUM(CASE WHEN GioVao IS NULL OR GioRa IS NULL THEN 1 ELSE 0 END) AS incomplete
             FROM CHAMCONG WHERE MaNV = :employee_id AND Ngay BETWEEN :start AND :end'
        );
        $summary->execute(['employee_id' => $employeeId, 'start' => $start, 'end' => $end]);
        $totals = $summary->fetch() ?: [];

        $overtime = $this->db->prepare(
            "SELECT COALESCE(SUM(SoGio), 0) FROM TANGCA
             WHERE MaNV = :employee_id AND Ngay BETWEEN :start AND :end AND TrangThai = 'Đã duyệt'"
        );
        $overtime->execute(['employee_id' => $employeeId, 'start' => $start, 'end' => $end]);

        $today = $this->db->prepare(
            'SELECT MaCC, Ngay, GioVao, GioRa, TrangThai, DaChot
             FROM CHAMCONG WHERE MaNV = :employee_id AND Ngay = CURRENT_DATE() LIMIT 1'
        );
        $today->execute(['employee_id' => $employeeId]);
        $todayRow = $today->fetch();

        $history = $this->db->prepare(
            'SELECT MaCC, Ngay, GioVao, GioRa, TrangThai, DaChot FROM CHAMCONG
             WHERE MaNV = :employee_id AND Ngay BETWEEN :start AND :end
             ORDER BY Ngay DESC LIMIT 31'
        );
        $history->execute(['employee_id' => $employeeId, 'start' => $start, 'end' => $end]);

        return [
            'summary' => [
                'days' => (int) ($totals['days'] ?? 0),
                'complete' => (int) ($totals['complete'] ?? 0),
                'incomplete' => (int) ($totals['incomplete'] ?? 0),
                'overtime' => (float) $overtime->fetchColumn(),
            ],
            'today' => $todayRow === false ? null : $todayRow,
            'history' => $history->fetchAll(),
        ];
    }

    public function checkIn(string $employeeId): void
    {
        $monthStart = date('Y-m-01');
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
        $closed = $this->db->prepare(
            'SELECT COUNT(*) FROM CHAMCONG
             WHERE MaNV = :employee_id AND Ngay BETWEEN :start AND :end AND DaChot = 1'
        );
        $closed->execute(['employee_id' => $employeeId, 'start' => $monthStart, 'end' => $monthEnd]);
        if ((int) $closed->fetchColumn() > 0) {
            throw new DomainException('Kỳ chấm công tháng này đã được quản lý xác nhận.');
        }

        $query = $this->db->prepare(
            "INSERT INTO CHAMCONG (MaCC, MaNV, Ngay, GioVao, TrangThai)
             VALUES (:id, :employee_id, CURRENT_DATE(), CURRENT_TIME(), 'Chưa hoàn tất')"
        );
        try {
            $query->execute(['id' => RecordId::make('CC'), 'employee_id' => $employeeId]);
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new DomainException('Bạn đã có bản ghi chấm công trong ngày hôm nay.');
            }
            throw $exception;
        }
    }

    public function checkOut(string $employeeId): void
    {
        $query = $this->db->prepare(
            "UPDATE CHAMCONG SET GioRa = CURRENT_TIME(), TrangThai = 'Đủ công'
             WHERE MaNV = :employee_id AND Ngay = CURRENT_DATE()
               AND GioVao IS NOT NULL AND GioRa IS NULL AND DaChot = 0"
        );
        $query->execute(['employee_id' => $employeeId]);
        if ($query->rowCount() !== 1) {
            throw new DomainException('Chưa chấm vào, đã chấm ra hoặc kỳ chấm công đã được xác nhận.');
        }
    }

    public function createAttendanceCorrection(
            string $attendanceId,
            string $employeeId,
            ?string $checkIn,
            ?string $checkOut,
            string $reason
        ): void {
            if (trim($reason) === '' || mb_strlen($reason, 'UTF-8') > 1000
                || ($checkIn !== null && !$this->validTime($checkIn))
                || ($checkOut !== null && !$this->validTime($checkOut))
                || ($checkIn === null && $checkOut === null)) {
                throw new DomainException('Giờ cần điều chỉnh hoặc lý do không hợp lệ.');
            }
            $owner = $this->db->prepare('SELECT MaNV FROM CHAMCONG WHERE MaCC = :id');
            $owner->execute(['id' => $attendanceId]);
            if ($owner->fetchColumn() !== $employeeId) {
                throw new DomainException('Không tìm thấy bản ghi chấm công của bạn.');
            }
            Scope::requireEmployee($this->db, $employeeId);

            $this->db->beginTransaction();
            try {
                $attendance = $this->db->prepare(
                    'SELECT DaChot FROM CHAMCONG WHERE MaCC = :id AND MaNV = :employee_id FOR UPDATE'
                );
                $attendance->execute(['id' => $attendanceId, 'employee_id' => $employeeId]);
                $closed = $attendance->fetchColumn();
                if ($closed === false || (int) $closed === 1) {
                    throw new DomainException('Không tìm thấy bản ghi hoặc kỳ chấm công đã được chốt.');
                }
                $history = $this->db->prepare(
                    "SELECT COUNT(*) FROM PHEDUYET
                     WHERE LoaiQuyTrinh = 'dieu_chinh_cong' AND MaDoiTuong = :id"
                );
                $history->execute(['id' => $attendanceId]);
                if ((int) $history->fetchColumn() > 0) {
                    throw new DomainException('Bản ghi này đã có yêu cầu điều chỉnh; không thể gửi yêu cầu trùng.');
                }
                Workflow::recordSubmission($this->db, 'dieu_chinh_cong', $attendanceId, $employeeId, [
                    'GioVao' => $checkIn,
                    'GioRa' => $checkOut,
                    'LyDo' => trim($reason),
                ]);
                $this->db->commit();
            } catch (\Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $exception;
            }
    }

        public function attendanceCorrections(): array
        {
            [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
            $query = $this->db->prepare(
                "SELECT pd.MaPD, pd.MaDoiTuong AS MaCC, pd.NguoiGui, pd.DuLieu, pd.ThoiGian,
                        nv.HoTen, cc.Ngay,
                        CASE WHEN pb.MaTruongBP = pd.NguoiGui THEN 'nhansu' ELSE 'truong_bp' END AS BuocTiepTheo
                 FROM PHEDUYET pd
                 INNER JOIN CHAMCONG cc ON cc.MaCC = pd.MaDoiTuong
                 INNER JOIN NHANVIEN nv ON nv.MaNV = cc.MaNV
                 INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                 WHERE pd.LoaiQuyTrinh = 'dieu_chinh_cong' AND pd.KetQua = 'Đã gửi'
                   AND NOT EXISTS (
                       SELECT 1 FROM PHEDUYET decision
                       WHERE decision.LoaiQuyTrinh = pd.LoaiQuyTrinh
                         AND decision.MaDoiTuong = pd.MaDoiTuong
                         AND decision.KetQua IN ('Đã duyệt', 'Từ chối')
                   )
                   AND (" . $scopeSql . ")
                 ORDER BY pd.MaPD DESC LIMIT 100"
            );
            $query->execute($scopeParams);
            return $query->fetchAll();
        }

        public function decideAttendanceCorrection(
            string $attendanceId,
            string $decision,
            string $approverId,
            string $reason = ''
        ): void {
            if (!in_array($decision, ['Đã duyệt', 'Từ chối'], true)
                || ($decision === 'Từ chối' && trim($reason) === '')) {
                throw new DomainException('Quyết định không hợp lệ; khi từ chối phải nhập lý do.');
            }
            $owner = $this->db->prepare('SELECT MaNV FROM CHAMCONG WHERE MaCC = :id');
            $owner->execute(['id' => $attendanceId]);
            $employeeId = $owner->fetchColumn();
            if (!is_string($employeeId)) {
                throw new DomainException('Không tìm thấy bản ghi chấm công.');
            }
            Scope::requireEmployee($this->db, $employeeId);

            $this->db->beginTransaction();
            try {
                $step = Workflow::assertNextStep($this->db, 'dieu_chinh_cong', $attendanceId, $approverId);
                $submission = $this->db->prepare(
                    "SELECT DuLieu FROM PHEDUYET
                     WHERE LoaiQuyTrinh = 'dieu_chinh_cong' AND MaDoiTuong = :id AND KetQua = 'Đã gửi'
                     ORDER BY MaPD DESC LIMIT 1 FOR UPDATE"
                );
                $submission->execute(['id' => $attendanceId]);
                $payload = json_decode((string) $submission->fetchColumn(), true);
                if (!is_array($payload)) {
                    throw new DomainException('Dữ liệu yêu cầu điều chỉnh bị lỗi.');
                }
                if ($decision === 'Đã duyệt') {
                    $update = $this->db->prepare(
                        'UPDATE CHAMCONG SET GioVao = COALESCE(:check_in, GioVao),
                            GioRa = COALESCE(:check_out, GioRa), TrangThai = \'Đủ công\'
                         WHERE MaCC = :id AND MaNV = :employee_id AND DaChot = 0'
                    );
                    $update->execute([
                        'check_in' => $payload['GioVao'] ?? null,
                        'check_out' => $payload['GioRa'] ?? null,
                        'id' => $attendanceId,
                        'employee_id' => $employeeId,
                    ]);
                    if ($update->rowCount() !== 1) {
                        throw new DomainException('Bản ghi đã chốt hoặc không thể cập nhật.');
                    }
                }
                Workflow::recordDecision(
                    $this->db,
                    'dieu_chinh_cong',
                    $attendanceId,
                    $step,
                    $approverId,
                    $decision,
                    $decision === 'Từ chối' ? $reason : (string) ($payload['LyDo'] ?? '')
                );
                $this->db->commit();
            } catch (\Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $exception;
            }
        }

    public function confirmAttendanceMonth(string $month): int
    {
        if ($month >= date('Y-m')) {
            throw new DomainException('Chỉ xác nhận kỳ chấm công của tháng đã kết thúc.');
        }
        [$start, $end] = $this->monthBounds($month);
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $this->db->beginTransaction();
        try {
            $incomplete = $this->db->prepare(
                'SELECT COUNT(*) FROM CHAMCONG cc INNER JOIN NHANVIEN nv ON nv.MaNV = cc.MaNV
                 WHERE cc.Ngay BETWEEN :start AND :end
                   AND (cc.GioVao IS NULL OR cc.GioRa IS NULL) AND (' . $scopeSql . ')'
            );
            $incomplete->execute(['start' => $start, 'end' => $end] + $scopeParams);
            if ((int) $incomplete->fetchColumn() > 0) {
                throw new DomainException('Còn bản ghi thiếu giờ vào hoặc giờ ra; hãy hoàn tất trước khi xác nhận kỳ.');
            }

            $update = $this->db->prepare(
                'UPDATE CHAMCONG cc INNER JOIN NHANVIEN nv ON nv.MaNV = cc.MaNV
                 SET cc.DaChot = 1
                 WHERE cc.Ngay BETWEEN :start AND :end AND cc.DaChot = 0
                   AND (' . $scopeSql . ')'
            );
            $update->execute(['start' => $start, 'end' => $end] + $scopeParams);
            if ($update->rowCount() === 0) {
                throw new DomainException('Kỳ không có bản ghi mới để xác nhận hoặc đã được chốt.');
            }
            $this->db->commit();

            return $update->rowCount();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function leaveRequests(?string $employeeId = null): array
    {
        $sql = 'SELECT np.MaNP, np.MaNV, np.TuNgay, np.DenNgay, np.LyDo, np.TrangThai, nv.HoTen
                FROM NGHIPHEP np INNER JOIN NHANVIEN nv ON nv.MaNV = np.MaNV';
        $params = [];
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $sql .= ' WHERE (' . $scopeSql . ')';
        $params += $scopeParams;
        if ($employeeId !== null) {
            $sql .= ' AND np.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $sql .= ' ORDER BY CASE np.TrangThai WHEN \'Chờ duyệt\' THEN 0 ELSE 1 END, np.TuNgay DESC LIMIT 200';
        $query = $this->db->prepare($sql);
        $query->execute($params);

        return $query->fetchAll();
    }

    /** @return array{used:int,reserved:int,remaining:int} */
    public function leaveBalance(string $employeeId, int $year, ?string $excludeRequestId = null): array
    {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
        $query = $this->db->prepare(
            "SELECT MaNP, TuNgay, DenNgay, TrangThai FROM NGHIPHEP
             WHERE MaNV = :employee_id
               AND TrangThai IN ('Chờ duyệt', 'Đã duyệt')
               AND TuNgay <= :year_end AND DenNgay >= :year_start"
        );
        $query->execute(['employee_id' => $employeeId, 'year_end' => $end, 'year_start' => $start]);
        $used = 0;
        $reserved = 0;
        foreach ($query->fetchAll() as $leave) {
            if ($excludeRequestId !== null && $leave['MaNP'] === $excludeRequestId) {
                continue;
            }
            $days = $this->workingDays(
                max($start, (string) $leave['TuNgay']),
                min($end, (string) $leave['DenNgay'])
            );
            if ($leave['TrangThai'] === 'Đã duyệt') {
                $used += $days;
            } else {
                $reserved += $days;
            }
        }

        return ['used' => $used, 'reserved' => $reserved, 'remaining' => max(0, 12 - $used - $reserved)];
    }

    public function createLeaveRequest(string $employeeId, string $startDate, string $endDate, string $reason): void
    {
        if (mb_strlen($reason, 'UTF-8') < 3 || mb_strlen($reason, 'UTF-8') > 2000
            || $endDate < $startDate || $startDate < date('Y-m-d')) {
            throw new DomainException('Ngày nghỉ hoặc lý do không hợp lệ.');
        }
        if ($this->workingDays($startDate, $endDate) === 0) {
            throw new DomainException('Khoảng nghỉ phải có ít nhất một ngày làm việc.');
        }

        $this->db->beginTransaction();
        try {
            $this->lockEmployee($employeeId);
            $overlap = $this->db->prepare(
                "SELECT COUNT(*) FROM NGHIPHEP
                 WHERE MaNV = :employee_id AND TrangThai IN ('Chờ duyệt', 'Đã duyệt')
                   AND TuNgay <= :end_date AND DenNgay >= :start_date"
            );
            $overlap->execute(['employee_id' => $employeeId, 'end_date' => $endDate, 'start_date' => $startDate]);
            if ((int) $overlap->fetchColumn() > 0) {
                throw new DomainException('Khoảng nghỉ bị trùng với một đơn đang chờ hoặc đã được duyệt.');
            }
            foreach ($this->yearsInRange($startDate, $endDate) as $year) {
                $balance = $this->leaveBalance($employeeId, $year);
                $requested = $this->workingDays(
                    max(sprintf('%04d-01-01', $year), $startDate),
                    min(sprintf('%04d-12-31', $year), $endDate)
                );
                if ($requested > $balance['remaining']) {
                    throw new DomainException("Năm {$year} chỉ còn {$balance['remaining']} ngày phép.");
                }
            }
            $insert = $this->db->prepare(
                "INSERT INTO NGHIPHEP (MaNP, MaNV, TuNgay, DenNgay, LyDo, TrangThai)
                 VALUES (:id, :employee_id, :start_date, :end_date, :reason, 'Chờ duyệt')"
            );
            $requestId = RecordId::make('NP');
            $insert->execute([
                'id' => $requestId,
                'employee_id' => $employeeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
            ]);
            Workflow::recordSubmission($this->db, 'nghi_phep', $requestId, $employeeId, [
                'TuNgay' => $startDate,
                'DenNgay' => $endDate,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function decideLeave(string $requestId, string $decision, string $approverId, string $reason = ''): void
    {
        if (!in_array($decision, ['Đã duyệt', 'Từ chối'], true)) {
            throw new DomainException('Trạng thái duyệt đơn không hợp lệ.');
        }
        $owner = $this->db->prepare('SELECT MaNV FROM NGHIPHEP WHERE MaNP = :id');
        $owner->execute(['id' => $requestId]);
        $employeeId = $owner->fetchColumn();
        if (!is_string($employeeId)) {
            throw new DomainException('Không tìm thấy đơn nghỉ phép.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $this->db->beginTransaction();
        try {
            [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
            $query = $this->db->prepare(
                'SELECT np.* FROM NGHIPHEP np INNER JOIN NHANVIEN nv ON nv.MaNV = np.MaNV
                 WHERE np.MaNP = :id AND (' . $scopeSql . ') FOR UPDATE'
            );
            $query->execute(['id' => $requestId] + $scopeParams);
            $request = $query->fetch();
            if ($request === false || $request['TrangThai'] !== 'Chờ duyệt') {
                throw new DomainException('Đơn nghỉ không tồn tại hoặc đã được xử lý.');
            }
            if ($request['MaNV'] === $approverId) {
                throw new DomainException('Bạn không thể tự phê duyệt đơn nghỉ của mình.');
            }
            $step = Workflow::assertNextStep($this->db, 'nghi_phep', $requestId, $approverId);
            $this->lockEmployee((string) $request['MaNV']);
            if ($decision === 'Đã duyệt') {
                foreach ($this->yearsInRange((string) $request['TuNgay'], (string) $request['DenNgay']) as $year) {
                    $balance = $this->leaveBalance((string) $request['MaNV'], $year, $requestId);
                    $requested = $this->workingDays(
                        max(sprintf('%04d-01-01', $year), (string) $request['TuNgay']),
                        min(sprintf('%04d-12-31', $year), (string) $request['DenNgay'])
                    );
                    if ($requested > $balance['remaining']) {
                        throw new DomainException("Không thể duyệt: năm {$year} chỉ còn {$balance['remaining']} ngày phép.");
                    }
                }
            }
            $update = $this->db->prepare(
                'UPDATE NGHIPHEP SET TrangThai = :status WHERE MaNP = :id AND TrangThai = \'Chờ duyệt\''
            );
            $update->execute(['status' => $decision, 'id' => $requestId]);
            Workflow::recordDecision(
                $this->db,
                'nghi_phep',
                $requestId,
                $step,
                $approverId,
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
    public function overtimeRequests(?string $employeeId = null): array
    {
        $sql = 'SELECT tc.MaTC, tc.MaNV, tc.Ngay, tc.SoGio, tc.HeSo, tc.TrangThai, nv.HoTen,
                       (SELECT COALESCE(SUM(t2.SoGio), 0) FROM TANGCA t2
                        WHERE t2.MaNV = tc.MaNV AND YEAR(t2.Ngay) = YEAR(tc.Ngay)
                          AND MONTH(t2.Ngay) = MONTH(tc.Ngay)
                          AND t2.TrangThai IN (\'Chờ duyệt\', \'Đã duyệt\')) AS TongGioThang,
                       (SELECT COALESCE(SUM(t3.SoGio), 0) FROM TANGCA t3
                        WHERE t3.MaNV = tc.MaNV AND t3.Ngay = tc.Ngay
                          AND t3.TrangThai IN (\'Chờ duyệt\', \'Đã duyệt\')) AS TongGioNgay
                FROM TANGCA tc INNER JOIN NHANVIEN nv ON nv.MaNV = tc.MaNV';
        $params = [];
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $sql .= ' WHERE (' . $scopeSql . ')';
        $params += $scopeParams;
        if ($employeeId !== null) {
            $sql .= ' AND tc.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $sql .= ' ORDER BY CASE tc.TrangThai WHEN \'Chờ duyệt\' THEN 0 ELSE 1 END, tc.Ngay DESC LIMIT 200';
        $query = $this->db->prepare($sql);
        $query->execute($params);

        return $query->fetchAll();
    }

    /** @return array{month:float,day:float,warnings:list<string>} */
    public function overtimeTotals(string $employeeId, string $date, float $additional = 0): array
    {
        $monthStart = substr($date, 0, 7) . '-01';
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
        $query = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN Ngay BETWEEN :month_start AND :month_end THEN SoGio ELSE 0 END), 0) AS month_hours,
                COALESCE(SUM(CASE WHEN Ngay = :date THEN SoGio ELSE 0 END), 0) AS day_hours
             FROM TANGCA WHERE MaNV = :employee_id AND TrangThai IN ('Chờ duyệt', 'Đã duyệt')"
        );
        $query->execute([
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'date' => $date,
            'employee_id' => $employeeId,
        ]);
        $totals = $query->fetch();
        $monthHours = (float) $totals['month_hours'] + $additional;
        $dayHours = (float) $totals['day_hours'] + $additional;
        $warnings = [];
        if ($monthHours > 40) {
            $warnings[] = sprintf('Tổng tăng ca tháng là %.2f giờ, vượt ngưỡng 40 giờ.', $monthHours);
        }
        if ($dayHours > 12) {
            $warnings[] = sprintf('Tổng tăng ca ngày là %.2f giờ, vượt ngưỡng 12 giờ.', $dayHours);
        }

        return ['month' => $monthHours, 'day' => $dayHours, 'warnings' => $warnings];
    }

    public function createOvertimeRequest(string $employeeId, string $date, float $hours): array
    {
        if ($hours <= 0 || $hours > 24 || $date > date('Y-m-d')) {
            throw new DomainException('Ngày hoặc số giờ tăng ca không hợp lệ.');
        }
        $totals = $this->overtimeTotals($employeeId, $date, $hours);
        $weekday = (int) (new DateTimeImmutable($date))->format('N');
        $multiplier = $weekday >= 6 ? 2.00 : 1.50;
        $insert = $this->db->prepare(
            "INSERT INTO TANGCA (MaTC, MaNV, Ngay, SoGio, HeSo, TrangThai)
             VALUES (:id, :employee_id, :date, :hours, :multiplier, 'Chờ duyệt')"
        );
        $requestId = RecordId::make('TC');
        $this->db->beginTransaction();
        try {
            $insert->execute([
                'id' => $requestId,
                'employee_id' => $employeeId,
                'date' => $date,
                'hours' => $hours,
                'multiplier' => $multiplier,
            ]);
            Workflow::recordSubmission($this->db, 'tang_ca', $requestId, $employeeId, [
                'Ngay' => $date,
                'SoGio' => $hours,
                'HeSo' => $multiplier,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $totals;
    }

    public function decideOvertime(
        string $requestId,
        string $decision,
        string $approverId,
        float $multiplier = 1.50,
        string $reason = ''
    ): void
    {
        if (!in_array($decision, ['Đã duyệt', 'Từ chối'], true)) {
            throw new DomainException('Trạng thái duyệt tăng ca không hợp lệ.');
        }
        if (!in_array($multiplier, [1.50, 2.00, 3.00], true)) {
            throw new DomainException('Hệ số tăng ca không hợp lệ.');
        }
        $owner = $this->db->prepare('SELECT MaNV FROM TANGCA WHERE MaTC = :id');
        $owner->execute(['id' => $requestId]);
        $employeeId = $owner->fetchColumn();
        if (!is_string($employeeId)) {
            throw new DomainException('Không tìm thấy đề nghị tăng ca.');
        }
        Scope::requireEmployee($this->db, $employeeId);
        $this->db->beginTransaction();
        try {
            [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
            $query = $this->db->prepare(
                'SELECT tc.MaNV, tc.TrangThai FROM TANGCA tc
                 INNER JOIN NHANVIEN nv ON nv.MaNV = tc.MaNV
                 WHERE tc.MaTC = :id AND (' . $scopeSql . ') FOR UPDATE'
            );
            $query->execute(['id' => $requestId] + $scopeParams);
            $request = $query->fetch();
            if ($request === false || $request['TrangThai'] !== 'Chờ duyệt') {
                throw new DomainException('Đề nghị tăng ca không tồn tại, đã xử lý hoặc ngoài phạm vi của bạn.');
            }
            $step = Workflow::assertNextStep($this->db, 'tang_ca', $requestId, $approverId);
            $update = $this->db->prepare(
                'UPDATE TANGCA SET TrangThai = :status, HeSo = :multiplier
                 WHERE MaTC = :id AND TrangThai = \'Chờ duyệt\''
            );
            $update->execute(['status' => $decision, 'multiplier' => $multiplier, 'id' => $requestId]);
            if ($update->rowCount() !== 1) {
                throw new DomainException('Đề nghị tăng ca đã được xử lý.');
            }
            Workflow::recordDecision(
                $this->db,
                'tang_ca',
                $requestId,
                $step,
                $approverId,
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

    private function lockEmployee(string $employeeId): void
    {
        $query = $this->db->prepare('SELECT MaNV FROM NHANVIEN WHERE MaNV = :id FOR UPDATE');
        $query->execute(['id' => $employeeId]);
        if ($query->fetchColumn() === false) {
            throw new DomainException('Không tìm thấy nhân viên.');
        }
    }

    private function workingDays(string $startDate, string $endDate): int
    {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
        if ($end < $start) {
            return 0;
        }

        $days = 0;
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $days++;
            }
        }

        return $days;
    }

    private function validTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time) === 1;
    }

    /** @return list<int> */
    private function yearsInRange(string $startDate, string $endDate): array
    {
        return range((int) substr($startDate, 0, 4), (int) substr($endDate, 0, 4));
    }

    /** @return array{0:string,1:string} */
    private function monthBounds(string $month): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new DomainException('Tháng cần xem không hợp lệ.');
        }
        $start = $month . '-01';

        return [$start, (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d')];
    }
}
