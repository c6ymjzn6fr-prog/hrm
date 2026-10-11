<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecordId;
use App\Core\Upload;
use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Recruitment;
use DomainException;
use PDOException;

final class ApplicantController extends Controller
{
    private const STAGES = ['Mới', 'Đang sàng lọc', 'Mời phỏng vấn', 'Đạt', 'Từ chối'];

    public function index(): void
    {
        $this->authorize('recruitment', 'manage');
        $model = new Applicant(Database::connection());
        $term = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, [...self::STAGES, 'Đã tiếp nhận'], true)) {
            $status = '';
        }
        $page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
        $result = $model->search($term, $status, $page);
        $this->render('applicants/index', [
            'title' => 'Ứng viên',
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / 10)),
            'term' => $term,
            'status' => $status,
            'stages' => self::STAGES,
            'jobs' => (new Recruitment(Database::connection()))->openJobs(),
            'canManage' => Auth::can('recruitment', 'manage'),
        ]);
    }

    public function create(): void
    {
        $this->authorize('recruitment', 'manage');
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $jobId = trim((string) ($_POST['job_id'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150
            || !preg_match('/^[0-9+() .-]{8,20}$/', $phone)
            || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            flash('error', 'Thông tin ứng viên không hợp lệ; vui lòng kiểm tra họ tên, điện thoại và email.');
            $this->redirect('applicants');
        }
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phone) < 8 || strlen($phone) > 15) {
            flash('error', 'Số điện thoại phải có từ 8 đến 15 chữ số.');
            $this->redirect('applicants');
        }

        $db = Database::connection();
        $model = new Applicant($db);
        $job = $model->findOpenJob($jobId);
        if ($job === null) {
            flash('error', 'Hãy chọn một tin tuyển dụng đang mở.');
            $this->redirect('applicants');
        }

        $duplicatePhone = $model->phoneMatchCount($phone) > 0;
        $upload = null;
        try {
            $upload = Upload::save($_FILES['cv'] ?? [], 'candidates');
            $model->create([
                'MaUV' => RecordId::make('UV'),
                'HoTen' => $name,
                'SoDienThoai' => $phone,
                'Email' => $email !== '' ? $email : null,
                'ViTriUngTuyen' => $job['ViTri'],
                'MaTD' => $jobId,
                'HoSoCV' => $upload['stored'] ?? null,
            ]);
        } catch (DomainException $exception) {
            if ($upload !== null) {
                @unlink(APP_ROOT . '/uploads/' . $upload['stored']);
            }
            flash('error', $exception->getMessage());
            $this->redirect('applicants');
        } catch (\Throwable $exception) {
            if ($upload !== null) {
                @unlink(APP_ROOT . '/uploads/' . $upload['stored']);
            }
            throw $exception;
        }

        flash($duplicatePhone ? 'warning' : 'success', $duplicatePhone
            ? 'Đã nhận hồ sơ. Cảnh báo: số điện thoại này đã xuất hiện ở hồ sơ ứng viên khác.'
            : 'Đã nhận hồ sơ ứng viên.');
        $this->redirect('applicants');
    }

    public function updateStage(): void
    {
        $this->authorize('recruitment', 'manage');
        $id = trim((string) ($_POST['id'] ?? ''));
        $stage = trim((string) ($_POST['stage'] ?? ''));
        $interviewAt = trim((string) ($_POST['interview_at'] ?? ''));
        $result = trim((string) ($_POST['interview_result'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!in_array($stage, self::STAGES, true)
            || ($stage === 'Mời phỏng vấn' && !$this->validDateTime($interviewAt))
            || mb_strlen($result, 'UTF-8') > 4000
            || mb_strlen($note, 'UTF-8') > 4000) {
            flash('error', 'Trạng thái hoặc thông tin phỏng vấn không hợp lệ.');
            $this->redirect('applicants');
        }

        $model = new Applicant(Database::connection());
        $candidate = $model->find($id);
        if ($candidate === null) {
            flash('error', 'Không tìm thấy hồ sơ ứng viên.');
            $this->redirect('applicants');
        }
        if (!$this->allowedTransition((string) $candidate['TrangThai'], $stage)) {
            flash('error', 'Trạng thái ứng viên không thể chuyển theo luồng hiện tại.');
            $this->redirect('applicants');
        }

        $updated = $model->updateStage(
            $id,
            (string) $candidate['TrangThai'],
            $stage,
            $interviewAt !== '' ? str_replace('T', ' ', $interviewAt) . ':00' : null,
            $result !== '' ? $result : null,
            $note !== '' ? $note : null
        );
        if (!$updated) {
            flash('error', 'Trạng thái ứng viên vừa được cập nhật bởi người dùng khác. Vui lòng tải lại danh sách.');
            $this->redirect('applicants');
        }
        flash('success', 'Đã cập nhật giai đoạn tuyển dụng của ứng viên.');
        $this->redirect('applicants');
    }

    public function onboardForm(): void
    {
        $this->authorize('recruitment', 'manage');
        $id = trim((string) ($_GET['id'] ?? ''));
        $candidate = (new Applicant(Database::connection()))->find($id);
        if ($candidate === null || $candidate['TrangThai'] !== 'Đạt') {
            flash('error', 'Chỉ có thể tiếp nhận ứng viên đã đạt phỏng vấn.');
            $this->redirect('applicants');
        }

        $employees = new Employee(Database::connection());
        $this->render('applicants/onboard', [
            'title' => 'Tiếp nhận ứng viên',
            'candidate' => $candidate,
            'departments' => $employees->departments(),
            'positions' => $employees->positions(),
        ]);
    }

    public function downloadCv(): void
    {
        $this->authorize('recruitment', 'manage');
        $candidateId = trim((string) ($_GET['id'] ?? ''));
        $stored = (new Applicant(Database::connection()))->documentPath($candidateId);
        if ($stored === null || !preg_match('#^candidates/\d{4}/\d{2}/[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$#', $stored)) {
            http_response_code(404);
            return;
        }

        $path = APP_ROOT . '/uploads/' . $stored;
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            http_response_code(415);
            return;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="ho-so-ung-vien"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    public function onboard(): void
    {
        $this->authorize('recruitment', 'manage');
        $id = trim((string) ($_POST['id'] ?? ''));
        $data = [
            'MaUV' => $id,
            'MaNV' => RecordId::make('NV'),
            'MaHoSo' => RecordId::make('HS'),
            'MaHD' => RecordId::make('HD'),
            'MaTK' => RecordId::make('TK'),
            'MaTV' => RecordId::make('TV'),
            'NgaySinh' => trim((string) ($_POST['birth_date'] ?? '')),
            'GioiTinh' => trim((string) ($_POST['gender'] ?? '')),
            'DiaChi' => trim((string) ($_POST['address'] ?? '')),
            'NgayVaoLam' => trim((string) ($_POST['start_date'] ?? '')),
            'TrangThaiNV' => trim((string) ($_POST['employee_status'] ?? '')),
            'MaPB' => trim((string) ($_POST['department'] ?? '')),
            'MaCV' => trim((string) ($_POST['position'] ?? '')),
            'SoCCCD' => trim((string) ($_POST['identity'] ?? '')),
            'TrinhDo' => trim((string) ($_POST['education'] ?? '')),
            'LoaiHD' => trim((string) ($_POST['contract_type'] ?? '')),
            'NgayKetThuc' => trim((string) ($_POST['contract_end'] ?? '')),
            'MucLuong' => filter_var($_POST['salary'] ?? null, FILTER_VALIDATE_FLOAT),
            'TenDangNhap' => trim((string) ($_POST['username'] ?? '')),
            'MatKhau' => (string) ($_POST['password'] ?? ''),
        ];
        if (!$this->validOnboardingData($data)) {
            flash('error', 'Vui lòng kiểm tra thông tin tiếp nhận; CCCD phải gồm 9 hoặc 12 số, mật khẩu tối thiểu 12 ký tự.');
            header('Location: ' . url('candidate-onboard') . '&id=' . rawurlencode($id));
            exit;
        }

        try {
            (new Applicant(Database::connection()))->onboard($data);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            header('Location: ' . url('candidate-onboard') . '&id=' . rawurlencode($id));
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'Không thể tiếp nhận: CCCD, tên đăng nhập hoặc mã hồ sơ đã tồn tại.');
                header('Location: ' . url('candidate-onboard') . '&id=' . rawurlencode($id));
                exit;
            }
            throw $exception;
        }

        flash('success', 'Đã tạo nhân viên, hồ sơ, hợp đồng và tài khoản; ứng viên được đánh dấu đã tiếp nhận.');
        $this->redirect('employees');
    }

    private function allowedTransition(string $from, string $to): bool
    {
        $transitions = [
            'Mới' => ['Đang sàng lọc', 'Từ chối'],
            'Đang sàng lọc' => ['Mời phỏng vấn', 'Từ chối'],
            'Mời phỏng vấn' => ['Đạt', 'Từ chối'],
        ];

        return in_array($to, $transitions[$from] ?? [], true);
    }

    private function validDateTime(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        return $date !== false && $date->format('Y-m-d\TH:i') === $value;
    }

    private function validOnboardingData(array $data): bool
    {
        $date = $this->parseDate($data['NgayVaoLam']);
        $birthDate = $data['NgaySinh'] === '' ? null : $this->parseDate($data['NgaySinh']);
        $contractEnd = $data['NgayKetThuc'] === ''
            ? null
            : $this->parseDate($data['NgayKetThuc']);

        return preg_match('/^\d{9}(\d{3})?$/', $data['SoCCCD']) === 1
            && mb_strlen($data['TenDangNhap'], 'UTF-8') >= 4
            && mb_strlen($data['TenDangNhap'], 'UTF-8') <= 50
            && strlen($data['MatKhau']) >= 12
            && in_array($data['TrangThaiNV'], ['Thử việc', 'Chính thức'], true)
            && in_array($data['GioiTinh'], ['Nam', 'Nữ', 'Khác'], true)
            && ($data['NgaySinh'] === '' || $birthDate !== null)
            && $data['MucLuong'] !== false && $data['MucLuong'] > 0 && $data['MucLuong'] <= 999999999999.99
            && $date !== null
            && ($data['NgayKetThuc'] === '' || ($contractEnd !== null && $contractEnd >= $date))
            && $data['MaPB'] !== '' && $data['MaCV'] !== ''
            && $data['LoaiHD'] !== '';
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || $date->format('Y-m-d') !== $value
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
