<?php $dateText = static fn (mixed $value): string => date('d/m/Y', strtotime((string) $value)); ?>
<div class="mb-4">
    <h1 class="h3 mb-1">Tăng ca</h1>
    <p class="text-secondary mb-0">Đề nghị vượt 40 giờ/tháng hoặc 12 giờ/ngày vẫn được ghi nhận kèm cảnh báo để người duyệt xem xét.</p>
</div>
<?php if ($canRequest): ?>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Gửi đề nghị tăng ca</h2>
            <form method="post" action="<?= e(url('overtime-request')) ?>" class="row g-3">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <div class="col-md-4"><label class="form-label" for="overtime-date">Ngày tăng ca</label><input class="form-control" id="overtime-date" name="date" type="date" max="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="overtime-hours">Số giờ</label><input class="form-control" id="overtime-hours" name="hours" type="number" min="0.25" max="24" step="0.25" required></div>
                <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Gửi đề nghị</button></div>
            </form>
        </div>
    </section>
<?php endif; ?>
<section class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Danh sách đề nghị tăng ca</h2></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Ngày</th><th>Số giờ / cảnh báo</th><th>Trạng thái</th><?php if ($canApprove): ?><th>Phê duyệt</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $badge = $row['TrangThai'] === 'Đã duyệt' ? 'success' : ($row['TrangThai'] === 'Từ chối' ? 'danger' : 'warning'); ?>
                <tr><td class="ps-3"><?= e($row['HoTen']) ?></td><td><?= e($dateText($row['Ngay'])) ?></td><td><?= e(number_format((float) $row['SoGio'], 2, ',', '.')) ?> giờ
                    <?php if ((float) $row['TongGioThang'] > 40): ?><div class="small text-danger">Tháng: <?= e(number_format((float) $row['TongGioThang'], 2, ',', '.')) ?> giờ (&gt;40)</div><?php endif; ?>
                    <?php if ((float) $row['TongGioNgay'] > 12): ?><div class="small text-danger">Ngày: <?= e(number_format((float) $row['TongGioNgay'], 2, ',', '.')) ?> giờ (&gt;12)</div><?php endif; ?>
                    </td><td><span class="badge text-bg-<?= $badge ?>"><?= e($row['TrangThai']) ?></span></td>
                    <?php if ($canApprove): ?><td><?php if ($row['TrangThai'] === 'Chờ duyệt'): ?><div class="d-flex gap-1">
                        <form method="post" action="<?= e(url('overtime-decide')) ?>" class="d-flex gap-1">
                            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($row['MaTC']) ?>">
                            <select class="form-select form-select-sm" name="multiplier" aria-label="Hệ số ngày tăng ca">
                                <option value="1.5" <?= (float) $row['HeSo'] === 1.5 ? 'selected' : '' ?>>Ngày thường 150%</option>
                                <option value="2" <?= (float) $row['HeSo'] === 2.0 ? 'selected' : '' ?>>Ngày nghỉ 200%</option>
                                <option value="3">Lễ, Tết 300%</option>
                            </select>
                            <input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Lý do (khi từ chối)" aria-label="Lý do từ chối">
                            <button class="btn btn-sm btn-success" name="decision" value="Đã duyệt" type="submit">Duyệt</button>
                            <button class="btn btn-sm btn-outline-danger" name="decision" value="Từ chối" type="submit">Từ chối</button>
                        </form>
                    </div><?php else: ?>—<?php endif; ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="<?= $canApprove ? 5 : 4 ?>" class="text-center text-secondary py-4">Chưa có đề nghị tăng ca.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
