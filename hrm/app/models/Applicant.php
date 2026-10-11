<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\ProbationPolicy;
use PDO;

final class Applicant
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function search(string $term, string $status, int $page, int $pageSize = 10): array
    {
        $where = [];
        $params = [];
        if ($term !== '') {
            $where[] = '(u.HoTen LIKE :term_name OR u.Email LIKE :term_email OR u.SoDienThoai LIKE :term_phone)';
            $like = '%' . $term . '%';
            $params += ['term_name' => $like, 'term_email' => $like, 'term_phone' => $like];
        }
        if ($status !== '') {
            $where[] = 'u.TrangThai = :status';
            $params['status'] = $status;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->db->prepare('SELECT COUNT(*) FROM UNGVIEN u' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $query = $this->db->prepare(
            'SELECT u.*, t.ViTri AS TinTuyenDung
             FROM UNGVIEN u
             LEFT JOIN TUYENDUNG t ON t.MaTD = u.MaTD'
            . $whereSql . ' ORDER BY u.NgayNop DESC, u.MaUV DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $query->execute();

        return ['rows' => $query->fetchAll(), 'total' => $total];
    }

    public function phoneMatchCount(string $phone): int
    {
        $query = $this->db->prepare(
            'SELECT COUNT(*) FROM UNGVIEN WHERE SoDienThoai = :phone'
        );
        $query->execute(['phone' => $phone]);

        return (int) $query->fetchColumn();
    }

    public function findForUpdate(string $candidateId): ?array
    {
        $query = $this->db->prepare(
            'SELECT * FROM UNGVIEN WHERE MaUV = :id LIMIT 1 FOR UPDATE'
        );
        $query->execute(['id' => $candidateId]);
        $row = $query->fetch();

        return $row === false ? null : $row;
    }

    public function findOpenJob(string $jobId): ?array
    {
        $query = $this->db->prepare(
            "SELECT MaTD, ViTri FROM TUYENDUNG WHERE MaTD = :id AND TrangThai = 'Đang tuyển' LIMIT 1"
        );
        $query->execute(['id' => $jobId]);
        $job = $query->fetch();

        return $job === false ? null : $job;
    }

    public function create(array $data): void
    {
        $query = $this->db->prepare(
            "INSERT INTO UNGVIEN
                (MaUV, HoTen, SoDienThoai, Email, ViTriUngTuyen, TrangThai, MaTD, NgayNop, HoSoCV)
             VALUES
                (:id, :name, :phone, :email, :position, 'Mới', :job_id, CURRENT_DATE(), :cv)"
        );
        $query->execute([
            'id' => $data['MaUV'],
            'name' => $data['HoTen'],
            'phone' => $data['SoDienThoai'],
            'email' => $data['Email'],
            'position' => $data['ViTriUngTuyen'],
            'job_id' => $data['MaTD'],
            'cv' => $data['HoSoCV'],
        ]);
    }

    public function updateStage(
        string $candidateId,
        string $currentStage,
        string $stage,
        ?string $interviewAt,
        ?string $result,
        ?string $note
    ): bool
    {
        $query = $this->db->prepare(
            'UPDATE UNGVIEN SET TrangThai = :stage,
               NgayPhongVan = COALESCE(:interview_at, NgayPhongVan),
               KetQuaPhongVan = COALESCE(:result, KetQuaPhongVan),
               NhanXet = COALESCE(:note, NhanXet)
             WHERE MaUV = :id AND TrangThai = :current_stage'
        );
        $query->execute([
            'stage' => $stage,
            'interview_at' => $interviewAt,
            'result' => $result,
            'note' => $note,
            'id' => $candidateId,
            'current_stage' => $currentStage,
        ]);

        return $query->rowCount() > 0;
    }

    public function find(string $candidateId): ?array
    {
        $query = $this->db->prepare(
            'SELECT * FROM UNGVIEN WHERE MaUV = :id LIMIT 1'
        );
        $query->execute(['id' => $candidateId]);
        $row = $query->fetch();

        return $row === false ? null : $row;
    }

    public function documentPath(string $candidateId): ?string
    {
        $query = $this->db->prepare('SELECT HoSoCV FROM UNGVIEN WHERE MaUV = :id LIMIT 1');
        $query->execute(['id' => $candidateId]);
        $path = $query->fetchColumn();

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** Tiếp nhận ứng viên trong một transaction để tránh tạo hồ sơ dở dang. */
    public function onboard(array $data): void
    {
        $this->db->beginTransaction();
        try {
            $candidate = $this->findForUpdate((string) $data['MaUV']);
            if ($candidate === null || $candidate['TrangThai'] !== 'Đạt') {
                throw new \DomainException('Chỉ tiếp nhận ứng viên có kết quả Đạt.');
            }

            $positionQuery = $this->db->prepare('SELECT TenCV FROM CHUCVU WHERE MaCV = :id');
            $positionQuery->execute(['id' => $data['MaCV']]);
            $position = $positionQuery->fetchColumn();
            if ($position === false) {
                throw new \DomainException('Không tìm thấy chức vụ được chọn.');
            }
            $probationEnd = ProbationPolicy::endDate(
                (string) $data['NgayVaoLam'],
                ProbationPolicy::maximumDays((string) $data['TrinhDo'], (string) $position)
            );

            $employee = $this->db->prepare(
                'INSERT INTO NHANVIEN
                    (MaNV, HoTen, NgaySinh, GioiTinh, SoDienThoai, Email, DiaChi, NgayVaoLam, TrangThai, MaPB, MaCV)
                 VALUES
                    (:id, :name, :birth_date, :gender, :phone, :email, :address, :start_date, :status, :department, :position)'
            );
            $employee->execute([
                'id' => $data['MaNV'],
                'name' => $candidate['HoTen'],
                'birth_date' => $data['NgaySinh'] !== '' ? $data['NgaySinh'] : null,
                'gender' => $data['GioiTinh'],
                'phone' => $candidate['SoDienThoai'],
                'email' => $candidate['Email'],
                'address' => $data['DiaChi'],
                'start_date' => $data['NgayVaoLam'],
                'status' => $data['TrangThaiNV'],
                'department' => $data['MaPB'],
                'position' => $data['MaCV'],
            ]);

            $profile = $this->db->prepare(
                'INSERT INTO HOSONHANVIEN (MaHoSo, MaNV, SoCCCD, TrinhDo, MaUV, TaiLieu, NgayCapNhat)
                 VALUES (:profile_id, :employee_id, :identity, :education, :candidate_id, :documents, CURRENT_DATE())'
            );
            $profile->execute([
                'profile_id' => $data['MaHoSo'],
                'employee_id' => $data['MaNV'],
                'identity' => $data['SoCCCD'],
                'education' => $data['TrinhDo'],
                'candidate_id' => $data['MaUV'],
                'documents' => '[]',
            ]);

            $contractEnd = $data['NgayKetThuc'] !== '' ? $data['NgayKetThuc'] : null;
            $contract = $this->db->prepare(
                "INSERT INTO HOPDONG
                    (MaHD, MaNV, LoaiHD, NgayBatDau, NgayKetThuc, MucLuong, TrangThai)
                 VALUES (:id, :employee_id, :type, :start_date, :end_date, :salary, 'Hiệu lực')"
            );
            $contract->execute([
                'id' => $data['MaHD'],
                'employee_id' => $data['MaNV'],
                'type' => $data['LoaiHD'],
                'start_date' => $data['NgayVaoLam'],
                'end_date' => $contractEnd,
                'salary' => $data['MucLuong'],
            ]);

            $account = $this->db->prepare(
                "INSERT INTO TAIKHOAN (MaTK, MaNV, TenDangNhap, MatKhau, VaiTro, TrangThai)
                 VALUES (:id, :employee_id, :username, :password, 'nhanvien', 'Hoạt động')"
            );
            $account->execute([
                'id' => $data['MaTK'],
                'employee_id' => $data['MaNV'],
                'username' => $data['TenDangNhap'],
                'password' => password_hash((string) $data['MatKhau'], PASSWORD_DEFAULT),
            ]);

            if ($data['TrangThaiNV'] === 'Thử việc') {
                $probation = $this->db->prepare(
                    'INSERT INTO THUVIEC (MaTV, MaNV, NgayBatDau, NgayKetThuc)
                     VALUES (:id, :employee_id, :start_date, :end_date)'
                );
                $probation->execute([
                    'id' => $data['MaTV'],
                    'employee_id' => $data['MaNV'],
                    'start_date' => $data['NgayVaoLam'],
                    'end_date' => $probationEnd,
                ]);
            }

            $mark = $this->db->prepare(
                "UPDATE UNGVIEN SET TrangThai = 'Đã tiếp nhận' WHERE MaUV = :id AND TrangThai = 'Đạt'"
            );
            $mark->execute(['id' => $data['MaUV']]);
            if ($mark->rowCount() !== 1) {
                throw new \DomainException('Ứng viên đã được tiếp nhận hoặc trạng thái đã thay đổi.');
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
