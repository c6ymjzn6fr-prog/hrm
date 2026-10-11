<?php $money = static fn (mixed $value): string => number_format((float) $value, 0, ',', '.') . ' đ'; ?>
<div class="mb-4"><h1 class="h3 mb-1">Đề xuất và lịch sử tăng lương</h1><p class="text-secondary mb-0">Quản lý đề xuất; mức lương chỉ có hiệu lực sau khi Giám đốc phê duyệt.</p></div>
<?php if ($canPropose): ?>
    <section class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h2 class="h5">Đề xuất tăng lương</h2>
        <form method="post" action="<?= e(url('salary-propose')) ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <div class="col-md-3"><label class="form-label" for="salary-employee">Nhân viên</label><select class="form-select" id="salary-employee" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= e($employee['MaNV']) ?>"><?= e($employee['HoTen']) ?> (<?= e($employee['MaNV']) ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label" for="salary-amount">Mức lương mới (đ/tháng)</label><input class="form-control" id="salary-amount" name="salary" type="number" min="1" step="1000" required></div>
            <div class="col-md-2"><label class="form-label" for="salary-effective">Ngày hiệu lực</label><input class="form-control" id="salary-effective" name="effective_date" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-md-3"><label class="form-label" for="salary-reason">Lý do</label><input class="form-control" id="salary-reason" name="reason" minlength="3" maxlength="2000" required></div>
            <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Gửi</button></div>
        </form>
    </div></section>
<?php endif; ?>
<section class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Ngày hiệu lực</th><th>Mức lương</th><th>Lý do</th><th>Người đề xuất</th><th>Trạng thái / lịch sử</th><?php if ($canApprove): ?><th>Phê duyệt</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <?php $isPending = $row['TrangThai'] === 'Chờ duyệt tăng lương'; $badge = $isPending ? 'warning' : ($row['TrangThai'] === 'Từ chối' ? 'danger' : ($row['TrangThai'] === 'Hiệu lực' ? 'success' : 'secondary')); ?>
        <tr><td class="ps-3"><?= e($row['HoTen']) ?><div class="small text-secondary"><?= e($row['MaNV']) ?></div></td><td><?= e(date('d/m/Y', strtotime((string) $row['NgayHieuLuc']))) ?></td><td><?= e($money($row['MucLuong'])) ?></td><td><?= e($row['LyDoDieuChinh'] ?: 'Mức lương hợp đồng') ?></td><td><?= e($row['TenNguoiDeXuat'] ?: 'Hợp đồng ban đầu') ?></td><td><span class="badge text-bg-<?= $badge ?>"><?= e($row['TrangThai']) ?></span><?php if ($row['TenNguoiPheDuyet']): ?><div class="small text-secondary">Duyệt: <?= e($row['TenNguoiPheDuyet']) ?></div><?php endif; ?></td>
        <?php if ($canApprove): ?><td><?php if ($isPending): ?><form method="post" action="<?= e(url('salary-approve')) ?>" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaHD']) ?>"><input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Lý do (khi từ chối)" aria-label="Lý do từ chối"><button class="btn btn-sm btn-success" name="decision" value="Đã duyệt" type="submit">Duyệt</button><button class="btn btn-sm btn-outline-danger" name="decision" value="Từ chối" type="submit">Từ chối</button></form><?php else: ?>—<?php endif; ?></td><?php endif; ?>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="<?= $canApprove ? 7 : 6 ?>" class="text-center text-secondary py-4">Chưa có hợp đồng hoặc đề xuất tăng lương.</td></tr><?php endif; ?>
    </tbody>
</table></div></section>
