<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Tuyển dụng</h1>
        <p class="text-secondary mb-0">Đề xuất → Giám đốc phê duyệt → Nhân sự đăng tin tuyển dụng.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(url('applicants')) ?>">
        <i class="bi bi-people me-1"></i>Quản lý ứng viên
    </a>
</div>

<?php if ($canManage || $canPropose): ?>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Tạo đề xuất tuyển dụng</h2>
            <form method="post" action="<?= e(url('recruitment-create')) ?>" class="row g-3">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <div class="col-lg-3">
                    <label class="form-label" for="job-position">Vị trí tuyển dụng</label>
                    <input class="form-control" id="job-position" name="position" maxlength="150" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="job-department">Phòng ban</label>
                    <select class="form-select" id="job-department" name="department" required>
                        <option value="">Chọn phòng ban</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= e($department['MaPB']) ?>"><?= e($department['TenPB']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="job-quantity">Số lượng</label>
                    <input class="form-control" id="job-quantity" name="quantity" type="number" min="1" max="1000" value="1" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="job-requirements">Yêu cầu</label>
                    <input class="form-control" id="job-requirements" name="requirements" maxlength="5000">
                </div>
                <div class="col-lg-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-send me-1"></i>Gửi đề xuất
                    </button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Vị trí</th>
                        <th>Phòng ban</th>
                        <th>Số lượng</th>
                        <th>Ngày đề xuất</th>
                        <th>Trạng thái</th>
                        <th>Đề xuất / phê duyệt</th>
                        <th>Ứng viên</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $badge = match ($job['TrangThai']) {
                        'Đang tuyển' => 'success',
                        'Đã duyệt' => 'primary',
                        'Chờ phê duyệt' => 'warning',
                        'Từ chối' => 'danger',
                        default => 'secondary',
                    };
                    ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold"><?= e($job['ViTri']) ?></div>
                            <div class="small text-secondary"><?= e($job['MaTD']) ?></div>
                            <?php if ($job['YeuCau']): ?><div class="small text-secondary"><?= e($job['YeuCau']) ?></div><?php endif; ?>
                        </td>
                        <td><?= e($job['TenPB'] ?? 'Chưa phân công') ?></td>
                        <td><?= e($job['SoLuong']) ?></td>
                        <td><?= e(date('d/m/Y', strtotime((string) $job['NgayDang']))) ?></td>
                        <td><span class="badge text-bg-<?= e($badge) ?>"><?= e($job['TrangThai']) ?></span></td>
                        <td class="small">
                            <?= e($job['NguoiDeXuat'] ?? '—') ?>
                            <?php if ($job['NguoiDuyet']): ?><div class="text-secondary">Duyệt: <?= e($job['NguoiDuyet']) ?></div><?php endif; ?>
                        </td>
                        <td><?= e($job['SoUngVien']) ?></td>
                        <td class="text-end pe-3">
                            <?php if ($canApprove && $job['TrangThai'] === 'Chờ phê duyệt'): ?>
                                <form method="post" action="<?= e(url('recruitment-approve')) ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($job['MaTD']) ?>">
                                    <button class="btn btn-sm btn-success" type="submit" title="Phê duyệt"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="post" action="<?= e(url('recruitment-reject')) ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($job['MaTD']) ?>">
                                    <input class="form-control form-control-sm d-inline-block" style="width: 130px" name="reason" maxlength="500" placeholder="Lý do từ chối" required aria-label="Lý do từ chối">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Từ chối"><i class="bi bi-x-lg"></i></button>
                                </form>
                            <?php elseif ($canManage && $job['TrangThai'] === 'Đã duyệt'): ?>
                                <form method="post" action="<?= e(url('recruitment-publish')) ?>" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($job['MaTD']) ?>">
                                    <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-megaphone me-1"></i>Đăng tin</button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($jobs === []): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">Chưa có đề xuất tuyển dụng.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
