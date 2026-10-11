<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Core\Scope;

final class Dashboard
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{summary:array<string,int|float>,departments:list<array{TenPB:string,SoLuong:int}>,notifications:list<array<string,mixed>>} */
    public function companyData(): array
    {
        $statement = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM NHANVIEN WHERE TrangThai <> 'Nghỉ việc') AS TongNhanVien,
                (SELECT COUNT(*) FROM NHANVIEN WHERE TrangThai = 'Thử việc') AS ThuViec,
                (SELECT COUNT(*) FROM NHANVIEN WHERE TrangThai = 'Chính thức') AS ChinhThuc,
                (SELECT COUNT(*) FROM UNGVIEN) AS UngVien,
                (SELECT COALESCE(SUM(ThucNhan), 0) FROM LUONG
                 WHERE Thang = MONTH(CURRENT_DATE()) AND Nam = YEAR(CURRENT_DATE())) AS QuyLuong"
        );
        $statement->execute();
        $summary = $statement->fetch();
        if ($summary === false) {
            throw new \RuntimeException('Không thể tải số liệu tổng quan.');
        }

        $departmentQuery = $this->db->prepare(
            "SELECT pb.TenPB, COUNT(nv.MaNV) AS SoLuong
             FROM PHONGBAN pb
             LEFT JOIN NHANVIEN nv
               ON nv.MaPB = pb.MaPB AND nv.TrangThai <> 'Nghỉ việc'
             GROUP BY pb.MaPB, pb.TenPB
             ORDER BY pb.TenPB"
        );
        $departmentQuery->execute();

        return [
            'summary' => [
                'TongNhanVien' => (int) $summary['TongNhanVien'],
                'ThuViec' => (int) $summary['ThuViec'],
                'ChinhThuc' => (int) $summary['ChinhThuc'],
                'UngVien' => (int) $summary['UngVien'],
                'QuyLuong' => (float) $summary['QuyLuong'],
            ],
            'departments' => $departmentQuery->fetchAll(),
            'notifications' => $this->companyNotifications(),
        ];
    }

    /** @return array{summary:array<string,int|float|string>,departments:list<array<never,never>>,notifications:list<array<string,mixed>>} */
    public function personalData(string $employeeId): array
    {
        $summaryQuery = $this->db->prepare(
            "SELECT nv.TrangThai,
                (SELECT COUNT(*) FROM CHAMCONG cc
                 WHERE cc.MaNV = nv.MaNV
                   AND MONTH(cc.Ngay) = MONTH(CURRENT_DATE())
                   AND YEAR(cc.Ngay) = YEAR(CURRENT_DATE())) AS NgayCong,
                (SELECT COUNT(*) FROM NGHIPHEP np
                 WHERE np.MaNV = nv.MaNV AND np.TrangThai IN ('Chờ duyệt', 'Tiếp nhận')) AS PhepChoDuyet,
                (SELECT COUNT(*) FROM TANGCA tc
                 WHERE tc.MaNV = nv.MaNV AND tc.TrangThai IN ('Chờ duyệt', 'Tiếp nhận')) AS TangCaChoDuyet,
                (SELECT COALESCE(SUM(l.ThucNhan), 0) FROM LUONG l
                 WHERE l.MaNV = nv.MaNV
                   AND l.Thang = MONTH(CURRENT_DATE())
                   AND l.Nam = YEAR(CURRENT_DATE())) AS LuongCaNhan
             FROM NHANVIEN nv WHERE nv.MaNV = :employee_id LIMIT 1"
        );
        $summaryQuery->execute(['employee_id' => $employeeId]);
        $summary = $summaryQuery->fetch();
        if ($summary === false) {
            throw new \RuntimeException('Không tìm thấy hồ sơ nhân viên đang đăng nhập.');
        }
        return [
            'summary' => [
                'TrangThai' => (string) $summary['TrangThai'],
                'NgayCong' => (int) $summary['NgayCong'],
                'PhepChoDuyet' => (int) $summary['PhepChoDuyet'],
                'TangCaChoDuyet' => (int) $summary['TangCaChoDuyet'],
                'LuongCaNhan' => (float) $summary['LuongCaNhan'],
            ],
            'departments' => [],
            'notifications' => $this->personalNotifications($employeeId),
        ];
    }

        /** @return array{cards:list<array{label:string,value:string,icon:string,color:string}>,departments:list<array<string,mixed>>,notifications:list<array<string,mixed>>,pending:list<array{label:string,count:int,route:string}>} */
        public function roleData(string $role, array $user): array
        {
            if ($role === 'giamdoc') {
                $data = $this->companyData();
                $actorId = (string) ($user['ma_nv'] ?? $user['MaNV'] ?? '');
                $pending = [
                    ['label' => 'Tuyển dụng', 'count' => $this->count(
                        "SELECT COUNT(*) FROM TUYENDUNG
                         WHERE TrangThai = 'Chờ phê duyệt' AND (DeXuatBoi IS NULL OR DeXuatBoi <> :actor)",
                        ['actor' => $actorId]
                    ), 'route' => 'recruitment'],
                    ['label' => 'Tăng lương', 'count' => $this->count(
                        "SELECT COUNT(*) FROM HOPDONG
                         WHERE TrangThai = 'Chờ duyệt tăng lương' AND (DeXuatBoi IS NULL OR DeXuatBoi <> :actor)",
                        ['actor' => $actorId]
                    ), 'route' => 'salary_changes'],
                    ['label' => 'Điều chuyển', 'count' => $this->count(
                        "SELECT COUNT(*) FROM DIEUCHUYEN
                         WHERE TrangThai = 'Chờ phê duyệt' AND (NguoiDeXuat IS NULL OR NguoiDeXuat <> :actor)",
                        ['actor' => $actorId]
                    ), 'route' => 'transfers'],
                    ['label' => 'Nghỉ việc', 'count' => $this->count(
                        "SELECT COUNT(*) FROM NGHIVIEC r
                         INNER JOIN NHANVIEN emp ON emp.MaNV = r.MaNV
                         INNER JOIN PHONGBAN pb ON pb.MaPB = emp.MaPB
                         WHERE r.TrangThai = 'Phê duyệt'
                           AND COALESCE(r.NguoiDeXuat, r.MaNV) <> :actor
                           AND (pb.MaTruongBP = COALESCE(r.NguoiDeXuat, r.MaNV)
                                OR EXISTS (
                                    SELECT 1 FROM PHEDUYET pd
                                    WHERE pd.LoaiQuyTrinh = 'nghi_viec' AND pd.MaDoiTuong = r.MaNViec
                                      AND pd.Buoc = 'truong_bp' AND pd.KetQua = 'Đã duyệt'
                                ))",
                        ['actor' => $actorId]
                    ), 'route' => 'resignations'],
                    ['label' => 'Bảng lương', 'count' => $this->count(
                        "SELECT COUNT(*) FROM LUONG
                         WHERE TrangThai = 'Chờ duyệt' AND (NguoiTao IS NULL OR NguoiTao <> :actor)",
                        ['actor' => $actorId]
                    ), 'route' => 'payroll'],
                ];
                $summary = $data['summary'];
                return [
                    'cards' => $this->cards([
                        ['Tổng nhân viên', (string) $summary['TongNhanVien'], 'bi-people', 'primary'],
                        ['Đang thử việc', (string) $summary['ThuViec'], 'bi-hourglass-split', 'warning'],
                        ['Chính thức', (string) $summary['ChinhThuc'], 'bi-person-check', 'success'],
                        ['Ứng viên', (string) $summary['UngVien'], 'bi-person-plus', 'info'],
                        ['Quỹ lương tháng', number_format((float) $summary['QuyLuong'], 0, ',', '.') . ' đ', 'bi-cash-stack', 'success'],
                    ]),
                    'departments' => $data['departments'],
                    'notifications' => $data['notifications'],
                    'pending' => $pending,
                ];
            }

            if ($role === 'nhansu') {
                $newApplicants = $this->count("SELECT COUNT(*) FROM UNGVIEN WHERE TrangThai = 'Mới'");
                $probation = $this->count(
                    "SELECT COUNT(*) FROM THUVIEC tv INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
                     WHERE tv.NgayKetThuc BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                       AND tv.KetQua IS NULL AND nv.TrangThai = 'Thử việc'"
                );
                $contracts = $this->count(
                    "SELECT COUNT(*) FROM HOPDONG WHERE TrangThai = 'Hiệu lực'
                     AND NgayKetThuc BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)"
                );
                $resignations = $this->count("SELECT COUNT(*) FROM NGHIVIEC WHERE TrangThai = 'Tiếp nhận'");
                return [
                    'cards' => $this->cards([
                        ['Ứng viên mới', (string) $newApplicants, 'bi-person-plus', 'info'],
                        ['Thử việc hết hạn trong 7 ngày', (string) $probation, 'bi-hourglass-split', 'warning'],
                        ['Hợp đồng hết hạn trong 7 ngày', (string) $contracts, 'bi-file-earmark-text', 'warning'],
                        ['Đề nghị nghỉ việc cần tiếp nhận', (string) $resignations, 'bi-person-x', 'danger'],
                    ]),
                    'departments' => [],
                    'notifications' => $this->hrNotifications(),
                    'pending' => [],
                ];
            }

            if ($role === 'ketoan') {
                $month = date('Y-m');
                $incomplete = $this->count(
                    "SELECT COUNT(*) FROM NHANVIEN nv
                     WHERE nv.TrangThai <> 'Nghỉ việc'
                       AND EXISTS (SELECT 1 FROM CHAMCONG cc WHERE cc.MaNV = nv.MaNV
                                   AND DATE_FORMAT(cc.Ngay, '%Y-%m') = :month
                                   AND (cc.GioVao IS NULL OR cc.GioRa IS NULL OR cc.DaChot = 0))"
                    ,
                    ['month' => $month]
                );
                $overtime = $this->count(
                    "SELECT COUNT(*) FROM TANGCA WHERE TrangThai = 'Đã duyệt'
                     AND DATE_FORMAT(Ngay, '%Y-%m') = :month",
                    ['month' => $month]
                );
                $resignations = $this->count("SELECT COUNT(*) FROM NGHIVIEC WHERE TrangThai = 'Chốt công/lương'");
                $payrollCount = $this->count(
                    'SELECT COUNT(*) FROM LUONG WHERE Thang = ' . (int) date('n') . ' AND Nam = ' . (int) date('Y')
                );
                return [
                    'cards' => $this->cards([
                        ['Kỳ lương hiện tại', date('m/Y'), 'bi-calendar-month', 'primary'],
                        ['Nhân viên thiếu/chưa chốt công', (string) $incomplete, 'bi-exclamation-triangle', $incomplete > 0 ? 'warning' : 'success'],
                        ['Tăng ca đã duyệt tháng này', (string) $overtime, 'bi-clock-history', 'info'],
                        ['Nghỉ việc chờ chốt lương', (string) $resignations, 'bi-person-x', 'danger'],
                        ['Phiếu lương kỳ này', (string) $payrollCount, 'bi-cash-stack', 'success'],
                    ]),
                    'departments' => [],
                    'notifications' => $this->accountingNotifications(),
                    'pending' => [],
                ];
            }

            if ($role === 'quanly') {
                $departmentId = (string) ($user['ma_pb'] ?? '');
                $staff = $this->count(
                    "SELECT COUNT(*) FROM NHANVIEN WHERE MaPB = :department AND TrangThai <> 'Nghỉ việc'",
                    ['department' => $departmentId]
                );
                $pendingLeave = $this->count(
                    "SELECT COUNT(*) FROM NGHIPHEP np INNER JOIN NHANVIEN nv ON nv.MaNV = np.MaNV
                     INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                     WHERE nv.MaPB = :department AND np.TrangThai = 'Chờ duyệt'
                       AND pb.MaTruongBP <> np.MaNV",
                    ['department' => $departmentId]
                );
                $pendingOvertime = $this->count(
                    "SELECT COUNT(*) FROM TANGCA tc INNER JOIN NHANVIEN nv ON nv.MaNV = tc.MaNV
                     INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                     WHERE nv.MaPB = :department AND tc.TrangThai = 'Chờ duyệt'
                       AND pb.MaTruongBP <> tc.MaNV",
                    ['department' => $departmentId]
                );
                $probation = $this->count(
                    "SELECT COUNT(*) FROM THUVIEC tv INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
                     WHERE nv.MaPB = :department AND tv.KetQua IS NULL",
                    ['department' => $departmentId]
                );
                $pendingCorrection = $this->count(
                    "SELECT COUNT(*) FROM PHEDUYET pd INNER JOIN NHANVIEN nv
                       ON nv.MaNV = (SELECT cc.MaNV FROM CHAMCONG cc WHERE cc.MaCC = pd.MaDoiTuong)
                     INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                     WHERE pd.LoaiQuyTrinh = 'dieu_chinh_cong' AND pd.KetQua = 'Đã gửi'
                       AND NOT EXISTS (
                           SELECT 1 FROM PHEDUYET decision
                           WHERE decision.LoaiQuyTrinh = pd.LoaiQuyTrinh
                             AND decision.MaDoiTuong = pd.MaDoiTuong
                             AND decision.KetQua IN ('Đã duyệt', 'Từ chối')
                       )
                       AND pb.MaTruongBP <> pd.NguoiGui
                       AND nv.MaPB = :department",
                    ['department' => $departmentId]
                );
                $notifications = $this->managerNotifications($departmentId);
                return [
                    'cards' => $this->cards([
                        ['Quân số bộ phận', (string) $staff, 'bi-people', 'primary'],
                        ['Đơn nghỉ phép chờ duyệt', (string) $pendingLeave, 'bi-calendar2-minus', 'warning'],
                        ['Đề nghị tăng ca chờ duyệt', (string) $pendingOvertime, 'bi-clock-history', 'info'],
                        ['Điều chỉnh công chờ duyệt', (string) $pendingCorrection, 'bi-calendar-check', 'warning'],
                        ['Thử việc cần đánh giá', (string) $probation, 'bi-hourglass-split', 'success'],
                    ]),
                    'departments' => $this->departmentData($departmentId),
                    'notifications' => $notifications,
                    'pending' => [
                        ['label' => 'Nghỉ phép', 'count' => $pendingLeave, 'route' => 'leave'],
                        ['label' => 'Tăng ca', 'count' => $pendingOvertime, 'route' => 'overtime'],
                        ['label' => 'Điều chỉnh công', 'count' => $pendingCorrection, 'route' => 'attendance'],
                    ],
                ];
            }

            if ($role === 'admin') {
                $users = $this->count('SELECT COUNT(*) FROM TAIKHOAN');
                $locked = $this->count('SELECT COUNT(*) FROM TAIKHOAN WHERE KhoaDen > CURRENT_TIMESTAMP');
                $logs = $this->count('SELECT COUNT(*) FROM NHATKY WHERE ThoiGian >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)');
                return [
                    'cards' => $this->cards([
                        ['Tài khoản hệ thống', (string) $users, 'bi-person-gear', 'primary'],
                        ['Tài khoản đang khóa', (string) $locked, 'bi-lock', 'warning'],
                        ['Hoạt động 24 giờ', (string) $logs, 'bi-journal-text', 'info'],
                    ]),
                    'departments' => [],
                    'notifications' => [],
                    'pending' => [],
                ];
            }

            $employeeId = (string) ($user['ma_nv'] ?? '');
            $personal = $this->personalData($employeeId);
            $attendance = $this->db->prepare(
                'SELECT GioVao, GioRa FROM CHAMCONG WHERE MaNV = :employee_id AND Ngay = CURRENT_DATE() LIMIT 1'
            );
            $attendance->execute(['employee_id' => $employeeId]);
            $today = $attendance->fetch() ?: [];
            $remaining = $this->leaveBalance($employeeId, (int) date('Y'));
            $pendingResignation = $this->count(
                "SELECT COUNT(*) FROM NGHIVIEC WHERE MaNV = :employee_id
                 AND TrangThai NOT IN ('Lưu hồ sơ', 'Từ chối')",
                ['employee_id' => $employeeId]
            );
            $personal['notifications'][] = [
                'type' => 'info',
                'category' => 'Đơn nghỉ việc',
                'title' => 'Trạng thái đề nghị của bạn',
                'detail' => $pendingResignation > 0 ? 'Đang được xử lý' : 'Chưa có đề nghị đang xử lý',
                'date' => null,
            ];
            return [
                'cards' => $this->cards([
                    ['Chấm vào / Chấm ra', empty($today['GioVao']) ? 'Chưa chấm vào' : (empty($today['GioRa']) ? 'Chưa chấm ra' : 'Đã hoàn tất'), 'bi-fingerprint', 'primary'],
                    ['Ngày công tháng này', (string) $personal['summary']['NgayCong'], 'bi-calendar-check', 'success'],
                    ['Ngày phép còn lại', (string) $remaining['remaining'], 'bi-calendar2-minus', 'warning'],
                    ['Phiếu lương tháng này', number_format((float) $personal['summary']['LuongCaNhan'], 0, ',', '.') . ' đ', 'bi-cash-stack', 'success'],
                    ['Đề nghị nghỉ việc', $pendingResignation > 0 ? 'Đang xử lý' : 'Chưa gửi', 'bi-person-x', 'info'],
                ]),
                'departments' => [],
                'notifications' => $personal['notifications'],
                'pending' => [],
                'attendanceToday' => $today,
            ];
        }

        /** @param list<array{0:string,1:string,2:string,3:string}> $values
         * @return list<array{label:string,value:string,icon:string,color:string}>
         */
        private function cards(array $values): array
        {
            return array_map(
                static fn (array $item): array => [
                    'label' => $item[0], 'value' => $item[1], 'icon' => $item[2], 'color' => $item[3],
                ],
                $values
            );
        }

        private function count(string $sql, array $params = []): int
        {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            return (int) $query->fetchColumn();
        }

        /** @return list<array<string,mixed>> */
        private function departmentData(string $departmentId): array
        {
            $query = $this->db->prepare(
                'SELECT pb.TenPB, COUNT(nv.MaNV) AS SoLuong FROM PHONGBAN pb
                 LEFT JOIN NHANVIEN nv ON nv.MaPB = pb.MaPB AND nv.TrangThai <> \'Nghỉ việc\'
                 WHERE pb.MaPB = :department GROUP BY pb.MaPB, pb.TenPB'
            );
            $query->execute(['department' => $departmentId]);
            return $query->fetchAll();
        }

        /** @return list<array<string,mixed>> */
        private function hrNotifications(): array
        {
            $notifications = [];
            $rows = $this->db->query(
                "SELECT nv.HoTen AS Ten, tv.NgayKetThuc AS Ngay, 'Thử việc' AS Loai
                 FROM THUVIEC tv INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
                 WHERE tv.KetQua IS NULL AND tv.NgayKetThuc BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                 UNION ALL
                 SELECT nv.HoTen, hd.NgayKetThuc, 'Hợp đồng'
                 FROM HOPDONG hd INNER JOIN NHANVIEN nv ON nv.MaNV = hd.MaNV
                 WHERE hd.TrangThai = 'Hiệu lực' AND hd.NgayKetThuc BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                 ORDER BY Ngay LIMIT 10"
            )->fetchAll();
            foreach ($rows as $row) {
                $notifications[] = [
                    'type' => 'warning', 'category' => (string) $row['Loai'], 'title' => (string) $row['Ten'],
                    'detail' => 'Sắp hết hạn', 'date' => (string) $row['Ngay'],
                ];
            }
            return $notifications;
        }

        /** @return list<array<string,mixed>> */
        private function accountingNotifications(): array
        {
            return $this->db->query(
                "SELECT nv.HoTen AS title, 'Nghỉ việc chờ chốt lương' AS category,
                        n.NgayNghi AS date, n.MaNV AS detail
                 FROM NGHIVIEC n INNER JOIN NHANVIEN nv ON nv.MaNV = n.MaNV
                 WHERE n.TrangThai = 'Chốt công/lương' AND n.BanGiao = 1 AND n.LuongDaChot = 0
                 ORDER BY n.NgayNghi LIMIT 10"
            )->fetchAll();
        }

        /** @return list<array<string,mixed>> */
        private function managerNotifications(string $departmentId): array
        {
            $query = $this->db->prepare(
                "SELECT np.MaNP AS Ma, nv.HoTen AS Ten, np.TuNgay AS Ngay, 'Nghỉ phép' AS Loai
                 FROM NGHIPHEP np INNER JOIN NHANVIEN nv ON nv.MaNV = np.MaNV
                 INNER JOIN PHONGBAN pb1 ON pb1.MaPB = nv.MaPB
                 WHERE nv.MaPB = :department_leave AND np.TrangThai = 'Chờ duyệt'
                   AND pb1.MaTruongBP <> np.MaNV
                 UNION ALL
                 SELECT tc.MaTC, nv.HoTen, tc.Ngay, 'Tăng ca'
                 FROM TANGCA tc INNER JOIN NHANVIEN nv ON nv.MaNV = tc.MaNV
                 INNER JOIN PHONGBAN pb2 ON pb2.MaPB = nv.MaPB
                 WHERE nv.MaPB = :department_overtime AND tc.TrangThai = 'Chờ duyệt'
                   AND pb2.MaTruongBP <> tc.MaNV
                 ORDER BY Ngay DESC LIMIT 10"
            );
            $query->execute(['department_leave' => $departmentId, 'department_overtime' => $departmentId]);
            return array_map(
                static fn (array $row): array => [
                    'type' => 'warning', 'category' => (string) $row['Loai'], 'title' => (string) $row['Ten'],
                    'detail' => 'Chờ trưởng bộ phận phê duyệt', 'date' => (string) $row['Ngay'],
                ],
                $query->fetchAll()
            );
        }

    /** @return list<array<string,mixed>> */
    private function companyNotifications(): array
    {
        $notifications = [];
        $probation = $this->db->prepare(
            "SELECT nv.HoTen, tv.NgayKetThuc
             FROM THUVIEC tv
             INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
             WHERE tv.NgayKetThuc BETWEEN CURRENT_DATE()
                   AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
               AND tv.KetQua IS NULL
               AND nv.TrangThai = 'Thử việc'
             ORDER BY tv.NgayKetThuc
             LIMIT 5"
        );
        $probation->execute();
        foreach ($probation->fetchAll() as $row) {
            $notifications[] = [
                'type' => 'warning',
                'category' => 'Thử việc',
                'title' => (string) $row['HoTen'],
                'detail' => 'Thử việc sắp hết hạn',
                'date' => (string) $row['NgayKetThuc'],
            ];
        }

        $pendingRequests = $this->db->prepare(
            "SELECT 'Nghỉ phép' AS Loai, nv.HoTen, np.TuNgay AS Ngay, np.TrangThai
             FROM NGHIPHEP np INNER JOIN NHANVIEN nv ON nv.MaNV = np.MaNV
             WHERE np.TrangThai IN ('Chờ duyệt', 'Chờ phê duyệt', 'Tiếp nhận')
             UNION ALL
             SELECT 'Tăng ca', nv.HoTen, tc.Ngay, tc.TrangThai
             FROM TANGCA tc INNER JOIN NHANVIEN nv ON nv.MaNV = tc.MaNV
             WHERE tc.TrangThai IN ('Chờ duyệt', 'Chờ phê duyệt', 'Tiếp nhận')
             UNION ALL
             SELECT 'Nghỉ việc', nv.HoTen, nvr.NgayDeNghi, nvr.TrangThai
             FROM NGHIVIEC nvr INNER JOIN NHANVIEN nv ON nv.MaNV = nvr.MaNV
             WHERE nvr.TrangThai IN ('Chờ duyệt', 'Chờ phê duyệt', 'Tiếp nhận', 'Đề xuất')
             ORDER BY Ngay DESC
             LIMIT 8"
        );
        $pendingRequests->execute();
        foreach ($pendingRequests->fetchAll() as $row) {
            $notifications[] = [
                'type' => 'primary',
                'category' => 'Đề nghị chờ duyệt',
                'title' => (string) $row['Loai'] . ' - ' . (string) $row['HoTen'],
                'detail' => (string) $row['TrangThai'],
                'date' => (string) $row['Ngay'],
            ];
        }

        $newApplicants = $this->db->prepare(
            "SELECT HoTen, ViTriUngTuyen
             FROM UNGVIEN WHERE TrangThai = 'Mới'
             ORDER BY MaUV DESC LIMIT 5"
        );
        $newApplicants->execute();
        foreach ($newApplicants->fetchAll() as $row) {
            $notifications[] = [
                'type' => 'success',
                'category' => 'Ứng viên mới',
                'title' => (string) $row['HoTen'],
                'detail' => 'Ứng tuyển vị trí ' . (string) $row['ViTriUngTuyen'],
                'date' => null,
            ];
        }

        return $notifications;
    }

    /** @return array{used:int,reserved:int,remaining:int} */
    private function leaveBalance(string $employeeId, int $year): array
    {
        $query = $this->db->prepare(
            "SELECT TuNgay, DenNgay, TrangThai FROM NGHIPHEP
             WHERE MaNV = :employee_id AND TrangThai IN ('Đã duyệt', 'Chờ duyệt')
               AND TuNgay <= :year_end AND DenNgay >= :year_start"
        );
        $query->execute([
            'employee_id' => $employeeId,
            'year_start' => sprintf('%04d-01-01', $year),
            'year_end' => sprintf('%04d-12-31', $year),
        ]);
        $used = 0;
        $reserved = 0;
        foreach ($query->fetchAll() as $request) {
            $start = max(sprintf('%04d-01-01', $year), (string) $request['TuNgay']);
            $end = min(sprintf('%04d-12-31', $year), (string) $request['DenNgay']);
            $days = 0;
            for ($day = new \DateTimeImmutable($start), $last = new \DateTimeImmutable($end);
                $day <= $last;
                $day = $day->modify('+1 day')) {
                if ((int) $day->format('N') <= 5) {
                    $days++;
                }
            }
            if ($request['TrangThai'] === 'Đã duyệt') {
                $used += $days;
            } else {
                $reserved += $days;
            }
        }

        return ['used' => $used, 'reserved' => $reserved, 'remaining' => max(0, 12 - $used - $reserved)];
    }

    /** @return list<array<string,mixed>> */
    private function personalNotifications(string $employeeId): array
    {
        $query = $this->db->prepare(
            "SELECT tv.NgayKetThuc
             FROM THUVIEC tv
             INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
             WHERE tv.MaNV = :employee_id
               AND tv.NgayKetThuc BETWEEN CURRENT_DATE()
                   AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
               AND tv.KetQua IS NULL
             LIMIT 1"
        );
        $query->execute(['employee_id' => $employeeId]);
        $notifications = [];
        $probationEnd = $query->fetchColumn();
        if ($probationEnd !== false) {
            $notifications[] = [
                'type' => 'warning',
                'category' => 'Thử việc',
                'title' => 'Thời hạn thử việc của bạn sắp kết thúc',
                'detail' => 'Vui lòng liên hệ quản lý để được đánh giá.',
                'date' => (string) $probationEnd,
            ];
        }

        $pending = $this->db->prepare(
            "SELECT 'Nghỉ phép' AS Loai, TuNgay AS Ngay, TrangThai
             FROM NGHIPHEP
             WHERE MaNV = :leave_employee_id AND TrangThai IN ('Chờ duyệt', 'Chờ phê duyệt', 'Tiếp nhận')
             UNION ALL
             SELECT 'Tăng ca', Ngay, TrangThai
             FROM TANGCA
             WHERE MaNV = :overtime_employee_id AND TrangThai IN ('Chờ duyệt', 'Chờ phê duyệt', 'Tiếp nhận')
             ORDER BY Ngay DESC LIMIT 5"
        );
        $pending->execute([
            'leave_employee_id' => $employeeId,
            'overtime_employee_id' => $employeeId,
        ]);
        foreach ($pending->fetchAll() as $row) {
            $notifications[] = [
                'type' => 'primary',
                'category' => 'Đề nghị của bạn',
                'title' => (string) $row['Loai'],
                'detail' => (string) $row['TrangThai'],
                'date' => (string) $row['Ngay'],
            ];
        }

        return $notifications;
    }
}
