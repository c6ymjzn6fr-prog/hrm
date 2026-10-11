<?php
$money = static fn (mixed $value): string => number_format((float) $value, 0, ',', '.') . ' đ';
$employees = $readiness['employees'];
$missing = array_filter($employees, static fn (array $employee): bool => $employee['Issues'] !== []);
$allReady = $employees !== [] && $missing === [];
$hasPendingPayroll = array_filter($rows, static fn (array $row): bool => $row['TrangThai'] === 'Chờ duyệt') !== [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1"><?= $personal ? 'Phiếu lương của tôi' : 'Tính lương' ?></h1><p class="text-secondary mb-0">Lương cơ bản theo ngày công đã chốt; tăng ca lấy từ đề nghị đã duyệt và hệ số theo ngày.</p></div>
    <?php
    $exportUrl = url('payroll-export') . '&month=' . rawurlencode($month);
    if ($departmentId !== '') {
        $exportUrl .= '&department_id=' . rawurlencode($departmentId);
    }
    if ($selectedEmployeeId !== '') {
        $exportUrl .= '&employee_id=' . rawurlencode($selectedEmployeeId);
    }
    ?>
    <a class="btn btn-outline-success" href="<?= e($exportUrl) ?>"><i class="bi bi-filetype-csv me-1"></i>Xuất CSV</a>
</div>
<section class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(APP_BASE_URL) ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="route" value="payroll">
            <div class="col-md-4"><label class="form-label" for="payroll-month">Kỳ lương</label><input class="form-control" id="payroll-month" type="month" name="month" value="<?= e($month) ?>" max="<?= e($latestClosedMonth) ?>" required></div>
            <div class="col-md-3"><button class="btn btn-outline-primary" type="submit">Xem kỳ lương</button></div>
            <?php if (!$personal): ?>
                <div class="col-md-4">
                    <label class="form-label" for="payroll-department">Phòng ban</label>
                    <select class="form-select" id="payroll-department" name="department_id">
                        <option value="">Toàn bộ phòng ban trong phạm vi</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= e($department['MaPB']) ?>" <?= $departmentId === $department['MaPB'] ? 'selected' : '' ?>><?= e($department['TenPB']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="payroll-employee">Cá nhân</label>
                    <select class="form-select" id="payroll-employee" name="employee_id">
                        <option value="">Tất cả nhân viên phù hợp</option>
                        <?php foreach ($employeeOptions as $option): ?>
                            <option value="<?= e($option['MaNV']) ?>" <?= $selectedEmployeeId === $option['MaNV'] ? 'selected' : '' ?>><?= e($option['HoTen']) ?> (<?= e($option['MaNV']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </form>
        <p class="form-text mb-0 mt-2">
            Chỉ xem, tính và phê duyệt được kỳ lương đã kết thúc (tối đa <?= e($latestClosedMonth) ?>).
            <?php if (!$personal): ?>Có thể lọc theo phòng ban, cá nhân hoặc kết hợp cả hai; để trống bộ lọc thì áp dụng cho toàn bộ nhân viên trong phạm vi được cấp quyền.<?php endif; ?>
        </p>
    </div>
</section>
<?php if (!$personal && $canManage): ?>
    <?php if (!$allReady): ?>
        <div class="alert alert-danger"><h2 class="h6">Chưa thể tính lương — cần bổ sung dữ liệu</h2>
            <?php if ($employees === []): ?><p class="mb-0">Không có nhân viên thuộc kỳ này.</p><?php else: ?>
                <ul class="mb-0"><?php foreach ($missing as $employee): ?><li><strong><?= e($employee['HoTen']) ?> (<?= e($employee['MaNV']) ?>):</strong> <?= e(implode('; ', $employee['Issues'])) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(url('payroll-calculate')) ?>" class="card border-0 shadow-sm mb-4">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <input type="hidden" name="department_id" value="<?= e($departmentId) ?>">
            <input type="hidden" name="employee_id" value="<?= e($selectedEmployeeId) ?>">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Kiểm tra dữ liệu và tính lương</h2></div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Công hưởng lương</th><th>Lương cơ bản</th><th>Phụ cấp</th><th>Thưởng</th><th>Tăng ca</th><th>Khấu trừ</th></tr></thead>
                <tbody><?php foreach ($employees as $employee): ?><tr>
                    <td class="ps-3"><?= e($employee['HoTen']) ?><div class="small text-secondary"><?= e($employee['MaNV']) ?></div></td>
                    <td><?= e($employee['NgayCong']) ?> ngày</td>
                    <td><?= e($money($employee['LuongCoBan'])) ?></td>
                    <td><input class="form-control form-control-sm" name="adjustments[<?= e($employee['MaNV']) ?>][allowance]" type="number" min="0" step="0.01" value="0" aria-label="Phụ cấp <?= e($employee['HoTen']) ?>"></td>
                    <td><input class="form-control form-control-sm" name="adjustments[<?= e($employee['MaNV']) ?>][bonus]" type="number" min="0" step="0.01" value="0" aria-label="Thưởng <?= e($employee['HoTen']) ?>"></td>
                    <td><?= e($money($employee['TangCa'])) ?></td>
                    <td><input class="form-control form-control-sm" name="adjustments[<?= e($employee['MaNV']) ?>][deduction]" type="number" min="0" step="0.01" value="0" aria-label="Khấu trừ <?= e($employee['HoTen']) ?>"></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-calculator me-1"></i>Tính lương kỳ <?= e($month) ?></button></div>
        </form>
    <?php endif; ?>
<?php endif; ?>
<section class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0">Bảng lương kỳ <?= e($month) ?></h2>
        <?php if (!$personal && $canApprove && $hasPendingPayroll): ?>
            <form method="post" action="<?= e(url('payroll-approve')) ?>" onsubmit="return confirm('Phê duyệt toàn bộ phiếu lương đang hiển thị trong kỳ này?')">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="department_id" value="<?= e($departmentId) ?>"><input type="hidden" name="employee_id" value="<?= e($selectedEmployeeId) ?>">
                <button class="btn btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Phê duyệt bảng lương</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Lương cơ bản</th><th>Phụ cấp</th><th>Thưởng</th><th>Tăng ca</th><th>Khấu trừ</th><th>Thực nhận</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $badge = $row['TrangThai'] === 'Đã duyệt' ? 'success' : 'warning'; ?>
            <tr><td class="ps-3"><?= e($row['HoTen']) ?><div class="small text-secondary"><?= e($row['MaNV']) ?></div></td>
                <td><?= e($money($row['LuongCoBan'])) ?></td><td><?= e($money($row['PhuCap'])) ?></td><td><?= e($money($row['Thuong'])) ?></td><td><?= e($money($row['TangCa'])) ?></td><td><?= e($money($row['KhauTru'])) ?></td><td class="fw-semibold"><?= e($money($row['ThucNhan'])) ?></td>
                <td><span class="badge text-bg-<?= $badge ?>"><?= e($row['TrangThai']) ?></span></td>
                <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('payslip')) ?>&id=<?= e($row['MaLuong']) ?>"><i class="bi bi-printer me-1"></i>Phiếu lương</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?><tr><td colspan="9" class="text-center text-secondary py-4">Chưa có phiếu lương trong kỳ này.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
