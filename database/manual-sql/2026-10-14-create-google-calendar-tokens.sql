-- Fitur: integrasi Google Calendar di menu Assignment (jadwal Zoom otomatis
-- tersinkron ke Google Calendar).
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel/kolom barunya).
-- Referensi migration Laravel:
--   database/migrations/2026_10_14_074518_create_google_calendar_tokens_table.php
--   database/migrations/2026_10_14_074520_add_google_event_id_to_mitra_assignment_sesis_table.php

CREATE TABLE `google_calendar_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `access_token` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `refresh_token` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `google_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `connected_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `google_calendar_tokens_connected_by_foreign` (`connected_by`),
  CONSTRAINT `google_calendar_tokens_connected_by_foreign` FOREIGN KEY (`connected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `mitra_assignment_sesis`
  ADD COLUMN `google_event_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `jadwal_zoom`;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_10_14_074518_create_google_calendar_tokens_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_14_074518_create_google_calendar_tokens_table');
INSERT INTO migrations (migration, batch)
SELECT '2026_10_14_074520_add_google_event_id_to_mitra_assignment_sesis_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_14_074520_add_google_event_id_to_mitra_assignment_sesis_table');
