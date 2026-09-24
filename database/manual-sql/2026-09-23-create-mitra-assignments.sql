-- Fitur: Assignment (follow-up Zoom untuk mitra Kurang/Warning di Data Development)
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM kode
-- fitur Assignment di-deploy (kode sudah nunggu di GitHub, commit 850ac0e s/d 7905a53).
-- Referensi migration Laravel-nya: database/migrations/2026_09_23_090000_create_mitra_assignments_table.php

CREATE TABLE `mitra_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mitra_id` bigint unsigned NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `status_bulan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'terjadwal',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mitra_assignments_mitra_id_foreign` (`mitra_id`),
  KEY `mitra_assignments_created_by_foreign` (`created_by`),
  KEY `mitra_assignments_bulan_tahun_index` (`bulan`,`tahun`),
  CONSTRAINT `mitra_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `mitra_assignments_mitra_id_foreign` FOREIGN KEY (`mitra_id`) REFERENCES `mitra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mitra_assignment_sesis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mitra_assignment_id` bigint unsigned NOT NULL,
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
  UNIQUE KEY `mitra_assignment_sesis_mitra_assignment_id_urutan_unique` (`mitra_assignment_id`,`urutan`),
  KEY `mitra_assignment_sesis_filled_by_foreign` (`filled_by`),
  CONSTRAINT `mitra_assignment_sesis_filled_by_foreign` FOREIGN KEY (`filled_by`) REFERENCES `users` (`id`),
  CONSTRAINT `mitra_assignment_sesis_mitra_assignment_id_foreign` FOREIGN KEY (`mitra_assignment_id`) REFERENCES `mitra_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_09_23_090000_create_mitra_assignments_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_09_23_090000_create_mitra_assignments_table');
