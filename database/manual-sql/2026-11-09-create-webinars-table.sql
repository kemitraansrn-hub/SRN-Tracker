-- Fitur: menu Development > Pareto & RTP > Tracking Webinar (log sesi
-- webinar Pareto/Reguler/Dr. Shindy + rekap jumlah peserta per bulan).
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel barunya).
-- Referensi migration Laravel:
--   database/migrations/2026_11_09_100000_create_webinars_table.php
-- DDL di bawah diambil langsung dari SHOW CREATE TABLE hasil migration ini
-- di database lokal, jadi dijamin persis sama yang Laravel bikin.
--
-- CATATAN: seperti semua file manual-sql lain di project ini, CREATE TABLE
-- di bawah TIDAK idempotent -- cuma aman dijalankan SEKALI.

CREATE TABLE `webinars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_webinar` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_webinar` date NOT NULL,
  `jumlah_peserta` int unsigned NOT NULL,
  `bukti_zoom_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webinars_created_by_foreign` (`created_by`),
  KEY `webinars_tanggal_webinar_index` (`tanggal_webinar`),
  CONSTRAINT `webinars_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_11_09_100000_create_webinars_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_09_100000_create_webinars_table');
