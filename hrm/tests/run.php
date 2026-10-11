<?php
declare(strict_types=1);

$testDatabase = getenv('HRM_TEST_DB') ?: '';
if (preg_match('/^hrm_test_[a-z0-9_]+$/', $testDatabase) !== 1
    || (getenv('HRM_DB_NAME') ?: '') !== $testDatabase) {
    fwrite(STDERR, "Set HRM_TEST_DB to an isolated database named hrm_test_*; production DB is refused.\n");
    exit(2);
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/core/Database.php';
require APP_ROOT . '/app/core/RecordId.php';
require APP_ROOT . '/app/core/Auth.php';
require APP_ROOT . '/app/core/Scope.php';
require APP_ROOT . '/app/core/Workflow.php';
require APP_ROOT . '/app/core/ProbationPolicy.php';
require APP_ROOT . '/app/models/Account.php';
require APP_ROOT . '/app/models/AccountAdmin.php';
require APP_ROOT . '/app/models/AuditLog.php';
require APP_ROOT . '/app/models/Dashboard.php';
require APP_ROOT . '/app/models/Employee.php';
require APP_ROOT . '/app/models/Reports.php';
require APP_ROOT . '/app/models/Payroll.php';
require APP_ROOT . '/app/models/PersonnelOperations.php';
require APP_ROOT . '/app/models/WorkforceOperations.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Scope;
use App\Core\Workflow;
use App\Models\Account;
use App\Models\AccountAdmin;
use App\Models\AuditLog;
use App\Models\Dashboard;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PersonnelOperations;
use App\Models\Reports;
use App\Models\WorkforceOperations;

$db = Database::connection();
$currentDatabase = $db->query('SELECT DATABASE()')->fetchColumn();
if ($currentDatabase !== $testDatabase) {
    fwrite(STDERR, "Connected database does not match HRM_TEST_DB; refusing to run tests.\n");
    exit(2);
}
$cases = [];
$check = static function (bool $condition, string $message = 'assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$cases[] = ['Demo password verifies and bad password rejects', static function () use ($db, $check): void {
    $query = $db->prepare('SELECT MatKhau FROM TAIKHOAN WHERE TenDangNhap = :username');
    $query->execute(['username' => 'admin']);
    $hash = $query->fetchColumn();
    $check(is_string($hash) && password_verify('HtMedia@2026', $hash), 'Demo password hash invalid');
    $check(!password_verify('wrong-password', (string) $hash), 'Wrong password accepted');
}];
$cases[] = ['Failed login counter reaches lock threshold', static function () use ($db, $check): void {
    $db->beginTransaction();
    try {
        $account = new Account($db);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $account->recordFailure('TK001', $attempt, $attempt === 5);
        }
        $query = $db->prepare('SELECT SoLanSai, KhoaDen > NOW() FROM TAIKHOAN WHERE MaTK = :id');
        $query->execute(['id' => 'TK001']);
        $state = $query->fetch(PDO::FETCH_NUM);
        $check((int) $state[0] === 5 && (int) $state[1] === 1, 'Account was not locked after five attempts');
    } finally {
        $db->rollBack();
    }
}];
$cases[] = ['Account can be created and assigned one of six roles', static function () use ($db, $check): void {
    $db->beginTransaction();
    try {
        $admin = new AccountAdmin($db);
        $check(AccountAdmin::roles() === ['admin', 'nhansu', 'quanly', 'ketoan', 'nhanvien', 'giamdoc']);
        $check($admin->create('TKTEST001', 'NV007', 'testaccount', password_hash('Temporary@12345', PASSWORD_DEFAULT), 'nhansu'));
        $check(!$admin->create('TKTEST002', 'NV008', 'testaccount', password_hash('Temporary@12345', PASSWORD_DEFAULT), 'nhansu'));
        $check($admin->update('TKTEST001', 'quanly', 'Hoạt động'));
        $role = $db->query("SELECT VaiTro FROM TAIKHOAN WHERE MaTK = 'TKTEST001'")->fetchColumn();
        $check($role === 'quanly', 'Account role was not updated');
    } finally {
        $db->rollBack();
    }
}];
$cases[] = ['Activity log table and write operation work transactionally', static function () use ($db, $check): void {
    $db->beginTransaction();
    try {
        $log = new AuditLog($db);
        $log->record('NV001', 'TEST', 'test event');
        $check(($log->recent(1)[0]['HanhDong'] ?? '') === 'TEST', 'Activity log row not readable');
    } finally {
        $db->rollBack();
    }
}];
$cases[] = ['Administrator can manage accounts', static function () use ($check): void {
    $_SESSION['user'] = ['MaTK' => 'TK001', 'MaNV' => 'NV001', 'HoTen' => 'Admin', 'VaiTro' => 'admin', 'ma_nv' => 'NV001', 'ma_pb' => 'PB001', 'vai_tro' => ['admin']];
    $check(Auth::can('accounts', 'manage'));
}];
$cases[] = ['Non-administrator cannot manage accounts', static function () use ($check): void {
    $_SESSION['user'] = ['MaTK' => 'TK002', 'MaNV' => 'NV002', 'HoTen' => 'HR', 'VaiTro' => 'nhansu', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002', 'vai_tro' => ['nhansu']];
    $check(!Auth::can('accounts', 'manage'));
}];
$cases[] = ['Manager may propose but not approve recruitment', static function () use ($check): void {
    $_SESSION['user'] = ['MaTK' => 'TK003', 'MaNV' => 'NV003', 'HoTen' => 'Manager', 'VaiTro' => 'quanly', 'ma_nv' => 'NV003', 'ma_pb' => 'PB003', 'vai_tro' => ['quanly']];
    $check(Auth::can('recruitment', 'propose') && !Auth::can('recruitment', 'approve'));
}];
$cases[] = ['Director may approve but is not granted recruitment management', static function () use ($check): void {
    $_SESSION['user'] = ['MaTK' => 'TK006', 'MaNV' => 'NV006', 'HoTen' => 'Director', 'VaiTro' => 'giamdoc', 'ma_nv' => 'NV006', 'ma_pb' => 'PB001', 'vai_tro' => ['giamdoc']];
    $check(Auth::can('recruitment', 'approve') && !Auth::can('recruitment', 'manage'));
}];
$cases[] = ['Employee attendance permission stays personal', static function () use ($check): void {
    $_SESSION['user'] = ['MaTK' => 'TK005', 'MaNV' => 'NV005', 'HoTen' => 'Employee', 'VaiTro' => 'nhanvien', 'ma_nv' => 'NV005', 'ma_pb' => 'PB003', 'vai_tro' => ['nhanvien']];
    $check(Auth::can('attendance', 'personal_manage') && !Auth::can('attendance', 'manage'));
}];
$cases[] = ['Department head receives an effective manager role', static function () use ($db, $check): void {
    $account = $db->prepare(
        'SELECT a.VaiTro, a.MaNV, nv.MaPB, pb.MaTruongBP
         FROM TAIKHOAN a INNER JOIN NHANVIEN nv ON nv.MaNV = a.MaNV
         INNER JOIN PHONGBAN pb ON pb.MaPB = nv.MaPB WHERE a.TenDangNhap = :username'
    );
    $account->execute(['username' => 'quanly']);
    $row = $account->fetch();
    $_SESSION['user'] = [
        'MaNV' => $row['MaNV'], 'ma_nv' => $row['MaNV'], 'ma_pb' => $row['MaPB'],
        'VaiTro' => $row['VaiTro'], 'vai_tro' => [$row['VaiTro'], 'quanly'],
    ];
    $check($row['MaTruongBP'] === $row['MaNV'] && Auth::hasRole('quanly'));
}];
$cases[] = ['Manager scope contains own department and self only', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV003', 'ma_nv' => 'NV003', 'ma_pb' => 'PB003', 'vai_tro' => ['quanly']];
    $check(Scope::allowsEmployee($db, 'NV025'));
    $check(Scope::allowsEmployee($db, 'NV003'));
    $check(!Scope::allowsEmployee($db, 'NV002'));
}];
$cases[] = ['Personnel employee picker query respects scope and runs successfully', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV003', 'ma_nv' => 'NV003', 'ma_pb' => 'PB003', 'vai_tro' => ['quanly']];
    $employees = (new PersonnelOperations($db))->employees();
    $check($employees !== [], 'Personnel employee picker returned no employees');
    foreach ($employees as $employee) {
        $check($employee['MaPB'] === 'PB003', 'Personnel employee picker returned an employee outside the manager department');
    }
}];
$cases[] = ['Employee profile exposes the four dossier groups and related history', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV002', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002', 'vai_tro' => ['nhansu']];
    $profile = (new Employee($db))->profile('NV001');
    $check(is_array($profile), 'Employee profile did not load');
    foreach (['NoiSinh', 'NgayCapCCCD', 'ChuyenNganh', 'TruongTotNghiep', 'MaUV', 'MaSoThue', 'SoBHXH'] as $field) {
        $check(array_key_exists($field, $profile), 'Employee profile is missing field ' . $field);
    }
    $check(array_keys($profile['TaiLieuTheoNhom']) === ['identity', 'education', 'work', 'termination']);
    $check(isset($profile['DanhGia'], $profile['NghiViec']), 'Employee profile history groups were not loaded');
}];
$cases[] = ['Administrator has no employee data scope', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV001', 'ma_nv' => 'NV001', 'ma_pb' => 'PB001', 'vai_tro' => ['admin']];
    $check(!Scope::allowsEmployee($db, 'NV001'));
}];
$cases[] = ['Approval workflow is loaded from configuration', static function () use ($check): void {
    $check(Workflow::steps('nghi_phep') === ['truong_bp']);
    $check(Workflow::steps('nghi_viec') === ['truong_bp', 'giamdoc']);
}];
$cases[] = ['All six role dashboards load their required data', static function () use ($db, $check): void {
    $users = [
        'giamdoc' => ['MaNV' => 'NV006', 'ma_nv' => 'NV006', 'ma_pb' => 'PB001'],
        'nhansu' => ['MaNV' => 'NV002', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002'],
        'ketoan' => ['MaNV' => 'NV004', 'ma_nv' => 'NV004', 'ma_pb' => 'PB006'],
        'quanly' => ['MaNV' => 'NV003', 'ma_nv' => 'NV003', 'ma_pb' => 'PB003'],
        'nhanvien' => ['MaNV' => 'NV005', 'ma_nv' => 'NV005', 'ma_pb' => 'PB003'],
        'admin' => ['MaNV' => 'NV001', 'ma_nv' => 'NV001', 'ma_pb' => 'PB001'],
    ];
    $dashboard = new Dashboard($db);
    foreach ($users as $role => $user) {
        $_SESSION['user'] = $user + ['VaiTro' => $role, 'vai_tro' => [$role]];
        $data = $dashboard->roleData($role, $user);
        $check($data['cards'] !== [], 'No dashboard cards for role ' . $role);
    }
}];
$cases[] = ['Department head approval is recorded for an employee leave request', static function () use ($db, $check): void {
    $start = date('Y-m-d', strtotime('+60 days'));
    $end = date('Y-m-d', strtotime('+61 days'));
    $workforce = new WorkforceOperations($db);
    $workforce->createLeaveRequest('NV005', $start, $end, 'Kiểm thử quy trình nghỉ phép');
    $query = $db->prepare(
        "SELECT MaNP FROM NGHIPHEP WHERE MaNV = 'NV005' AND LyDo = :reason ORDER BY MaNP DESC LIMIT 1"
    );
    $query->execute(['reason' => 'Kiểm thử quy trình nghỉ phép']);
    $requestId = (string) $query->fetchColumn();
    $_SESSION['user'] = ['MaNV' => 'NV003', 'ma_nv' => 'NV003', 'ma_pb' => 'PB003', 'vai_tro' => ['quanly']];
    $workforce->decideLeave($requestId, 'Đã duyệt', 'NV003');
    $status = $db->prepare('SELECT TrangThai FROM NGHIPHEP WHERE MaNP = :id');
    $status->execute(['id' => $requestId]);
    $decision = $db->prepare(
        "SELECT COUNT(*) FROM PHEDUYET
         WHERE LoaiQuyTrinh = 'nghi_phep' AND MaDoiTuong = :id
           AND Buoc = 'truong_bp' AND KetQua = 'Đã duyệt' AND NguoiDuyet = 'NV003'"
    );
    $decision->execute(['id' => $requestId]);
    $check($status->fetchColumn() === 'Đã duyệt' && (int) $decision->fetchColumn() === 1);
}];
$cases[] = ['Department head cannot self-approve a leave request; HR is the configured fallback', static function () use ($db, $check): void {
    $start = date('Y-m-d', strtotime('+90 days'));
    $end = date('Y-m-d', strtotime('+91 days'));
    $workforce = new WorkforceOperations($db);
    $workforce->createLeaveRequest('NV003', $start, $end, 'Kiểm thử trưởng bộ phận nghỉ phép');
    $query = $db->prepare(
        "SELECT MaNP FROM NGHIPHEP WHERE MaNV = 'NV003' AND LyDo = :reason ORDER BY MaNP DESC LIMIT 1"
    );
    $query->execute(['reason' => 'Kiểm thử trưởng bộ phận nghỉ phép']);
    $requestId = (string) $query->fetchColumn();
    $_SESSION['user'] = ['MaNV' => 'NV003', 'ma_nv' => 'NV003', 'ma_pb' => 'PB003', 'vai_tro' => ['quanly']];
    try {
        $workforce->decideLeave($requestId, 'Đã duyệt', 'NV003');
        $check(false, 'Requester approved their own leave request');
    } catch (DomainException $exception) {
        $check(str_contains($exception->getMessage(), 'tự phê duyệt'));
    }
    $_SESSION['user'] = ['MaNV' => 'NV002', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002', 'vai_tro' => ['nhansu']];
    $workforce->decideLeave($requestId, 'Đã duyệt', 'NV002');
    $fallback = $db->prepare(
        "SELECT COUNT(*) FROM PHEDUYET
         WHERE LoaiQuyTrinh = 'nghi_phep' AND MaDoiTuong = :id
           AND Buoc = 'nhansu' AND KetQua = 'Đã duyệt' AND NguoiDuyet = 'NV002'"
    );
    $fallback->execute(['id' => $requestId]);
    $check((int) $fallback->fetchColumn() === 1);
}];
$cases[] = ['Employee reports permission and rows are personal only', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV005', 'ma_nv' => 'NV005', 'ma_pb' => 'PB003', 'vai_tro' => ['nhanvien']];
    $check(Auth::can('reports', 'personal') && !Auth::can('reports', 'view'));
    $report = (new Reports($db))->report('employees', '2000-01-01', date('Y-m-d'), '', 'NV005');
    $check($report['rows'] !== [], 'Expected the signed-in employee row');
    foreach ($report['rows'] as $row) {
        $check($row[0] === 'NV005', 'Personal report exposed another employee');
    }
}];
$cases[] = ['Exactly six report types are registered', static function () use ($check): void {
    $check(count(Reports::reportTypes()) === 6);
}];
$cases[] = ['Employee report obeys date and department filters', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV002', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002', 'vai_tro' => ['nhansu']];
    $report = (new Reports($db))->report('employees', '2000-01-01', date('Y-m-d'), 'PB002', null);
    $check($report['rows'] !== [], 'Expected seeded HR department employees');
    foreach ($report['rows'] as $row) {
        $check($row[2] === 'Phòng Nhân sự', 'Employee outside selected department returned');
    }
    $empty = (new Reports($db))->report('employees', '1900-01-01', '1900-12-31', '', null);
    $check($empty['rows'] === [], 'Date filter returned out-of-period employees');
}];
$cases[] = ['Recruitment report respects department selection', static function () use ($db, $check): void {
    $today = (string) $db->query('SELECT CURRENT_DATE()')->fetchColumn();
    $report = (new Reports($db))->report('recruitment', '2000-01-01', $today, 'PB003', null);
    $check($report['rows'] !== [], 'Expected seeded recruitment record');
    foreach ($report['rows'] as $row) {
        $check($row[2] === 'Phòng Kinh doanh', 'Recruitment from another department returned');
    }
}];
$cases[] = ['Probation report filters by department', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV002', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002', 'vai_tro' => ['nhansu']];
    $report = (new Reports($db))->report('probation', '2000-01-01', '2099-12-31', 'PB002', null);
    $check($report['rows'] !== [], 'Expected seeded probation record');
    foreach ($report['rows'] as $row) {
        $check($row[2] === 'Phòng Nhân sự', 'Probation from another department returned');
    }
}];
$cases[] = ['Attendance report filters by department and period', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV002', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002', 'vai_tro' => ['nhansu']];
    $report = (new Reports($db))->report('attendance', '2000-01-01', '2099-12-31', 'PB002', null);
    $check($report['rows'] !== [], 'Expected seeded attendance record');
    foreach ($report['rows'] as $row) {
        $check($row[2] === 'Phòng Nhân sự', 'Attendance from another department returned');
    }
}];
$cases[] = ['Payroll report filters by salary month and department', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV006', 'ma_nv' => 'NV006', 'ma_pb' => 'PB001', 'vai_tro' => ['giamdoc']];
    $report = (new Reports($db))->report('payroll', '2000-01-01', '2099-12-31', 'PB001', null);
    $check($report['rows'] !== [], 'Expected seeded director payroll row');
    foreach ($report['rows'] as $row) {
        $check($row[2] === 'Ban Giám đốc', 'Payroll from another department returned');
    }
}];
$cases[] = ['Resignation report executes with defined output shape', static function () use ($db, $check): void {
    $report = (new Reports($db))->report('resignations', '2000-01-01', '2099-12-31', '', null);
    $check(count($report['headers']) === 9);
    foreach ($report['rows'] as $row) {
        $check(count($row) === 9, 'Unexpected resignation report row');
    }
}];
$cases[] = ['Unknown report type is rejected', static function () use ($db, $check): void {
    try {
        (new Reports($db))->report('unknown', '2000-01-01', '2099-12-31', '', null);
    } catch (InvalidArgumentException) {
        return;
    }
    $check(false, 'Unknown report type accepted');
}];
$cases[] = ['Payroll readiness lists employees missing confirmed attendance', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV006', 'ma_nv' => 'NV006', 'ma_pb' => 'PB001', 'vai_tro' => ['giamdoc']];
    $month = date('Y-m', strtotime('first day of last month'));
    $ready = (new Payroll($db))->readiness($month);
    $check($ready['employees'] !== [], 'Expected employees in payroll period');
    $hasIssue = false;
    foreach ($ready['employees'] as $employee) {
        if ($employee['Issues'] !== []) {
            $hasIssue = true;
            break;
        }
    }
    $check($hasIssue, 'Seed data unexpectedly has no readiness issues');
}];
$cases[] = ['Payroll readiness filters by employee and department', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV006', 'ma_nv' => 'NV006', 'ma_pb' => 'PB001', 'vai_tro' => ['giamdoc']];
    $month = date('Y-m', strtotime('first day of last month'));
    $source = $db->query('SELECT MaNV, MaPB FROM NHANVIEN ORDER BY MaNV LIMIT 1')->fetch();
    $check(is_array($source), 'No employee available for payroll filter test');
    $payroll = new Payroll($db);
    $byEmployee = $payroll->readiness($month, null, (string) $source['MaNV']);
    $check(count($byEmployee['employees']) === 1, 'Employee filter did not return exactly one employee');
    $check($byEmployee['employees'][0]['MaNV'] === $source['MaNV'], 'Employee filter returned a different employee');
    $byDepartment = $payroll->readiness($month, (string) $source['MaPB']);
    $check($byDepartment['employees'] !== [], 'Department filter returned no employees');
    foreach ($byDepartment['employees'] as $employee) {
        $check($employee['MaPB'] === $source['MaPB'], 'Department filter returned an employee from another department');
    }
}];
$cases[] = ['Payroll readiness rejects the current and future periods', static function () use ($db, $check): void {
    $payroll = new Payroll($db);
    foreach ([date('Y-m'), date('Y-m', strtotime('first day of next month'))] as $month) {
        try {
            $payroll->readiness($month);
        } catch (DomainException $exception) {
            $check(str_contains($exception->getMessage(), 'kỳ đã kết thúc'));
            continue;
        }
        $check(false, "Unfinished payroll period {$month} was accepted");
    }
}];
$cases[] = ['Payroll calculation stops when readiness has missing data', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV004', 'ma_nv' => 'NV004', 'ma_pb' => 'PB006', 'vai_tro' => ['ketoan']];
    try {
        (new Payroll($db))->calculate(date('Y-m', strtotime('first day of last month')), [], 'NV001');
    } catch (DomainException) {
        return;
    }
    $check(false, 'Payroll calculation proceeded despite missing data');
}];
$cases[] = ['Duplicate attendance for employee and day is rejected', static function () use ($db, $check): void {
    $source = $db->query('SELECT MaNV, Ngay FROM CHAMCONG LIMIT 1')->fetch();
    $check(is_array($source), 'No attendance seed row to test unique constraint');
    try {
        $insert = $db->prepare(
            "INSERT INTO CHAMCONG (MaCC, MaNV, Ngay, GioVao, GioRa, TrangThai)
             VALUES ('TESTCC001', :employee_id, :day, '08:00:00', '17:00:00', 'Đủ công')"
        );
        $insert->execute(['employee_id' => $source['MaNV'], 'day' => $source['Ngay']]);
    } catch (PDOException) {
        return;
    }
    $check(false, 'Duplicate attendance row inserted');
}];
$cases[] = ['Resignation request before statutory notice date is blocked', static function () use ($db, $check): void {
    $_SESSION['user'] = ['MaNV' => 'NV002', 'ma_nv' => 'NV002', 'ma_pb' => 'PB002', 'vai_tro' => ['nhansu']];
    try {
        (new PersonnelOperations($db))->createResignation(
            'NV001',
            date('Y-m-d', strtotime('+1 day')),
            'Kiểm thử thời hạn báo trước',
            'NV001'
        );
    } catch (DomainException $exception) {
        $check(str_contains($exception->getMessage(), 'báo trước'), 'Unexpected resignation validation message');
        return;
    }
    $check(false, 'Resignation with insufficient notice was accepted');
}];

$passed = 0;
foreach ($cases as $index => [$title, $test]) {
    try {
        $test();
        $passed++;
        printf("PASS %02d: %s\n", $index + 1, $title);
    } catch (Throwable $exception) {
        printf("FAIL %02d: %s — %s\n", $index + 1, $title, $exception->getMessage());
    }
}
printf("\nResult: %d/%d passed\n", $passed, count($cases));
exit($passed === count($cases) ? 0 : 1);
