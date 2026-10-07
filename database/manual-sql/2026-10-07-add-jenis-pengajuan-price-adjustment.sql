-- Fitur: Price Adjustment - Jenis Pengajuan (Traffic / SKU Slow Moving).
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak menambah kolom barunya).
-- Referensi migration Laravel-nya: database/migrations/2026_10_07_100000_add_jenis_pengajuan_to_price_adjustment_requests_table.php

ALTER TABLE `price_adjustment_requests`
  ADD COLUMN `jenis_pengajuan` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SKU Slow Moving' AFTER `marketplace`;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_10_07_100000_add_jenis_pengajuan_to_price_adjustment_requests_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_10_07_100000_add_jenis_pengajuan_to_price_adjustment_requests_table');
