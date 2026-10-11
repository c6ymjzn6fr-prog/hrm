ALTER TABLE TUYENDUNG
    ADD COLUMN DeXuatBoi VARCHAR(10) NULL AFTER TrangThai,
    ADD COLUMN NguoiPheDuyet VARCHAR(10) NULL AFTER DeXuatBoi,
    ADD COLUMN NgayPheDuyet DATE NULL AFTER NguoiPheDuyet,
    ADD INDEX idx_tuyendung_trangthai (TrangThai),
    ADD CONSTRAINT chk_tuyendung_trangthai CHECK (TrangThai IN ('Chờ phê duyệt', 'Đã duyệt', 'Đang tuyển', 'Từ chối', 'Đã đóng'));

ALTER TABLE UNGVIEN
    ADD COLUMN NgayNop DATE NULL AFTER MaTD,
    ADD COLUMN HoSoCV VARCHAR(255) NULL AFTER NgayNop,
    ADD COLUMN NgayPhongVan DATETIME NULL AFTER HoSoCV,
    ADD COLUMN KetQuaPhongVan TEXT NULL AFTER NgayPhongVan,
    ADD COLUMN NhanXet TEXT NULL AFTER KetQuaPhongVan,
    ADD INDEX idx_ungvien_dienthoai (SoDienThoai),
    ADD INDEX idx_ungvien_trangthai_ngaynop (TrangThai, NgayNop),
    ADD CONSTRAINT chk_ungvien_trangthai CHECK (TrangThai IN ('Mới', 'Đang sàng lọc', 'Mời phỏng vấn', 'Đạt', 'Từ chối', 'Đã tiếp nhận'));

UPDATE UNGVIEN SET NgayNop = CURRENT_DATE WHERE NgayNop IS NULL;
ALTER TABLE UNGVIEN MODIFY COLUMN NgayNop DATE NOT NULL;

ALTER TABLE HOSONHANVIEN
    MODIFY COLUMN TrinhDo TEXT NULL;

ALTER TABLE TUYENDUNG
    ADD CONSTRAINT fk_tuyendung_nguoide_xuat FOREIGN KEY (DeXuatBoi) REFERENCES NHANVIEN (MaNV),
    ADD CONSTRAINT fk_tuyendung_nguoiduyet FOREIGN KEY (NguoiPheDuyet) REFERENCES NHANVIEN (MaNV);
