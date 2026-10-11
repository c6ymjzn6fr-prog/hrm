<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Core\Scope;

final class Employee
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function search(
        string $term,
        string $department,
        string $status,
        int $page,
        int $pageSize = 10,
        ?string $employeeId = null
    ): array
    {
        $where = [];
        $params = [];
        if ($term !== '') {
            $where[] = '(nv.MaNV LIKE :code OR nv.HoTen LIKE :name OR nv.Email LIKE :email OR nv.SoDienThoai LIKE :phone)';
            $like = '%' . $term . '%';
            $params += ['code' => $like, 'name' => $like, 'email' => $like, 'phone' => $like];
        }
        if ($department !== '') {
            $where[] = 'nv.MaPB = :department';
            $params['department'] = $department;
        }
        if ($status !== '') {
            $where[] = 'nv.TrangThai = :status';
            $params['status'] = $status;
        }
        if ($employeeId !== null) {
            $where[] = 'nv.MaNV = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $where[] = '(' . $scopeSql . ')';
        $params += $scopeParams;
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*) FROM NHANVIEN nv' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $query = $this->db->prepare(
            'SELECT nv.MaNV, nv.HoTen, nv.SoDienThoai, nv.Email, nv.NgayVaoLam, nv.TrangThai,
                    pb.TenPB, cv.TenCV
             FROM NHANVIEN nv
             INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
             INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV'
            . $whereSql . ' ORDER BY nv.HoTen LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $query->execute();

        return ['rows' => $query->fetchAll(), 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public function profile(string $employeeId): ?array
    {
        $query = $this->db->prepare(
            'SELECT nv.*, pb.TenPB, cv.TenCV,
                    hs.MaHoSo, hs.SoCCCD, hs.TrinhDo, hs.ChuyenNganh, hs.TruongTotNghiep,
                    hs.NamTotNghiep, hs.NoiSinh, hs.QuocTich, hs.TinhTrangHonNhan,
                    hs.DiaChiThuongTru, hs.DiaChiHienTai, hs.NgayCapCCCD, hs.NoiCapCCCD,
                    hs.MaSoThue, hs.SoBHXH, hs.SoTaiKhoan, hs.NganHang,
                    hs.NguoiLienHeKhanCap, hs.SDTLienHeKhanCap, hs.MaUV, hs.TaiLieu, hs.NgayCapNhat,
                    uv.HoTen AS UngVienHoTen, uv.NgayNop AS NgayUngTuyen,
                    uv.ViTriUngTuyen, uv.TrangThai AS TrangThaiUngTuyen
             FROM NHANVIEN nv
             INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB
             INNER JOIN CHUCVU cv ON cv.MaCV = nv.MaCV
             LEFT JOIN HOSONHANVIEN hs ON hs.MaNV = nv.MaNV
             LEFT JOIN UNGVIEN uv ON uv.MaUV = hs.MaUV
             WHERE nv.MaNV = :id AND (' . Scope::nhanVien('nv')[0] . ') LIMIT 1'
        );
        $query->execute(['id' => $employeeId] + Scope::nhanVien('nv')[1]);
        $profile = $query->fetch();
        if ($profile === false) {
            return null;
        }

        $profile['HopDong'] = $this->contracts($employeeId);
        $profile['LichSu'] = $this->workHistory($employeeId);
        $profile['DanhGia'] = $this->reviewsForProfile($employeeId);
        $profile['NghiViec'] = $this->resignationsForProfile($employeeId);
        $profile['PhongBanList'] = $this->departments();
        $profile['ChucVuList'] = $this->positions();
        $decoded = json_decode((string) ($profile['TaiLieu'] ?? '[]'), true);
        $profile['TaiLieuDanhSach'] = is_array($decoded) ? $decoded : [];
        $profile['TaiLieuTheoNhom'] = [
            'identity' => [],
            'education' => [],
            'work' => [],
            'termination' => [],
        ];
        foreach ($profile['TaiLieuDanhSach'] as $document) {
            if (!is_array($document) || !isset($document['stored'], $document['original'])) {
                continue;
            }
            $group = (string) ($document['group'] ?? 'work');
            if (!array_key_exists($group, $profile['TaiLieuTheoNhom'])) {
                $group = 'work';
            }
            $profile['TaiLieuTheoNhom'][$group][] = $document;
        }

        return $profile;
    }

    /** @return list<array<string,mixed>> */
    private function reviewsForProfile(string $employeeId): array
    {
        $query = $this->db->prepare(
            'SELECT NgayDanhGia, KetQua, XepLoai, NhanXet
             FROM DANHGIA WHERE MaNV = :id ORDER BY NgayDanhGia DESC'
        );
        $query->execute(['id' => $employeeId]);

        return $query->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function resignationsForProfile(string $employeeId): array
    {
        $query = $this->db->prepare(
            'SELECT MaNViec, NgayDeNghi, NgayNghi, LyDo, TrangThai, BanGiao,
                    LuongDaChot, NgayBanGiao, NgayChotLuong, NgayLuuHoSo
             FROM NGHIVIEC WHERE MaNV = :id ORDER BY NgayDeNghi DESC'
        );
        $query->execute(['id' => $employeeId]);

        return $query->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function contracts(string $employeeId): array
    {
        $query = $this->db->prepare(
            'SELECT MaHD, LoaiHD, NgayBatDau, NgayKetThuc, MucLuong, TrangThai, LyDoDieuChinh
             FROM HOPDONG WHERE MaNV = :id ORDER BY NgayBatDau DESC'
        );
        $query->execute(['id' => $employeeId]);

        return $query->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function workHistory(string $employeeId): array
    {
        $query = $this->db->prepare(
            'SELECT dc.NgayDC AS Ngay, pbcu.TenPB AS BoPhanCu, pbmoi.TenPB AS BoPhanMoi,
                    cvcu.TenCV AS ViTriCu, cvmoi.TenCV AS ViTriMoi, dc.LyDo
             FROM DIEUCHUYEN dc
             INNER JOIN PHONGBAN pbcu ON pbcu.MaPB = dc.BoPhanCu
             INNER JOIN PHONGBAN pbmoi ON pbmoi.MaPB = dc.BoPhanMoi
             INNER JOIN CHUCVU cvcu ON cvcu.MaCV = dc.ViTriCu
             INNER JOIN CHUCVU cvmoi ON cvmoi.MaCV = dc.ViTriMoi
             WHERE dc.MaNV = :id ORDER BY dc.NgayDC DESC'
        );
        $query->execute(['id' => $employeeId]);

        return $query->fetchAll();
    }

    /** @return list<array{MaPB:string,TenPB:string}> */
    public function departments(): array
    {
        $query = $this->db->prepare('SELECT MaPB, TenPB FROM PHONGBAN ORDER BY TenPB');
        $query->execute();

        return $query->fetchAll();
    }

    /** @return list<array{MaPB:string,TenPB:string}> */
    public function accessibleDepartments(): array
    {
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $query = $this->db->prepare(
            'SELECT DISTINCT pb.MaPB, pb.TenPB
             FROM PHONGBAN pb INNER JOIN NHANVIEN nv ON nv.MaPB = pb.MaPB
             WHERE (' . $scopeSql . ') ORDER BY pb.TenPB'
        );
        $query->execute($scopeParams);

        return $query->fetchAll();
    }

    /** @return list<array{MaNV:string,HoTen:string,MaPB:string}> */
    public function payrollOptions(?string $departmentId = null): array
    {
        [$scopeSql, $scopeParams] = Scope::nhanVien('nv');
        $departmentSql = $departmentId === null || $departmentId === ''
            ? ''
            : ' AND nv.MaPB = :payroll_option_department';
        $params = $scopeParams;
        if ($departmentSql !== '') {
            $params['payroll_option_department'] = $departmentId;
        }
        $query = $this->db->prepare(
            "SELECT nv.MaNV, nv.HoTen, nv.MaPB
             FROM NHANVIEN nv
             WHERE ({$scopeSql}){$departmentSql}
             ORDER BY nv.HoTen"
        );
        $query->execute($params);

        return $query->fetchAll();
    }

    /** @return list<array{MaCV:string,TenCV:string}> */
    public function positions(): array
    {
        $query = $this->db->prepare('SELECT MaCV, TenCV FROM CHUCVU ORDER BY TenCV');
        $query->execute();

        return $query->fetchAll();
    }

    public function createContract(
        string $employeeId,
        string $type,
        string $startDate,
        string $endDate,
        float $salary
    ): void {
        if (!in_array($type, ['Thử việc', 'Xác định thời hạn', 'Không xác định thời hạn'], true)
            || $salary <= 0 || $salary > 999999999999.99
            || !$this->validDate($startDate)
            || ($endDate !== '' && (!$this->validDate($endDate) || $endDate < $startDate))
            || ($type === 'Không xác định thời hạn' && $endDate !== '')
            || ($type !== 'Không xác định thời hạn' && $endDate === '')) {
            throw new \DomainException('Loại hợp đồng, ngày hiệu lực hoặc mức lương không hợp lệ.');
        }

        $this->db->beginTransaction();
        try {
            $employee = $this->db->prepare('SELECT TrangThai FROM NHANVIEN WHERE MaNV = :id FOR UPDATE');
            $employee->execute(['id' => $employeeId]);
            $status = $employee->fetchColumn();
            if ($status === false || $status === 'Nghỉ việc') {
                throw new \DomainException('Không thể tạo hợp đồng cho nhân viên không hoạt động.');
            }
            $overlap = $this->db->prepare(
                "SELECT COUNT(*) FROM HOPDONG
                 WHERE MaNV = :employee_id AND TrangThai = 'Hiệu lực'
                   AND NgayBatDau <= COALESCE(:end_date, '9999-12-31')
                   AND COALESCE(NgayKetThuc, '9999-12-31') >= :start_date"
            );
            $overlap->execute([
                'employee_id' => $employeeId,
                'end_date' => $endDate !== '' ? $endDate : null,
                'start_date' => $startDate,
            ]);
            if ((int) $overlap->fetchColumn() > 0) {
                throw new \DomainException('Thời hạn hợp đồng bị trùng với một hợp đồng đang hiệu lực.');
            }
            $insert = $this->db->prepare(
                "INSERT INTO HOPDONG
                    (MaHD, MaNV, LoaiHD, NgayBatDau, NgayKetThuc, MucLuong, TrangThai)
                 VALUES (:id, :employee_id, :type, :start_date, :end_date, :salary, 'Hiệu lực')"
            );
            $insert->execute([
                'id' => \App\Core\RecordId::make('HD'),
                'employee_id' => $employeeId,
                'type' => $type,
                'start_date' => $startDate,
                'end_date' => $endDate !== '' ? $endDate : null,
                'salary' => $salary,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function updateProfile(string $employeeId, array $data): void
    {
        $this->db->beginTransaction();
        try {
            $current = $this->db->prepare(
                'SELECT MaHoSo FROM HOSONHANVIEN WHERE MaNV = :employee_id FOR UPDATE'
            );
            $current->execute(['employee_id' => $employeeId]);
            $profileId = $current->fetchColumn();

            $employee = $this->db->prepare(
                'UPDATE NHANVIEN
                 SET HoTen = :name, NgaySinh = :birth_date, GioiTinh = :gender,
                     SoDienThoai = :phone, Email = :email, DiaChi = :address,
                     MaPB = :department, MaCV = :position
                 WHERE MaNV = :id'
            );
            $employee->execute([
                'name' => $data['HoTen'],
                'birth_date' => $data['NgaySinh'] !== '' ? $data['NgaySinh'] : null,
                'gender' => $data['GioiTinh'] !== '' ? $data['GioiTinh'] : null,
                'phone' => $data['SoDienThoai'],
                'email' => $data['Email'] !== '' ? $data['Email'] : null,
                'address' => $data['DiaChi'],
                'department' => $data['MaPB'],
                'position' => $data['MaCV'],
                'id' => $employeeId,
            ]);

            if ($profileId === false) {
                $profile = $this->db->prepare(
                    'INSERT INTO HOSONHANVIEN
                        (MaHoSo, MaNV, SoCCCD, TrinhDo, ChuyenNganh, TruongTotNghiep, NamTotNghiep,
                         NoiSinh, QuocTich, TinhTrangHonNhan, DiaChiThuongTru, DiaChiHienTai,
                         NgayCapCCCD, NoiCapCCCD, MaSoThue, SoBHXH, SoTaiKhoan, NganHang,
                         NguoiLienHeKhanCap, SDTLienHeKhanCap, MaUV, TaiLieu, NgayCapNhat)
                     VALUES
                        (:profile_id, :employee_id, :identity, :education, :major, :school, :graduation_year,
                         :birth_place, :nationality, :marital_status, :permanent_address, :current_address,
                         :identity_issue_date, :identity_issue_place, :tax_code, :social_insurance,
                         :bank_account, :bank_name, :emergency_name, :emergency_phone, :candidate_id,
                         :documents, CURRENT_DATE())'
                );
                $profile->execute([
                    'profile_id' => $data['MaHoSo'],
                    'employee_id' => $employeeId,
                    'identity' => $data['SoCCCD'],
                    'education' => $data['TrinhDo'],
                    'major' => $data['ChuyenNganh'] ?: null,
                    'school' => $data['TruongTotNghiep'] ?: null,
                    'graduation_year' => $data['NamTotNghiep'] !== '' ? $data['NamTotNghiep'] : null,
                    'birth_place' => $data['NoiSinh'] ?: null,
                    'nationality' => $data['QuocTich'] ?: null,
                    'marital_status' => $data['TinhTrangHonNhan'] ?: null,
                    'permanent_address' => $data['DiaChiThuongTru'] ?: null,
                    'current_address' => $data['DiaChiHienTai'] ?: null,
                    'identity_issue_date' => $data['NgayCapCCCD'] !== '' ? $data['NgayCapCCCD'] : null,
                    'identity_issue_place' => $data['NoiCapCCCD'] ?: null,
                    'tax_code' => $data['MaSoThue'] ?: null,
                    'social_insurance' => $data['SoBHXH'] ?: null,
                    'bank_account' => $data['SoTaiKhoan'] ?: null,
                    'bank_name' => $data['NganHang'] ?: null,
                    'emergency_name' => $data['NguoiLienHeKhanCap'] ?: null,
                    'emergency_phone' => $data['SDTLienHeKhanCap'] ?: null,
                    'candidate_id' => $data['MaUV'] ?: null,
                    'documents' => '[]',
                ]);
            } else {
                $profile = $this->db->prepare(
                    'UPDATE HOSONHANVIEN
                     SET SoCCCD = :identity, TrinhDo = :education, ChuyenNganh = :major,
                         TruongTotNghiep = :school, NamTotNghiep = :graduation_year,
                         NoiSinh = :birth_place, QuocTich = :nationality,
                         TinhTrangHonNhan = :marital_status, DiaChiThuongTru = :permanent_address,
                         DiaChiHienTai = :current_address, NgayCapCCCD = :identity_issue_date,
                         NoiCapCCCD = :identity_issue_place, MaSoThue = :tax_code,
                         SoBHXH = :social_insurance, SoTaiKhoan = :bank_account, NganHang = :bank_name,
                         NguoiLienHeKhanCap = :emergency_name, SDTLienHeKhanCap = :emergency_phone,
                         NgayCapNhat = CURRENT_DATE()
                     WHERE MaNV = :employee_id'
                );
                $profile->execute([
                    'identity' => $data['SoCCCD'],
                    'education' => $data['TrinhDo'],
                    'major' => $data['ChuyenNganh'] ?: null,
                    'school' => $data['TruongTotNghiep'] ?: null,
                    'graduation_year' => $data['NamTotNghiep'] !== '' ? $data['NamTotNghiep'] : null,
                    'birth_place' => $data['NoiSinh'] ?: null,
                    'nationality' => $data['QuocTich'] ?: null,
                    'marital_status' => $data['TinhTrangHonNhan'] ?: null,
                    'permanent_address' => $data['DiaChiThuongTru'] ?: null,
                    'current_address' => $data['DiaChiHienTai'] ?: null,
                    'identity_issue_date' => $data['NgayCapCCCD'] !== '' ? $data['NgayCapCCCD'] : null,
                    'identity_issue_place' => $data['NoiCapCCCD'] ?: null,
                    'tax_code' => $data['MaSoThue'] ?: null,
                    'social_insurance' => $data['SoBHXH'] ?: null,
                    'bank_account' => $data['SoTaiKhoan'] ?: null,
                    'bank_name' => $data['NganHang'] ?: null,
                    'emergency_name' => $data['NguoiLienHeKhanCap'] ?: null,
                    'emergency_phone' => $data['SDTLienHeKhanCap'] ?: null,
                    'employee_id' => $employeeId,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function addDocument(string $employeeId, array $document): void
    {
        $query = $this->db->prepare('SELECT TaiLieu FROM HOSONHANVIEN WHERE MaNV = :id FOR UPDATE');
        $query->execute(['id' => $employeeId]);
        $current = $query->fetchColumn();
        $documents = $current === false ? [] : json_decode((string) $current, true);
        if (!is_array($documents)) {
            $documents = [];
        }
        $documents[] = $document;

        if ($current === false) {
            throw new \DomainException('Hồ sơ nhân viên chưa có CCCD; hãy cập nhật hồ sơ trước khi tải tài liệu.');
        }
        $update = $this->db->prepare(
            'UPDATE HOSONHANVIEN SET TaiLieu = :documents, NgayCapNhat = CURRENT_DATE() WHERE MaNV = :id'
        );
        $update->execute([
            'documents' => json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'id' => $employeeId,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function document(string $employeeId, string $storedName): ?array
    {
        $query = $this->db->prepare('SELECT TaiLieu FROM HOSONHANVIEN WHERE MaNV = :id');
        $query->execute(['id' => $employeeId]);
        $documents = json_decode((string) $query->fetchColumn(), true);
        if (!is_array($documents)) {
            return null;
        }
        foreach ($documents as $document) {
            if (is_array($document) && ($document['stored'] ?? null) === $storedName) {
                return $document;
            }
        }

        return null;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
