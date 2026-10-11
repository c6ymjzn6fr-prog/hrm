ALTER TABLE HOPDONG
    ADD COLUMN DeXuatBoi VARCHAR(10) NULL AFTER TrangThai,
    ADD COLUMN LyDoDieuChinh TEXT NULL AFTER DeXuatBoi,
    ADD COLUMN NguoiPheDuyet VARCHAR(10) NULL AFTER LyDoDieuChinh,
    ADD COLUMN NgayPheDuyet DATE NULL AFTER NguoiPheDuyet,
    ADD INDEX idx_hopdong_trangthai (TrangThai),
    ADD CONSTRAINT fk_hopdong_nguoide_xuat FOREIGN KEY (DeXuatBoi) REFERENCES NHANVIEN (MaNV),
    ADD CONSTRAINT fk_hopdong_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV);

ALTER TABLE TANGCA
    ADD COLUMN HeSo DECIMAL(3,2) NOT NULL DEFAULT 1.50 AFTER SoGio,
    ADD CONSTRAINT chk_tangca_heso CHECK (HeSo IN (1.50, 2.00, 3.00));

UPDATE TANGCA SET HeSo = 2.00 WHERE DAYOFWEEK(Ngay) IN (1, 7);

ALTER TABLE LUONG
    ADD COLUMN TrangThai VARCHAR(30) NOT NULL DEFAULT 'Chờ duyệt' AFTER ThucNhan,
    ADD COLUMN NguoiPheDuyet VARCHAR(10) NULL AFTER TrangThai,
    ADD COLUMN NgayPheDuyet DATE NULL AFTER NguoiPheDuyet,
    ADD INDEX idx_luong_trangthai_ky (Nam, Thang, TrangThai),
    ADD CONSTRAINT chk_luong_trangthai CHECK (TrangThai IN ('Chờ duyệt', 'Đã duyệt')),
    ADD CONSTRAINT fk_luong_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV);

ALTER TABLE DIEUCHUYEN
    ADD COLUMN TrangThai VARCHAR(30) NOT NULL DEFAULT 'Đã duyệt' AFTER LyDo,
    ADD COLUMN NguoiDeXuat VARCHAR(10) NULL AFTER TrangThai,
    ADD COLUMN NguoiPheDuyet VARCHAR(10) NULL AFTER NguoiDeXuat,
    ADD COLUMN NgayPheDuyet DATE NULL AFTER NguoiPheDuyet,
    ADD INDEX idx_dieuchuyen_trangthai (TrangThai),
    ADD CONSTRAINT fk_dieuchuyen_nguoide_xuat FOREIGN KEY (NguoiDeXuat) REFERENCES NHANVIEN (MaNV),
    ADD CONSTRAINT fk_dieuchuyen_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV),
    ADD CONSTRAINT chk_dieuchuyen_trangthai CHECK (TrangThai IN ('Chờ phê duyệt', 'Đã duyệt', 'Từ chối'));

ALTER TABLE NGHIVIEC
    ADD COLUMN BanGiao TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER TrangThai,
    ADD COLUMN LuongDaChot TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER BanGiao,
    ADD COLUMN NguoiPheDuyet VARCHAR(10) NULL AFTER LuongDaChot,
    ADD COLUMN NgayPheDuyet DATE NULL AFTER NguoiPheDuyet,
    ADD COLUMN NgayBanGiao DATE NULL AFTER NgayPheDuyet,
    ADD COLUMN NgayChotLuong DATE NULL AFTER NgayBanGiao,
    ADD COLUMN NgayLuuHoSo DATE NULL AFTER NgayChotLuong,
    ADD INDEX idx_nghiviec_trangthai (TrangThai),
    ADD CONSTRAINT chk_nghiviec_bangiao CHECK (BanGiao IN (0, 1)),
    ADD CONSTRAINT chk_nghiviec_luongdachot CHECK (LuongDaChot IN (0, 1)),
    ADD CONSTRAINT chk_nghiviec_trangthai CHECK (TrangThai IN ('Tiếp nhận', 'Phê duyệt', 'Bàn giao', 'Chốt công/lương', 'Lưu hồ sơ', 'Từ chối')),
    ADD CONSTRAINT fk_nghiviec_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV);
