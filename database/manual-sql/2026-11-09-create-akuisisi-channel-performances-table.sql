-- Fitur: menu Akuisisi > Performa Channel (input manual Lead/Prospect/
-- Closing/Biaya/Investasi Mitra per channel per bulan) + tab baru "Akuisisi"
-- di Dashboard (funnel konversi closing by lead/prospect & CAC).
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel barunya).
-- Referensi migration Laravel:
--   database/migrations/2026_11_09_120000_create_akuisisi_channel_performances_table.php
-- DDL di bawah diambil langsung dari SHOW CREATE TABLE hasil migration ini
-- di database lokal, jadi dijamin persis sama yang Laravel bikin.
-- channel_id pakai smallint unsigned (BUKAN bigint) karena FK ke
-- akuisisi_channels.id yang tipenya smallIncrements -- lihat
-- database/migrations/2026_11_02_100000_create_akuisisi_leads_tables.php.
--
-- CATATAN: seperti semua file manual-sql lain di project ini, CREATE TABLE
-- di bawah TIDAK idempotent -- cuma aman dijalankan SEKALI.

CREATE TABLE `akuisisi_channel_performances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` smallint unsigned NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `lead` int unsigned NOT NULL DEFAULT '0',
  `prospect` int unsigned NOT NULL DEFAULT '0',
  `closing` int unsigned NOT NULL DEFAULT '0',
  `biaya` decimal(15,2) NOT NULL DEFAULT '0.00',
  `investasi_mitra` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_channel_performances_channel_id_bulan_tahun_unique` (`channel_id`,`bulan`,`tahun`),
  KEY `akuisisi_channel_performances_created_by_foreign` (`created_by`),
  KEY `akuisisi_channel_performances_updated_by_foreign` (`updated_by`),
  CONSTRAINT `akuisisi_channel_performances_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `akuisisi_channels` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_channel_performances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `akuisisi_channel_performances_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_11_09_120000_create_akuisisi_channel_performances_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_09_120000_create_akuisisi_channel_performances_table');
