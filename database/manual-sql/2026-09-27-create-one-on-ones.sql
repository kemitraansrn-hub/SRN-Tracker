-- Fitur: 1 on 1 (follow-up Zoom untuk mitra Kurang Belanja/Belum Belanja/Over RO di Komit Tracker)
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM kode
-- fitur "1 on 1" (tombol Assignment di Komit Tracker) di-deploy.
-- Referensi migration Laravel-nya: database/migrations/2026_09_27_090000_create_one_on_ones_table.php

CREATE TABLE `one_on_ones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mitra_id` bigint unsigned NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `status_belanja` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'terjadwal',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `one_on_ones_mitra_id_foreign` (`mitra_id`),
  KEY `one_on_ones_created_by_foreign` (`created_by`),
  KEY `one_on_ones_bulan_tahun_index` (`bulan`,`tahun`),
  CONSTRAINT `one_on_ones_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `one_on_ones_mitra_id_foreign` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `one_on_one_sesis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `one_on_one_id` bigint unsigned NOT NULL,
  `urutan` tinyint unsigned NOT NULL,
  `jadwal_zoom` datetime NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'terjadwal',
  `problem` text COLLATE utf8mb4_unicode_ci,
  `solusi` text COLLATE utf8mb4_unicode_ci,
  `action_plan` text COLLATE utf8mb4_unicode_ci,
  `filled_by` bigint unsigned DEFAULT NULL,
  `filled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `one_on_one_sesis_one_on_one_id_urutan_unique` (`one_on_one_id`,`urutan`),
  KEY `one_on_one_sesis_filled_by_foreign` (`filled_by`),
  CONSTRAINT `one_on_one_sesis_filled_by_foreign` FOREIGN KEY (`filled_by`) REFERENCES `users` (`id`),
  CONSTRAINT `one_on_one_sesis_one_on_one_id_foreign` FOREIGN KEY (`one_on_one_id`) REFERENCES `one_on_ones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_09_27_090000_create_one_on_ones_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_09_27_090000_create_one_on_ones_table');
