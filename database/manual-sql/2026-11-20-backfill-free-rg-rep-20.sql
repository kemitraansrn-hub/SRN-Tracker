-- Fitur: FREE-RG-REP-20 gak pernah kebuat di Master Produk karena kode
-- import Order Harian (app/Services/OrderImportService.php,
-- isRealProductName()) dulu menolak produk apapun yang harga & subtotalnya
-- nol, padahal itu nama/SKU-nya jelas, cuma memang item gratis (FREE),
-- bukan placeholder/sampah. Kode importnya sudah dibenerin (commit terkait
-- sesi ini) supaya ke depan item gratis bernama jelas tetap dibuatkan
-- Produk baru. SQL ini membereskan data LAMA: bikinkan Produk
-- FREE-RG-REP-20 kalau belum ada, lalu cocokkan ulang order_items lama
-- yang produk_id-nya masih NULL tapi sebenarnya item ini.
--
-- Kirim SQL ini ke tim IT untuk dijalankan di database production. AMAN
-- dijalankan BERULANG KALI (idempotent) -- INSERT dijaga NOT EXISTS,
-- UPDATE cuma menyentuh baris yang produk_id-nya masih NULL.

-- 1) Pastikan Produk FREE-RG-REP-20 ada.
INSERT INTO `produk` (`kode_sku`, `nama`, `brand`, `status`, `auto_created`, `created_at`, `updated_at`)
SELECT 'FREE-RG-REP-20', 'FREE - Rice PDRN Hydra Smooth Essence Pad-20GR', 'Reglow', 'aktif', 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `produk` WHERE `kode_sku` = 'FREE-RG-REP-20');

-- 2) Cocokkan ulang order_items lama yang produk_id-nya masih NULL tapi
--    sku_raw/nama_produk_raw-nya menunjukkan FREE-RG-REP-20 (dicocokkan ke
--    sku_raw dulu karena paling presisi, plus 2 varian teks nama yang
--    mungkin muncul tergantung sumber file upload-nya).
UPDATE `order_items` `oi`
JOIN `produk` `p` ON `p`.`kode_sku` = 'FREE-RG-REP-20'
SET `oi`.`produk_id` = `p`.`id`
WHERE `oi`.`produk_id` IS NULL
  AND (
    `oi`.`sku_raw` = 'FREE-RG-REP-20'
    OR `oi`.`nama_produk_raw` = 'FREE - Rice PDRN Hydra Smooth Essence Pad-20GR'
    OR `oi`.`nama_produk_raw` = 'FREE - Rice PDRN Hydra Smooth Essence Pad, 20GR'
  );
