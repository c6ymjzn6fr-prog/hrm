CREATE DATABASE IF NOT EXISTS hrm_htmedia
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hrm_htmedia;

CREATE TABLE IF NOT EXISTS PHONGBAN (
    MaPB VARCHAR(10) PRIMARY KEY,
    TenPB VARCHAR(100) NOT NULL,
    MaTruongBP VARCHAR(10) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CHUCVU (
    MaCV VARCHAR(10) PRIMARY KEY,
    TenCV VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS TUYENDUNG (
    MaTD VARCHAR(10) PRIMARY KEY,
    ViTri VARCHAR(150) NOT NULL,
    SoLuong INT UNSIGNED NOT NULL DEFAULT 1,
    MaPB VARCHAR(10) NULL,
    YeuCau TEXT NULL,
    NgayDang DATE NOT NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Đang tuyển',
    DeXuatBoi VARCHAR(10) NULL,
    NguoiPheDuyet VARCHAR(10) NULL,
    NgayPheDuyet DATE NULL,
    NguoiTao VARCHAR(10) NULL,
    INDEX idx_tuyendung_trangthai (TrangThai),
    CONSTRAINT chk_tuyendung_soluong CHECK (SoLuong >= 1),
    CONSTRAINT chk_tuyendung_trangthai CHECK (TrangThai IN ('Chờ phê duyệt', 'Đã duyệt', 'Đang tuyển', 'Từ chối', 'Đã đóng')),
    CONSTRAINT fk_tuyendung_phongban FOREIGN KEY (MaPB) REFERENCES PHONGBAN (MaPB)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS NHANVIEN (
    MaNV VARCHAR(10) PRIMARY KEY,
    HoTen VARCHAR(150) NOT NULL,
    NgaySinh DATE NULL,
    GioiTinh VARCHAR(20) NULL,
    SoDienThoai VARCHAR(20) NULL,
    Email VARCHAR(150) NULL,
    DiaChi VARCHAR(255) NULL,
    NgayVaoLam DATE NOT NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Chính thức',
    MaPB VARCHAR(10) NOT NULL,
    MaCV VARCHAR(10) NOT NULL,
    INDEX idx_nhanvien_hoten (HoTen),
    INDEX idx_nhanvien_trangthai (TrangThai),
    CONSTRAINT chk_nhanvien_gioitinh CHECK (GioiTinh IS NULL OR GioiTinh IN ('Nam', 'Nữ', 'Khác')),
    CONSTRAINT fk_nhanvien_phongban FOREIGN KEY (MaPB) REFERENCES PHONGBAN (MaPB),
    CONSTRAINT fk_nhanvien_chucvu FOREIGN KEY (MaCV) REFERENCES CHUCVU (MaCV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE PHONGBAN
    ADD CONSTRAINT fk_phongban_truongbp FOREIGN KEY (MaTruongBP) REFERENCES NHANVIEN (MaNV) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS UNGVIEN (
    MaUV VARCHAR(10) PRIMARY KEY,
    HoTen VARCHAR(150) NOT NULL,
    SoDienThoai VARCHAR(20) NULL,
    Email VARCHAR(150) NULL,
    ViTriUngTuyen VARCHAR(150) NOT NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Mới',
    MaTD VARCHAR(10) NULL,
    NgayNop DATE NOT NULL,
    HoSoCV VARCHAR(255) NULL,
    NgayPhongVan DATETIME NULL,
    KetQuaPhongVan TEXT NULL,
    NhanXet TEXT NULL,
    INDEX idx_ungvien_hoten (HoTen),
    INDEX idx_ungvien_dienthoai (SoDienThoai),
    INDEX idx_ungvien_trangthai_ngaynop (TrangThai, NgayNop),
    CONSTRAINT chk_ungvien_trangthai CHECK (TrangThai IN ('Mới', 'Đang sàng lọc', 'Mời phỏng vấn', 'Đạt', 'Từ chối', 'Đã tiếp nhận')),
    CONSTRAINT fk_ungvien_tuyendung FOREIGN KEY (MaTD) REFERENCES TUYENDUNG (MaTD)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS HOSONHANVIEN (
    MaHoSo VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL UNIQUE,
    SoCCCD VARCHAR(20) NOT NULL UNIQUE,
    TrinhDo TEXT NULL,
    ChuyenNganh VARCHAR(150) NULL,
    TruongTotNghiep VARCHAR(200) NULL,
    NamTotNghiep SMALLINT UNSIGNED NULL,
    NoiSinh VARCHAR(150) NULL,
    QuocTich VARCHAR(80) NULL,
    TinhTrangHonNhan VARCHAR(30) NULL,
    DiaChiThuongTru VARCHAR(255) NULL,
    DiaChiHienTai VARCHAR(255) NULL,
    NgayCapCCCD DATE NULL,
    NoiCapCCCD VARCHAR(150) NULL,
    MaSoThue VARCHAR(20) NULL UNIQUE,
    SoBHXH VARCHAR(20) NULL UNIQUE,
    SoTaiKhoan VARCHAR(34) NULL,
    NganHang VARCHAR(150) NULL,
    NguoiLienHeKhanCap VARCHAR(150) NULL,
    SDTLienHeKhanCap VARCHAR(20) NULL,
    MaUV VARCHAR(10) NULL UNIQUE,
    TaiLieu TEXT NULL,
    NgayCapNhat DATE NOT NULL,
    CONSTRAINT chk_hosonhanvien_ngaycapnhat CHECK (NgayCapNhat >= '2000-01-01'),
    CONSTRAINT chk_hosonhanvien_namtotnghiep CHECK (NamTotNghiep IS NULL OR NamTotNghiep BETWEEN 1900 AND 2100),
    CONSTRAINT chk_hosonhanvien_honnhan CHECK (TinhTrangHonNhan IS NULL OR TinhTrangHonNhan IN ('Độc thân', 'Đã kết hôn', 'Khác')),
    CONSTRAINT fk_hosonhanvien_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_hosonhanvien_ungvien FOREIGN KEY (MaUV) REFERENCES UNGVIEN (MaUV) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS HOPDONG (
    MaHD VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    LoaiHD VARCHAR(50) NOT NULL,
    NgayBatDau DATE NOT NULL,
    NgayKetThuc DATE NULL,
    MucLuong DECIMAL(14,2) NOT NULL DEFAULT 0,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Hiệu lực',
    DeXuatBoi VARCHAR(10) NULL,
    LyDoDieuChinh TEXT NULL,
    NguoiPheDuyet VARCHAR(10) NULL,
    NgayPheDuyet DATE NULL,
    INDEX idx_hopdong_nhanvien (MaNV),
    INDEX idx_hopdong_trangthai (TrangThai),
    CONSTRAINT chk_hopdong_luong CHECK (MucLuong >= 0),
    CONSTRAINT chk_hopdong_ngay CHECK (NgayKetThuc IS NULL OR NgayKetThuc >= NgayBatDau),
    CONSTRAINT fk_hopdong_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_hopdong_nguoide_xuat FOREIGN KEY (DeXuatBoi) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_hopdong_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS THUVIEC (
    MaTV VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    NgayBatDau DATE NOT NULL,
    NgayKetThuc DATE NOT NULL,
    KetQua VARCHAR(30) NULL,
    NhanXet TEXT NULL,
    INDEX idx_thuviec_nhanvien (MaNV),
    CONSTRAINT chk_thuviec_ngay CHECK (NgayKetThuc >= NgayBatDau),
    CONSTRAINT fk_thuviec_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CHAMCONG (
    MaCC VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    Ngay DATE NOT NULL,
    GioVao TIME NULL,
    GioRa TIME NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Đủ công',
    DaChot TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_chamcong_nhanvien_ngay (MaNV, Ngay),
    CONSTRAINT chk_chamcong_dachot CHECK (DaChot IN (0, 1)),
    CONSTRAINT fk_chamcong_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS NGHIPHEP (
    MaNP VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    TuNgay DATE NOT NULL,
    DenNgay DATE NOT NULL,
    LyDo TEXT NOT NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Chờ duyệt',
    INDEX idx_nghiphep_nhanvien (MaNV),
    CONSTRAINT chk_nghiphep_ngay CHECK (DenNgay >= TuNgay),
    CONSTRAINT fk_nghiphep_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS TANGCA (
    MaTC VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    Ngay DATE NOT NULL,
    SoGio DECIMAL(5,2) NOT NULL DEFAULT 0,
    HeSo DECIMAL(3,2) NOT NULL DEFAULT 1.50,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Chờ duyệt',
    INDEX idx_tangca_nhanvien_ngay (MaNV, Ngay),
    CONSTRAINT chk_tangca_sogio CHECK (SoGio > 0 AND SoGio <= 24),
    CONSTRAINT chk_tangca_heso CHECK (HeSo IN (1.50, 2.00, 3.00)),
    CONSTRAINT fk_tangca_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS LUONG (
    MaLuong VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    Thang TINYINT UNSIGNED NOT NULL,
    Nam SMALLINT UNSIGNED NOT NULL,
    LuongCoBan DECIMAL(14,2) NOT NULL DEFAULT 0,
    PhuCap DECIMAL(14,2) NOT NULL DEFAULT 0,
    Thuong DECIMAL(14,2) NOT NULL DEFAULT 0,
    TangCa DECIMAL(14,2) NOT NULL DEFAULT 0,
    KhauTru DECIMAL(14,2) NOT NULL DEFAULT 0,
    ThucNhan DECIMAL(14,2) NOT NULL DEFAULT 0,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Chờ duyệt',
    NguoiPheDuyet VARCHAR(10) NULL,
    NgayPheDuyet DATE NULL,
    NguoiTao VARCHAR(10) NULL,
    UNIQUE KEY uq_luong_nhanvien_thang_nam (MaNV, Thang, Nam),
    INDEX idx_luong_trangthai_ky (Nam, Thang, TrangThai),
    CONSTRAINT chk_luong_thang CHECK (Thang BETWEEN 1 AND 12),
    CONSTRAINT chk_luong_nam CHECK (Nam BETWEEN 2000 AND 2200),
    CONSTRAINT chk_luong_giatri CHECK (
        LuongCoBan >= 0 AND PhuCap >= 0 AND Thuong >= 0
        AND TangCa >= 0 AND KhauTru >= 0 AND ThucNhan >= 0
    ),
    CONSTRAINT chk_luong_thucnhan CHECK (
        ThucNhan = LuongCoBan + PhuCap + Thuong + TangCa - KhauTru
    ),
    CONSTRAINT fk_luong_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT chk_luong_trangthai CHECK (TrangThai IN ('Chờ duyệt', 'Đã duyệt')),
    CONSTRAINT fk_luong_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_luong_nguoitao FOREIGN KEY (NguoiTao) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS DANHGIA (
    MaDG VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    NgayDanhGia DATE NOT NULL,
    KetQua TEXT NULL,
    XepLoai VARCHAR(50) NULL,
    NhanXet TEXT NULL,
    INDEX idx_danhgia_nhanvien_ngay (MaNV, NgayDanhGia),
    CONSTRAINT fk_danhgia_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS DIEUCHUYEN (
    MaDC VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    NgayDC DATE NOT NULL,
    BoPhanCu VARCHAR(10) NOT NULL,
    BoPhanMoi VARCHAR(10) NOT NULL,
    ViTriCu VARCHAR(10) NOT NULL,
    ViTriMoi VARCHAR(10) NOT NULL,
    LyDo TEXT NOT NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Chờ phê duyệt',
    NguoiDeXuat VARCHAR(10) NULL,
    NguoiPheDuyet VARCHAR(10) NULL,
    NgayPheDuyet DATE NULL,
    INDEX idx_dieuchuyen_nhanvien (MaNV),
    INDEX idx_dieuchuyen_trangthai (TrangThai),
    CONSTRAINT chk_dieuchuyen_phongban CHECK (BoPhanCu <> BoPhanMoi),
    CONSTRAINT fk_dieuchuyen_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_dieuchuyen_phongban_cu FOREIGN KEY (BoPhanCu) REFERENCES PHONGBAN (MaPB),
    CONSTRAINT fk_dieuchuyen_phongban_moi FOREIGN KEY (BoPhanMoi) REFERENCES PHONGBAN (MaPB),
    CONSTRAINT fk_dieuchuyen_chucvu_cu FOREIGN KEY (ViTriCu) REFERENCES CHUCVU (MaCV),
    CONSTRAINT fk_dieuchuyen_chucvu_moi FOREIGN KEY (ViTriMoi) REFERENCES CHUCVU (MaCV),
    CONSTRAINT fk_dieuchuyen_nguoide_xuat FOREIGN KEY (NguoiDeXuat) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_dieuchuyen_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT chk_dieuchuyen_trangthai CHECK (TrangThai IN ('Chờ phê duyệt', 'Đã duyệt', 'Từ chối'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS NGHIVIEC (
    MaNViec VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL,
    NgayDeNghi DATE NOT NULL,
    NgayNghi DATE NOT NULL,
    LyDo TEXT NOT NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Tiếp nhận',
    BanGiao TINYINT UNSIGNED NOT NULL DEFAULT 0,
    LuongDaChot TINYINT UNSIGNED NOT NULL DEFAULT 0,
    NguoiPheDuyet VARCHAR(10) NULL,
    NgayPheDuyet DATE NULL,
    NgayBanGiao DATE NULL,
    NgayChotLuong DATE NULL,
    NgayLuuHoSo DATE NULL,
    NguoiDeXuat VARCHAR(10) NULL,
    INDEX idx_nghiviec_nhanvien (MaNV),
    INDEX idx_nghiviec_trangthai (TrangThai),
    CONSTRAINT chk_nghiviec_ngay CHECK (NgayNghi >= NgayDeNghi),
    CONSTRAINT chk_nghiviec_bangiao CHECK (BanGiao IN (0, 1)),
    CONSTRAINT chk_nghiviec_luongdachot CHECK (LuongDaChot IN (0, 1)),
    CONSTRAINT chk_nghiviec_trangthai CHECK (TrangThai IN ('Tiếp nhận', 'Phê duyệt', 'Bàn giao', 'Chốt công/lương', 'Lưu hồ sơ', 'Từ chối')),
    CONSTRAINT fk_nghiviec_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_nghiviec_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV),
    CONSTRAINT fk_nghiviec_nguoide_xuat FOREIGN KEY (NguoiDeXuat) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS TAIKHOAN (
    MaTK VARCHAR(10) PRIMARY KEY,
    MaNV VARCHAR(10) NOT NULL UNIQUE,
    TenDangNhap VARCHAR(50) NOT NULL UNIQUE,
    MatKhau VARCHAR(255) NOT NULL,
    VaiTro ENUM('admin', 'nhansu', 'quanly', 'ketoan', 'nhanvien', 'giamdoc') NOT NULL,
    TrangThai VARCHAR(30) NOT NULL DEFAULT 'Hoạt động',
    SoLanSai TINYINT UNSIGNED NOT NULL DEFAULT 0,
    KhoaDen DATETIME NULL,
    ResetTokenHash CHAR(64) NULL,
    ResetTokenExpiresAt DATETIME NULL,
    UNIQUE KEY uq_taikhoan_reset_token (ResetTokenHash),
    CONSTRAINT fk_taikhoan_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS NHATKY (
    MaNhatKy BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    MaNV VARCHAR(10) NULL,
    HanhDong VARCHAR(100) NOT NULL,
    ChiTiet VARCHAR(1000) NOT NULL,
    DiaChiIP VARCHAR(45) NULL,
    ThoiGian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nhatky_thoigian (ThoiGian),
    INDEX idx_nhatky_nhanvien (MaNV),
    CONSTRAINT fk_nhatky_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PHEDUYET (
    MaPD BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    LoaiQuyTrinh VARCHAR(40) NOT NULL,
    MaDoiTuong VARCHAR(20) NOT NULL,
    Buoc VARCHAR(30) NOT NULL,
    NguoiGui VARCHAR(10) NULL,
    NguoiDuyet VARCHAR(10) NULL,
    KetQua VARCHAR(20) NOT NULL,
    LyDo TEXT NULL,
    DuLieu TEXT NULL,
    ThoiGian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pheduyet_doi_tuong (LoaiQuyTrinh, MaDoiTuong, MaPD),
    INDEX idx_pheduyet_nguoi_duyet (NguoiDuyet, ThoiGian),
    CONSTRAINT chk_pheduyet_ketqua CHECK (KetQua IN ('Đã gửi', 'Đã duyệt', 'Từ chối')),
    CONSTRAINT fk_pheduyet_nguoigui FOREIGN KEY (NguoiGui) REFERENCES NHANVIEN (MaNV) ON DELETE SET NULL,
    CONSTRAINT fk_pheduyet_nguoiduyet FOREIGN KEY (NguoiDuyet) REFERENCES NHANVIEN (MaNV) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE TUYENDUNG
    ADD CONSTRAINT fk_tuyendung_nguoide_xuat FOREIGN KEY (DeXuatBoi) REFERENCES NHANVIEN (MaNV),
    ADD CONSTRAINT fk_tuyendung_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV);
