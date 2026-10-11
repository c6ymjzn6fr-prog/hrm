# Hệ thống quản lý nhân lực HT MEDIA

Nền tảng PHP thuần theo MVC tối giản, giao diện tiếng Việt, chạy với PHP 8.x và MySQL/MariaDB trong XAMPP.

## Cài đặt

1. Khởi động Apache và MySQL trong XAMPP.
2. Mở `http://localhost/phpmyadmin`, chọn **Import**, chọn tệp `database/schema.sql` rồi nhấn **Import/Go**. Tệp schema tự tạo CSDL `hrm_htmedia` và 16 bảng.
3. Sau khi schema chạy thành công, chọn CSDL `hrm_htmedia` ở cột bên trái, mở **Import**, chọn `database/seed.sql` rồi nhấn **Import/Go** để nạp dữ liệu mẫu. Chỉ nạp seed một lần trên CSDL mới, còn trống.
4. Cấu hình bằng biến môi trường `HRM_DB_HOST`, `HRM_DB_NAME`, `HRM_DB_USER`, `HRM_DB_PASSWORD` nếu môi trường không dùng thông số XAMPP mặc định.
5. Truy cập `http://localhost/hrm/`.

### Cấu trúc MVC

```text
app/
  controllers/   # Điều phối yêu cầu và kiểm tra quyền
  core/          # Router, PDO, xác thực, CSRF và tiện ích dùng chung
  models/        # Truy vấn dữ liệu bằng PDO
  views/         # Giao diện các phân hệ
config/
  database.php   # Cấu hình PDO qua biến môi trường hoặc mặc định XAMPP
database/
  migrations/    # Migration cho CSDL đã cài từ phiên bản trước
  schema.sql     # Tạo CSDL và 16 bảng
  seed.sql       # Dữ liệu mẫu
public/
  index.php      # Front controller; điều hướng bằng ?route=...
  assets/        # CSS, JavaScript và tài nguyên giao diện
uploads/         # Tệp tải lên, không truy cập trực tiếp qua web
```

Tài khoản demo (cùng mật khẩu **`HtMedia@2026`**):

| Vai trò | Tên đăng nhập |
|---|---|
| Quản trị hệ thống | `admin` |
| Nhân sự | `nhansu` |
| Quản lý | `quanly` |
| Kế toán | `ketoan` |
| Nhân viên | `nhanvien` |
| Giám đốc | `giamdoc` |

Mật khẩu được lưu dưới dạng bcrypt trong seed. Đây là thông tin demo công khai, không dùng trên môi trường thật; hãy đổi mật khẩu hoặc xóa dữ liệu mẫu trước khi triển khai.

## Phạm vi nền tảng hiện tại

Danh sách 22 ca kiểm thử đã chạy cùng hướng dẫn chạy lại: [TESTING.md](./TESTING.md).

- Schema InnoDB gồm 16 bảng nghiệp vụ và 2 bảng hỗ trợ quy trình/nhật ký (`PHEDUYET`, `NHATKY`), khóa ngoại, ràng buộc duy nhất và CHECK constraint.
- PDO với prepared statements; xác thực tài khoản, đổi session ID khi đăng nhập, CSRF cho mọi POST và khóa tài khoản 15 phút sau 5 lần đăng nhập sai.
- Đặt lại mật khẩu bằng token ngẫu nhiên lưu dạng SHA-256 trong CSDL, có hạn dùng một giờ và chỉ sử dụng một lần.
- Ma trận sáu vai trò dùng qua `Auth::can($chucNang, $hanhDong)` và middleware `Auth::requirePermission()` trong `app/core/Auth.php`.
- Trang tổng quan riêng cho Giám đốc, Nhân sự, Kế toán, Trưởng bộ phận, Nhân viên và Quản trị; Giám đốc có năm thẻ thống kê, biểu đồ phòng ban và hộp chờ duyệt.
- Người được đặt làm trưởng phòng qua `PHONGBAN.MaTruongBP` có thêm vai trò hiệu lực `quanly`; người có nhiều vai trò có thể chuyển vai trò tại thanh trên. Quản lý chỉ xem dữ liệu bộ phận mình và bản ghi cá nhân.
- `Scope::nhanVien()` tập trung điều kiện truy vấn danh sách; các trang chi tiết/duyệt kiểm tra phạm vi, truy cập bản ghi ngoài quyền trả HTTP 403. `admin` chỉ quản trị tài khoản và nhật ký, không có quyền xem dữ liệu nhân sự/lương.
- Các quy trình duyệt cấu hình tại `config/workflow.php`; lịch sử bước duyệt, người duyệt và lý do từ chối ghi ở `PHEDUYET`. Nhật ký thay đổi ghi tại `NHATKY`.
- Với nghỉ phép, tăng ca hoặc điều chỉnh công do chính trưởng bộ phận gửi, bước trưởng bộ phận được bỏ và chuyển người duyệt dự phòng sang Nhân sự để tránh tự duyệt.
- Luồng tuyển dụng: đề xuất, Giám đốc duyệt/từ chối, Nhân sự đăng tin; tiếp nhận CV, sàng lọc, phỏng vấn và kết quả.
- Tiếp nhận ứng viên đạt tạo nhân viên, hồ sơ CCCD, hợp đồng, tài khoản và (nếu chọn thử việc) hồ sơ thử việc trong một giao dịch.
- Danh sách nhân viên tìm kiếm/lọc/phân trang; hồ sơ theo tab thông tin cá nhân, hợp đồng, trình độ/tài liệu và lịch sử điều chuyển.
- Tệp PDF/JPG/PNG tối đa 5 MB lưu dưới `uploads/`; tên tệp ngẫu nhiên, kiểm tra MIME và chỉ tải xuống qua controller có kiểm quyền.
- CCCD được ràng buộc duy nhất; khi nhận ứng viên, hệ thống cảnh báo nếu số điện thoại đã có trong hồ sơ khác.
- Thử việc kiểm tra thời hạn tối đa theo trình độ/chức vụ; đánh giá đạt sẽ cập nhật nhân viên thành chính thức, còn kết quả không đạt được lưu trong hồ sơ.
- Chấm công cá nhân ghi nhận giờ vào/ra, chặn bản ghi trùng ngày; có bốn thẻ tổng hợp tháng, lịch sử và thao tác xác nhận cuối kỳ cho người quản lý.
- Nghỉ phép tính 12 ngày làm việc/năm (không tính cuối tuần), giữ chỗ đơn đang chờ duyệt và kiểm tra lại hạn mức khi phê duyệt.
- Tăng ca có đề nghị/phê duyệt, cảnh báo khi tổng giờ vượt 40 giờ/tháng hoặc 12 giờ/ngày.
- Bảng lương chọn kỳ đã kết thúc, lọc tính/xem/duyệt theo cá nhân, phòng ban hoặc toàn bộ phạm vi được cấp quyền; dừng và liệt kê nhân viên thiếu hợp đồng/chấm công đã chốt/tăng ca đã duyệt. Lương cơ bản tính theo ngày công và phép được duyệt, phụ cấp/thưởng/khấu trừ nhập trước khi tính, tăng ca áp hệ số 150%/200%/300%.
- Bảng lương chờ Giám đốc duyệt; hỗ trợ CSV tương thích Excel và phiếu lương có thể in. Nhân viên chỉ xem phiếu lương của mình.
- Sáu báo cáo nhân viên, tuyển dụng, thử việc, chấm công, tiền lương và nghỉ việc; lọc theo khoảng ngày/phòng ban; xuất CSV mở bằng Excel và in hoặc lưu PDF bằng hộp thoại in trình duyệt.
- Quản trị tài khoản chỉ dành cho `admin`: tạo tài khoản, đổi vai trò/trạng thái và xem ma trận quyền. Các thao tác POST và xuất báo cáo được ghi vào `NHATKY`.
- Đánh giá lưu kết quả, xếp loại và nhận xét. Đề xuất tăng lương được Giám đốc duyệt bằng cách đóng hợp đồng/mức lương cũ và tạo lịch sử hợp đồng mới.
- Điều chuyển chỉ cập nhật phòng ban/chức vụ sau phê duyệt và ghi lịch sử trong `DIEUCHUYEN`.
- Nghỉ việc theo năm bước; kiểm tra báo trước theo loại/thời hạn hợp đồng, chặn chốt lương trước bàn giao, chỉ nghỉ tài khoản sau khi lưu hồ sơ.

### Phân hệ tuyển dụng và nhân viên

- Tuyển dụng theo luồng đề xuất, Giám đốc phê duyệt/từ chối, Nhân sự đăng tin.
- Ứng viên có giai đoạn sàng lọc, lịch/kết quả phỏng vấn, nhận hồ sơ CV và thao tác tiếp nhận thành nhân viên.
- Tiếp nhận tạo nhân viên, hồ sơ CCCD, hợp đồng, tài khoản và hồ sơ thử việc (nếu chọn) trong một giao dịch.
- Nhân viên có tìm kiếm/lọc phòng ban/trạng thái và hồ sơ theo tab thông tin, hợp đồng, trình độ/tài liệu, lịch sử điều chuyển.
- Tệp đính kèm giới hạn PDF/JPG/PNG tối đa 5 MB, tải qua controller đã kiểm quyền; đường dẫn lưu trong `uploads/` bị chặn truy cập trực tiếp.
- Số CCCD duy nhất trong `HOSONHANVIEN`; hệ thống cảnh báo khi tiếp nhận số điện thoại ứng viên đã có.

### Cập nhật CSDL và email đặt lại mật khẩu

- Cài mới: chạy `database/schema.sql`.
- CSDL đã cài từ bản nền tảng trước: chạy một lần `database/migrations/20261007_add_password_reset_tokens.sql`.
- Sau đó chạy một lần `database/migrations/20261007_recruitment_employee_modules.sql` để thêm các cột/index/ràng buộc tuyển dụng. Không chạy migration lần thứ hai.
- CSDL đã cài từ trước phân hệ chấm công: chạy một lần `database/migrations/20261007_workforce_operations.sql` để thêm cờ xác nhận cuối kỳ vào bảng `CHAMCONG`. CSDL cài mới đã có sẵn cột này trong schema.
- CSDL đã cài từ trước các phân hệ lương/nhân sự: chạy một lần `database/migrations/20261008_payroll_personnel_workflows.sql` để thêm trạng thái phê duyệt, hệ số tăng ca và thông tin quy trình/lịch sử. Schema cài mới đã có sẵn các cột.
- CSDL đã cài từ trước báo cáo/quản trị: chạy một lần `database/migrations/20261008_reports_accounts_audit.sql` để thêm phòng ban cho tin tuyển dụng và bảng nhật ký. Schema cài mới đã có sẵn các cấu trúc này.
- CSDL đã chạy migration báo cáo/quản trị: tiếp tục chạy một lần `database/migrations/20261008_org_workflows.sql` để thêm trưởng bộ phận, người gửi, bảng `PHEDUYET` và `NHATKY`. Migration sao chép nhật ký cũ từ `NHATKYHOATDONG` nếu bảng tồn tại; dữ liệu nguồn vẫn được giữ. CSDL cài mới không cần chạy các migration trên.
- CSDL hiện hữu: chạy một lần `database/migrations/20261009_employee_dossier_groups.sql` để mở rộng hồ sơ nhân viên theo 4 nhóm nhân thân/xác minh, học tập/tuyển dụng, quá trình làm việc và chấm dứt hợp đồng. CSDL cài mới có sẵn các cột trong `database/schema.sql`.
- Sau khi nạp seed, trưởng các bộ phận mẫu được gán tại `PHONGBAN.MaTruongBP`. PB004 là Phòng Kho theo cơ cấu 5 bộ phận. PB004/NV009 và PB005/NV010 chưa có tài khoản mẫu; quản trị viên có thể tạo tài khoản từ màn hình **Quản lý tài khoản**, sau đó người dùng đăng nhập sẽ tự nhận quyền `quanly`. Khi triển khai dữ liệu thật, chỉ gán `MaTruongBP` cho đúng trưởng bộ phận và bảo đảm nhân viên đó thuộc đúng phòng ban.
- Đặt `HRM_MAIL_FROM` thành địa chỉ email gửi hợp lệ. Trên XAMPP, cấu hình SMTP/sendmail trong `php.ini` và `sendmail.ini` để PHP `mail()` gửi thư được. Nếu không gửi được, token được thu hồi và nguyên nhân được ghi vào PHP error log.

### Dữ liệu mẫu

`database/seed.sql` dành cho CSDL mới được tạo bằng schema ở trên. Script thêm 6 phòng ban (Ban Giám đốc và 5 bộ phận: Nhân sự, Kinh doanh, Kho, Công nghệ thông tin, Kế toán), 8 chức vụ, 30 nhân viên, 6 tài khoản (mỗi vai trò một tài khoản), tin tuyển dụng và ứng viên mẫu, hồ sơ thử việc, dữ liệu chấm công các ngày làm việc trong tháng liền trước và từ đầu tháng hiện tại đến ngày chạy seed, cùng một phiếu lương mẫu. Chỉ import seed một lần trên CSDL trống; không chạy lại trên CSDL đang có dữ liệu. Cần có hợp đồng và chấm công đủ/chốt kỳ thì mới tính được bảng lương cho kỳ đó.

Các hợp đồng giả lập được thêm cho 29 nhân viên còn thiếu trong CSDL hiện tại và các hợp đồng tạo bởi `database/seed.sql` được đánh dấu `DEMO`; chúng chỉ giúp trình diễn/kiểm thử luồng tính lương, tuyệt đối không dùng để trả lương hoặc hạch toán. Mức mẫu theo quy tắc seed (12.000.000 đ + 250.000 đ theo số thứ tự nhân viên; thử việc bằng 85% mức mẫu), không phải mức lương thực tế. Hợp đồng có sẵn của NV027 được giữ nguyên và chưa xác minh nguồn, cần đối chiếu chứng từ trước khi sử dụng. Khi thay bằng dữ liệu thật, đối chiếu hợp đồng đã ký và cập nhật từng hồ sơ ở tab **Hợp đồng**; ngày nhập theo `YYYY-MM-DD`, loại hợp đồng dùng `Thử việc`, `Xác định thời hạn` hoặc `Không xác định thời hạn`, lương là số VND/tháng. Chưa hỗ trợ nhập CSV hàng loạt.
