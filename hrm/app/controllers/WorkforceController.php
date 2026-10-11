<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\ProbationPolicy;
use App\Core\Scope;
use App\Models\Employee;
use App\Models\WorkforceOperations;
use DomainException;

final class WorkforceController extends Controller
{
    public function probation(): void
    {
        $this->authorizePersonalOrManage('probation');
        $employeeId = $this->personalScope('probation');
        $rows = (new WorkforceOperations(Database::connection()))->probationList($employeeId);
        foreach ($rows as &$row) {
            $row['ThoiHanToiDa'] = ProbationPolicy::maximumDays(
                (string) ($row['TrinhDo'] ?? ''),
                (string) $row['TenCV']
            );
            $row['SoNgayThuViec'] = ProbationPolicy::durationDays(
                (string) $row['NgayBatDau'],
                (string) $row['NgayKetThuc']
            );
        }
        unset($row);
        $this->render('probation/index', [
            'title' => 'Thử việc',
            'rows' => $rows,
            'canManage' => Auth::can('probation', 'manage'),
            'personal' => $employeeId !== null,
        ]);
    }

    public function evaluateProbation(): void
    {
        $this->authorize('probation', 'manage');
        try {
            (new WorkforceOperations(Database::connection()))->evaluateProbation(
                trim((string) ($_POST['id'] ?? '')),
                trim((string) ($_POST['result'] ?? '')),
                trim((string) ($_POST['note'] ?? '')),
                Auth::user()['MaNV']
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('probation');
        }

        flash('success', 'Đã lưu đánh giá thử việc' .
            (($_POST['result'] ?? '') === 'Đạt' ? ' và chuyển nhân viên sang trạng thái chính thức.' : '.'));
        $this->redirect('probation');
    }

    public function attendance(): void
    {
        $this->authorizePersonalOrManage('attendance');
        $user = Auth::user();
        $month = trim((string) ($_GET['month'] ?? date('Y-m')));
        $employeeId = $this->personalScope('attendance') ?? trim((string) ($_GET['employee_id'] ?? $user['MaNV']));
        Scope::requireEmployee(Database::connection(), $employeeId);
        try {
            $data = (new WorkforceOperations(Database::connection()))->attendanceData($employeeId, $month);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('attendance');
        }
        $employees = Auth::can('attendance', 'manage')
            ? (new Employee(Database::connection()))->search('', '', '', 1, 1000)['rows']
            : [];
        $this->render('attendance/index', [
            'title' => 'Chấm công',
            'attendance' => $data,
            'month' => $month,
            'employeeId' => $employeeId,
            'employees' => $employees,
            'canManage' => Auth::can('attendance', 'manage'),
            'canPunch' => Auth::can('attendance', 'personal_manage'),
            'canRequestCorrection' => Auth::can('attendance', 'personal_request'),
            'canApproveCorrection' => Auth::can('attendance', 'approve'),
            'corrections' => Auth::can('attendance', 'approve')
                ? (new WorkforceOperations(Database::connection()))->attendanceCorrections() : [],
        ]);
    }

    public function requestAttendanceCorrection(): void
    {
        $this->authorize('attendance', 'personal_request');
        try {
            (new WorkforceOperations(Database::connection()))->createAttendanceCorrection(
                trim((string) ($_POST['id'] ?? '')),
                (string) Auth::user()['MaNV'],
                trim((string) ($_POST['check_in'] ?? '')) ?: null,
                trim((string) ($_POST['check_out'] ?? '')) ?: null,
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('attendance');
        }
        flash('success', 'Đã gửi yêu cầu điều chỉnh công cho trưởng bộ phận.');
        $this->redirect('attendance');
    }

    public function decideAttendanceCorrection(): void
    {
        $this->authorize('attendance', 'approve');
        try {
            (new WorkforceOperations(Database::connection()))->decideAttendanceCorrection(
                trim((string) ($_POST['id'] ?? '')),
                trim((string) ($_POST['decision'] ?? '')),
                (string) Auth::user()['MaNV'],
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('attendance');
        }
        flash('success', 'Đã xử lý yêu cầu điều chỉnh công.');
        $this->redirect('attendance');
    }

    public function checkIn(): void
    {
        $this->authorize('attendance', 'personal_manage');
        try {
            (new WorkforceOperations(Database::connection()))->checkIn(Auth::user()['MaNV']);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('attendance');
        }
        flash('success', 'Đã ghi nhận giờ chấm vào.');
        $this->redirect('attendance');
    }

    public function checkOut(): void
    {
        $this->authorize('attendance', 'personal_manage');
        try {
            (new WorkforceOperations(Database::connection()))->checkOut(Auth::user()['MaNV']);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('attendance');
        }
        flash('success', 'Đã ghi nhận giờ chấm ra.');
        $this->redirect('attendance');
    }

    public function confirmAttendance(): void
    {
        $this->authorize('attendance', 'manage');
        try {
            $count = (new WorkforceOperations(Database::connection()))
                ->confirmAttendanceMonth(trim((string) ($_POST['month'] ?? '')));
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('attendance');
        }
        flash('success', "Đã xác nhận cuối kỳ cho {$count} bản ghi chấm công.");
        $this->redirect('attendance');
    }

    public function leave(): void
    {
        $this->authorizePersonalOrManage('leave');
        $user = Auth::user();
        $model = new WorkforceOperations(Database::connection());
        $employeeId = $this->personalScope('leave');
        $balance = $model->leaveBalance($user['MaNV'], (int) date('Y'));
        $this->render('leave/index', [
            'title' => 'Nghỉ phép',
            'rows' => $model->leaveRequests($employeeId),
            'balance' => $balance,
            'canManage' => Auth::can('leave', 'manage'),
            'canApprove' => Auth::can('leave', 'approve'),
            'canRequest' => Auth::can('leave', 'personal_request') || Auth::can('leave', 'manage'),
        ]);
    }

    public function requestLeave(): void
    {
        $this->authorizePersonalOrManage('leave');
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        if (!$this->validDate($startDate) || !$this->validDate($endDate)) {
            flash('error', 'Vui lòng nhập ngày bắt đầu và kết thúc hợp lệ.');
            $this->redirect('leave');
        }
        try {
            (new WorkforceOperations(Database::connection()))->createLeaveRequest(
                Auth::user()['MaNV'],
                $startDate,
                $endDate,
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('leave');
        }
        flash('success', 'Đã gửi đề nghị nghỉ phép; ngày chờ duyệt được tạm tính vào số ngày còn lại.');
        $this->redirect('leave');
    }

    public function decideLeave(): void
    {
        $this->authorize('leave', 'approve');
        try {
            (new WorkforceOperations(Database::connection()))->decideLeave(
                trim((string) ($_POST['id'] ?? '')),
                trim((string) ($_POST['decision'] ?? '')),
                Auth::user()['MaNV'],
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('leave');
        }
        flash('success', 'Đã cập nhật quyết định nghỉ phép.');
        $this->redirect('leave');
    }

    public function overtime(): void
    {
        $this->authorizePersonalOrManage('overtime');
        $employeeId = $this->personalScope('overtime');
        $this->render('overtime/index', [
            'title' => 'Tăng ca',
            'rows' => (new WorkforceOperations(Database::connection()))->overtimeRequests($employeeId),
            'canManage' => Auth::can('overtime', 'manage'),
            'canApprove' => Auth::can('overtime', 'approve'),
            'canRequest' => Auth::can('overtime', 'personal_request') || Auth::can('overtime', 'manage'),
        ]);
    }

    public function requestOvertime(): void
    {
        $this->authorizePersonalOrManage('overtime');
        $hours = filter_var($_POST['hours'] ?? null, FILTER_VALIDATE_FLOAT);
        $date = trim((string) ($_POST['date'] ?? ''));
        if ($hours === false || !$this->validDate($date)) {
            flash('error', 'Vui lòng kiểm tra ngày và số giờ tăng ca.');
            $this->redirect('overtime');
        }
        try {
            $totals = (new WorkforceOperations(Database::connection()))->createOvertimeRequest(
                Auth::user()['MaNV'],
                $date,
                (float) $hours
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('overtime');
        }
        if ($totals['warnings'] !== []) {
            flash('warning', 'Đã gửi đề nghị. ' . implode(' ', $totals['warnings']));
        } else {
            flash('success', 'Đã gửi đề nghị tăng ca.');
        }
        $this->redirect('overtime');
    }

    public function decideOvertime(): void
    {
        $this->authorize('overtime', 'approve');
        try {
            (new WorkforceOperations(Database::connection()))->decideOvertime(
                trim((string) ($_POST['id'] ?? '')),
                trim((string) ($_POST['decision'] ?? '')),
                Auth::user()['MaNV'],
                (float) ($_POST['multiplier'] ?? 1.5),
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('overtime');
        }
        flash('success', 'Đã cập nhật quyết định tăng ca.');
        $this->redirect('overtime');
    }

    private function authorizePersonalOrManage(string $feature): void
    {
        if (Auth::can($feature, 'manage')) {
            $this->authorize($feature, 'manage');
            return;
        }
        if (Auth::can($feature, 'view')) {
            $this->authorize($feature, 'view');
            return;
        }
        if (Auth::can($feature, 'personal_manage')) {
            $this->authorize($feature, 'personal_manage');
            return;
        }
        if (Auth::can($feature, 'personal_request')) {
            $this->authorize($feature, 'personal_request');
            return;
        }
        $this->authorize($feature, 'personal');
    }

    private function personalScope(string $feature): ?string
    {
        if (Auth::can($feature, 'manage')
            || (Auth::can($feature, 'view')
                && !Auth::can($feature, 'personal')
                && !Auth::can($feature, 'personal_manage')
                && !Auth::can($feature, 'personal_request'))) {
            return null;
        }

        return Auth::user()['MaNV'];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false && $date->format('Y-m-d') === $value && $date->format('Y') >= '2000'
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
