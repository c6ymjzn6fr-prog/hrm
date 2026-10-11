<?php
$dateText = static fn (mixed $value): string => $value ? date('d/m/Y', strtotime((string) $value)) : '—';
$monthDefault = date('Y-m', strtotime('first day of last month'));
$stageColor = static fn (string $stage): string => $stage === 'Lưu hồ sơ' ? 'secondary'
    : ($stage === 'Từ chối' ? 'danger' : (in_array($stage, ['Tiếp nhận','Phê duyệt','Bàn giao','Chốt công/lương'], true) ? 'warning' : 'success'));
?>
<div class="mb-4"><h1 class="h3 mb-1">Quy trình nghỉ việc</h1><p class="text-secondary mb-0">Tiếp nhận → Phê duyệt → Bàn giao → Chốt công/lương → Lưu hồ sơ. Kế toán chỉ chốt lương sau khi đã bàn giao.</p></div>
<?php if ($canRequest): ?>
    <section class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h2 class="h5">Tạo đề nghị nghỉ việc</h2>
        <form method="post" action="<?= e(url('resignation-request')) ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <?php if ($employees !== []): ?><div class="col-md-3"><label class="form-label" for="resignation-employee">Nhân viên</label><select class="form-select" id="resignation-employee" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= e($employee['MaNV']) ?>"><?= e($employee['HoTen']) ?> (<?= e($employee['MaNV']) ?>)</option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-md-3"><label class="form-label" for="departure-date">Ngày nghỉ dự kiến</label><input class="form-control" id="departure-date" name="departure_date" type="date" min="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-md-4"><label class="form-label" for="resignation-reason">Lý do</label><input class="form-control" id="resignation-reason" name="reason" minlength="3" maxlength="2000" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Gửi đề nghị</button></div>
        </form>
        <div class="form-text mt-2">Hệ thống áp thời hạn báo trước theo hợp đồng: 45 ngày (không xác định thời hạn), 30 ngày (xác định thời hạn từ 12 tháng) hoặc 3 ngày làm việc (dưới 12 tháng).</div>
    </div></section>
<?php endif; ?>
<section class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Ngày đề nghị / ngày nghỉ</th><th>Hợp đồng</th><th>Lý do</th><th>Quy trình</th><th>Bàn giao / chốt lương</th><th>Thao tác</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr><td class="ps-3"><?= e($row['HoTen']) ?><div class="small text-secondary"><?= e($row['MaNV']) ?></div></td>
            <td><?= e($dateText($row['NgayDeNghi'])) ?><div class="small text-secondary">Dự kiến: <?= e($dateText($row['NgayNghi'])) ?></div></td>
            <td><?= e($row['LoaiHD'] ?: '—') ?></td><td><?= e($row['LyDo']) ?></td>
            <td><span class="badge text-bg-<?= e($stageColor((string) $row['TrangThai'])) ?>"><?= e($row['TrangThai']) ?></span></td>
            <td><div><span class="badge text-bg-<?= (int) $row['BanGiao'] ? 'success' : 'secondary' ?>">Bàn giao <?= (int) $row['BanGiao'] ? 'xong' : 'chưa xong' ?></span></div><div class="mt-1"><span class="badge text-bg-<?= (int) $row['LuongDaChot'] ? 'success' : 'secondary' ?>">Chốt lương <?= (int) $row['LuongDaChot'] ? 'xong' : 'chưa xong' ?></span></div></td>
            <td>
                <?php if ($canReceive && $row['TrangThai'] === 'Tiếp nhận'): ?>
                    <form method="post" action="<?= e(url('resignation-step')) ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaNViec']) ?>"><input type="hidden" name="action" value="tiếp nhận"><button class="btn btn-sm btn-outline-primary" type="submit">Chuyển Giám đốc duyệt</button></form>
                <?php elseif (($canApprove || $canManagerApprove) && $row['TrangThai'] === 'Phê duyệt'): ?>
                    <div class="d-flex gap-1">
                        <?php if (($canApprove && ($row['BuocTiepTheo'] ?? '') === 'giamdoc')
                            || ($canManagerApprove && ($row['BuocTiepTheo'] ?? '') === 'truong_bp')): ?>
                            <form method="post" action="<?= e(url('resignation-step')) ?>" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaNViec']) ?>"><input type="hidden" name="action" value="phê duyệt"><button class="btn btn-sm btn-success" type="submit"><?= ($row['BuocTiepTheo'] ?? '') === 'truong_bp' ? 'Trưởng bộ phận duyệt' : 'Giám đốc duyệt' ?></button></form>
                            <form method="post" action="<?= e(url('resignation-step')) ?>" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaNViec']) ?>"><input type="hidden" name="action" value="từ chối"><input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Lý do từ chối" required aria-label="Lý do từ chối"><button class="btn btn-sm btn-outline-danger" type="submit">Từ chối</button></form>
                        <?php endif; ?>
                    </div>
                <?php elseif ($canReceive && $row['TrangThai'] === 'Bàn giao'): ?>
                    <form method="post" action="<?= e(url('resignation-step')) ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaNViec']) ?>"><input type="hidden" name="action" value="bàn giao"><button class="btn btn-sm btn-outline-primary" type="submit">Xác nhận bàn giao</button></form>
                <?php elseif ($canFinalize && $row['TrangThai'] === 'Chốt công/lương' && (int) $row['BanGiao'] === 1 && (int) $row['LuongDaChot'] === 0): ?>
                    <form method="post" action="<?= e(url('resignation-step')) ?>" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaNViec']) ?>"><input type="hidden" name="action" value="chốt lương"><input class="form-control form-control-sm" type="month" name="payroll_month" value="<?= e($monthDefault) ?>" required><button class="btn btn-sm btn-primary" type="submit">Chốt</button></form>
                <?php elseif ($canReceive && $row['TrangThai'] === 'Chốt công/lương' && (int) $row['LuongDaChot'] === 1): ?>
                    <form method="post" action="<?= e(url('resignation-step')) ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaNViec']) ?>"><input type="hidden" name="action" value="lưu hồ sơ"><button class="btn btn-sm btn-success" type="submit">Lưu hồ sơ</button></form>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-secondary py-4">Chưa có đề nghị nghỉ việc.</td></tr><?php endif; ?>
    </tbody>
</table></div></section>
