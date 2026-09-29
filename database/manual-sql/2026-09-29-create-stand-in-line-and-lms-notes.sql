-- Fitur: tab "Stand in Line" (Tracking Performance) + tombol Note di Set Up LMS
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM kode
-- fitur ini di-deploy.
-- Referensi migration Laravel-nya: database/migrations/2026_09_29_090000_create_stand_in_line_and_lms_notes_tables.php

CREATE TABLE `stand_in_line_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mitra_id` bigint unsigned NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stand_in_line_notes_mitra_id_bulan_tahun_unique` (`mitra_id`,`bulan`,`tahun`),
  KEY `stand_in_line_notes_created_by_foreign` (`created_by`),
  CONSTRAINT `stand_in_line_notes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stand_in_line_notes_mitra_id_foreign` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `set_up_lms_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mitra_id` bigint unsigned NOT NULL,
  `platform` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `set_up_lms_notes_mitra_id_platform_unique` (`mitra_id`,`platform`),
  KEY `set_up_lms_notes_created_by_foreign` (`created_by`),
  CONSTRAINT `set_up_lms_notes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `set_up_lms_notes_mitra_id_foreign` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_09_29_090000_create_stand_in_line_and_lms_notes_tables', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_09_29_090000_create_stand_in_line_and_lms_notes_tables');
