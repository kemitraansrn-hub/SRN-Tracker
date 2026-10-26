-- Fitur baru: menu Sales > Cek Trend SKU (+ tab "Input Trend SKU" di menu Input NPD).
-- Menambah 1 tabel baru: trend_skus.

CREATE TABLE `trend_skus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produk_id` bigint unsigned NOT NULL,
  `tanggal_mulai_campaign` date NOT NULL,
  `ditandai_oleh` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trend_skus_produk_id_unique` (`produk_id`),
  KEY `trend_skus_ditandai_oleh_foreign` (`ditandai_oleh`),
  CONSTRAINT `trend_skus_ditandai_oleh_foreign` FOREIGN KEY (`ditandai_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trend_skus_produk_id_foreign` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_26_014157_create_trend_skus_table', (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations` m2)
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_26_014157_create_trend_skus_table'
);
