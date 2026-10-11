<div class="mb-4">
    <h1 class="h3 mb-1">Quản trị tài khoản và phân quyền</h1>
    <p class="text-secondary mb-0">Quyền truy cập được xác định theo vai trò; chỉ Quản trị hệ thống được thay đổi tài khoản.</p>
</div>
<section class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Tạo tài khoản</h2></div>
    <div class="card-body">
        <?php if ($employees === []): ?>
            <p class="text-secondary mb-0">Không có nhân viên hoạt động chưa được cấp tài khoản.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('account-create')) ?>" class="row g-3 align-items-end">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <div class="col-lg-3">
                    <label class="form-label" for="account-employee">Nhân viên</label>
                    <select class="form-select" id="account-employee" name="employee_id" required>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= e($employee['MaNV']) ?>"><?= e($employee['HoTen']) ?> (<?= e($employee['MaNV']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3"><label class="form-label" for="account-username">Tên đăng nhập</label><input class="form-control" id="account-username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" required></div>
                <div class="col-lg-3"><label class="form-label" for="account-password">Mật khẩu khởi tạo</label><input class="form-control" id="account-password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
                <div class="col-lg-2"><label class="form-label" for="account-role">Vai trò</label><select class="form-select" id="account-role" name="role" required><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?></select></div>
                <div class="col-lg-1 d-grid"><button class="btn btn-primary" type="submit">Tạo</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
<section class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Tài khoản hiện có</h2></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Tài khoản / nhân viên</th><th>Số lần sai</th><th>Khóa đến</th><th>Phân quyền / trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($accounts as $account): ?>
            <tr>
                <td class="ps-3"><strong><?= e($account['TenDangNhap']) ?></strong><div class="small text-secondary"><?= e($account['HoTen']) ?> · <?= e($account['MaNV']) ?></div></td>
                <td><?= e($account['SoLanSai']) ?></td>
                <td><?= $account['KhoaDen'] ? e(date('d/m/Y H:i', strtotime($account['KhoaDen']))) : '—' ?></td>
                <td>
                    <form method="post" action="<?= e(url('account-update')) ?>" class="d-flex gap-2">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="account_id" value="<?= e($account['MaTK']) ?>">
                        <select class="form-select form-select-sm" name="role" aria-label="Vai trò">
                            <?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= $account['VaiTro'] === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm" name="status" aria-label="Trạng thái">
                            <?php foreach (['Hoạt động', 'Ngưng hoạt động'] as $status): ?><option value="<?= e($status) ?>" <?= $account['TrangThai'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Lưu</button>
                    </form>
                </td>
                <td><span class="badge text-bg-<?= $account['TrangThai'] === 'Hoạt động' ? 'success' : 'secondary' ?>"><?= e($account['TrangThai']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<section class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Ma trận quyền theo vai trò</h2></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Chức năng</th><?php foreach ($roles as $role): ?><th><?= e($role) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($permissionMatrix as $feature => $grants): ?><tr>
            <th class="ps-3"><?= e($feature) ?></th>
            <?php foreach ($roles as $role): ?><td><?= e($grants[$role] ?? '—') ?></td><?php endforeach; ?>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <div class="card-footer small text-secondary">Gán vai trò ở trên sẽ áp dụng ma trận này cho người dùng. Quyền cá nhân luôn giới hạn theo mã nhân viên đăng nhập.</div>
</section>
<section class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Nhật ký hoạt động gần đây</h2></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Thời gian</th><th>Người dùng</th><th>Thao tác</th><th>Chi tiết</th><th>IP</th></tr></thead>
        <tbody><?php foreach ($activityLogs as $entry): ?><tr>
            <td class="ps-3"><?= e(date('d/m/Y H:i:s', strtotime($entry['ThoiGian']))) ?></td>
            <td><?= e($entry['HoTen']) ?></td>
            <td><?= e($entry['HanhDong']) ?></td>
            <td><?= e($entry['ChiTiet']) ?></td>
            <td><?= e($entry['DiaChiIP'] ?? '—') ?></td>
        </tr><?php endforeach; ?>
        <?php if ($activityLogs === []): ?><tr><td class="text-center text-secondary py-3" colspan="5">Chưa có nhật ký.</td></tr><?php endif; ?></tbody>
    </table></div>
</section>
