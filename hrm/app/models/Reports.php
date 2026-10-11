<?php
declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use App\Core\Auth;
use PDO;
use App\Core\Scope;

final class Reports
{
    private const REPORTS = [
        'employees' => [
            'title' => 'Báo cáo nhân viên',
            'headers' => ['Mã nhân viên', 'Họ tên', 'Phòng ban', 'Chức vụ', 'Ngày vào làm', 'Trạng thái'],
        ],
        'recruitment' => [
            'title' => 'Báo cáo tuyển dụng',
            'headers' => ['Mã tuyển dụng', 'Vị trí', 'Phòng ban', 'Số lượng', 'Ứng viên', 'Ngày đăng', 'Trạng thái'],
        ],
        'probation' => [
            'title' => 'Báo cáo thử việc',
            'headers' => ['Mã nhân viên', 'Họ tên', 'Phòng ban', 'Chức vụ', 'Từ ngày', 'Đến ngày', 'Kết quả'],
        ],
        'attendance' => [
            'title' => 'Báo cáo chấm công',
            'headers' => ['Mã nhân viên', 'Họ tên', 'Phòng ban', 'Ngày', 'Giờ vào', 'Giờ ra', 'Trạng thái', 'Đã chốt'],
        ],
        'payroll' => [
            'title' => 'Báo cáo tiền lương',
            'headers' => ['Mã nhân viên', 'Họ tên', 'Phòng ban', 'Kỳ lương', 'Lương cơ bản', 'Phụ cấp', 'Thưởng', 'Tăng ca', 'Khấu trừ', 'Thực nhận', 'Trạng thái'],
        ],
        'resignations' => [
            'title' => 'Báo cáo nghỉ việc',
            'headers' => ['Mã nhân viên', 'Họ tên', 'Phòng ban', 'Ngày đề nghị', 'Ngày nghỉ', 'Lý do', 'Trạng thái', 'Bàn giao', 'Đã chốt lương'],
        ],
    ];

    public function __construct(private PDO $db)
    {
    }

    /** @return array{title:string,headers:list<string>,rows:list<list<mixed>>} */
    public function report(string $type, string $from, string $to, string $department, ?string $employeeId): array
    {
        if (!isset(self::REPORTS[$type])) {
            throw new InvalidArgumentException('Loại báo cáo không hợp lệ.');
        }

        $scope = $employeeId === null ? '' : ' AND nv.MaNV = :employee_id';
        [$scopeClause, $scopeParams] = Scope::nhanVien('nv');
        if ($employeeId === null && $scopeClause !== '1 = 1') {
            $scope .= ' AND (' . $scopeClause . ')';
        }
        $departmentFilter = $department === '' ? '' : ' AND %s = :department';
        $params = $type === 'payroll' ? [] : ['from_date' => $from, 'to_date' => $to];
        if ($type === 'recruitment') {
            $params['candidate_from'] = $from;
            $params['candidate_to'] = $to;
        }
        if ($department !== '') {
            $params['department'] = $department;
        }
        if ($employeeId !== null && $type !== 'recruitment') {
            $params['employee_id'] = $employeeId;
        }
        if ($employeeId === null && $scopeClause !== '1 = 1') {
            $params += $scopeParams;
        }
        $recruitmentScope = '';
        if (Auth::hasRole('quanly') && !array_intersect(Auth::roles(), ['giamdoc', 'nhansu', 'ketoan'])) {
            $recruitmentScope = ' AND td.MaPB = :scope_ma_pb';
        } elseif (Auth::hasRole('nhanvien') && !array_intersect(Auth::roles(), ['giamdoc', 'nhansu', 'ketoan', 'quanly'])) {
            $recruitmentScope = ' AND 1 = 0';
        }

        $sql = match ($type) {
            'employees' => "SELECT nv.MaNV, nv.HoTen, pb.TenPB, cv.TenCV, nv.NgayVaoLam, nv.TrangThai
                FROM NHANVIEN nv
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV
                WHERE nv.NgayVaoLam BETWEEN :from_date AND :to_date"
                . sprintf($departmentFilter, 'nv.MaPB') . $scope . "
                ORDER BY nv.NgayVaoLam DESC, nv.MaNV",
            'recruitment' => "SELECT td.MaTD, td.ViTri, COALESCE(pb.TenPB, 'Chưa phân công') AS TenPB, td.SoLuong,
                    (SELECT COUNT(*) FROM UNGVIEN uv
                     WHERE uv.MaTD = td.MaTD AND uv.NgayNop BETWEEN :candidate_from AND :candidate_to) AS SoUngVien,
                    td.NgayDang, td.TrangThai
                FROM TUYENDUNG td
                LEFT JOIN PHONGBAN pb ON pb.MaPB = td.MaPB
                WHERE td.NgayDang BETWEEN :from_date AND :to_date"
                . sprintf($departmentFilter, 'td.MaPB') . $recruitmentScope . "
                ORDER BY td.NgayDang DESC, td.MaTD",
            'probation' => "SELECT nv.MaNV, nv.HoTen, pb.TenPB, cv.TenCV,
                    tv.NgayBatDau, tv.NgayKetThuc, COALESCE(tv.KetQua, 'Đang thử việc') AS KetQua
                FROM THUVIEC tv
                INNER JOIN NHANVIEN nv ON nv.MaNV = tv.MaNV
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV
                WHERE tv.NgayBatDau <= :to_date AND tv.NgayKetThuc >= :from_date"
                . sprintf($departmentFilter, 'nv.MaPB') . $scope . "
                ORDER BY tv.NgayBatDau DESC, nv.HoTen",
            'attendance' => "SELECT nv.MaNV, nv.HoTen, pb.TenPB, cc.Ngay, cc.GioVao, cc.GioRa,
                    cc.TrangThai, IF(cc.DaChot = 1, 'Có', 'Không') AS DaChot
                FROM CHAMCONG cc
                INNER JOIN NHANVIEN nv ON nv.MaNV = cc.MaNV
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                WHERE cc.Ngay BETWEEN :from_date AND :to_date"
                . sprintf($departmentFilter, 'nv.MaPB') . $scope . "
                ORDER BY cc.Ngay DESC, nv.HoTen",
            'payroll' => "SELECT nv.MaNV, nv.HoTen, pb.TenPB,
                    CONCAT(LPAD(l.Thang, 2, '0'), '/', l.Nam) AS KyLuong,
                    l.LuongCoBan, l.PhuCap, l.Thuong, l.TangCa, l.KhauTru, l.ThucNhan, l.TrangThai
                FROM LUONG l
                INNER JOIN NHANVIEN nv ON nv.MaNV = l.MaNV
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                WHERE (l.Nam * 100 + l.Thang) BETWEEN :from_month AND :to_month"
                . sprintf($departmentFilter, 'nv.MaPB') . $scope . "
                ORDER BY l.Nam DESC, l.Thang DESC, nv.HoTen",
            'resignations' => "SELECT nv.MaNV, nv.HoTen, pb.TenPB, nvx.NgayDeNghi, nvx.NgayNghi,
                    nvx.LyDo, nvx.TrangThai,
                    IF(nvx.BanGiao = 1, 'Có', 'Không') AS BanGiao,
                    IF(nvx.LuongDaChot = 1, 'Có', 'Không') AS LuongDaChot
                FROM NGHIVIEC nvx
                INNER JOIN NHANVIEN nv ON nv.MaNV = nvx.MaNV
                INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
                WHERE nvx.NgayDeNghi BETWEEN :from_date AND :to_date"
                . sprintf($departmentFilter, 'nv.MaPB') . $scope . "
                ORDER BY nvx.NgayDeNghi DESC, nv.HoTen",
        };

        if ($type === 'payroll') {
            $params['from_month'] = (int) date('Ym', strtotime($from));
            $params['to_month'] = (int) date('Ym', strtotime($to));
        }
        if ($type === 'recruitment' && str_contains($recruitmentScope, ':scope_ma_pb')) {
            $params['scope_ma_pb'] = (string) (Auth::user()['ma_pb'] ?? '');
        }
        $query = $this->db->prepare($sql);
        $query->execute($params);

        return [
            'title' => self::REPORTS[$type]['title'],
            'headers' => self::REPORTS[$type]['headers'],
            'rows' => array_map('array_values', $query->fetchAll()),
        ];
    }

    /** @return list<array{MaPB:string,TenPB:string}> */
    public function departments(): array
    {
        $query = $this->db->query('SELECT MaPB, TenPB FROM PHONGBAN ORDER BY TenPB');

        return $query->fetchAll();
    }

    /** @return list<array{key:string,title:string}> */
    public static function reportTypes(): array
    {
        return array_map(
            static fn (string $key, array $report): array => ['key' => $key, 'title' => $report['title']],
            array_keys(self::REPORTS),
            array_values(self::REPORTS)
        );
    }
}
