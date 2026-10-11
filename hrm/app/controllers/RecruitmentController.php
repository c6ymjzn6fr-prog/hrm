<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\RecordId;
use App\Models\Recruitment;
use App\Models\Employee;
use DomainException;

final class RecruitmentController extends Controller
{
    public function index(): void
    {
        $this->authorize('recruitment', 'view');
        $employees = new Employee(Database::connection());
        $this->render('recruitment/index', [
            'title' => 'Tuyển dụng',
            'jobs' => (new Recruitment(Database::connection()))->all(),
            'canManage' => Auth::can('recruitment', 'manage'),
            'canPropose' => Auth::can('recruitment', 'propose'),
            'canApprove' => Auth::can('recruitment', 'approve'),
            'departments' => $employees->departments(),
        ]);
    }

    public function create(): void
    {
        if (Auth::can('recruitment', 'manage')) {
            $this->authorize('recruitment', 'manage');
        } else {
            $this->authorize('recruitment', 'propose');
        }

        $position = trim((string) ($_POST['position'] ?? ''));
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
        $requirements = trim((string) ($_POST['requirements'] ?? ''));
        $departmentId = trim((string) ($_POST['department'] ?? ''));
        $validDepartments = array_column((new Employee(Database::connection()))->departments(), 'MaPB');
        if ($position === '' || mb_strlen($position, 'UTF-8') > 150
            || $quantity === false || $quantity < 1 || $quantity > 1000
            || mb_strlen($requirements, 'UTF-8') > 5000 || !in_array($departmentId, $validDepartments, true)) {
            flash('error', 'Vui lòng nhập vị trí, phòng ban, số lượng (1–1000) và yêu cầu hợp lệ.');
            $this->redirect('recruitment');
        }

        (new Recruitment(Database::connection()))->create(
            RecordId::make('TD'),
            $position,
            $quantity,
            $departmentId,
            $requirements,
            Auth::user()['MaNV']
        );
        flash('success', 'Đã gửi đề xuất tuyển dụng để Giám đốc phê duyệt.');
        $this->redirect('recruitment');
    }

    public function approve(): void
    {
        $this->authorize('recruitment', 'approve');
        $jobId = trim((string) ($_POST['id'] ?? ''));
        try {
            $updated = (new Recruitment(Database::connection()))->approve($jobId, Auth::user()['MaNV']);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('recruitment');
        }
        flash($updated ? 'success' : 'error', $updated
            ? 'Đề xuất tuyển dụng đã được phê duyệt.'
            : 'Đề xuất không còn ở trạng thái chờ phê duyệt.');
        $this->redirect('recruitment');
    }

    public function reject(): void
    {
        $this->authorize('recruitment', 'approve');
        $jobId = trim((string) ($_POST['id'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        try {
            $updated = (new Recruitment(Database::connection()))->reject(
                $jobId,
                Auth::user()['MaNV'],
                $reason
            );
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('recruitment');
        }
        flash($updated ? 'success' : 'error', $updated
            ? 'Đề xuất tuyển dụng đã bị từ chối.'
            : 'Đề xuất không còn ở trạng thái chờ phê duyệt.');
        $this->redirect('recruitment');
    }

    public function publish(): void
    {
        $this->authorize('recruitment', 'manage');
        $jobId = trim((string) ($_POST['id'] ?? ''));
        $updated = (new Recruitment(Database::connection()))->publish($jobId);
        flash($updated ? 'success' : 'error', $updated
            ? 'Tin tuyển dụng đã được đăng.'
            : 'Chỉ có thể đăng tin sau khi đề xuất được phê duyệt.');
        $this->redirect('recruitment');
    }
}
