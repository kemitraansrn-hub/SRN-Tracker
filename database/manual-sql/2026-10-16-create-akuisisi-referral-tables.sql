-- Fitur: menu Akuisisi > Tracking Referral (Program Akuisisi Mitra Offline).
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel barunya).
-- Referensi migration Laravel:
--   database/migrations/2026_10_16_065056_create_akuisisi_referrals_table.php
--   database/migrations/2026_10_16_065057_create_akuisisi_referral_tahaps_table.php

CREATE TABLE `akuisisi_referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mitra_id` bigint unsigned NOT NULL,
  `nama_pengakuisisi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_akuisisi` date NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_referrals_mitra_id_unique` (`mitra_id`),
  KEY `akuisisi_referrals_created_by_foreign` (`created_by`),
  CONSTRAINT `akuisisi_referrals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `akuisisi_referrals_mitra_id_foreign` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_referral_tahaps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `akuisisi_referral_id` bigint unsigned NOT NULL,
  `tahap` tinyint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_bayar',
  `konfirmasi_tanpa_retur` tinyint(1) NOT NULL DEFAULT '0',
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `paid_by` bigint unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_referral_tahaps_akuisisi_referral_id_tahap_unique` (`akuisisi_referral_id`,`tahap`),
  KEY `akuisisi_referral_tahaps_paid_by_foreign` (`paid_by`),
  CONSTRAINT `akuisisi_referral_tahaps_akuisisi_referral_id_foreign` FOREIGN KEY (`akuisisi_referral_id`) REFERENCES `akuisisi_referrals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `akuisisi_referral_tahaps_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_10_16_065056_create_akuisisi_referrals_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_16_065056_create_akuisisi_referrals_table');
INSERT INTO migrations (migration, batch)
SELECT '2026_10_16_065057_create_akuisisi_referral_tahaps_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_16_065057_create_akuisisi_referral_tahaps_table');
