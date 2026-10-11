<?php
$dateText = static fn (mixed $value): string => date('d/m/Y', strtotime((string) $value));
?>
<div class="mb-4">
    <h1 class="h3 mb-1">Nghỉ phép</h1>
    <p class="text-secondary mb-0">Định mức 12 ngày làm việc mỗi năm; ngày cuối tuần không trừ vào phép.</p>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Đã sử dụng</div><div class="h3 mb-0"><?= e($balance['used']) ?> ngày</div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Đang chờ duyệt</div><div class="h3 text-warning mb-0"><?= e($balance['reserved']) ?> ngày</div></div></div></div>
    <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Còn lại năm <?= e(date('Y')) ?></div><div class="h3 text-success mb-0"><?= e($balance['remaining']) ?> ngày</div></div></div></div>
</div>
<?php if ($canRequest): ?>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Gửi đề nghị nghỉ phép</h2>
            <form method="post" action="<?= e(url('leave-request')) ?>" class="row g-3">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <div class="col-md-3"><label class="form-label" for="leave-start">Từ ngày</label><input class="form-control" id="leave-start" name="start_date" type="date" min="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="leave-end">Đến ngày</label><input class="form-control" id="leave-end" name="end_date" type="date" min="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="leave-reason">Lý do</label><input class="form-control" id="leave-reason" name="reason" minlength="3" maxlength="2000" required></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Gửi đơn</button></div>
            </form>
        </div>
    </section>
<?php endif; ?>
<section class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Đơn nghỉ phép</h2></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Thời gian</th><th>Lý do</th><th>Trạng thái</th><?php if ($canApprove): ?><th>Phê duyệt</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $badge = $row['TrangThai'] === 'Đã duyệt' ? 'success' : ($row['TrangThai'] === 'Từ chối' ? 'danger' : 'warning'); ?>
                <tr><td class="ps-3"><?= e($row['HoTen']) ?></td><td><?= e($dateText($row['TuNgay'])) ?> – <?= e($dateText($row['DenNgay'])) ?></td><td><?= e($row['LyDo']) ?></td><td><span class="badge text-bg-<?= $badge ?>"><?= e($row['TrangThai']) ?></span></td>
                    <?php if ($canApprove): ?><td><?php if ($row['TrangThai'] === 'Chờ duyệt'): ?><div class="d-flex gap-1">
                        <?php foreach (['Đã duyệt' => 'success', 'Từ chối' => 'outline-danger'] as $decision => $style): ?>
                            <form method="post" action="<?= e(url('leave-decide')) ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaNP']) ?>"><input type="hidden" name="decision" value="<?= e($decision) ?>"><?php if ($decision === 'Từ chối'): ?><input class="form-control form-control-sm mb-1" name="reason" maxlength="500" placeholder="Lý do từ chối" required aria-label="Lý do từ chối"><?php endif; ?><button class="btn btn-sm btn-<?= $style ?>" type="submit"><?= e($decision) ?></button></form>
                        <?php endforeach; ?>
                    </div><?php else: ?>—<?php endif; ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="<?= $canApprove ? 5 : 4 ?>" class="text-center text-secondary py-4">Chưa có đơn nghỉ phép.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
