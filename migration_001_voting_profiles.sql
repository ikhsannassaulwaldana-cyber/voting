-- Jalankan sekali pada database yang dibuat dari database.sql, setelah backup.
-- Migration ini tidak menghapus tabel, pengguna, peserta, transaksi, atau vote.
ALTER TABLE users
    ADD COLUMN phone VARCHAR(30) NULL AFTER email;

ALTER TABLE payments
    ADD COLUMN participant_id INT NULL AFTER user_id;

-- Transaksi yang sudah memiliki vote dipasangkan kembali ke peserta asalnya.
UPDATE payments p
JOIN votes v ON v.payment_id = p.id
SET p.participant_id = v.participant_id;

ALTER TABLE payments
    ADD CONSTRAINT fk_payments_participant
    FOREIGN KEY (participant_id) REFERENCES participants(id)
    ON UPDATE CASCADE ON DELETE RESTRICT;

-- Beri ruang untuk normalisasi status sebelum memakai nilai lowercase.
ALTER TABLE payments
    MODIFY status ENUM('PENDING', 'PAID', 'FAILED', 'pending', 'paid', 'failed')
    NOT NULL DEFAULT 'pending';

UPDATE payments
SET status = CASE UPPER(status)
    WHEN 'PENDING' THEN 'pending'
    WHEN 'PAID' THEN 'paid'
    WHEN 'FAILED' THEN 'failed'
    ELSE 'failed'
END;

ALTER TABLE payments
    MODIFY status ENUM('pending', 'paid', 'failed')
    NOT NULL DEFAULT 'pending';
