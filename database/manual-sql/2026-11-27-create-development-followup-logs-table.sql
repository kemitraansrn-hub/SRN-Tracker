-- =============================================================
-- Tanggal persiapan : 2026-11-27
-- Fitur             : Menu "Follow Up" khusus Development (beda dari
--                     Follow-up Log Sales yang sudah ada)
-- Migration asal    : database/migrations/2026_11_27_064319_create_development_followup_logs_table.php
-- (sudah dijalankan & ditest di database lokal dev lewat `php artisan migrate`,
--  DDL di bawah diambil langsung dari SHOW CREATE TABLE hasil migration ini
--  di database lokal, jadi dijamin persis sama yang Laravel bikin)
--
-- >>> BACKUP DATABASE PRODUCTION SEBELUM MENJALANKAN SQL INI <<<
--
-- Sifat perubahan: AMAN / NON-DESTRUKTIF.
-- Cuma CREATE TABLE baru (`development_followup_logs`). TIDAK menyentuh
-- tabel lain, TIDAK ada DROP, TIDAK ada ubah tipe kolom, TIDAK ada
-- hapus/ubah data yang sudah ada. Tabel ini punya foreign key ke `mitra`
-- dan `users` saja -- kedua tabel itu sudah ada di production, jadi aman.
-- CATATAN: seperti semua file manual-sql lain di project ini, CREATE TABLE
-- di bawah TIDAK idempotent -- cuma aman dijalankan SEKALI.
-- =============================================================

-- 1) Buat tabel development_followup_logs
CREATE TABLE `development_followup_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tanggal_fu` date NOT NULL,
  `minggu` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mitra_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status_followup` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alasan_kendala` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `total_menit` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `development_followup_logs_user_id_foreign` (`user_id`),
  KEY `development_followup_logs_mitra_id_index` (`mitra_id`),
  CONSTRAINT `development_followup_logs_mitra_id_foreign` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`),
  CONSTRAINT `development_followup_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Catat migration ini sebagai "sudah jalan" di tabel migrations,
--    supaya Laravel tidak mencoba menjalankannya lagi otomatis nanti.
--    (batch diambil otomatis = batch terakhir yang ada di production + 1)
--    Catatan: dibungkus pakai subquery FROM (derived table) karena MySQL
--    tidak boleh INSERT ke tabel yang sama sambil SELECT langsung dari
--    tabel itu di VALUES (error #1093).
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_27_064319_create_development_followup_logs_table', `batch_baru`
FROM (SELECT COALESCE(MAX(`batch`), 0) + 1 AS `batch_baru` FROM `migrations`) AS `t`;

-- =============================================================
-- Cara cek setelah dijalankan:
--   DESCRIBE development_followup_logs;   -> tabel baru harus muncul dengan
--     kolom-kolom di atas
--   SELECT * FROM migrations WHERE migration = '2026_11_27_064319_create_development_followup_logs_table';
--   -> harus muncul 1 baris
-- =============================================================
