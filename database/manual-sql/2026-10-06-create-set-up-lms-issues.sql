-- Fitur: menu "Issue" di Set Up LMS (catat kendala mitra per platform:
-- device, tidak respon, waktu, dll).
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel barunya).
-- Referensi migration Laravel-nya: database/migrations/2026_10_06_100000_create_set_up_lms_issues_table.php

CREATE TABLE `set_up_lms_issues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mitra_id` bigint unsigned NOT NULL,
  `platform` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isu_kendala` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `set_up_lms_issues_mitra_id_platform_index` (`mitra_id`,`platform`),
  KEY `set_up_lms_issues_created_by_foreign` (`created_by`),
  CONSTRAINT `set_up_lms_issues_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `set_up_lms_issues_mitra_id_foreign` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_10_06_100000_create_set_up_lms_issues_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_06_100000_create_set_up_lms_issues_table');
