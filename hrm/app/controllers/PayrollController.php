<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Employee;
use App\Models\Payroll;
use DomainException;

final class PayrollController extends Controller
{
    public function index(): void
    {
        $this->authorizePayrollView();
        $month = trim((string) ($_GET['month'] ?? date('Y-m', strtotime('first day of last month'))));
        $personalId = $this->personalScope();
        $departmentId = $personalId === null ? trim((string) ($_GET['department_id'] ?? '')) : '';
        $selectedEmployeeId = $personalId === null ? trim((string) ($_GET['employee_id'] ?? '')) : '';
        $db = Database::connection();
        if ($selectedEmployeeId !== '') {
            Scope::requireEmployee($db, $selectedEmployeeId);
        }
        $model = new Payroll(Database::connection());
        try {
            $readiness = $model->readiness($month, $departmentId, $selectedEmployeeId);
            $rows = $model->rows($month, $personalId, $departmentId, $selectedEmployeeId);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('payroll');
        }
        if (!Auth::can('payroll', 'manage') && !Auth::can('payroll', 'approve') && !Auth::can('payroll', 'view')) {
            $readiness['employees'] = array_values(array_filter(
                $readiness['employees'],
                static fn (array $employee): bool => $employee['MaNV'] === $personalId
            ));
        }
        $this->render('payroll/index', [
            'title' => 'Tính lương',
            'month' => $month,
            'latestClosedMonth' => date('Y-m', strtotime('first day of last month')),
            'departmentId' => $departmentId,
            'selectedEmployeeId' => $selectedEmployeeId,
            'departments' => $personalId === null ? (new Employee($db))->accessibleDepartments() : [],
            'employeeOptions' => $personalId === null ? (new Employee($db))->payrollOptions($departmentId) : [],
            'readiness' => $readiness,
            'rows' => $rows,
            'canManage' => Auth::can('payroll', 'manage'),
            'canApprove' => Auth::can('payroll', 'approve'),
            'personal' => $personalId !== null,
        ]);
    }

    public function calculate(): void
    {
        $this->authorize('payroll', 'manage');
        $month = trim((string) ($_POST['month'] ?? ''));
        $departmentId = trim((string) ($_POST['department_id'] ?? ''));
        $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        if ($employeeId !== '') {
            Scope::requireEmployee(Database::connection(), $employeeId);
        }
        try {
            $count = (new Payroll(Database::connection()))->calculate(
                $month,
                is_array($_POST['adjustments'] ?? null) ? $_POST['adjustments'] : [],
                (string) Auth::user()['MaNV'],
                $departmentId,
                $employeeId
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirectToPeriod($month, $departmentId, $employeeId);
        }
        flash('success', "Đã tính {$count} phiếu lương ở trạng thái chờ Giám đốc phê duyệt.");
        $this->redirectToPeriod($month, $departmentId, $employeeId);
    }

    public function approve(): void
    {
        $this->authorize('payroll', 'approve');
        $month = trim((string) ($_POST['month'] ?? ''));
        $departmentId = trim((string) ($_POST['department_id'] ?? ''));
        $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        if ($employeeId !== '') {
            Scope::requireEmployee(Database::connection(), $employeeId);
        }
        try {
            $count = (new Payroll(Database::connection()))->approvePeriod(
                $month,
                Auth::user()['MaNV'],
                $departmentId,
                $employeeId
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirectToPeriod($month, $departmentId, $employeeId);
        }
        flash('success', "Giám đốc đã phê duyệt {$count} phiếu lương.");
        $this->redirectToPeriod($month, $departmentId, $employeeId);
    }

    public function exportCsv(): void
    {
        $this->authorizePayrollView();
        $month = trim((string) ($_GET['month'] ?? ''));
        $departmentId = trim((string) ($_GET['department_id'] ?? ''));
        $selectedEmployeeId = trim((string) ($_GET['employee_id'] ?? ''));
        $personalId = $this->personalScope();
        if ($selectedEmployeeId !== '') {
            Scope::requireEmployee(Database::connection(), $selectedEmployeeId);
        }
        $rows = (new Payroll(Database::connection()))->rows(
            $month,
            $personalId,
            $personalId === null ? $departmentId : '',
            $personalId === null ? $selectedEmployeeId : ''
        );
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bang-luong-' . rawurlencode($month) . '.csv"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('Không thể tạo tệp CSV bảng lương.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Mã nhân viên', 'Họ tên', 'Phòng ban', 'Tháng', 'Năm', 'Lương cơ bản', 'Phụ cấp', 'Thưởng', 'Tăng ca', 'Khấu trừ', 'Thực nhận', 'Trạng thái']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $this->safeCsv((string) $row['MaNV']),
                $this->safeCsv((string) $row['HoTen']),
                $this->safeCsv((string) $row['TenPB']),
                $row['Thang'],
                $row['Nam'],
                $row['LuongCoBan'],
                $row['PhuCap'],
                $row['Thuong'],
                $row['TangCa'],
                $row['KhauTru'],
                $row['ThucNhan'],
                $this->safeCsv((string) $row['TrangThai']),
            ]);
        }
        fclose($output);
    }

    public function payslip(): void
    {
        $this->authorizePayrollView();
        $employeeId = $this->personalScope();
        $db = Database::connection();
        $owner = $db->prepare('SELECT MaNV FROM LUONG WHERE MaLuong = :id');
        $owner->execute(['id' => trim((string) ($_GET['id'] ?? ''))]);
        $recordOwner = $owner->fetchColumn();
        if (is_string($recordOwner)) {
            Scope::requireEmployee($db, $recordOwner);
        }
        $slip = (new Payroll(Database::connection()))->payslip(
            trim((string) ($_GET['id'] ?? '')),
            $employeeId
        );
        if ($slip === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Không tìm thấy phiếu lương']);
            return;
        }
        if ($slip['TrangThai'] !== 'Đã duyệt'
            && !Auth::can('payroll', 'manage')
            && !Auth::can('payroll', 'approve')) {
            $this->authorize('payroll', 'manage');
        }
        $this->render('payroll/payslip', ['title' => 'Phiếu lương', 'slip' => $slip, 'printable' => true]);
    }

    private function authorizePayrollView(): void
    {
        if (Auth::can('payroll', 'manage')) {
            $this->authorize('payroll', 'manage');
        } elseif (Auth::can('payroll', 'view')) {
            $this->authorize('payroll', 'view');
        } elseif (Auth::can('payroll', 'approve')) {
            $this->authorize('payroll', 'approve');
        } else {
            $this->authorize('payroll', 'personal');
        }
    }

    private function personalScope(): ?string
    {
        return Scope::nhanVien('nv')[0] === 'nv.MaNV = :scope_ma_nv'
            ? (string) Auth::user()['ma_nv'] : null;
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }

    private function redirectToPeriod(string $month, string $departmentId = '', string $employeeId = ''): void
    {
        $query = '&month=' . rawurlencode($month);
        if ($departmentId !== '') {
            $query .= '&department_id=' . rawurlencode($departmentId);
        }
        if ($employeeId !== '') {
            $query .= '&employee_id=' . rawurlencode($employeeId);
        }
        header('Location: ' . url('payroll') . $query);
        exit;
    }
}
