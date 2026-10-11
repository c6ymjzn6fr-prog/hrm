USE hrm_htmedia;

SET NAMES utf8mb4;
INSERT INTO PHONGBAN (MaPB, TenPB) VALUES
('PB001', 'Ban Giám đốc'),
('PB002', 'Phòng Nhân sự'),
('PB003', 'Phòng Kinh doanh'),
('PB004', 'Phòng Kho'),
('PB005', 'Phòng Công nghệ thông tin'),
('PB006', 'Phòng Kế toán');

INSERT INTO CHUCVU (MaCV, TenCV) VALUES
('CV001', 'Giám đốc'),
('CV002', 'Trưởng phòng Nhân sự'),
('CV003', 'Chuyên viên Nhân sự'),
('CV004', 'Trưởng phòng Kinh doanh'),
('CV005', 'Nhân viên Kinh doanh'),
('CV006', 'Nhân viên Kho'),
('CV007', 'Lập trình viên'),
('CV008', 'Kế toán viên');

INSERT INTO NHANVIEN
(MaNV, HoTen, NgaySinh, GioiTinh, SoDienThoai, Email, DiaChi, NgayVaoLam, TrangThai, MaPB, MaCV)
VALUES
('NV001', 'Nguyễn Minh Anh', '1985-03-12', 'Nam', '0900000001', 'admin@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 8 YEAR), 'Chính thức', 'PB001', 'CV001'),
('NV002', 'Trần Thu Hà', '1990-07-21', 'Nữ', '0900000002', 'nhansu@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 6 YEAR), 'Chính thức', 'PB002', 'CV002'),
('NV003', 'Lê Quốc Bảo', '1994-02-08', 'Nam', '0900000003', 'quanly@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 5 YEAR), 'Chính thức', 'PB003', 'CV004'),
('NV004', 'Phạm Ngọc Lan', '1992-11-15', 'Nữ', '0900000004', 'ketoan@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 4 YEAR), 'Chính thức', 'PB006', 'CV008'),
('NV005', 'Đỗ Hoàng Nam', '1998-05-30', 'Nam', '0900000005', 'nhanvien@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR), 'Chính thức', 'PB003', 'CV005'),
('NV006', 'Vũ Thanh Tùng', '1982-09-02', 'Nam', '0900000006', 'giamdoc@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 9 YEAR), 'Chính thức', 'PB001', 'CV001'),
('NV007', 'Nguyễn Thị Mai', '1995-01-18', 'Nữ', '0900000007', 'nv007@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 3 YEAR), 'Chính thức', 'PB002', 'CV003'),
('NV008', 'Trần Văn Huy', '1997-04-25', 'Nam', '0900000008', 'nv008@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR), 'Chính thức', 'PB003', 'CV005'),
('NV009', 'Lê Thị Hồng', '1996-06-09', 'Nữ', '0900000009', 'nv009@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR), 'Chính thức', 'PB004', 'CV006'),
('NV010', 'Phạm Đức Long', '1993-08-11', 'Nam', '0900000010', 'nv010@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 4 YEAR), 'Chính thức', 'PB005', 'CV007'),
('NV011', 'Đặng Thu Trang', '1999-12-01', 'Nữ', '0900000011', 'nv011@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 10 MONTH), 'Chính thức', 'PB006', 'CV008'),
('NV012', 'Bùi Quang Vinh', '1998-03-19', 'Nam', '0900000012', 'nv012@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 8 MONTH), 'Chính thức', 'PB004', 'CV006'),
('NV013', 'Hoàng Thị Ngọc', '2000-10-05', 'Nữ', '0900000013', 'nv013@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH), 'Chính thức', 'PB003', 'CV005'),
('NV014', 'Phan Minh Đức', '1996-02-27', 'Nam', '0900000014', 'nv014@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 4 MONTH), 'Chính thức', 'PB005', 'CV007'),
('NV015', 'Đỗ Khánh Linh', '2001-07-14', 'Nữ', '0900000015', 'nv015@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 70 DAY), 'Thử việc', 'PB002', 'CV003'),
('NV016', 'Nguyễn Tiến Đạt', '1999-09-23', 'Nam', '0900000016', 'nv016@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 60 DAY), 'Thử việc', 'PB003', 'CV005'),
('NV017', 'Trần Bảo Châu', '2000-05-17', 'Nữ', '0900000017', 'nv017@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 50 DAY), 'Thử việc', 'PB004', 'CV006'),
('NV018', 'Lê Anh Khoa', '1997-11-28', 'Nam', '0900000018', 'nv018@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 40 DAY), 'Thử việc', 'PB005', 'CV007'),
('NV019', 'Phạm Thùy Dương', '1998-01-07', 'Nữ', '0900000019', 'nv019@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY), 'Thử việc', 'PB006', 'CV008'),
('NV020', 'Đỗ Thành Công', '1995-12-13', 'Nam', '0900000020', 'nv020@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 20 DAY), 'Thử việc', 'PB003', 'CV005'),
('NV021', 'Vũ Ngọc Ánh', '2001-04-03', 'Nữ', '0900000021', 'nv021@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 15 DAY), 'Thử việc', 'PB004', 'CV006'),
('NV022', 'Nguyễn Hoài Phương', '1999-06-22', 'Nữ', '0900000022', 'nv022@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 12 DAY), 'Thử việc', 'PB002', 'CV003'),
('NV023', 'Trần Nhật Minh', '1997-10-16', 'Nam', '0900000023', 'nv023@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 3 YEAR), 'Chính thức', 'PB005', 'CV007'),
('NV024', 'Lê Thuỳ An', '1994-08-04', 'Nữ', '0900000024', 'nv024@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 3 YEAR), 'Chính thức', 'PB006', 'CV008'),
('NV025', 'Phạm Gia Hưng', '1993-05-26', 'Nam', '0900000025', 'nv025@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR), 'Chính thức', 'PB003', 'CV005'),
('NV026', 'Đặng Mỹ Linh', '1998-12-19', 'Nữ', '0900000026', 'nv026@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR), 'Chính thức', 'PB004', 'CV006'),
('NV027', 'Bùi Đức Anh', '1996-07-08', 'Nam', '0900000027', 'nv027@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 18 MONTH), 'Chính thức', 'PB005', 'CV007'),
('NV028', 'Hoàng Minh Châu', '1995-03-31', 'Nữ', '0900000028', 'nv028@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 15 MONTH), 'Chính thức', 'PB002', 'CV003'),
('NV029', 'Phan Quốc Thịnh', '1992-01-24', 'Nam', '0900000029', 'nv029@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR), 'Chính thức', 'PB003', 'CV005'),
('NV030', 'Đỗ Phương Uyên', '2000-09-10', 'Nữ', '0900000030', 'nv030@htmedia.local', 'Hà Nội', DATE_SUB(CURRENT_DATE(), INTERVAL 8 MONTH), 'Chính thức', 'PB004', 'CV006');

UPDATE PHONGBAN SET MaTruongBP = CASE MaPB
    WHEN 'PB002' THEN 'NV002'
    WHEN 'PB003' THEN 'NV003'
    WHEN 'PB004' THEN 'NV009'
    WHEN 'PB005' THEN 'NV010'
    WHEN 'PB006' THEN 'NV004'
    ELSE NULL
END
WHERE MaPB IN ('PB002', 'PB003', 'PB004', 'PB005', 'PB006');

INSERT INTO TUYENDUNG
(MaTD, ViTri, SoLuong, MaPB, YeuCau, NgayDang, TrangThai, DeXuatBoi, NguoiPheDuyet, NgayPheDuyet)
VALUES
('TD001', 'Chuyên viên tuyển dụng', 1, 'PB002', 'Tốt nghiệp cao đẳng trở lên, giao tiếp tốt.', CURRENT_DATE(), 'Đang tuyển', 'NV002', 'NV006', CURRENT_DATE()),
('TD002', 'Nhân viên kinh doanh', 3, 'PB003', 'Có kỹ năng tư vấn và chăm sóc khách hàng.', CURRENT_DATE(), 'Đang tuyển', 'NV003', 'NV006', CURRENT_DATE()),
('TD003', 'Lập trình viên PHP', 2, 'PB005', 'Có kiến thức PHP, MySQL và Git.', CURRENT_DATE(), 'Chờ phê duyệt', 'NV003', NULL, NULL),
('TD004', 'Chuyên viên truyền thông', 1, 'PB004', 'Có kinh nghiệm xây dựng nội dung số.', CURRENT_DATE(), 'Đang tuyển', 'NV002', 'NV006', CURRENT_DATE());

INSERT INTO UNGVIEN
(MaUV, HoTen, SoDienThoai, Email, ViTriUngTuyen, TrangThai, MaTD, NgayNop, HoSoCV)
VALUES
('UV001', 'Nguyễn Hải Yến', '0988000001', 'yen.nguyen@example.com', 'Chuyên viên tuyển dụng', 'Mới', 'TD001', CURRENT_DATE(), NULL),
('UV002', 'Trần Đức Phúc', '0988000002', 'phuc.tran@example.com', 'Nhân viên kinh doanh', 'Đang sàng lọc', 'TD002', DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY), NULL),
('UV003', 'Phạm Ngọc Hân', '0988000003', 'han.pham@example.com', 'Nhân viên kinh doanh', 'Mời phỏng vấn', 'TD002', DATE_SUB(CURRENT_DATE(), INTERVAL 4 DAY), NULL),
('UV004', 'Lê Minh Quân', '0988000004', 'quan.le@example.com', 'Chuyên viên tuyển dụng', 'Đạt', 'TD001', DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY), NULL),
('UV005', 'Đỗ Thùy Chi', '0988000005', 'chi.do@example.com', 'Chuyên viên tuyển dụng', 'Từ chối', 'TD001', DATE_SUB(CURRENT_DATE(), INTERVAL 8 DAY), NULL);

INSERT INTO THUVIEC (MaTV, MaNV, NgayBatDau, NgayKetThuc, KetQua, NhanXet) VALUES
('TV001', 'NV015', DATE_SUB(CURRENT_DATE(), INTERVAL 70 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 5 DAY), NULL, NULL),
('TV002', 'NV016', DATE_SUB(CURRENT_DATE(), INTERVAL 60 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 10 DAY), NULL, NULL),
('TV003', 'NV017', DATE_SUB(CURRENT_DATE(), INTERVAL 50 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 15 DAY), NULL, NULL),
('TV004', 'NV018', DATE_SUB(CURRENT_DATE(), INTERVAL 40 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 20 DAY), NULL, NULL),
('TV005', 'NV019', DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), NULL, NULL),
('TV006', 'NV020', DATE_SUB(CURRENT_DATE(), INTERVAL 20 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY), NULL, NULL),
('TV007', 'NV021', DATE_SUB(CURRENT_DATE(), INTERVAL 15 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY), NULL, NULL),
('TV008', 'NV022', DATE_SUB(CURRENT_DATE(), INTERVAL 12 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 6 DAY), NULL, NULL);

INSERT INTO HOPDONG
(MaHD, MaNV, LoaiHD, NgayBatDau, NgayKetThuc, MucLuong, TrangThai, LyDoDieuChinh)
SELECT
    CONCAT('HD', RIGHT(nv.MaNV, 3)),
    nv.MaNV,
    CASE WHEN nv.TrangThai = 'Thử việc' THEN 'Thử việc' ELSE 'Không xác định thời hạn' END,
    nv.NgayVaoLam,
    tv.NgayKetThuc,
    ROUND(
        (12000000 + CAST(RIGHT(nv.MaNV, 3) AS UNSIGNED) * 250000)
        * CASE WHEN nv.TrangThai = 'Thử việc' THEN 0.85 ELSE 1 END,
        2
    ),
    'Hiệu lực',
    'DỮ LIỆU GIẢ LẬP DEMO - chỉ dùng kiểm thử; không dùng trả lương hoặc hạch toán'
FROM NHANVIEN nv
LEFT JOIN THUVIEC tv ON tv.MaNV = nv.MaNV;

INSERT INTO TAIKHOAN
(MaTK, MaNV, TenDangNhap, MatKhau, VaiTro, TrangThai)
VALUES
('TK001', 'NV001', 'admin', '$2y$10$xG3Y/KinJ6sHCeDJGBEneenlxaCicdfOZRQ89ruMzYD0uU40Gtzbm', 'admin', 'Hoạt động'),
('TK002', 'NV002', 'nhansu', '$2y$10$xG3Y/KinJ6sHCeDJGBEneenlxaCicdfOZRQ89ruMzYD0uU40Gtzbm', 'nhansu', 'Hoạt động'),
('TK003', 'NV003', 'quanly', '$2y$10$xG3Y/KinJ6sHCeDJGBEneenlxaCicdfOZRQ89ruMzYD0uU40Gtzbm', 'quanly', 'Hoạt động'),
('TK004', 'NV004', 'ketoan', '$2y$10$xG3Y/KinJ6sHCeDJGBEneenlxaCicdfOZRQ89ruMzYD0uU40Gtzbm', 'ketoan', 'Hoạt động'),
('TK005', 'NV005', 'nhanvien', '$2y$10$xG3Y/KinJ6sHCeDJGBEneenlxaCicdfOZRQ89ruMzYD0uU40Gtzbm', 'nhanvien', 'Hoạt động'),
('TK006', 'NV006', 'giamdoc', '$2y$10$xG3Y/KinJ6sHCeDJGBEneenlxaCicdfOZRQ89ruMzYD0uU40Gtzbm', 'giamdoc', 'Hoạt động');

INSERT INTO CHAMCONG (MaCC, MaNV, Ngay, GioVao, GioRa, TrangThai)
SELECT
    CONCAT('C', RIGHT(nv.MaNV, 3), DATE_FORMAT(d.NgayCham, '%y%m%d')),
    nv.MaNV,
    d.NgayCham,
    '08:30:00',
    '17:30:00',
    'Đủ công'
FROM NHANVIEN nv
CROSS JOIN (
    SELECT DATE_ADD(
        DATE_SUB(DATE_SUB(CURRENT_DATE(), INTERVAL DAY(CURRENT_DATE()) - 1 DAY), INTERVAL 1 MONTH),
        INTERVAL day_numbers.SoNgay DAY
    ) AS NgayCham
    FROM (
        SELECT ones.n + tens.n * 10 AS SoNgay
        FROM
            (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
             UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
        CROSS JOIN
            (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
             UNION ALL SELECT 5 UNION ALL SELECT 6) tens
    ) day_numbers
) d
WHERE d.NgayCham <= CURRENT_DATE()
  AND DAYOFWEEK(d.NgayCham) NOT IN (1, 7)
  AND nv.TrangThai <> 'Nghỉ việc';

INSERT INTO LUONG
(MaLuong, MaNV, Thang, Nam, LuongCoBan, PhuCap, Thuong, TangCa, KhauTru, ThucNhan)
VALUES
(
    'LG001',
    'NV001',
    MONTH(CURRENT_DATE()),
    YEAR(CURRENT_DATE()),
    30000000,
    3000000,
    2000000,
    1500000,
    500000,
    36000000
);
