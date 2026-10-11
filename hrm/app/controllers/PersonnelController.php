<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Employee;
use App\Models\PersonnelOperations;
use DomainException;

final class PersonnelController extends Controller
{
    public function reviews(): void
    {
        $this->authorizeView('reviews');
        $employeeId = Auth::can('reviews', 'personal') ? Auth::user()['MaNV'] : null;
        $this->render('reviews/index', [
            'title' => 'Đánh giá',
            'rows' => (new PersonnelOperations(Database::connection()))->reviews($employeeId),
            'employees' => Auth::can('reviews', 'manage') ? (new Employee(Database::connection()))->search('', '', '', 1, 1000)['rows'] : [],
            'canManage' => Auth::can('reviews', 'manage'),
            'personal' => $employeeId !== null,
        ]);
    }

    public function createReview(): void
    {
        $this->authorize('reviews', 'manage');
        try {
            (new PersonnelOperations(Database::connection()))->createReview(
                trim((string) ($_POST['employee_id'] ?? '')),
                trim((string) ($_POST['date'] ?? '')),
                trim((string) ($_POST['outcome'] ?? '')),
                trim((string) ($_POST['rating'] ?? '')),
                trim((string) ($_POST['note'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('reviews');
        }
        flash('success', 'Đã lưu đánh giá nhân viên.');
        $this->redirect('reviews');
    }

    public function salaryChanges(): void
    {
        $this->authorizeView('salary_changes');
        $model = new PersonnelOperations(Database::connection());
        $this->render('salary_changes/index', [
            'title' => 'Tăng lương',
            'rows' => $model->salaryChanges(),
            'employees' => Auth::can('salary_changes', 'manage') || Auth::can('salary_changes', 'propose')
                ? $model->employees() : [],
            'canPropose' => Auth::can('salary_changes', 'manage') || Auth::can('salary_changes', 'propose'),
            'canApprove' => Auth::can('salary_changes', 'approve'),
        ]);
    }

    public function proposeSalary(): void
    {
        if (Auth::can('salary_changes', 'manage')) {
            $this->authorize('salary_changes', 'manage');
        } else {
            $this->authorize('salary_changes', 'propose');
        }
        $salary = filter_var($_POST['salary'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($salary === false) {
            flash('error', 'Mức lương mới không hợp lệ.');
            $this->redirect('salary_changes');
        }
        try {
            (new PersonnelOperations(Database::connection()))->proposeSalary(
                trim((string) ($_POST['employee_id'] ?? '')),
                (float) $salary,
                trim((string) ($_POST['effective_date'] ?? '')),
                trim((string) ($_POST['reason'] ?? '')),
                Auth::user()['MaNV']
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('salary_changes');
        }
        flash('success', 'Đã lưu đề xuất tăng lương, chờ Giám đốc phê duyệt.');
        $this->redirect('salary_changes');
    }

    public function approveSalary(): void
    {
        $this->authorize('salary_changes', 'approve');
        try {
            (new PersonnelOperations(Database::connection()))->approveSalary(
                trim((string) ($_POST['id'] ?? '')),
                trim((string) ($_POST['decision'] ?? '')),
                Auth::user()['MaNV'],
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('salary_changes');
        }
        flash('success', 'Đã xử lý đề xuất; lịch sử hợp đồng và mức lương được lưu.');
        $this->redirect('salary_changes');
    }

    public function transfers(): void
    {
        $this->authorizeView('transfers');
        $model = new PersonnelOperations(Database::connection());
        $employeeModel = new Employee(Database::connection());
        $this->render('transfers/index', [
            'title' => 'Điều chuyển',
            'rows' => $model->transfers(),
            'employees' => $model->employees(),
            'departments' => $employeeModel->departments(),
            'positions' => $employeeModel->positions(),
            'canPropose' => Auth::can('transfers', 'manage') || Auth::can('transfers', 'propose'),
            'canApprove' => Auth::can('transfers', 'approve'),
        ]);
    }

    public function createTransfer(): void
    {
        if (Auth::can('transfers', 'manage')) {
            $this->authorize('transfers', 'manage');
        } else {
            $this->authorize('transfers', 'propose');
        }
        try {
            (new PersonnelOperations(Database::connection()))->createTransfer(
                trim((string) ($_POST['employee_id'] ?? '')),
                trim((string) ($_POST['department'] ?? '')),
                trim((string) ($_POST['position'] ?? '')),
                trim((string) ($_POST['date'] ?? '')),
                trim((string) ($_POST['reason'] ?? '')),
                Auth::user()['MaNV']
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('transfers');
        }
        flash('success', 'Đã gửi đề xuất điều chuyển chờ Giám đốc phê duyệt.');
        $this->redirect('transfers');
    }

    public function decideTransfer(): void
    {
        $this->authorize('transfers', 'approve');
        try {
            (new PersonnelOperations(Database::connection()))->decideTransfer(
                trim((string) ($_POST['id'] ?? '')),
                trim((string) ($_POST['decision'] ?? '')),
                Auth::user()['MaNV'],
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('transfers');
        }
        flash('success', 'Đã xử lý đề xuất điều chuyển.');
        $this->redirect('transfers');
    }

    public function resignations(): void
    {
        $this->authorizeView('resignations');
        $employeeId = $this->personalResignationScope();
        $model = new PersonnelOperations(Database::connection());
        $this->render('resignations/index', [
            'title' => 'Nghỉ việc',
            'rows' => $model->resignations($employeeId),
            'employees' => $employeeId === null ? $model->employees() : [],
            'canRequest' => Auth::can('resignations', 'personal_request')
                || Auth::can('resignations', 'propose') || Auth::can('resignations', 'manage'),
            'canReceive' => Auth::can('resignations', 'manage'),
            'canApprove' => Auth::can('resignations', 'approve'),
            'canManagerApprove' => Auth::hasRole('quanly'),
            'canFinalize' => Auth::can('resignations', 'finalize_payroll'),
        ]);
    }

    public function requestResignation(): void
    {
        if (Auth::can('resignations', 'personal_request')) {
            $this->authorize('resignations', 'personal_request');
            $employeeId = Auth::user()['MaNV'];
        } elseif (Auth::can('resignations', 'manage')) {
            $this->authorize('resignations', 'manage');
            $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        } else {
            $this->authorize('resignations', 'propose');
            $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        }
        try {
            (new PersonnelOperations(Database::connection()))->createResignation(
                $employeeId,
                trim((string) ($_POST['departure_date'] ?? '')),
                trim((string) ($_POST['reason'] ?? '')),
                (string) Auth::user()['MaNV']
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('resignations');
        }
        flash('success', 'Đã gửi đề nghị nghỉ việc; hồ sơ đang ở bước tiếp nhận.');
        $this->redirect('resignations');
    }

    public function advanceResignation(): void
    {
        $action = trim((string) ($_POST['action'] ?? ''));
        $permission = match ($action) {
            'tiếp nhận', 'bàn giao', 'lưu hồ sơ' => 'manage',
            'phê duyệt', 'từ chối' => 'approve',
            'chốt lương' => 'finalize_payroll',
            default => 'manage',
        };
        if (!Auth::can('resignations', $permission)) {
            $this->authorize('resignations', $permission);
        }
        $payrollMonth = trim((string) ($_POST['payroll_month'] ?? '')) ?: null;
        try {
            (new PersonnelOperations(Database::connection()))->advanceResignation(
                trim((string) ($_POST['id'] ?? '')),
                $action,
                Auth::user()['MaNV'],
                Auth::roles(),
                $payrollMonth,
                trim((string) ($_POST['reason'] ?? ''))
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('resignations');
        }
        flash('success', 'Đã cập nhật quy trình nghỉ việc.');
        $this->redirect('resignations');
    }

    private function authorizeView(string $feature): void
    {
        if (Auth::can($feature, 'manage')) {
            $this->authorize($feature, 'manage');
        } elseif (Auth::can($feature, 'view')) {
            $this->authorize($feature, 'view');
        } elseif (Auth::can($feature, 'approve')) {
            $this->authorize($feature, 'approve');
        } elseif (Auth::can($feature, 'propose')) {
            $this->authorize($feature, 'propose');
        } elseif (Auth::can($feature, 'finalize_payroll')) {
            $this->authorize($feature, 'finalize_payroll');
        } elseif (Auth::can($feature, 'personal_request')) {
            $this->authorize($feature, 'personal_request');
        } else {
            $this->authorize($feature, 'personal');
        }
    }

    private function personalResignationScope(): ?string
    {
        if (Auth::can('resignations', 'manage') || Auth::can('resignations', 'approve')
            || Auth::can('resignations', 'propose') || Auth::can('resignations', 'finalize_payroll')) {
            return null;
        }

        return Auth::user()['MaNV'];
    }
}
