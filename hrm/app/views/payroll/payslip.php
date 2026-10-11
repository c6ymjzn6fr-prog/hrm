<?php
$money = static fn (mixed $value): string => number_format((float) $value, 0, ',', '.') . ' đ';
?>
<div class="d-print-none mb-3"><a href="<?= e(url('payroll')) ?>&month=<?= e(sprintf('%04d-%02d', $slip['Nam'], $slip['Thang'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại</a> <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>In phiếu lương</button></div>
<article class="card border-0 shadow-sm mx-auto" style="max-width: 800px">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4"><h1 class="h3">CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ HT MEDIA</h1><h2 class="h4 mt-3">PHIẾU LƯƠNG THÁNG <?= e(sprintf('%02d/%04d', $slip['Thang'], $slip['Nam'])) ?></h2></div>
        <dl class="row"><dt class="col-sm-3">Nhân viên</dt><dd class="col-sm-9"><?= e($slip['HoTen']) ?> (<?= e($slip['MaNV']) ?>)</dd><dt class="col-sm-3">Phòng ban</dt><dd class="col-sm-9"><?= e($slip['TenPB']) ?> · <?= e($slip['TenCV']) ?></dd><dt class="col-sm-3">Trạng thái</dt><dd class="col-sm-9"><span class="badge text-bg-<?= $slip['TrangThai'] === 'Đã duyệt' ? 'success' : 'warning' ?>"><?= e($slip['TrangThai']) ?></span></dd></dl>
        <table class="table table-bordered"><tbody>
            <tr><th>Lương cơ bản theo công</th><td class="text-end"><?= e($money($slip['LuongCoBan'])) ?></td></tr>
            <tr><th>Phụ cấp</th><td class="text-end"><?= e($money($slip['PhuCap'])) ?></td></tr>
            <tr><th>Thưởng</th><td class="text-end"><?= e($money($slip['Thuong'])) ?></td></tr>
            <tr><th>Tiền tăng ca</th><td class="text-end"><?= e($money($slip['TangCa'])) ?></td></tr>
            <tr><th>Khấu trừ</th><td class="text-end">− <?= e($money($slip['KhauTru'])) ?></td></tr>
            <tr class="table-light"><th>THỰC NHẬN</th><td class="text-end fw-bold fs-5"><?= e($money($slip['ThucNhan'])) ?></td></tr>
        </tbody></table>
        <?php if ($slip['TrangThai'] === 'Đã duyệt' && $slip['NgayPheDuyet']): ?><p class="small text-secondary">Ngày phê duyệt: <?= e(date('d/m/Y', strtotime((string) $slip['NgayPheDuyet']))) ?></p><?php endif; ?>
        <div class="row text-center mt-5"><div class="col">Người lập bảng</div><div class="col">Người lao động</div><div class="col">Giám đốc</div></div>
    </div>
</article>
<style>@media print { body { background: #fff !important; } .topbar, .sidebar, .d-print-none { display: none !important; } .app-shell, .main-content { display: block !important; margin: 0 !important; padding: 0 !important; } .card { box-shadow: none !important; } }</style>
