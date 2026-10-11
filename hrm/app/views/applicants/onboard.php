<div class="mb-4">
    <a href="<?= e(url('applicants')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quay lại danh sách ứng viên</a>
    <h1 class="h3 mt-2 mb-1">Tiếp nhận thành nhân viên</h1>
    <p class="text-secondary mb-0">Ứng viên đạt: <strong><?= e($candidate['HoTen']) ?></strong> · <?= e($candidate['ViTriUngTuyen']) ?></p>
</div>
<form method="post" action="<?= e(url('applicant-onboard')) ?>">
    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= e($candidate['MaUV']) ?>">
    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Thông tin nhân viên và hồ sơ</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="identity">Số CCCD</label>
                            <input class="form-control" id="identity" name="identity" pattern="[0-9]{9}([0-9]{3})?" maxlength="12" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="birth-date">Ngày sinh</label>
                            <input class="form-control" id="birth-date" name="birth_date" type="date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gender">Giới tính</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Chọn</option><option>Nam</option><option>Nữ</option><option>Khác</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="address">Địa chỉ</label>
                            <input class="form-control" id="address" name="address" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="department">Phòng ban</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Chọn phòng ban</option>
                                <?php foreach ($departments as $department): ?><option value="<?= e($department['MaPB']) ?>"><?= e($department['TenPB']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="position">Chức vụ</label>
                            <select class="form-select" id="position" name="position" required>
                                <option value="">Chọn chức vụ</option>
                                <?php foreach ($positions as $position): ?><option value="<?= e($position['MaCV']) ?>"><?= e($position['TenCV']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="education">Trình độ</label>
                            <input class="form-control" id="education" name="education" maxlength="1000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="start-date">Ngày bắt đầu làm việc</label>
                            <input class="form-control" id="start-date" name="start_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="employee-status">Trạng thái</label>
                            <select class="form-select" id="employee-status" name="employee_status" required>
                                <option value="Thử việc">Thử việc</option><option value="Chính thức">Chính thức</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contract-type">Loại hợp đồng</label>
                            <select class="form-select" id="contract-type" name="contract_type" required>
                                <option value="Thử việc">Thử việc</option>
                                <option value="Xác định thời hạn">Xác định thời hạn</option>
                                <option value="Không xác định thời hạn">Không xác định thời hạn</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contract-end">Ngày kết thúc hợp đồng (nếu có)</label>
                            <input class="form-control" id="contract-end" name="contract_end" type="date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="salary">Mức lương hợp đồng (đ)</label>
                            <input class="form-control" id="salary" name="salary" type="number" min="1" step="1000" required>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Tài khoản nhân viên</h2>
                    <div class="alert alert-info small">Tài khoản sẽ được tạo cùng nhân viên trong một giao dịch. Vai trò mặc định là Nhân viên. Nếu chọn thử việc, thời hạn tối đa được tính theo trình độ và chức vụ: 180, 60, 30 hoặc 6 ngày.</div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Tên đăng nhập</label>
                        <input class="form-control" id="username" name="username" minlength="4" maxlength="50" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu ban đầu</label>
                        <input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                        <div class="form-text">Ít nhất 12 ký tự. Chia sẻ mật khẩu an toàn cho nhân viên.</div>
                    </div>
                    <button class="btn btn-success w-100" type="submit">
                        <i class="bi bi-person-check me-1"></i>Tạo nhân viên và tài khoản
                    </button>
                </div>
            </section>
        </div>
    </div>
</form>
