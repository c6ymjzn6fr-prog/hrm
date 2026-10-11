<?php
$dateText = static fn (mixed $value): string => $value ? date('d/m/Y', strtotime((string) $value)) : '—';
?>
<div class="mb-4">
    <h1 class="h3 mb-1"><?= $personal ? 'Thử việc của tôi' : 'Quản lý thử việc' ?></h1>
    <p class="text-secondary mb-0">Thời hạn tối đa: quản lý 180 ngày, cao đẳng/đại học 60 ngày, trung cấp 30 ngày, trình độ khác 6 ngày.</p>
</div>
<section class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th class="ps-3">Nhân viên</th><th>Trình độ / chức vụ</th><th>Thời gian</th><th>Đánh giá</th><?php if ($canManage): ?><th>Thao tác</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $overLimit = $row['SoNgayThuViec'] === null || $row['SoNgayThuViec'] > $row['ThoiHanToiDa']; ?>
                <tr>
                    <td class="ps-3"><div class="fw-semibold"><?= e($row['HoTen']) ?></div><div class="small text-secondary"><?= e($row['MaNV']) ?></div></td>
                    <td><div><?= e($row['TrinhDo'] ?: 'Chưa cập nhật trình độ') ?></div><div class="small text-secondary"><?= e($row['TenCV']) ?></div></td>
                    <td>
                        <?= e($dateText($row['NgayBatDau'])) ?> – <?= e($dateText($row['NgayKetThuc'])) ?>
                        <div class="small text-<?= $overLimit ? 'danger' : 'secondary' ?>">
                            <?= e($row['SoNgayThuViec'] ?? 'Không hợp lệ') ?> / <?= e($row['ThoiHanToiDa']) ?> ngày
                            <?= $overLimit ? '· Vượt giới hạn' : '' ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($row['KetQua'] === 'Đạt'): ?>
                            <span class="badge text-bg-success">Đạt</span>
                        <?php elseif ($row['KetQua'] === 'Không đạt'): ?>
                            <span class="badge text-bg-danger">Không đạt</span>
                        <?php else: ?>
                            <span class="badge text-bg-warning">Chưa đánh giá</span>
                        <?php endif; ?>
                        <?php if ($row['NhanXet']): ?><div class="small text-secondary mt-1"><?= e($row['NhanXet']) ?></div><?php endif; ?>
                    </td>
                    <?php if ($canManage): ?>
                        <td>
                            <?php if ($row['KetQua'] === null && $row['TrangThai'] === 'Thử việc'): ?>
                                <?php if ($overLimit): ?>
                                    <span class="small text-danger">Không thể đánh giá: cần cập nhật trình độ hoặc thời hạn đúng quy định.</span>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('probation-evaluate')) ?>" class="d-flex flex-wrap gap-2">
                                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                        <input type="hidden" name="id" value="<?= e($row['MaTV']) ?>">
                                        <select class="form-select form-select-sm" name="result" aria-label="Kết quả đánh giá" required>
                                            <option value="">Chọn kết quả</option><option value="Đạt">Đạt</option><option value="Không đạt">Không đạt</option>
                                        </select>
                                        <input class="form-control form-control-sm" name="note" maxlength="4000" placeholder="Nhận xét">
                                        <button class="btn btn-sm btn-primary" type="submit">Lưu đánh giá</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($row['TrangThai'] === 'Chính thức'): ?>
                                <span class="small text-success">Đã chuyển chính thức</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="<?= $canManage ? 5 : 4 ?>" class="text-center text-secondary py-4">Chưa có hồ sơ thử việc.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
