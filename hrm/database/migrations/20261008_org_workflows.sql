ALTER TABLE PHONGBAN
    ADD COLUMN MaTruongBP VARCHAR(10) NULL,
    ADD CONSTRAINT fk_phongban_truongbp FOREIGN KEY (MaTruongBP) REFERENCES NHANVIEN (MaNV) ON DELETE SET NULL;

ALTER TABLE NGHIVIEC
    ADD COLUMN NguoiDeXuat VARCHAR(10) NULL,
    ADD CONSTRAINT fk_nghiviec_nguoide_xuat FOREIGN KEY (NguoiDeXuat) REFERENCES NHANVIEN (MaNV);

ALTER TABLE LUONG
    ADD COLUMN NguoiTao VARCHAR(10) NULL,
    ADD CONSTRAINT fk_luong_nguoitao FOREIGN KEY (NguoiTao) REFERENCES NHANVIEN (MaNV);

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

CREATE TABLE IF NOT EXISTS NHATKY (
    MaNhatKy BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    MaNV VARCHAR(10) NULL,
    HanhDong VARCHAR(100) NOT NULL,
    ChiTiet VARCHAR(1000) NOT NULL,
    DiaChiIP VARCHAR(45) NULL,
    ThoiGian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nhatky_thoigian (ThoiGian),
    INDEX idx_nhatky_nhanvien (MaNV),
    CONSTRAINT fk_nhatky_org_nhanvien FOREIGN KEY (MaNV) REFERENCES NHANVIEN (MaNV) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @legacy_log_copy = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'NHATKYHOATDONG'
    ),
    'INSERT IGNORE INTO NHATKY (MaNhatKy, MaNV, HanhDong, ChiTiet, DiaChiIP, ThoiGian)
     SELECT MaNhatKy, MaNV, HanhDong, ChiTiet, DiaChiIP, ThoiGian FROM NHATKYHOATDONG',
    'SELECT 1'
);
PREPARE copy_legacy_logs FROM @legacy_log_copy;
EXECUTE copy_legacy_logs;
DEALLOCATE PREPARE copy_legacy_logs;
