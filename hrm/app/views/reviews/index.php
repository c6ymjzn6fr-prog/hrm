<?php $dateText = static fn (mixed $value): string => date('d/m/Y', strtotime((string) $value)); ?>
<div class="mb-4"><h1 class="h3 mb-1"><?= $personal ? 'Đánh giá của tôi' : 'Đánh giá nhân viên' ?></h1><p class="text-secondary mb-0">Lưu nhận xét, kết quả và xếp loại theo từng kỳ đánh giá.</p></div>
<?php if ($canManage): ?>
    <section class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h2 class="h5">Ghi nhận đánh giá</h2>
        <form method="post" action="<?= e(url('review-create')) ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <div class="col-md-3"><label class="form-label" for="review-employee">Nhân viên</label><select class="form-select" id="review-employee" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= e($employee['MaNV']) ?>"><?= e($employee['HoTen']) ?> (<?= e($employee['MaNV']) ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label" for="review-date">Ngày đánh giá</label><input class="form-control" id="review-date" name="date" type="date" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-md-2"><label class="form-label" for="review-rating">Xếp loại</label><select class="form-select" id="review-rating" name="rating" required><?php foreach (['Xuất sắc','Tốt','Đạt','Cần cải thiện','Không đạt'] as $rating): ?><option value="<?= e($rating) ?>"><?= e($rating) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-5"><label class="form-label" for="review-outcome">Kết quả</label><input class="form-control" id="review-outcome" name="outcome" maxlength="4000" required></div>
            <div class="col-md-10"><label class="form-label" for="review-note">Nhận xét</label><textarea class="form-control" id="review-note" name="note" rows="2" maxlength="4000"></textarea></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Lưu đánh giá</button></div>
        </form>
    </div></section>
<?php endif; ?>
<section class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th class="ps-3">Ngày</th><th>Nhân viên</th><th>Kết quả</th><th>Xếp loại</th><th>Nhận xét</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr><td class="ps-3"><?= e($dateText($row['NgayDanhGia'])) ?></td><td><?= e($row['HoTen']) ?><div class="small text-secondary"><?= e($row['MaNV']) ?></div></td><td><?= e($row['KetQua']) ?></td><td><span class="badge text-bg-<?= $row['XepLoai'] === 'Xuất sắc' || $row['XepLoai'] === 'Tốt' ? 'success' : ($row['XepLoai'] === 'Không đạt' ? 'danger' : 'secondary') ?>"><?= e($row['XepLoai']) ?></span></td><td><?= e($row['NhanXet']) ?></td></tr><?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">Chưa có đánh giá.</td></tr><?php endif; ?></tbody>
</table></div></section>
