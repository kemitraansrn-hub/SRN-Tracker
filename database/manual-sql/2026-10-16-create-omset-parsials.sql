-- Fitur: "Omset Parsial" di Dashboard (card "MTD vs Bulan Lalu") -- angka
-- manual admin buat nandain omset yang belum sempat masuk database.
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel barunya).
-- Referensi migration Laravel:
--   database/migrations/2026_10_16_022614_create_omset_parsials_table.php

CREATE TABLE `omset_parsials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jumlah` decimal(15,2) NOT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `omset_parsials_updated_by_foreign` (`updated_by`),
  CONSTRAINT `omset_parsials_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_10_16_022614_create_omset_parsials_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_16_022614_create_omset_parsials_table');
