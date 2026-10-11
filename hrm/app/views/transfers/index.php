<?php $dateText = static fn (mixed $value): string => date('d/m/Y', strtotime((string) $value)); ?>
<div class="mb-4"><h1 class="h3 mb-1">Điều chuyển nhân sự</h1><p class="text-secondary mb-0">Đề xuất được áp dụng vào hồ sơ nhân viên sau khi Giám đốc phê duyệt.</p></div>
<?php if ($canPropose): ?>
    <section class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h2 class="h5">Tạo đề xuất điều chuyển</h2>
        <form method="post" action="<?= e(url('transfer-create')) ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <div class="col-md-3"><label class="form-label" for="transfer-employee">Nhân viên</label><select class="form-select" id="transfer-employee" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= e($employee['MaNV']) ?>"><?= e($employee['HoTen']) ?> · <?= e($employee['TenPB']) ?> · <?= e($employee['TenCV']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label" for="transfer-department">Phòng ban mới</label><select class="form-select" id="transfer-department" name="department" required><?php foreach ($departments as $item): ?><option value="<?= e($item['MaPB']) ?>"><?= e($item['TenPB']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label" for="transfer-position">Chức vụ mới</label><select class="form-select" id="transfer-position" name="position" required><?php foreach ($positions as $item): ?><option value="<?= e($item['MaCV']) ?>"><?= e($item['TenCV']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label" for="transfer-date">Ngày hiệu lực</label><input class="form-control" id="transfer-date" name="date" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-md-2"><label class="form-label" for="transfer-reason">Lý do</label><input class="form-control" id="transfer-reason" name="reason" minlength="3" maxlength="2000" required></div>
            <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Gửi</button></div>
        </form>
    </div></section>
<?php endif; ?>
<section class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Ngày</th><th>Phòng ban</th><th>Chức vụ</th><th>Lý do</th><th>Trạng thái</th><?php if ($canApprove): ?><th>Phê duyệt</th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($rows as $row): ?>
        <?php $pending = $row['TrangThai'] === 'Chờ phê duyệt'; ?>
        <tr><td class="ps-3"><?= e($row['HoTen']) ?></td><td><?= e($dateText($row['NgayDC'])) ?></td><td><?= e($row['TenBoPhanCu']) ?> → <?= e($row['TenBoPhanMoi']) ?></td><td><?= e($row['TenViTriCu']) ?> → <?= e($row['TenViTriMoi']) ?></td><td><?= e($row['LyDo']) ?></td><td><span class="badge text-bg-<?= $pending ? 'warning' : ($row['TrangThai'] === 'Đã duyệt' ? 'success' : 'danger') ?>"><?= e($row['TrangThai']) ?></span></td>
        <?php if ($canApprove): ?><td><?php if ($pending): ?><form method="post" action="<?= e(url('transfer-decide')) ?>" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaDC']) ?>"><input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Lý do (khi từ chối)" aria-label="Lý do từ chối"><button class="btn btn-sm btn-success" name="decision" value="Đã duyệt" type="submit">Duyệt</button><button class="btn btn-sm btn-outline-danger" name="decision" value="Từ chối" type="submit">Từ chối</button></form><?php else: ?>—<?php endif; ?></td><?php endif; ?></tr>
    <?php endforeach; ?><?php if ($rows === []): ?><tr><td colspan="<?= $canApprove ? 7 : 6 ?>" class="text-center text-secondary py-4">Chưa có đề xuất điều chuyển.</td></tr><?php endif; ?></tbody>
</table></div></section>
