<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecordId;
use App\Core\Upload;
use App\Core\Scope;
use App\Models\Employee;
use DomainException;
use PDOException;

final class EmployeeController extends Controller
{
    public function index(): void
    {
        $this->authorizeEmployeeView();
        $model = new Employee(Database::connection());
        $term = trim((string) ($_GET['q'] ?? ''));
        $department = trim((string) ($_GET['department'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['Thử việc', 'Chính thức', 'Nghỉ việc'], true)) {
            $status = '';
        }
        $page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
        $personalId = Scope::nhanVien('nv')[0] === 'nv.MaNV = :scope_ma_nv'
            ? (string) Auth::user()['ma_nv'] : null;
        $result = $model->search($term, $department, $status, $page, 10, $personalId);
        $this->render('employees/index', [
            'title' => 'Nhân viên',
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / 10)),
            'term' => $term,
            'department' => $department,
            'status' => $status,
            'departments' => $model->departments(),
            'personal' => $personalId !== null,
        ]);
    }

    public function profile(): void
    {
        $this->authorizeEmployeeView();
        $employeeId = $this->employeeIdFromRequest();
        Scope::requireEmployee(Database::connection(), $employeeId);
        $profile = (new Employee(Database::connection()))->profile($employeeId);
        if ($profile === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Không tìm thấy hồ sơ']);
            return;
        }

        $this->render('employees/profile', [
            'title' => 'Hồ sơ nhân viên',
            'profile' => $profile,
            'canManage' => Auth::can('employees', 'manage'),
        ]);
    }

    public function updateProfile(): void
    {
        $this->authorize('employees', 'manage');
        $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        $db = Database::connection();
        Scope::requireEmployee($db, $employeeId);
        $current = (new Employee($db))->profile($employeeId);
        if ($current === null) {
            flash('error', 'Không tìm thấy hồ sơ nhân viên.');
            $this->redirect('employees');
        }
        $field = static function (string $postKey, string $profileKey, array $current): string {
            return array_key_exists($postKey, $_POST)
                ? trim((string) $_POST[$postKey])
                : (string) ($current[$profileKey] ?? '');
        };
        $data = [
            'MaHoSo' => (string) ($current['MaHoSo'] ?? '') ?: RecordId::make('HS'),
            'MaUV' => (string) ($current['MaUV'] ?? ''),
            'SoCCCD' => $field('identity', 'SoCCCD', $current),
            'HoTen' => $field('name', 'HoTen', $current),
            'NgaySinh' => $field('birth_date', 'NgaySinh', $current),
            'GioiTinh' => $field('gender', 'GioiTinh', $current),
            'SoDienThoai' => $field('phone', 'SoDienThoai', $current),
            'Email' => $field('email', 'Email', $current),
            'DiaChi' => $field('address', 'DiaChi', $current),
            'MaPB' => $field('department', 'MaPB', $current),
            'MaCV' => $field('position', 'MaCV', $current),
            'TrinhDo' => $field('education', 'TrinhDo', $current),
            'ChuyenNganh' => $field('major', 'ChuyenNganh', $current),
            'TruongTotNghiep' => $field('school', 'TruongTotNghiep', $current),
            'NamTotNghiep' => $field('graduation_year', 'NamTotNghiep', $current),
            'NoiSinh' => $field('birth_place', 'NoiSinh', $current),
            'QuocTich' => $field('nationality', 'QuocTich', $current),
            'TinhTrangHonNhan' => $field('marital_status', 'TinhTrangHonNhan', $current),
            'DiaChiThuongTru' => $field('permanent_address', 'DiaChiThuongTru', $current),
            'DiaChiHienTai' => $field('current_address', 'DiaChiHienTai', $current),
            'NgayCapCCCD' => $field('identity_issue_date', 'NgayCapCCCD', $current),
            'NoiCapCCCD' => $field('identity_issue_place', 'NoiCapCCCD', $current),
            'MaSoThue' => $field('tax_code', 'MaSoThue', $current),
            'SoBHXH' => $field('social_insurance', 'SoBHXH', $current),
            'SoTaiKhoan' => $field('bank_account', 'SoTaiKhoan', $current),
            'NganHang' => $field('bank_name', 'NganHang', $current),
            'NguoiLienHeKhanCap' => $field('emergency_name', 'NguoiLienHeKhanCap', $current),
            'SDTLienHeKhanCap' => $field('emergency_phone', 'SDTLienHeKhanCap', $current),
        ];
        if (!$this->validProfileData($data)) {
            flash('error', 'Thông tin hồ sơ không hợp lệ. Vui lòng kiểm tra CCCD, ngày cấp, năm tốt nghiệp và mã định danh.');
            $this->redirectToProfile($employeeId, (string) ($_POST['group'] ?? 'identity'));
        }

        try {
            (new Employee($db))->updateProfile($employeeId, $data);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'CCCD, mã số thuế, số BHXH hoặc ứng viên đã được liên kết với hồ sơ khác.');
                $this->redirectToProfile($employeeId, (string) ($_POST['group'] ?? 'identity'));
            }
            throw $exception;
        }
        flash('success', 'Đã cập nhật thông tin hồ sơ.');
        $this->redirectToProfile($employeeId, (string) ($_POST['group'] ?? 'identity'));
    }

    public function createContract(): void
    {
        $this->authorize('employees', 'manage');
        $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        $salary = filter_var($_POST['salary'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($salary === false) {
            flash('error', 'Mức lương hợp đồng không hợp lệ.');
            $this->redirectToProfile($employeeId, 'contracts');
        }
        try {
            (new Employee(Database::connection()))->createContract(
                $employeeId,
                trim((string) ($_POST['contract_type'] ?? '')),
                trim((string) ($_POST['start_date'] ?? '')),
                trim((string) ($_POST['end_date'] ?? '')),
                (float) $salary
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirectToProfile($employeeId, 'contracts');
        }
        flash('success', 'Đã tạo hợp đồng và mức lương nền.');
        $this->redirectToProfile($employeeId, 'contracts');
    }

    public function uploadDocument(): void
    {
        $this->authorize('employees', 'manage');
        $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        $upload = null;
        try {
            $group = trim((string) ($_POST['document_group'] ?? 'work'));
            if (!in_array($group, ['identity', 'education', 'work', 'termination'], true)) {
                throw new DomainException('Nhóm tài liệu không hợp lệ.');
            }
            $upload = Upload::save($_FILES['document'] ?? [], 'employees');
            if ($upload === null) {
                throw new DomainException('Vui lòng chọn tài liệu cần tải lên.');
            }
            $upload['group'] = $group;
            $db = Database::connection();
            $db->beginTransaction();
            (new Employee($db))->addDocument($employeeId, $upload);
            $db->commit();
        } catch (DomainException $exception) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            if ($upload !== null) {
                @unlink(APP_ROOT . '/uploads/' . $upload['stored']);
            }
            flash('error', $exception->getMessage());
            $this->redirectToProfile($employeeId);
        } catch (\Throwable $exception) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            if ($upload !== null) {
                @unlink(APP_ROOT . '/uploads/' . $upload['stored']);
            }
            throw $exception;
        }

        flash('success', 'Đã tải tài liệu lên hồ sơ.');
        $this->redirectToProfile($employeeId, 'documents');
    }

    public function downloadDocument(): void
    {
        $this->authorizeEmployeeView();
        $employeeId = $this->employeeIdFromRequest();
        Scope::requireEmployee(Database::connection(), $employeeId);
        $stored = (string) ($_GET['file'] ?? '');
        if (!preg_match('#^employees/\d{4}/\d{2}/[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$#', $stored)) {
            http_response_code(404);
            return;
        }

        $document = (new Employee(Database::connection()))->document($employeeId, $stored);
        $path = APP_ROOT . '/uploads/' . $stored;
        if ($document === null || !is_file($path)) {
            http_response_code(404);
            return;
        }
        header('Content-Type: ' . $document['mime']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="tai-lieu"; filename*=UTF-8\'\'' . rawurlencode((string) $document['original']));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private function authorizeEmployeeView(): void
    {
        if (Auth::can('employees', 'view')) {
            $this->authorize('employees', 'view');
        } else {
            $this->authorize('employees', 'personal');
        }
    }

    private function employeeIdFromRequest(): string
    {
        if (!Auth::can('employees', 'view')) {
            return (string) Auth::user()['ma_nv'];
        }
        return trim((string) ($_GET['id'] ?? $_POST['employee_id'] ?? ''));
    }

    private function validProfileData(array $data): bool
    {
        $validBirth = $data['NgaySinh'] === ''
            || $this->isValidDate($data['NgaySinh']);
        $validIdentityIssue = $data['NgayCapCCCD'] === ''
            || $this->isValidDate($data['NgayCapCCCD']);
        $validPhone = $data['SoDienThoai'] === ''
            || preg_match('/^[0-9+() .-]{8,20}$/', $data['SoDienThoai']) === 1;
        $validEmergencyPhone = $data['SDTLienHeKhanCap'] === ''
            || preg_match('/^[0-9+() .-]{8,20}$/', $data['SDTLienHeKhanCap']) === 1;
        $validTaxCode = $data['MaSoThue'] === ''
            || preg_match('/^\d{10}(\d{3})?$/', $data['MaSoThue']) === 1;
        $validSocialInsurance = $data['SoBHXH'] === ''
            || preg_match('/^\d{10}$/', $data['SoBHXH']) === 1;
        $validAccount = $data['SoTaiKhoan'] === ''
            || preg_match('/^[0-9]{6,34}$/', $data['SoTaiKhoan']) === 1;
        $validGraduationYear = $data['NamTotNghiep'] === ''
            || (ctype_digit($data['NamTotNghiep'])
                && (int) $data['NamTotNghiep'] >= 1900
                && (int) $data['NamTotNghiep'] <= (int) date('Y') + 10);

        return $data['HoTen'] !== ''
            && mb_strlen($data['HoTen'], 'UTF-8') <= 150
            && preg_match('/^\d{9}(\d{3})?$/', $data['SoCCCD']) === 1
            && in_array($data['GioiTinh'], ['Nam', 'Nữ', 'Khác'], true)
            && $validPhone
            && $validEmergencyPhone
            && $validTaxCode
            && $validSocialInsurance
            && $validAccount
            && $validGraduationYear
            && $validIdentityIssue
            && ($data['Email'] === '' || filter_var($data['Email'], FILTER_VALIDATE_EMAIL) !== false)
            && $validBirth
            && $data['MaPB'] !== '' && $data['MaCV'] !== ''
            && in_array($data['TinhTrangHonNhan'], ['', 'Độc thân', 'Đã kết hôn', 'Khác'], true)
            && mb_strlen($data['TrinhDo'], 'UTF-8') <= 10000
            && mb_strlen($data['ChuyenNganh'], 'UTF-8') <= 150
            && mb_strlen($data['TruongTotNghiep'], 'UTF-8') <= 200
            && mb_strlen($data['NoiSinh'], 'UTF-8') <= 150
            && mb_strlen($data['QuocTich'], 'UTF-8') <= 80
            && mb_strlen($data['DiaChiThuongTru'], 'UTF-8') <= 255
            && mb_strlen($data['DiaChiHienTai'], 'UTF-8') <= 255
            && mb_strlen($data['NoiCapCCCD'], 'UTF-8') <= 150
            && mb_strlen($data['NganHang'], 'UTF-8') <= 150
            && mb_strlen($data['NguoiLienHeKhanCap'], 'UTF-8') <= 150;
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function redirectToProfile(string $employeeId, string $tab = 'personal'): void
    {
        header('Location: ' . url('employee-profile') . '&id='
            . rawurlencode($employeeId) . '#' . rawurlencode($tab));
        exit;
    }
}
