<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\RecordId;
use App\Core\Scope;
use App\Core\Workflow;
use DateTimeImmutable;
use DomainException;
use PDO;

final class Payroll
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{month:string,days:int,employees:list<array<string,mixed>>} */
    public function readiness(
        string $month,
        ?string $departmentId = null,
        ?string $employeeId = null
    ): array
    {
        [$first, $last] = $this->period($month);
        [$filterSql, $filterParams] = $this->employeeFilters('nv', $departmentId, $employeeId);
        $employeesQuery = $this->db->prepare(
            "SELECT nv.MaNV, nv.MaPB, nv.HoTen, nv.NgayVaoLam, nv.TrangThai
             FROM NHANVIEN nv
             WHERE nv.NgayVaoLam <= :period_end
               AND (" . Scope::nhanVien('nv')[0] . ")
               AND ({$filterSql})
               AND (nv.TrangThai <> 'Nghỉ việc' OR nv.MaNV IN (
                    SELECT MaNV FROM NGHIVIEC WHERE TrangThai = 'Lưu hồ sơ' AND NgayNghi >= :period_start
               ))
             ORDER BY HoTen"
        );
        $employeesQuery->execute(
            ['period_end' => $last, 'period_start' => $first]
                + Scope::nhanVien('nv')[1]
                + $filterParams
        );
        $rows = [];
        foreach ($employeesQuery->fetchAll() as $employee) {
            $employeeId = (string) $employee['MaNV'];
            $endDate = (string) $last;
            if ($employee['TrangThai'] === 'Nghỉ việc') {
                $endQuery = $this->db->prepare(
                    "SELECT MAX(NgayNghi) FROM NGHIVIEC
                     WHERE MaNV = :employee_id AND TrangThai = 'Lưu hồ sơ'
                       AND NgayNghi <= :period_end"
                );
                $endQuery->execute([
                    'employee_id' => $employeeId,
                    'period_end' => $last,
                ]);
                $lastWorked = $endQuery->fetchColumn();
                if (is_string($lastWorked)) {
                    $endDate = $lastWorked;
                }
            }

            $contractsQuery = $this->db->prepare(
                "SELECT MaHD, NgayBatDau, NgayKetThuc, MucLuong
                 FROM HOPDONG
                 WHERE MaNV = :employee_id AND TrangThai IN ('Hiệu lực', 'Hết hiệu lực')
                   AND NgayBatDau <= :period_end
                   AND (NgayKetThuc IS NULL OR NgayKetThuc >= :period_start)
                 ORDER BY NgayBatDau"
            );
            $contractsQuery->execute([
                'employee_id' => $employeeId,
                'period_end' => $endDate,
                'period_start' => $first,
            ]);
            $contracts = $contractsQuery->fetchAll();

            $attendanceQuery = $this->db->prepare(
                'SELECT Ngay, GioVao, GioRa, DaChot FROM CHAMCONG
                 WHERE MaNV = :employee_id AND Ngay BETWEEN :period_start AND :period_end'
            );
            $attendanceQuery->execute([
                'employee_id' => $employeeId,
                'period_start' => $first,
                'period_end' => $endDate,
            ]);
            $attendance = [];
            foreach ($attendanceQuery->fetchAll() as $day) {
                $attendance[(string) $day['Ngay']] = $day;
            }

            $leaveQuery = $this->db->prepare(
                "SELECT TuNgay, DenNgay FROM NGHIPHEP
                 WHERE MaNV = :employee_id AND TrangThai = 'Đã duyệt'
                   AND TuNgay <= :period_end AND DenNgay >= :period_start"
            );
            $leaveQuery->execute([
                'employee_id' => $employeeId,
                'period_end' => $endDate,
                'period_start' => $first,
            ]);
            $leaveDays = [];
            foreach ($leaveQuery->fetchAll() as $leave) {
                $leaveStart = max($first, (string) $leave['TuNgay']);
                $leaveEnd = min($endDate, (string) $leave['DenNgay']);
                foreach ($this->dates($leaveStart, $leaveEnd) as $day) {
                    if ($this->isWorkday($day)) {
                        $leaveDays[$day] = true;
                    }
                }
            }

            $overtimeQuery = $this->db->prepare(
                "SELECT MaTC, Ngay, SoGio, HeSo, TrangThai FROM TANGCA
                 WHERE MaNV = :employee_id AND Ngay BETWEEN :period_start AND :period_end
                   AND TrangThai IN ('Chờ duyệt', 'Đã duyệt')"
            );
            $overtimeQuery->execute([
                'employee_id' => $employeeId,
                'period_start' => $first,
                'period_end' => $endDate,
            ]);
            $overtime = $overtimeQuery->fetchAll();
            $issues = [];
            if ($contracts === []) {
                $issues[] = 'Thiếu hợp đồng có hiệu lực trong kỳ';
            }

            $expectedDays = 0;
            $paidDays = 0;
            $baseSalary = 0.0;
            $workdayCount = $this->workdayCount($first, $last);
            $missingAttendance = 0;
            $missingContractDays = 0;
            $contractByDate = [];
            $startDate = max($first, (string) $employee['NgayVaoLam']);
            foreach ($this->dates($startDate, $endDate) as $day) {
                $contract = $this->contractOn($contracts, $day);
                if ($contract !== null && (float) $contract['MucLuong'] > 0) {
                    $contractByDate[$day] = $contract;
                }
                if (!$this->isWorkday($day)) {
                    continue;
                }
                if ($contract === null || (float) $contract['MucLuong'] <= 0) {
                    $missingContractDays++;
                    continue;
                }
                if (isset($leaveDays[$day])) {
                    $paidDays++;
                    $baseSalary += (float) $contract['MucLuong'] / max(1, $workdayCount);
                    continue;
                }
                $expectedDays++;
                $record = $attendance[$day] ?? null;
                if ($record === null || $record['GioVao'] === null || $record['GioRa'] === null || (int) $record['DaChot'] !== 1) {
                    $missingAttendance++;
                    continue;
                }
                $paidDays++;
                $baseSalary += (float) $contract['MucLuong'] / max(1, $workdayCount);
            }
            if ($missingContractDays > 0) {
                $issues[] = "Thiếu hợp đồng/lương cho {$missingContractDays} ngày làm việc";
            }
            if ($missingAttendance > 0) {
                $issues[] = "Thiếu hoặc chưa chốt {$missingAttendance} ngày công";
            }

            $overtimeAmount = 0.0;
            foreach ($overtime as $extra) {
                if ($extra['TrangThai'] !== 'Đã duyệt') {
                    $issues[] = 'Còn đề nghị tăng ca chưa được duyệt';
                    continue;
                }
                $contract = $contractByDate[(string) $extra['Ngay']] ?? null;
                if ($contract === null) {
                    $issues[] = 'Thiếu hợp đồng để tính tiền tăng ca';
                    continue;
                }
                $hourlyRate = (float) $contract['MucLuong'] / max(1, $workdayCount) / 8;
                $overtimeAmount += $hourlyRate * (float) $extra['SoGio'] * (float) $extra['HeSo'];
            }

            $rows[] = [
                'MaNV' => $employeeId,
                'MaPB' => (string) $employee['MaPB'],
                'HoTen' => (string) $employee['HoTen'],
                'LuongHopDong' => $contracts === [] ? 0.0 : (float) $contracts[array_key_last($contracts)]['MucLuong'],
                'NgayCong' => $paidDays,
                'NgayDuKien' => $expectedDays,
                'LuongCoBan' => round($baseSalary, 2),
                'TangCa' => round($overtimeAmount, 2),
                'Issues' => array_values(array_unique($issues)),
            ];
        }

        return ['month' => $month, 'days' => $this->workdayCount($first, $last), 'employees' => $rows];
    }

    /** @param array<string,array{allowance?:mixed,bonus?:mixed,deduction?:mixed}> $adjustments */
    public function calculate(
        string $month,
        array $adjustments,
        string $creatorId,
        ?string $departmentId = null,
        ?string $employeeId = null
    ): int
    {
        $preview = $this->readiness($month, $departmentId, $employeeId);
        $missing = [];
        foreach ($preview['employees'] as $employee) {
            if ($employee['Issues'] !== []) {
                $missing[] = $employee['HoTen'] . ' (' . $employee['MaNV'] . '): ' . implode('; ', $employee['Issues']);
            }
        }
        if ($missing !== []) {
            throw new DomainException("Không thể tính lương vì thiếu dữ liệu:\n- " . implode("\n- ", $missing));
        }
        if ($preview['employees'] === []) {
            throw new DomainException('Không có nhân viên thuộc phòng ban/cá nhân đã chọn trong kỳ lương này.');
        }

        $this->db->beginTransaction();
        try {
            $existing = $this->db->prepare(
                'SELECT COUNT(*) FROM LUONG WHERE Thang = :month AND Nam = :year AND TrangThai = \'Đã duyệt\''
            );
            $existing->execute(['month' => (int) substr($month, 5, 2), 'year' => (int) substr($month, 0, 4)]);
            if ((int) $existing->fetchColumn() > 0) {
                throw new DomainException('Kỳ lương đã được Giám đốc phê duyệt và không thể tính lại.');
            }

            $upsert = $this->db->prepare(
                "INSERT INTO LUONG
                    (MaLuong, MaNV, Thang, Nam, LuongCoBan, PhuCap, Thuong, TangCa, KhauTru, ThucNhan, TrangThai, NguoiTao)
                 VALUES
                    (:id, :employee_id, :month, :year, :base, :allowance, :bonus, :overtime, :deduction, :net, 'Chờ duyệt', :creator)
                 ON DUPLICATE KEY UPDATE
                    LuongCoBan = VALUES(LuongCoBan), PhuCap = VALUES(PhuCap), Thuong = VALUES(Thuong),
                    TangCa = VALUES(TangCa), KhauTru = VALUES(KhauTru), ThucNhan = VALUES(ThucNhan),
                    TrangThai = 'Chờ duyệt', NguoiPheDuyet = NULL, NgayPheDuyet = NULL,
                    NguoiTao = VALUES(NguoiTao)"
            );
            foreach ($preview['employees'] as $employee) {
                $values = $adjustments[$employee['MaNV']] ?? [];
                if (!is_array($values)) {
                    throw new DomainException('Dữ liệu phụ cấp, thưởng hoặc khấu trừ không hợp lệ.');
                }
                $allowance = $this->money($values['allowance'] ?? 0);
                $bonus = $this->money($values['bonus'] ?? 0);
                $deduction = $this->money($values['deduction'] ?? 0);
                $base = (float) $employee['LuongCoBan'];
                $overtime = (float) $employee['TangCa'];
                $net = round($base + $allowance + $bonus + $overtime - $deduction, 2);
                if ($net < 0) {
                    throw new DomainException('Khấu trừ vượt tổng thu nhập của ' . $employee['HoTen'] . '.');
                }
                $upsert->execute([
                    'id' => RecordId::make('LG'),
                    'employee_id' => $employee['MaNV'],
                    'month' => (int) substr($month, 5, 2),
                    'year' => (int) substr($month, 0, 4),
                    'base' => $base,
                    'allowance' => $allowance,
                    'bonus' => $bonus,
                    'overtime' => $overtime,
                    'deduction' => $deduction,
                    'net' => $net,
                    'creator' => $creatorId,
                ]);
                $findId = $this->db->prepare(
                    'SELECT MaLuong FROM LUONG WHERE MaNV = :employee_id AND Thang = :month AND Nam = :year'
                );
                $findId->execute([
                    'employee_id' => $employee['MaNV'],
                    'month' => (int) substr($month, 5, 2),
                    'year' => (int) substr($month, 0, 4),
                ]);
                $payrollId = $findId->fetchColumn();
                if (!is_string($payrollId)) {
                    throw new \RuntimeException('Không đọc lại được mã phiếu lương vừa tính.');
                }
                Workflow::recordSubmission($this->db, 'bang_luong', $payrollId, $creatorId, [
                    'MaNV' => $employee['MaNV'],
                    'Thang' => (int) substr($month, 5, 2),
                    'Nam' => (int) substr($month, 0, 4),
                ]);
            }
            $this->db->commit();

            return count($preview['employees']);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function approvePeriod(
        string $month,
        string $directorId,
        ?string $departmentId = null,
        ?string $employeeId = null
    ): int
    {
        $preview = $this->readiness($month, $departmentId, $employeeId);
        $missing = [];
        foreach ($preview['employees'] as $employee) {
            if ($employee['Issues'] !== []) {
                $missing[] = $employee['HoTen'] . ' (' . $employee['MaNV'] . ')';
            }
        }
        if ($missing !== []) {
            throw new DomainException('Chưa thể duyệt vì dữ liệu công/hợp đồng chưa đầy đủ: ' . implode(', ', $missing));
        }
        $expectedEmployees = count($preview['employees']);
        if ($expectedEmployees === 0) {
            throw new DomainException('Không có nhân viên thuộc phòng ban/cá nhân đã chọn trong kỳ lương này.');
        }
        $employeePlaceholders = [];
        $employeeParams = [];
        foreach ($preview['employees'] as $index => $employee) {
            $placeholder = 'payroll_employee_' . $index;
            $employeePlaceholders[] = ':' . $placeholder;
            $employeeParams[$placeholder] = (string) $employee['MaNV'];
        }
        $countQuery = $this->db->prepare(
            'SELECT COUNT(*) FROM LUONG l INNER JOIN NHANVIEN nv ON nv.MaNV = l.MaNV
             WHERE l.Thang = :month AND l.Nam = :year
               AND l.MaNV IN (' . implode(', ', $employeePlaceholders) . ')'
        );
        $countQuery->execute(
            ['month' => (int) substr($month, 5, 2), 'year' => (int) substr($month, 0, 4)]
                + $employeeParams
        );
        if ((int) $countQuery->fetchColumn() !== $expectedEmployees) {
            throw new DomainException('Chưa có bảng lương hoàn chỉnh cho toàn bộ nhân viên thuộc kỳ.');
        }
        $periodQuery = $this->db->prepare(
            "SELECT MaLuong FROM LUONG
             WHERE Thang = :month AND Nam = :year AND TrangThai = 'Chờ duyệt'
               AND MaNV IN (" . implode(', ', $employeePlaceholders) . ')'
        );
        $periodQuery->execute(
            ['month' => (int) substr($month, 5, 2), 'year' => (int) substr($month, 0, 4)]
                + $employeeParams
        );
        $payrollIds = array_column($periodQuery->fetchAll(), 'MaLuong');
        if ($payrollIds === []) {
            throw new DomainException('Không có bảng lương chờ duyệt cho kỳ đã chọn.');
        }

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare(
                "UPDATE LUONG SET TrangThai = 'Đã duyệt', NguoiPheDuyet = :director_id, NgayPheDuyet = CURRENT_DATE()
                 WHERE MaLuong = :id AND TrangThai = 'Chờ duyệt'"
            );
            foreach ($payrollIds as $payrollId) {
                $step = Workflow::assertNextStep($this->db, 'bang_luong', (string) $payrollId, $directorId);
                Workflow::recordDecision($this->db, 'bang_luong', (string) $payrollId, $step, $directorId, 'Đã duyệt');
                $update->execute(['director_id' => $directorId, 'id' => $payrollId]);
                if ($update->rowCount() !== 1) {
                    throw new DomainException('Một phiếu lương đã thay đổi trong lúc phê duyệt.');
                }
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return count($payrollIds);
    }

    /** @return list<array<string,mixed>> */
    public function rows(
        string $month,
        ?string $employeeId = null,
        ?string $departmentId = null,
        ?string $selectedEmployeeId = null
    ): array
    {
        [$start, $end] = $this->period($month);
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        [$filterSql, $filterParams] = $this->employeeFilters('nv', $departmentId, $selectedEmployeeId);
        $sql = 'SELECT l.*, nv.HoTen, nv.MaNV, nv.Email, pb.TenPB
                FROM LUONG l INNER JOIN NHANVIEN nv ON nv.MaNV = l.MaNV
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                WHERE l.Nam = :year AND l.Thang = :month
                  AND (' . $scopeSql . ') AND (' . $filterSql . ')';
        $params = ['year' => (int) substr($month, 0, 4), 'month' => (int) substr($month, 5, 2)]
            + $scopeParams
            + $filterParams;
        if ($employeeId !== null) {
            $sql .= ' AND l.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $sql .= ' ORDER BY nv.HoTen';
        $query = $this->db->prepare($sql);
        $query->execute($params);

        return $query->fetchAll();
    }

    /** @return array{0:string,1:array<string,string>} */
    private function employeeFilters(string $alias, ?string $departmentId, ?string $employeeId): array
    {
        $filters = [];
        $params = [];
        if ($departmentId !== null && $departmentId !== '') {
            $filters[] = $alias . '.MaPB = :payroll_department';
            $params['payroll_department'] = $departmentId;
        }
        if ($employeeId !== null && $employeeId !== '') {
            $filters[] = $alias . '.MaNV = :payroll_employee';
            $params['payroll_employee'] = $employeeId;
        }

        return [$filters === [] ? '1 = 1' : implode(' AND ', $filters), $params];
    }

    /** @return array<string,mixed>|null */
    public function payslip(string $payrollId, ?string $employeeId = null): ?array
    {
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $sql = 'SELECT l.*, nv.HoTen, nv.MaNV, nv.Email, pb.TenPB, cv.TenCV
                FROM LUONG l INNER JOIN NHANVIEN nv ON nv.MaNV = l.MaNV
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV
                WHERE l.MaLuong = :id AND (' . $scopeSql . ')';
        $params = ['id' => $payrollId] + $scopeParams;
        if ($employeeId !== null) {
            $sql .= ' AND l.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $query = $this->db->prepare($sql . ' LIMIT 1');
        $query->execute($params);
        $row = $query->fetch();

        return $row === false ? null : $row;
    }

    private function money(mixed $value): float
    {
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 999999999999.99) {
            throw new DomainException('Phụ cấp, thưởng và khấu trừ phải là số không âm hợp lệ.');
        }

        return round((float) $value, 2);
    }

    /** @return array{0:string,1:string} */
    private function period(string $month): array
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new DomainException('Kỳ lương không hợp lệ.');
        }
        $first = $month . '-01';
        if ($month >= date('Y-m')) {
            throw new DomainException('Chỉ tính lương cho kỳ đã kết thúc.');
        }

        return [$first, (new DateTimeImmutable($first))->modify('last day of this month')->format('Y-m-d')];
    }

    /** @param list<array<string,mixed>> $contracts
     *  @return array<string,mixed>|null
     */
    private function contractOn(array $contracts, string $date): ?array
    {
        $found = null;
        foreach ($contracts as $contract) {
            if ((string) $contract['NgayBatDau'] <= $date
                && ($contract['NgayKetThuc'] === null || (string) $contract['NgayKetThuc'] >= $date)) {
                $found = $contract;
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function dates(string $start, string $end): array
    {
        if ($end < $start) {
            return [];
        }
        $days = [];
        $last = new DateTimeImmutable($end);
        for ($date = new DateTimeImmutable($start); $date <= $last; $date = $date->modify('+1 day')) {
            $days[] = $date->format('Y-m-d');
        }

        return $days;
    }

    private function isWorkday(string $date): bool
    {
        return (int) (new DateTimeImmutable($date))->format('N') <= 5;
    }

    private function workdayCount(string $start, string $end): int
    {
        $count = 0;
        foreach ($this->dates($start, $end) as $date) {
            if ($this->isWorkday($date)) {
                $count++;
            }
        }

        return $count;
    }
}
