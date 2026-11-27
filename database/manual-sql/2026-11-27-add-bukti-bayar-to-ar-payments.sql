-- =============================================================
-- Tanggal persiapan : 2026-11-27
-- Fitur             : Upload bukti bayar cicilan AR
-- Migration asal     : database/migrations/2026_11_27_010338_add_bukti_bayar_to_ar_payments_table.php
-- (sudah dijalankan & ditest di database lokal dev lewat `php artisan migrate` + tinker)
--
-- >>> BACKUP DATABASE PRODUCTION SEBELUM MENJALANKAN SQL INI <<<
--
-- Sifat perubahan: AMAN / NON-DESTRUKTIF.
-- Cuma menambah 1 kolom baru yang boleh kosong (nullable) di tabel
-- `ar_payments`. TIDAK ada DROP, TIDAK ada ubah tipe kolom, TIDAK ada
-- hapus/ubah data yang sudah ada. Tapi tetap WAJIB backup dulu sebagai
-- jaga-jaga standar sebelum mengubah struktur tabel di production.
-- =============================================================

-- 1) Tambah kolom bukti_bayar (nama file bukti transfer, nullable)
ALTER TABLE `ar_payments`
    ADD COLUMN `bukti_bayar` VARCHAR(255) NULL DEFAULT NULL AFTER `tanggal_bayar`;

-- 2) Catat migration ini sebagai "sudah jalan" di tabel migrations,
--    supaya Laravel tidak mencoba menjalankannya lagi otomatis nanti.
--    (batch diambil otomatis = batch terakhir yang ada di production + 1)
INSERT INTO `migrations` (`migration`, `batch`)
VALUES (
    '2026_11_27_010338_add_bukti_bayar_to_ar_payments_table',
    (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`)
);

-- =============================================================
-- Cara cek setelah dijalankan:
--   DESCRIBE ar_payments;   -> harus ada kolom bukti_bayar (varchar, nullable)
--   SELECT * FROM migrations WHERE migration = '2026_11_27_010338_add_bukti_bayar_to_ar_payments_table';
--   -> harus muncul 1 baris
-- =============================================================
