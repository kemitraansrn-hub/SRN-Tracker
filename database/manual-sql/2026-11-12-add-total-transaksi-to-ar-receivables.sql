-- Fitur: Data Piutang / AR - ganti basis "Total Order" (orders.total_transaksi)
-- jadi "Total Transaksi" yang diinput manual per AR (karena nilai transaksi
-- riil belum ada di database manapun), plus "Tanggal Kirim" (juga manual)
-- yang jadi basis hitungan jatuh tempo (tanggal_kirim + 14/30 hari), ganti
-- dari sebelumnya yang dihitung dari tanggal input hari itu.
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak menambah kolom barunya).
-- AMAN langsung NOT NULL tanpa default: per 2026-11-12 belum ada data AR
-- sama sekali di production (dicek langsung oleh user), jadi tidak ada baris
-- lama yang perlu di-backfill.
-- Referensi migration Laravel-nya:
--   database/migrations/2026_11_12_100000_add_total_transaksi_to_ar_receivables_table.php

ALTER TABLE `ar_receivables`
  ADD COLUMN `total_transaksi` BIGINT UNSIGNED NOT NULL AFTER `order_id`,
  ADD COLUMN `tanggal_kirim` DATE NOT NULL AFTER `total_transaksi`;

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_11_12_100000_add_total_transaksi_to_ar_receivables_table', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_12_100000_add_total_transaksi_to_ar_receivables_table');
