-- Fitur: menu Akuisisi > Performa Channel sekarang pakai daftar channel
-- SENDIRI (Webinar, Paid Ads, Organik Instagram, Event Offline, Refferal),
-- terpisah total dari tabel `akuisisi_channels` milik Database Lead CRM.
-- Database Lead CRM & lead yang sudah ada TIDAK berubah sama sekali --
-- SQL ini cuma menyentuh tabel akuisisi_channel_performance_channels
-- (baru) dan akuisisi_channel_performances (ganti FK channel_id-nya).
--
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy.
-- Referensi migration Laravel:
--   database/migrations/2026_11_10_090000_create_akuisisi_channel_performance_channels_table.php
--   database/migrations/2026_11_10_090100_repoint_akuisisi_channel_performances_channel.php
--
-- PENTING: belum ada data input manual yang sempat diisi di tabel
-- akuisisi_channel_performances sejak fitur ini rilis (2026-11-09), jadi
-- TRUNCATE di bawah ini aman. Kalau ternyata SUDAH ada yang mengisi data
-- sebelum SQL ini dijalankan, data itu akan HILANG (channel_id-nya sudah
-- tidak relevan lagi ke channel yang baru) -- cek dulu kalau ragu:
--   SELECT COUNT(*) FROM akuisisi_channel_performances;
--
-- CATATAN: seperti semua file manual-sql lain di project ini, SQL di
-- bawah TIDAK idempotent -- cuma aman dijalankan SEKALI.

-- 1) Tabel channel baru, khusus Performa Channel
CREATE TABLE `akuisisi_channel_performance_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_channel_performance_channels_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `akuisisi_channel_performance_channels` (`code`, `name`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
('WEBINAR', 'Webinar', 1, 1, NOW(), NOW()),
('PAID_ADS', 'Paid Ads', 2, 1, NOW(), NOW()),
('ORGANIK_INSTAGRAM', 'Organik Instagram', 3, 1, NOW(), NOW()),
('EVENT_OFFLINE', 'Event Offline', 4, 1, NOW(), NOW()),
('REFFERAL', 'Refferal', 5, 1, NOW(), NOW());

-- 2) Kosongkan data lama (lihat catatan "PENTING" di atas), lalu alihkan
-- channel_id dari akuisisi_channels ke akuisisi_channel_performance_channels
TRUNCATE TABLE `akuisisi_channel_performances`;

ALTER TABLE `akuisisi_channel_performances`
  DROP FOREIGN KEY `akuisisi_channel_performances_channel_id_foreign`;

ALTER TABLE `akuisisi_channel_performances`
  DROP INDEX `akuisisi_channel_performances_channel_id_bulan_tahun_unique`;

ALTER TABLE `akuisisi_channel_performances`
  DROP COLUMN `channel_id`;

ALTER TABLE `akuisisi_channel_performances`
  ADD COLUMN `channel_id` bigint unsigned NOT NULL AFTER `id`;

ALTER TABLE `akuisisi_channel_performances`
  ADD CONSTRAINT `akuisisi_channel_performances_channel_id_foreign`
    FOREIGN KEY (`channel_id`) REFERENCES `akuisisi_channel_performance_channels` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD UNIQUE KEY `akuisisi_channel_performances_channel_id_bulan_tahun_unique` (`channel_id`, `bulan`, `tahun`);

-- 3) Catat kedua migration di bookkeeping Laravel
SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_11_10_090000_create_akuisisi_channel_performance_channels_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_10_090000_create_akuisisi_channel_performance_channels_table');

INSERT INTO migrations (migration, batch)
SELECT '2026_11_10_090100_repoint_akuisisi_channel_performances_channel', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_10_090100_repoint_akuisisi_channel_performances_channel');
