-- Fitur: master data Pengakuisisi (Nama/Bank/No Rekening/Alamat) di menu
-- Akuisisi > Pengakuisisi, dikaitkan ke Tracking Referral.
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel/kolom barunya).
-- Referensi migration Laravel:
--   database/migrations/2026_10_19_064703_create_pengakuisisis_table.php
--   database/migrations/2026_10_19_064704_add_pengakuisisi_id_to_akuisisi_referrals_table.php

CREATE TABLE `pengakuisisis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_rekening` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pengakuisisis_created_by_foreign` (`created_by`),
  CONSTRAINT `pengakuisisis_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kolom baru di tabel akuisisi_referrals yang SUDAH ADA di production
-- (dibuat 2026-10-16) -- tabel pengakuisisis harus sudah ada duluan (lihat
-- CREATE TABLE di atas) sebelum FK ini bisa dibuat.
ALTER TABLE `akuisisi_referrals`
  ADD COLUMN `pengakuisisi_id` bigint unsigned DEFAULT NULL AFTER `mitra_id`,
  ADD KEY `akuisisi_referrals_pengakuisisi_id_foreign` (`pengakuisisi_id`),
  ADD CONSTRAINT `akuisisi_referrals_pengakuisisi_id_foreign` FOREIGN KEY (`pengakuisisi_id`) REFERENCES `pengakuisisis` (`id`) ON DELETE SET NULL;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_10_19_064703_create_pengakuisisis_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_19_064703_create_pengakuisisis_table');
INSERT INTO migrations (migration, batch)
SELECT '2026_10_19_064704_add_pengakuisisi_id_to_akuisisi_referrals_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_19_064704_add_pengakuisisi_id_to_akuisisi_referrals_table');
