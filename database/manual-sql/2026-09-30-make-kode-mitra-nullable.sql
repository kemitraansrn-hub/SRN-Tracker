-- Fitur: Upload Target Bulanan tanpa kolom ID (cocokkan by Nama Mitra), mitra
-- baru yang gak ketemu tetap dibuat tapi tanpa kode_mitra dulu (diisi manual
-- belakangan). kode_mitra harus boleh kosong (NULL) dulu di database.
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy.
-- Referensi migration Laravel-nya: database/migrations/2026_09_30_090000_make_kode_mitra_nullable.php

ALTER TABLE `mitra` MODIFY `kode_mitra` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_09_30_090000_make_kode_mitra_nullable', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_09_30_090000_make_kode_mitra_nullable');
