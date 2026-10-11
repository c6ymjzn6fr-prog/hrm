ALTER TABLE TAIKHOAN
    ADD COLUMN ResetTokenHash CHAR(64) NULL AFTER KhoaDen,
    ADD COLUMN ResetTokenExpiresAt DATETIME NULL AFTER ResetTokenHash,
    ADD UNIQUE KEY uq_taikhoan_reset_token (ResetTokenHash);
