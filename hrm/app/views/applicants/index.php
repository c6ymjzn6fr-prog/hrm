<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Ứng viên</h1>
        <p class="text-secondary mb-0">Nhận hồ sơ, sàng lọc, phỏng vấn và ghi nhận kết quả tuyển dụng.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(url('recruitment')) ?>"><i class="bi bi-megaphone me-1"></i>Tin tuyển dụng</a>
</div>

<?php if ($canManage): ?>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Tiếp nhận hồ sơ ứng tuyển</h2>
            <?php if ($jobs === []): ?>
                <div class="alert alert-warning mb-0">Chưa có tin tuyển dụng đang mở. Hãy tạo đề xuất, phê duyệt và đăng tin trước.</div>
            <?php else: ?>
                <form method="post" action="<?= e(url('applicant-create')) ?>" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                    <div class="col-md-3">
                        <label for="candidate-name" class="form-label">Họ tên</label>
                        <input class="form-control" id="candidate-name" name="name" maxlength="150" required>
                    </div>
                    <div class="col-md-3">
                        <label for="candidate-phone" class="form-label">Số điện thoại</label>
                        <input class="form-control" id="candidate-phone" name="phone" maxlength="20" required>
                    </div>
                    <div class="col-md-3">
                        <label for="candidate-email" class="form-label">Email</label>
                        <input class="form-control" id="candidate-email" name="email" type="email" maxlength="150">
                    </div>
                    <div class="col-md-3">
                        <label for="candidate-job" class="form-label">Tin ứng tuyển</label>
                        <select class="form-select" id="candidate-job" name="job_id" required>
                            <option value="">Chọn vị trí</option>
                            <?php foreach ($jobs as $job): ?>
                                <option value="<?= e($job['MaTD']) ?>"><?= e($job['ViTri']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label for="candidate-cv" class="form-label">Hồ sơ/CV (PDF, JPG, PNG; tối đa 5 MB)</label>
                        <input class="form-control" id="candidate-cv" name="cv" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-inbox me-1"></i>Nhận hồ sơ</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(APP_BASE_URL) ?>/index.php">
            <input type="hidden" name="route" value="applicants">
            <div class="col-md-6">
                <label class="form-label" for="search-candidate">Tìm ứng viên</label>
                <input class="form-control" id="search-candidate" name="q" value="<?= e($term) ?>" placeholder="Họ tên, email, số điện thoại">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="candidate-status">Giai đoạn</label>
                <select class="form-select" id="candidate-status" name="status">
                    <option value="">Tất cả</option>
                    <?php foreach ($stages as $stage): ?>
                        <option value="<?= e($stage) ?>" <?= $status === $stage ? 'selected' : '' ?>><?= e($stage) ?></option>
                    <?php endforeach; ?>
                    <option value="Đã tiếp nhận" <?= $status === 'Đã tiếp nhận' ? 'selected' : '' ?>>Đã tiếp nhận</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Lọc</button></div>
        </form>
    </div>
</section>

<section class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Ứng viên</th><th>Vị trí</th><th>Ngày nộp</th><th>Hồ sơ</th><th>Trạng thái</th><?php if ($canManage): ?><th>Phỏng vấn / xử lý</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="ps-3">
                        <div class="fw-semibold"><?= e($row['HoTen']) ?></div>
                        <div class="small text-secondary"><?= e($row['SoDienThoai']) ?><?= $row['Email'] ? ' · ' . e($row['Email']) : '' ?></div>
                    </td>
                    <td><?= e($row['TinTuyenDung'] ?? $row['ViTriUngTuyen']) ?></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $row['NgayNop']))) ?></td>
                    <td>
                        <?php if (!empty($row['HoSoCV'])): ?>
                            <a href="<?= e(url('candidate-document')) ?>&id=<?= e($row['MaUV']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                <i class="bi bi-paperclip"></i> CV
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-<?= $row['TrangThai'] === 'Đạt' || $row['TrangThai'] === 'Đã tiếp nhận' ? 'success' : ($row['TrangThai'] === 'Từ chối' ? 'danger' : 'warning') ?>"><?= e($row['TrangThai']) ?></span></td>
                    <?php if ($canManage): ?>
                        <td class="candidate-actions">
                            <?php if (in_array($row['TrangThai'], ['Mới', 'Đang sàng lọc', 'Mời phỏng vấn'], true)): ?>
                                <form method="post" action="<?= e(url('applicant-stage')) ?>" class="row g-1">
                                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($row['MaUV']) ?>">
                                    <div class="col-12">
                                        <select name="stage" class="form-select form-select-sm" required>
                                            <?php
                                            $nextStages = match ($row['TrangThai']) {
                                                'Mới' => ['Đang sàng lọc', 'Từ chối'],
                                                'Đang sàng lọc' => ['Mời phỏng vấn', 'Từ chối'],
                                                default => ['Đạt', 'Từ chối'],
                                            };
                                            ?>
                                            <?php foreach ($nextStages as $stage): ?><option value="<?= e($stage) ?>"><?= e($stage) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12"><input class="form-control form-control-sm" type="datetime-local" name="interview_at" aria-label="Lịch phỏng vấn"></div>
                                    <div class="col-12"><input class="form-control form-control-sm" name="interview_result" maxlength="4000" placeholder="Kết quả phỏng vấn"></div>
                                    <div class="col-12"><input class="form-control form-control-sm" name="note" maxlength="4000" placeholder="Nhận xét"></div>
                                    <div class="col-12"><button class="btn btn-sm btn-primary w-100" type="submit">Cập nhật giai đoạn</button></div>
                                </form>
                            <?php elseif ($row['TrangThai'] === 'Đạt'): ?>
                                <a class="btn btn-sm btn-success" href="<?= e(url('candidate-onboard')) ?>&id=<?= e($row['MaUV']) ?>">
                                    <i class="bi bi-person-check me-1"></i>Tiếp nhận thành nhân viên
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="<?= $canManage ? 6 : 5 ?>" class="text-center text-secondary py-4">Không tìm thấy ứng viên.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="small text-secondary">Tổng <?= e($total) ?> hồ sơ</span>
        <nav aria-label="Phân trang ứng viên">
            <?php for ($p = 1; $p <= $pageCount; $p++): ?>
                <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   href="<?= e(url('applicants')) ?>&page=<?= $p ?>&q=<?= rawurlencode($term) ?>&status=<?= rawurlencode($status) ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    </div>
</section>
