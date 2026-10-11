<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ApplicantController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\ModuleController;
use App\Controllers\PayrollController;
use App\Controllers\RecruitmentController;
use App\Controllers\WorkforceController;
use App\Controllers\ReportsController;
use App\Controllers\AccountController;
use App\Controllers\PersonnelController;
use App\Core\Csrf;
use App\Core\Router;

session_name('htmedia_hrm');
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

define('APP_ROOT', dirname(__DIR__));
$publicPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('APP_BASE_URL', rtrim($publicPath, '/'));

require APP_ROOT . '/app/core/Database.php';
require APP_ROOT . '/app/core/Csrf.php';
require APP_ROOT . '/app/core/Auth.php';
require APP_ROOT . '/app/core/Scope.php';
require APP_ROOT . '/app/core/Workflow.php';
require APP_ROOT . '/app/core/Permission.php';
require APP_ROOT . '/app/core/Controller.php';
require APP_ROOT . '/app/core/Router.php';
require APP_ROOT . '/app/models/Account.php';
require APP_ROOT . '/app/models/AccountAdmin.php';
require APP_ROOT . '/app/models/AuditLog.php';
require APP_ROOT . '/app/models/Reports.php';
require APP_ROOT . '/app/models/Dashboard.php';
require APP_ROOT . '/app/models/Recruitment.php';
require APP_ROOT . '/app/models/Applicant.php';
require APP_ROOT . '/app/models/Employee.php';
require APP_ROOT . '/app/core/RecordId.php';
require APP_ROOT . '/app/core/Upload.php';
require APP_ROOT . '/app/core/ProbationPolicy.php';
require APP_ROOT . '/app/controllers/AuthController.php';
require APP_ROOT . '/app/controllers/RecruitmentController.php';
require APP_ROOT . '/app/controllers/ApplicantController.php';
require APP_ROOT . '/app/controllers/EmployeeController.php';
require APP_ROOT . '/app/controllers/DashboardController.php';
require APP_ROOT . '/app/controllers/ModuleController.php';
require APP_ROOT . '/app/models/WorkforceOperations.php';
require APP_ROOT . '/app/models/Payroll.php';
require APP_ROOT . '/app/models/PersonnelOperations.php';
require APP_ROOT . '/app/controllers/WorkforceController.php';
require APP_ROOT . '/app/controllers/PayrollController.php';
require APP_ROOT . '/app/controllers/PersonnelController.php';
require APP_ROOT . '/app/controllers/ReportsController.php';
require APP_ROOT . '/app/controllers/AccountController.php';
require APP_ROOT . '/app/core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::isValid($_POST['_csrf'] ?? null)) {
    http_response_code(403);
    render('errors/403', ['title' => 'Yêu cầu không hợp lệ']);
    exit;
}

$router = new Router();
$authController = new AuthController();
$router->get('login', [$authController, 'login']);
$router->post('login', [$authController, 'authenticate']);
$router->get('forgot-password', [$authController, 'forgotPassword']);
$router->post('forgot-password', [$authController, 'sendResetLink']);
$router->get('reset-password', [$authController, 'resetPassword']);
$router->post('reset-password', [$authController, 'updatePassword']);
$router->post('logout', [$authController, 'logout']);
$router->get('dashboard', [new DashboardController(), 'index']);

$recruitmentController = new RecruitmentController();
$router->get('recruitment', [$recruitmentController, 'index']);
$router->post('recruitment-create', [$recruitmentController, 'create']);
$router->post('recruitment-approve', [$recruitmentController, 'approve']);
$router->post('recruitment-reject', [$recruitmentController, 'reject']);
$router->post('recruitment-publish', [$recruitmentController, 'publish']);

$applicantController = new ApplicantController();
$router->get('applicants', [$applicantController, 'index']);
$router->post('applicant-create', [$applicantController, 'create']);
$router->post('applicant-stage', [$applicantController, 'updateStage']);
$router->get('candidate-onboard', [$applicantController, 'onboardForm']);
$router->post('applicant-onboard', [$applicantController, 'onboard']);
$router->get('candidate-document', [$applicantController, 'downloadCv']);

$employeeController = new EmployeeController();
$router->get('employees', [$employeeController, 'index']);
$router->get('employee-profile', [$employeeController, 'profile']);
$router->post('employee-profile-update', [$employeeController, 'updateProfile']);
$router->post('employee-contract-create', [$employeeController, 'createContract']);
$router->post('employee-document-upload', [$employeeController, 'uploadDocument']);
$router->get('employee-document', [$employeeController, 'downloadDocument']);

$workforceController = new WorkforceController();
$router->get('probation', [$workforceController, 'probation']);
$router->post('probation-evaluate', [$workforceController, 'evaluateProbation']);
$router->get('attendance', [$workforceController, 'attendance']);
$router->post('attendance-check-in', [$workforceController, 'checkIn']);
$router->post('attendance-check-out', [$workforceController, 'checkOut']);
$router->post('attendance-confirm', [$workforceController, 'confirmAttendance']);
$router->post('attendance-correction', [$workforceController, 'requestAttendanceCorrection']);
$router->post('attendance-correction-decide', [$workforceController, 'decideAttendanceCorrection']);
$router->get('leave', [$workforceController, 'leave']);
$router->post('leave-request', [$workforceController, 'requestLeave']);
$router->post('leave-decide', [$workforceController, 'decideLeave']);
$router->get('overtime', [$workforceController, 'overtime']);
$router->post('overtime-request', [$workforceController, 'requestOvertime']);
$router->post('overtime-decide', [$workforceController, 'decideOvertime']);

$payrollController = new PayrollController();
$router->get('payroll', [$payrollController, 'index']);
$router->post('payroll-calculate', [$payrollController, 'calculate']);
$router->post('payroll-approve', [$payrollController, 'approve']);
$router->get('payroll-export', [$payrollController, 'exportCsv']);
$router->get('payslip', [$payrollController, 'payslip']);

$personnelController = new PersonnelController();
$router->get('reviews', [$personnelController, 'reviews']);
$router->post('review-create', [$personnelController, 'createReview']);
$router->get('salary_changes', [$personnelController, 'salaryChanges']);
$router->post('salary-propose', [$personnelController, 'proposeSalary']);
$router->post('salary-approve', [$personnelController, 'approveSalary']);
$router->get('transfers', [$personnelController, 'transfers']);
$router->post('transfer-create', [$personnelController, 'createTransfer']);
$router->post('transfer-decide', [$personnelController, 'decideTransfer']);
$router->get('resignations', [$personnelController, 'resignations']);
$router->post('resignation-request', [$personnelController, 'requestResignation']);
$router->post('resignation-step', [$personnelController, 'advanceResignation']);

$reportsController = new ReportsController();
$router->get('reports', [$reportsController, 'index']);
$router->get('reports-export', [$reportsController, 'exportCsv']);
$accountController = new AccountController();
$router->get('accounts', [$accountController, 'index']);
$router->post('account-create', [$accountController, 'create']);
$router->post('account-update', [$accountController, 'update']);

$route = (string) ($_GET['route'] ?? $_GET['r'] ?? (isset($_SESSION['user']) ? 'dashboard' : 'login'));
$requestMethod = (string) $_SERVER['REQUEST_METHOD'];
$auditActor = $_SESSION['user']['MaNV'] ?? null;
if ($requestMethod === 'POST' || in_array($route, ['reports-export'], true)) {
    register_shutdown_function(static function () use ($auditActor, $route, $requestMethod): void {
        try {
            (new \App\Models\AuditLog(\App\Core\Database::connection()))->record(
                is_string($auditActor) ? $auditActor : null,
                'HTTP ' . $requestMethod,
                'route=' . preg_replace('/[^a-zA-Z0-9_-]/', '', $route)
                    . '; status=' . http_response_code()
            );
        } catch (Throwable $exception) {
            error_log('HRM activity log write failed: ' . $exception->getMessage());
        }
    });
}
try {
    $router->dispatch($route, $requestMethod);
} catch (PDOException $exception) {
    error_log('HRM database error: ' . $exception->getMessage());
    http_response_code(503);
    render('errors/503', ['title' => 'Không thể kết nối cơ sở dữ liệu']);
}
