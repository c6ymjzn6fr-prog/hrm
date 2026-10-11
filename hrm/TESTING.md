# Kiểm thử HT MEDIA

## Kết quả chạy

Chạy toàn bộ `tests/run.php` trên MariaDB 10.4.32 với CSDL kiểm thử riêng được tạo từ `database/schema.sql` và `database/seed.sql`. **Kết quả: 33/33 ca đạt.** CSDL kiểm thử đã được xóa sau khi chạy; không dùng hoặc cập nhật dữ liệu nghiệp vụ trong `hrm_htmedia`.

Smoke-test giao diện hồ sơ bằng trình duyệt với tài khoản Nhân sự: trang danh sách và trang hồ sơ trả thành công; cả bốn tab hiển thị được nội dung, biểu mẫu và dữ liệu lịch sử tương ứng. Không lưu dữ liệu hoặc tải tệp trong lần kiểm tra.

## Danh sách ca kiểm thử

| # | Ca kiểm thử | Kết quả |
|---:|---|---|
| 1 | Xác minh mật khẩu demo và từ chối mật khẩu sai | Đạt |
| 2 | Khóa tài khoản sau năm lần đăng nhập sai | Đạt |
| 3 | Tạo tài khoản, kiểm tra vai trò, trùng tên đăng nhập và cập nhật vai trò | Đạt |
| 4 | Ghi nhật ký hoạt động trong transaction | Đạt |
| 5 | Quản trị viên quản lý tài khoản | Đạt |
| 6 | Người không phải quản trị viên không được quản lý tài khoản | Đạt |
| 7 | Quản lý được đề xuất nhưng không phê duyệt tuyển dụng | Đạt |
| 8 | Giám đốc được phê duyệt nhưng không quản lý/đăng tin tuyển dụng | Đạt |
| 9 | Nhân viên chỉ có quyền chấm công cá nhân | Đạt |
| 10 | Trưởng bộ phận nhận vai trò hiệu lực `quanly` | Đạt |
| 11 | Scope trưởng bộ phận giới hạn đúng bộ phận và bản thân | Đạt |
| 12 | Truy vấn danh sách nhân viên của nghiệp vụ đúng scope | Đạt |
| 13 | Hồ sơ nhân viên trả đủ bốn nhóm hồ sơ và lịch sử liên quan | Đạt |
| 14 | `admin` không có phạm vi xem dữ liệu nhân viên | Đạt |
| 15 | Chuỗi phê duyệt được đọc từ cấu hình | Đạt |
| 16 | Dashboard của sáu vai trò tải dữ liệu | Đạt |
| 17 | Trưởng bộ phận phê duyệt đơn nghỉ phép của nhân viên trong bộ phận | Đạt |
| 18 | Chặn tự phê duyệt và chuyển đơn của trưởng bộ phận cho Nhân sự | Đạt |
| 19 | Báo cáo cá nhân không để lộ dữ liệu nhân viên khác | Đạt |
| 20 | Đăng ký đủ sáu loại báo cáo | Đạt |
| 21 | Báo cáo nhân viên lọc theo ngày và phòng ban | Đạt |
| 22 | Báo cáo tuyển dụng lọc theo phòng ban | Đạt |
| 23 | Báo cáo thử việc lọc theo phòng ban | Đạt |
| 24 | Báo cáo chấm công lọc theo phòng ban và kỳ | Đạt |
| 25 | Báo cáo lương lọc theo kỳ và phòng ban | Đạt |
| 26 | Báo cáo nghỉ việc trả đúng cấu trúc | Đạt |
| 27 | Từ chối loại báo cáo không hợp lệ | Đạt |
| 28 | Sẵn sàng tính lương liệt kê nhân viên thiếu công đã chốt | Đạt |
| 29 | Lọc mức độ sẵn sàng tính lương theo nhân viên/phòng ban | Đạt |
| 30 | Chặn kỳ lương hiện tại và tương lai | Đạt |
| 31 | Dừng tính lương khi còn thiếu dữ liệu | Đạt |
| 32 | Từ chối chấm công trùng nhân viên/ngày | Đạt |
| 33 | Chặn đề nghị nghỉ việc không đủ thời hạn báo trước | Đạt |

## Chạy lại bộ kiểm thử

1. Tạo một CSDL kiểm thử riêng, ví dụ `hrm_test_local`; không trỏ biến kiểm thử vào `hrm_htmedia`.
2. Import `database/schema.sql` và `database/seed.sql` vào CSDL kiểm thử.
3. Trong PowerShell, đặt tên CSDL kiểm thử và chạy:

```powershell
$env:HRM_DB_NAME = 'hrm_test_local'
$env:HRM_TEST_DB = 'hrm_test_local'
C:\xampp\php\php.exe tests\run.php
```

Script từ chối chạy nếu `HRM_TEST_DB` không có tiền tố `hrm_test_`, nếu khác `HRM_DB_NAME`, hoặc nếu CSDL kết nối thực tế không trùng tên đã khai báo. Các thao tác thay đổi dữ liệu trong bộ kiểm thử được thực hiện trong transaction và rollback.

## Ghi chú kiểm thử giao diện

- **Xuất Excel:** tải CSV UTF-8 có BOM để mở bằng Excel.
- **In PDF:** chọn **In / Lưu PDF**, sau đó chọn máy in **Save as PDF / Microsoft Print to PDF** trong trình duyệt.
- Dữ liệu tuyển dụng gắn phòng ban qua `TUYENDUNG.MaPB`; với CSDL nâng cấp, các đề xuất cũ giữ `MaPB = NULL` đến khi được phân công.
