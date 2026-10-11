<style>
@media print {
    .topbar, .sidebar, .report-controls, .report-actions, .alert { display: none !important; }
    .app-shell, .main-content { display: block !important; margin: 0 !important; padding: 0 !important; }
    .card { border: 0 !important; box-shadow: none !important; }
    body { background: #fff !important; font-size: 10pt; }
    table { width: 100%; border-collapse: collapse !important; }
    th, td { border: 1px solid #555 !important; padding: .3rem !important; }
}
</style>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($report['title']) ?></h1>
        <p class="text-secondary mb-0">Lọc dữ liệu theo thời gian và phòng ban. Dùng lệnh in của trình duyệt để lưu thành PDF.</p>
    </div>
    <div class="report-actions d-flex gap-2">
        <a class="btn btn-outline-success"
           href="<?= e(url('reports-export')) ?>&type=<?= e($type) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&department=<?= e($department) ?>">
            <i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel (CSV)
        </a>
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()">
            <i class="bi bi-file-earmark-pdf me-1"></i>In / Lưu PDF
        </button>
    </div>
</div>
<section class="card border-0 shadow-sm mb-4 report-controls">
    <div class="card-body">
        <form method="get" action="<?= e(APP_BASE_URL) ?>/index.php" class="row g-3 align-items-end">
            <input type="hidden" name="route" value="reports">
            <div class="col-md-4">
                <label class="form-label" for="report-type">Loại báo cáo</label>
                <select class="form-select" id="report-type" name="type">
                    <?php foreach ($reportTypes as $item): ?>
                        <option value="<?= e($item['key']) ?>" <?= $type === $item['key'] ? 'selected' : '' ?>><?= e($item['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="report-from">Từ ngày</label>
                <input class="form-control" id="report-from" type="date" name="from" value="<?= e($from) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="report-to">Đến ngày</label>
                <input class="form-control" id="report-to" type="date" name="to" value="<?= e($to) ?>" required>
            </div>
            <?php if (!$personal): ?>
                <div class="col-md-3">
                    <label class="form-label" for="report-department">Phòng ban</label>
                    <select class="form-select" id="report-department" name="department">
                        <option value="">Tất cả phòng ban</option>
                        <?php foreach ($departments as $item): ?>
                            <option value="<?= e($item['MaPB']) ?>" <?= $department === $item['MaPB'] ? 'selected' : '' ?>><?= e($item['TenPB']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-1 d-grid"><button class="btn btn-primary" type="submit">Lọc</button></div>
        </form>
    </div>
</section>
<section class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong><?= e($report['title']) ?></strong>
        <span class="small text-secondary"><?= e(date('d/m/Y', strtotime($from))) ?> – <?= e(date('d/m/Y', strtotime($to))) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><?php foreach ($report['headers'] as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <?php foreach ($row as $index => $cell): ?>
                        <?php
                        $value = $cell;
                        if (is_string($cell) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cell) === 1) {
                            $value = date('d/m/Y', strtotime($cell));
                        } elseif ($type === 'payroll' && $index >= 4 && $index <= 9) {
                            $value = number_format((float) $cell, 0, ',', '.') . ' đ';
                        }
                        ?>
                        <td><?= e($value) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($report['rows'] === []): ?>
                <tr><td class="text-center text-secondary py-4" colspan="<?= count($report['headers']) ?>">Không có dữ liệu phù hợp với bộ lọc.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-secondary">Tổng <?= count($report['rows']) ?> dòng dữ liệu<?= $personal ? ' cá nhân' : '' ?>.</div>
</section>
