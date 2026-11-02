-- Fitur: Omset Parsial di-lock per bulan -- ganti bulan otomatis mulai
-- dari 0, baris bulan lama tetap ada (histori), gak ketimpa/ikut kehapus.
-- Tabel omset_parsials SUDAH ADA di production (dibuat 2026-10-16, baru
-- ada 1 baris "latest wins"). Referensi migration Laravel:
--   database/migrations/2026_11_02_000000_add_bulan_tahun_to_omset_parsials_table.php

ALTER TABLE `omset_parsials`
  ADD COLUMN `bulan` tinyint unsigned NULL AFTER `id`,
  ADD COLUMN `tahun` smallint unsigned NULL AFTER `bulan`;

-- Baris lama (pola single-row) di-backfill ke bulan berjalan SAAT SQL INI
-- DIJALANKAN -- asumsi paling masuk akal, karena sebelumnya cuma ada 1
-- baris aktif yang berarti angka BULAN INI.
UPDATE `omset_parsials`
SET `bulan` = MONTH(CURDATE()), `tahun` = YEAR(CURDATE())
WHERE `bulan` IS NULL;

ALTER TABLE `omset_parsials`
  MODIFY COLUMN `bulan` tinyint unsigned NOT NULL,
  MODIFY COLUMN `tahun` smallint unsigned NOT NULL,
  ADD UNIQUE KEY `omset_parsials_bulan_tahun_unique` (`bulan`, `tahun`);

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_11_02_000000_add_bulan_tahun_to_omset_parsials_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_02_000000_add_bulan_tahun_to_omset_parsials_table');
