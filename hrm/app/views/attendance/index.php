<?php
use App\Core\Auth;

$today = $attendance['today'];
$summary = $attendance['summary'];
$dateText = static fn (mixed $value): string => $value ? date('d/m/Y', strtotime((string) $value)) : '—';
$timeText = static fn (mixed $value): string => $value ? date('H:i', strtotime((string) $value)) : '—';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Chấm công</h1>
        <p class="text-secondary mb-0"><?= $canManage ? 'Xem và xác nhận kỳ chấm công.' : 'Chấm vào/ra và theo dõi lịch sử của bạn.' ?></p>
    </div>
    <?php if ($canPunch): ?>
        <div class="d-flex gap-2">
            <?php if ($today === null): ?>
                <form method="post" action="<?= e(url('attendance-check-in')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                    <button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Chấm vào</button>
                </form>
            <?php elseif ($today['GioVao'] !== null && $today['GioRa'] === null && !(int) $today['DaChot']): ?>
                <form method="post" action="<?= e(url('attendance-check-out')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Chấm ra</button>
                </form>
            <?php else: ?>
                <span class="badge text-bg-secondary align-self-center">Hôm nay đã chấm công</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ($canManage): ?>
    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="<?= e(APP_BASE_URL) ?>/index.php" class="row g-2 align-items-end">
                <input type="hidden" name="route" value="attendance">
                <div class="col-md-4">
                    <label class="form-label" for="attendance-employee">Nhân viên</label>
                    <select class="form-select" id="attendance-employee" name="employee_id">
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= e($employee['MaNV']) ?>" <?= $employeeId === $employee['MaNV'] ? 'selected' : '' ?>><?= e($employee['HoTen']) ?> (<?= e($employee['MaNV']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label" for="attendance-month">Tháng</label><input class="form-control" id="attendance-month" type="month" name="month" value="<?= e($month) ?>" required></div>
                <div class="col-md-3"><button class="btn btn-outline-primary" type="submit">Xem tổng hợp</button></div>
            </form>
        </div>
    </section>
<?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Ngày có chấm công</div><div class="h3 mb-0"><?= e($summary['days']) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Ngày đủ giờ vào/ra</div><div class="h3 text-success mb-0"><?= e($summary['complete']) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Bản ghi còn thiếu giờ</div><div class="h3 text-warning mb-0"><?= e($summary['incomplete']) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Tăng ca đã duyệt trong tháng</div><div class="h3 mb-0"><?= e(number_format($summary['overtime'], 2, ',', '.')) ?> <span class="fs-6">giờ</span></div></div></div></div>
</div>
<section class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Lịch sử tháng <?= e($month) ?></h2>
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('attendance-confirm')) ?>" onsubmit="return confirm('Xác nhận cuối kỳ chấm công cho toàn bộ nhân viên trong tháng này?')">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Xác nhận cuối kỳ</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Ngày</th><th>Giờ vào</th><th>Giờ ra</th><th>Trạng thái</th><th>Xác nhận kỳ</th><?php if ($canRequestCorrection && $employeeId === (string) (Auth::user()['MaNV'] ?? '')): ?><th>Điều chỉnh</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($attendance['history'] as $row): ?>
                <tr><td class="ps-3"><?= e($dateText($row['Ngay'])) ?></td><td><?= e($timeText($row['GioVao'])) ?></td><td><?= e($timeText($row['GioRa'])) ?></td>
                    <td><span class="badge text-bg-<?= $row['GioVao'] !== null && $row['GioRa'] !== null ? 'success' : 'warning' ?>"><?= e($row['TrangThai']) ?></span></td>
                    <td><span class="badge text-bg-<?= (int) $row['DaChot'] ? 'secondary' : 'warning' ?>"><?= (int) $row['DaChot'] ? 'Đã xác nhận' : 'Chưa xác nhận' ?></span></td>
                    <?php if ($canRequestCorrection && $employeeId === (string) (Auth::user()['MaNV'] ?? '')): ?>
                        <td><?php if (!(int) $row['DaChot']): ?>
                            <details><summary class="small text-primary">Yêu cầu</summary>
                                <form method="post" action="<?= e(url('attendance-correction')) ?>" class="d-grid gap-1 mt-2">
                                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($row['MaCC']) ?>">
                                    <input class="form-control form-control-sm" type="time" name="check_in" aria-label="Giờ vào mới" value="<?= e($row['GioVao'] ? date('H:i', strtotime((string) $row['GioVao'])) : '') ?>">
                                    <input class="form-control form-control-sm" type="time" name="check_out" aria-label="Giờ ra mới" value="<?= e($row['GioRa'] ? date('H:i', strtotime((string) $row['GioRa'])) : '') ?>">
                                    <input class="form-control form-control-sm" name="reason" maxlength="1000" placeholder="Lý do điều chỉnh" required>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Gửi</button>
                                </form>
                            </details>
                        <?php else: ?>—<?php endif; ?></td>
                    <?php endif; ?></tr>
            <?php endforeach; ?>
            <?php if ($attendance['history'] === []): ?><tr><td colspan="<?= $canRequestCorrection ? 6 : 5 ?>" class="text-center text-secondary py-4">Chưa có dữ liệu chấm công trong tháng này.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($canApproveCorrection): ?>
    <section class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Điều chỉnh công chờ duyệt</h2></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Nhân viên</th><th>Ngày</th><th>Nội dung yêu cầu</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($corrections as $correction): ?>
                <?php $payload = json_decode((string) $correction['DuLieu'], true) ?: []; ?>
                <tr><td class="ps-3"><?= e($correction['HoTen']) ?></td><td><?= e($dateText($correction['Ngay'])) ?></td>
                    <td>Vào: <?= e($payload['GioVao'] ?? 'Không đổi') ?>; ra: <?= e($payload['GioRa'] ?? 'Không đổi') ?><div class="small text-secondary"><?= e($payload['LyDo'] ?? '') ?></div></td>
                    <td><?php if (Auth::hasRole((string) $correction['BuocTiepTheo'])
                        && $correction['NguoiGui'] !== (Auth::user()['MaNV'] ?? null)): ?><div class="d-flex gap-1">
                        <form method="post" action="<?= e(url('attendance-correction-decide')) ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($correction['MaCC']) ?>"><button class="btn btn-sm btn-success" name="decision" value="Đã duyệt" type="submit">Duyệt</button></form>
                        <form method="post" action="<?= e(url('attendance-correction-decide')) ?>" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="id" value="<?= e($correction['MaCC']) ?>"><input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Lý do từ chối" required><button class="btn btn-sm btn-outline-danger" name="decision" value="Từ chối" type="submit">Từ chối</button></form>
                    </div><?php else: ?>Chờ <?= e($correction['BuocTiepTheo']) ?><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if ($corrections === []): ?><tr><td colspan="4" class="text-center text-secondary py-3">Không có yêu cầu chờ duyệt.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
<?php endif; ?>
