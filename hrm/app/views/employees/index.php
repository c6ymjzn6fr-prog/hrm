<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $personal ? 'Hồ sơ của tôi' : 'Nhân viên' ?></h1>
        <p class="text-secondary mb-0">Tìm kiếm và lọc hồ sơ theo phòng ban, trạng thái.</p>
    </div>
</div>
<section class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(APP_BASE_URL) ?>/index.php">
            <input type="hidden" name="route" value="employees">
            <div class="col-lg-5">
                <label class="form-label" for="employee-search">Tìm nhân viên</label>
                <input class="form-control" id="employee-search" name="q" value="<?= e($term) ?>" placeholder="Mã, họ tên, email, số điện thoại">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="employee-department">Phòng ban</label>
                <select class="form-select" id="employee-department" name="department">
                    <option value="">Tất cả phòng ban</option>
                    <?php foreach ($departments as $item): ?>
                        <option value="<?= e($item['MaPB']) ?>" <?= $department === $item['MaPB'] ? 'selected' : '' ?>><?= e($item['TenPB']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="employee-status">Trạng thái</label>
                <select class="form-select" id="employee-status" name="status">
                    <option value="">Tất cả</option>
                    <?php foreach (['Thử việc', 'Chính thức', 'Nghỉ việc'] as $item): ?>
                        <option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Tìm kiếm</button></div>
        </form>
    </div>
</section>
<section class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Mã / họ tên</th><th>Phòng ban</th><th>Chức vụ</th><th>Điện thoại / Email</th><th>Ngày vào làm</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $badge = $row['TrangThai'] === 'Chính thức' ? 'success' : ($row['TrangThai'] === 'Thử việc' ? 'warning' : 'secondary'); ?>
                <tr>
                    <td class="ps-3"><div class="fw-semibold"><?= e($row['HoTen']) ?></div><div class="small text-secondary"><?= e($row['MaNV']) ?></div></td>
                    <td><?= e($row['TenPB']) ?></td>
                    <td><?= e($row['TenCV']) ?></td>
                    <td><div><?= e($row['SoDienThoai']) ?></div><div class="small text-secondary"><?= e($row['Email']) ?></div></td>
                    <td><?= e(date('d/m/Y', strtotime((string) $row['NgayVaoLam']))) ?></td>
                    <td><span class="badge text-bg-<?= e($badge) ?>"><?= e($row['TrangThai']) ?></span></td>
                    <td class="text-end pe-3"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('employee-profile')) ?>&id=<?= e($row['MaNV']) ?>"><i class="bi bi-folder2-open me-1"></i>Hồ sơ</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-secondary py-4">Không tìm thấy nhân viên.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="small text-secondary">Tổng <?= e($total) ?> nhân viên</span>
        <nav aria-label="Phân trang nhân viên">
            <?php for ($p = 1; $p <= $pageCount; $p++): ?>
                <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   href="<?= e(url('employees')) ?>&page=<?= $p ?>&q=<?= rawurlencode($term) ?>&department=<?= rawurlencode($department) ?>&status=<?= rawurlencode($status) ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    </div>
</section>
