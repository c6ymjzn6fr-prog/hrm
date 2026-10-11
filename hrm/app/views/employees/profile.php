<?php
$dateText = static fn (mixed $value): string => $value ? date('d/m/Y', strtotime((string) $value)) : '—';
$moneyText = static fn (mixed $value): string => number_format((float) $value, 0, ',', '.') . ' đ';
$renderDocuments = static function (string $group) use ($profile, $canManage): string {
    $documents = $profile['TaiLieuTheoNhom'][$group] ?? [];
    ob_start();
    if ($canManage): ?>
        <form method="post" action="<?= e(url('employee-document-upload')) ?>" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="employee_id" value="<?= e($profile['MaNV']) ?>">
            <input type="hidden" name="document_group" value="<?= e($group) ?>">
            <div class="col-md-9"><label class="form-label">Tài liệu PDF, JPG hoặc PNG (tối đa 5 MB)</label><input type="file" class="form-control" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-upload me-1"></i>Tải lên</button></div>
        </form>
    <?php endif; ?>
    <div class="list-group list-group-flush">
        <?php foreach ($documents as $document): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between gap-3"
               href="<?= e(url('employee-document')) ?>&id=<?= e($profile['MaNV']) ?>&file=<?= rawurlencode((string) $document['stored']) ?>">
                <span><i class="bi bi-paperclip me-2"></i><?= e($document['original']) ?></span>
                <span class="small text-secondary"><?= e(number_format(((int) ($document['size'] ?? 0)) / 1024, 0, ',', '.')) ?> KB</span>
            </a>
        <?php endforeach; ?>
        <?php if ($documents === []): ?><div class="text-secondary py-2">Chưa có tài liệu trong nhóm này.</div><?php endif; ?>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <a href="<?= e(url('employees')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Danh sách nhân viên</a>
        <h1 class="h3 mt-2 mb-1"><?= e($profile['HoTen']) ?></h1>
        <p class="text-secondary mb-0"><?= e($profile['MaNV']) ?> · <?= e($profile['TenPB']) ?> · <?= e($profile['TenCV']) ?></p>
    </div>
    <span class="badge text-bg-<?= $profile['TrangThai'] === 'Chính thức' ? 'success' : ($profile['TrangThai'] === 'Thử việc' ? 'warning' : 'secondary') ?> px-3 py-2"><?= e($profile['TrangThai']) ?></span>
</div>
<ul class="nav nav-tabs mb-3" id="employee-profile-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" id="identity-tab" data-bs-toggle="tab" data-bs-target="#identity" type="button" role="tab">1. Nhân thân &amp; xác minh</button></li>
    <li class="nav-item"><button class="nav-link" id="education-tab" data-bs-toggle="tab" data-bs-target="#education" type="button" role="tab">2. Học tập &amp; tuyển dụng</button></li>
    <li class="nav-item"><button class="nav-link" id="work-tab" data-bs-toggle="tab" data-bs-target="#work" type="button" role="tab">3. Quá trình làm việc</button></li>
    <li class="nav-item"><button class="nav-link" id="termination-tab" data-bs-toggle="tab" data-bs-target="#termination" type="button" role="tab">4. Chấm dứt HĐLĐ</button></li>
</ul>
<div class="tab-content">
    <section class="tab-pane fade show active" id="identity" role="tabpanel" aria-labelledby="identity-tab">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Thông tin nhân thân và xác minh</h2></div>
            <div class="card-body p-4">
                <?php if ($canManage): ?>
                    <form method="post" action="<?= e(url('employee-profile-update')) ?>" class="row g-3">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="employee_id" value="<?= e($profile['MaNV']) ?>">
                        <input type="hidden" name="group" value="identity">
                        <div class="col-md-6"><label class="form-label">Họ và tên</label><input class="form-control" name="name" maxlength="150" value="<?= e($profile['HoTen']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Số CCCD</label><input class="form-control" name="identity" pattern="[0-9]{9}([0-9]{3})?" maxlength="12" value="<?= e($profile['SoCCCD'] ?? '') ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Ngày sinh</label><input class="form-control" name="birth_date" type="date" value="<?= e($profile['NgaySinh'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Nơi sinh</label><input class="form-control" name="birth_place" maxlength="150" value="<?= e($profile['NoiSinh'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Quốc tịch</label><input class="form-control" name="nationality" maxlength="80" value="<?= e($profile['QuocTich'] ?? 'Việt Nam') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Giới tính</label><select class="form-select" name="gender" required><?php foreach (['Nam', 'Nữ', 'Khác'] as $gender): ?><option value="<?= e($gender) ?>" <?= $profile['GioiTinh'] === $gender ? 'selected' : '' ?>><?= e($gender) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Tình trạng hôn nhân</label><select class="form-select" name="marital_status"><option value="">Chưa cập nhật</option><?php foreach (['Độc thân', 'Đã kết hôn', 'Khác'] as $status): ?><option value="<?= e($status) ?>" <?= ($profile['TinhTrangHonNhan'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Điện thoại</label><input class="form-control" name="phone" maxlength="20" value="<?= e($profile['SoDienThoai']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email" maxlength="150" value="<?= e($profile['Email']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Địa chỉ hiện tại/liên hệ</label><input class="form-control" name="address" maxlength="255" value="<?= e($profile['DiaChi']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Địa chỉ thường trú</label><input class="form-control" name="permanent_address" maxlength="255" value="<?= e($profile['DiaChiThuongTru'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Địa chỉ cư trú hiện nay</label><input class="form-control" name="current_address" maxlength="255" value="<?= e($profile['DiaChiHienTai'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Ngày cấp CCCD</label><input class="form-control" name="identity_issue_date" type="date" value="<?= e($profile['NgayCapCCCD'] ?? '') ?>"></div>
                        <div class="col-md-8"><label class="form-label">Nơi cấp CCCD</label><input class="form-control" name="identity_issue_place" maxlength="150" value="<?= e($profile['NoiCapCCCD'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Người liên hệ khẩn cấp</label><input class="form-control" name="emergency_name" maxlength="150" value="<?= e($profile['NguoiLienHeKhanCap'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Điện thoại liên hệ khẩn cấp</label><input class="form-control" name="emergency_phone" maxlength="20" value="<?= e($profile['SDTLienHeKhanCap'] ?? '') ?>"></div>
                        <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Lưu nhóm nhân thân</button></div>
                    </form>
                <?php else: ?>
                    <dl class="row mb-4">
                        <dt class="col-sm-3">CCCD</dt><dd class="col-sm-3"><?= e($profile['SoCCCD'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Ngày cấp / nơi cấp</dt><dd class="col-sm-3"><?= e($dateText($profile['NgayCapCCCD'] ?? null)) ?> · <?= e($profile['NoiCapCCCD'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Ngày sinh / nơi sinh</dt><dd class="col-sm-3"><?= e($dateText($profile['NgaySinh'])) ?> · <?= e($profile['NoiSinh'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Giới tính / quốc tịch</dt><dd class="col-sm-3"><?= e($profile['GioiTinh']) ?> · <?= e($profile['QuocTich'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Tình trạng hôn nhân</dt><dd class="col-sm-3"><?= e($profile['TinhTrangHonNhan'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Điện thoại / email</dt><dd class="col-sm-3"><?= e($profile['SoDienThoai']) ?> · <?= e($profile['Email']) ?></dd>
                        <dt class="col-sm-3">Địa chỉ thường trú</dt><dd class="col-sm-9"><?= e($profile['DiaChiThuongTru'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Địa chỉ hiện tại</dt><dd class="col-sm-9"><?= e($profile['DiaChiHienTai'] ?? $profile['DiaChi']) ?></dd>
                        <dt class="col-sm-3">Liên hệ khẩn cấp</dt><dd class="col-sm-9"><?= e($profile['NguoiLienHeKhanCap'] ?? '—') ?> · <?= e($profile['SDTLienHeKhanCap'] ?? '—') ?></dd>
                    </dl>
                <?php endif; ?>
                <?= $renderDocuments('identity') ?>
            </div>
        </div>
    </section>
    <section class="tab-pane fade" id="education" role="tabpanel" aria-labelledby="education-tab">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Hồ sơ học tập và tuyển dụng</h2></div>
            <div class="card-body p-4">
                <?php if ($canManage): ?>
                    <form method="post" action="<?= e(url('employee-profile-update')) ?>" class="row g-3 mb-4">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="employee_id" value="<?= e($profile['MaNV']) ?>">
                        <input type="hidden" name="group" value="education">
                        <div class="col-12"><label class="form-label">Trình độ, bằng cấp</label><textarea class="form-control" name="education" rows="3" maxlength="10000"><?= e($profile['TrinhDo'] ?? '') ?></textarea></div>
                        <div class="col-md-5"><label class="form-label">Chuyên ngành</label><input class="form-control" name="major" maxlength="150" value="<?= e($profile['ChuyenNganh'] ?? '') ?>"></div>
                        <div class="col-md-5"><label class="form-label">Trường đào tạo</label><input class="form-control" name="school" maxlength="200" value="<?= e($profile['TruongTotNghiep'] ?? '') ?>"></div>
                        <div class="col-md-2"><label class="form-label">Năm tốt nghiệp</label><input class="form-control" name="graduation_year" type="number" min="1900" max="<?= e((int) date('Y') + 10) ?>" value="<?= e($profile['NamTotNghiep'] ?? '') ?>"></div>
                        <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Lưu học vấn</button></div>
                    </form>
                <?php else: ?>
                    <dl class="row">
                        <dt class="col-sm-3">Trình độ</dt><dd class="col-sm-9"><?= nl2br(e($profile['TrinhDo'] ?? '—')) ?></dd>
                        <dt class="col-sm-3">Chuyên ngành</dt><dd class="col-sm-3"><?= e($profile['ChuyenNganh'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Trường / năm</dt><dd class="col-sm-3"><?= e($profile['TruongTotNghiep'] ?? '—') ?> · <?= e($profile['NamTotNghiep'] ?? '—') ?></dd>
                    </dl>
                <?php endif; ?>
                <h3 class="h6 mt-3">Nguồn tuyển dụng</h3>
                <?php if (!empty($profile['MaUV'])): ?>
                    <p class="mb-1">Ứng viên <?= e($profile['UngVienHoTen'] ?? '') ?> (<?= e($profile['MaUV']) ?>), vị trí <?= e($profile['ViTriUngTuyen'] ?? '—') ?>.</p>
                    <p class="small text-secondary">Ngày nộp hồ sơ: <?= e($dateText($profile['NgayUngTuyen'] ?? null)) ?> · Kết quả: <?= e($profile['TrangThaiUngTuyen'] ?? '—') ?></p>
                <?php else: ?><p class="text-secondary">Hồ sơ chưa liên kết với ứng viên trong hệ thống tuyển dụng.</p><?php endif; ?>
                <?= $renderDocuments('education') ?>
            </div>
        </div>
    </section>
    <section class="tab-pane fade" id="work" role="tabpanel" aria-labelledby="work-tab">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Thông tin trong quá trình làm việc</h2></div>
            <div class="card-body">
                <?php if ($canManage): ?>
                    <form method="post" action="<?= e(url('employee-profile-update')) ?>" class="row g-3 mb-4">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="employee_id" value="<?= e($profile['MaNV']) ?>">
                        <input type="hidden" name="group" value="work">
                        <div class="col-md-6"><label class="form-label">Phòng ban</label><select class="form-select" name="department" required><?php foreach ($profile['PhongBanList'] as $item): ?><option value="<?= e($item['MaPB']) ?>" <?= $profile['MaPB'] === $item['MaPB'] ? 'selected' : '' ?>><?= e($item['TenPB']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Chức vụ</label><select class="form-select" name="position" required><?php foreach ($profile['ChucVuList'] as $item): ?><option value="<?= e($item['MaCV']) ?>" <?= $profile['MaCV'] === $item['MaCV'] ? 'selected' : '' ?>><?= e($item['TenCV']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label">Mã số thuế</label><input class="form-control" name="tax_code" inputmode="numeric" pattern="[0-9]{10}([0-9]{3})?" maxlength="13" value="<?= e($profile['MaSoThue'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Số BHXH</label><input class="form-control" name="social_insurance" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="<?= e($profile['SoBHXH'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Số tài khoản nhận lương</label><input class="form-control" name="bank_account" inputmode="numeric" maxlength="34" value="<?= e($profile['SoTaiKhoan'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Ngân hàng</label><input class="form-control" name="bank_name" maxlength="150" value="<?= e($profile['NganHang'] ?? '') ?>"></div>
                        <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Cập nhật thông tin làm việc</button></div>
                    </form>
                <?php else: ?>
                    <p>Mã số thuế: <?= e($profile['MaSoThue'] ?? '—') ?> · Số BHXH: <?= e($profile['SoBHXH'] ?? '—') ?></p>
                    <p>Tài khoản nhận lương: <?= e($profile['SoTaiKhoan'] ?? '—') ?> · <?= e($profile['NganHang'] ?? '—') ?></p>
                <?php endif; ?>
                <h3 class="h6">Hợp đồng và mức lương</h3>
                <?php if ($canManage): ?>
                    <div class="alert alert-warning py-2 small">Hợp đồng mẫu hiện có mang dữ liệu DEMO, chỉ dùng kiểm thử; không dùng để trả lương hoặc hạch toán.</div>
                    <form method="post" action="<?= e(url('employee-contract-create')) ?>" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>"><input type="hidden" name="employee_id" value="<?= e($profile['MaNV']) ?>">
                        <div class="col-md-3"><label class="form-label">Loại hợp đồng</label><select class="form-select" name="contract_type" required><option value="Thử việc">Thử việc</option><option value="Xác định thời hạn">Xác định thời hạn</option><option value="Không xác định thời hạn">Không xác định thời hạn</option></select></div>
                        <div class="col-md-2"><label class="form-label">Ngày bắt đầu</label><input class="form-control" name="start_date" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
                        <div class="col-md-2"><label class="form-label">Ngày kết thúc</label><input class="form-control" name="end_date" type="date"></div>
                        <div class="col-md-3"><label class="form-label">Lương tháng (đ)</label><input class="form-control" name="salary" type="number" min="1" step="1000" required></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Lưu hợp đồng</button></div>
                    </form>
                <?php endif; ?>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th>Loại hợp đồng</th><th>Bắt đầu</th><th>Kết thúc</th><th>Mức lương</th><th>Trạng thái</th></tr></thead><tbody>
                    <?php foreach ($profile['HopDong'] as $contract): ?>
                        <tr><td><?= e($contract['LoaiHD']) ?><?php if (str_contains((string) ($contract['LyDoDieuChinh'] ?? ''), 'DỮ LIỆU GIẢ LẬP DEMO')): ?> <span class="badge text-bg-warning">DEMO</span><?php endif; ?></td><td><?= e($dateText($contract['NgayBatDau'])) ?></td><td><?= e($dateText($contract['NgayKetThuc'])) ?></td><td><?= e($moneyText($contract['MucLuong'])) ?></td><td><?= e($contract['TrangThai']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ($profile['HopDong'] === []): ?><tr><td colspan="5" class="text-secondary">Chưa có hợp đồng.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
                <h3 class="h6 mt-4">Lịch sử điều chuyển</h3>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th>Ngày</th><th>Bộ phận cũ → mới</th><th>Vị trí cũ → mới</th><th>Lý do</th></tr></thead><tbody>
                    <?php foreach ($profile['LichSu'] as $history): ?><tr><td><?= e($dateText($history['Ngay'])) ?></td><td><?= e($history['BoPhanCu']) ?> → <?= e($history['BoPhanMoi']) ?></td><td><?= e($history['ViTriCu']) ?> → <?= e($history['ViTriMoi']) ?></td><td><?= e($history['LyDo']) ?></td></tr><?php endforeach; ?>
                    <?php if ($profile['LichSu'] === []): ?><tr><td colspan="4" class="text-secondary">Chưa có lịch sử điều chuyển.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
                <h3 class="h6 mt-4">Đánh giá hiệu suất</h3>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th>Ngày</th><th>Kết quả</th><th>Xếp loại</th><th>Nhận xét</th></tr></thead><tbody>
                    <?php foreach ($profile['DanhGia'] as $review): ?><tr><td><?= e($dateText($review['NgayDanhGia'])) ?></td><td><?= e($review['KetQua']) ?></td><td><?= e($review['XepLoai']) ?></td><td><?= e($review['NhanXet']) ?></td></tr><?php endforeach; ?>
                    <?php if ($profile['DanhGia'] === []): ?><tr><td colspan="4" class="text-secondary">Chưa có đánh giá.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
                <h3 class="h6 mt-4">Tài liệu quá trình làm việc</h3>
                <?= $renderDocuments('work') ?>
            </div>
        </div>
    </section>
    <section class="tab-pane fade" id="termination" role="tabpanel" aria-labelledby="termination-tab">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Hồ sơ khi chấm dứt hợp đồng lao động</h2></div>
            <div class="card-body">
                <p class="text-secondary">Lưu đề nghị nghỉ việc, phê duyệt, bàn giao, chốt công/lương và xác nhận lưu hồ sơ theo quy trình.</p>
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th>Ngày đề nghị</th><th>Ngày nghỉ</th><th>Lý do</th><th>Trạng thái</th><th>Bàn giao</th><th>Chốt lương</th><th>Lưu hồ sơ</th></tr></thead><tbody>
                    <?php foreach ($profile['NghiViec'] as $request): ?>
                        <tr><td><?= e($dateText($request['NgayDeNghi'])) ?></td><td><?= e($dateText($request['NgayNghi'])) ?></td><td><?= e($request['LyDo']) ?></td>
                            <td><span class="badge text-bg-<?= $request['TrangThai'] === 'Lưu hồ sơ' ? 'secondary' : ($request['TrangThai'] === 'Từ chối' ? 'danger' : 'warning') ?>"><?= e($request['TrangThai']) ?></span></td>
                            <td><?= (int) $request['BanGiao'] ? e($dateText($request['NgayBanGiao'])) : 'Chưa hoàn tất' ?></td>
                            <td><?= (int) $request['LuongDaChot'] ? e($dateText($request['NgayChotLuong'])) : 'Chưa chốt' ?></td>
                            <td><?= e($dateText($request['NgayLuuHoSo'])) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ($profile['NghiViec'] === []): ?><tr><td colspan="7" class="text-secondary">Chưa có hồ sơ chấm dứt hợp đồng.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
                <h3 class="h6 mt-4">Biên bản bàn giao, quyết định và tài liệu chấm dứt</h3>
                <?= $renderDocuments('termination') ?>
                <div class="small text-secondary mt-3">Tệp được lưu riêng, phân nhóm hồ sơ và chỉ tải được qua quyền xem hồ sơ nhân viên.</div>
            </div>
        </div>
    </section>
</div>
<script>
    document.querySelectorAll('#employee-profile-tabs [data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', (event) => {
            const target = event.target.getAttribute('data-bs-target');
            if (target) {
                history.replaceState(null, '', target);
            }
        });
    });
    const profileHash = window.location.hash;
    if (profileHash) {
        const tab = document.querySelector(`#employee-profile-tabs [data-bs-target="${CSS.escape(profileHash)}"]`);
        if (tab) {
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }
    }
</script>
