-- Fitur: menu Akuisisi > Database Lead (CRM lead calon mitra, porting dari
-- app standalone "PartnerAcquire CRM"). 10 tabel baru, prefix `akuisisi_`
-- konsisten sama `akuisisi_referrals` yang sudah ada. TIDAK bawa tabel
-- users/sessions sendiri -- created_by/updated_by semuanya FK ke `users`
-- SRN Tracker yang sudah ada.
-- Kirim SQL ini ke tim IT untuk dijalankan di database production SEBELUM
-- kode fitur ini di-deploy (FTP file saja tidak membuat tabel barunya).
-- Referensi migration Laravel:
--   database/migrations/2026_11_02_100000_create_akuisisi_leads_tables.php
-- DDL di bawah diambil langsung dari SHOW CREATE TABLE hasil migration ini
-- di database lokal, jadi dijamin persis sama yang Laravel bikin.

CREATE TABLE `akuisisi_channels` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_channels_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_business_statuses` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` tinyint unsigned NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_business_statuses_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_interest_levels` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` tinyint unsigned NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_interest_levels_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_capital_statuses` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` tinyint unsigned NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_capital_statuses_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_pipeline_stages` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `goal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exit_criteria` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_pipeline_stages_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_followup_statuses` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stops_schedule` tinyint(1) NOT NULL DEFAULT '0',
  `auto_schedule` tinyint(1) NOT NULL DEFAULT '1',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `is_closed_deal` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_followup_statuses_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_priorities` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_score` tinyint unsigned NOT NULL,
  `max_score` tinyint unsigned NOT NULL,
  `interval_days` smallint unsigned NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_priorities_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_import_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_rows` int unsigned NOT NULL DEFAULT '0',
  `success_rows` int unsigned NOT NULL DEFAULT '0',
  `failed_rows` int unsigned NOT NULL DEFAULT '0',
  `error_report` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `akuisisi_import_batches_created_by_foreign` (`created_by`),
  CONSTRAINT `akuisisi_import_batches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel_id` smallint unsigned NOT NULL,
  `business_status_id` smallint unsigned DEFAULT NULL,
  `interest_level_id` smallint unsigned DEFAULT NULL,
  `capital_status_id` smallint unsigned DEFAULT NULL,
  `score` tinyint unsigned DEFAULT NULL,
  `priority_id` smallint unsigned DEFAULT NULL,
  `pipeline_stage_id` smallint unsigned DEFAULT NULL,
  `last_followup_date` date DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `latest_followup_status_id` smallint unsigned DEFAULT NULL,
  `latest_note` text COLLATE utf8mb4_unicode_ci,
  `no_response_streak` tinyint unsigned NOT NULL DEFAULT '0',
  `is_legacy` tinyint(1) NOT NULL DEFAULT '0',
  `legacy_data` json DEFAULT NULL,
  `import_batch_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akuisisi_leads_whatsapp_unique` (`whatsapp`),
  KEY `akuisisi_leads_created_by_foreign` (`created_by`),
  KEY `akuisisi_leads_updated_by_foreign` (`updated_by`),
  KEY `akuisisi_leads_next_followup_date_index` (`next_followup_date`),
  KEY `akuisisi_leads_entry_date_index` (`entry_date`),
  KEY `akuisisi_leads_name_index` (`name`),
  KEY `akuisisi_leads_deleted_at_index` (`deleted_at`),
  KEY `akuisisi_leads_channel_id_foreign` (`channel_id`),
  KEY `akuisisi_leads_business_status_id_foreign` (`business_status_id`),
  KEY `akuisisi_leads_interest_level_id_foreign` (`interest_level_id`),
  KEY `akuisisi_leads_capital_status_id_foreign` (`capital_status_id`),
  KEY `akuisisi_leads_priority_id_foreign` (`priority_id`),
  KEY `akuisisi_leads_pipeline_stage_id_foreign` (`pipeline_stage_id`),
  KEY `akuisisi_leads_latest_followup_status_id_foreign` (`latest_followup_status_id`),
  KEY `akuisisi_leads_import_batch_id_foreign` (`import_batch_id`),
  CONSTRAINT `akuisisi_leads_business_status_id_foreign` FOREIGN KEY (`business_status_id`) REFERENCES `akuisisi_business_statuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_leads_capital_status_id_foreign` FOREIGN KEY (`capital_status_id`) REFERENCES `akuisisi_capital_statuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_leads_channel_id_foreign` FOREIGN KEY (`channel_id`) REFERENCES `akuisisi_channels` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `akuisisi_leads_import_batch_id_foreign` FOREIGN KEY (`import_batch_id`) REFERENCES `akuisisi_import_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `akuisisi_leads_interest_level_id_foreign` FOREIGN KEY (`interest_level_id`) REFERENCES `akuisisi_interest_levels` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_leads_latest_followup_status_id_foreign` FOREIGN KEY (`latest_followup_status_id`) REFERENCES `akuisisi_followup_statuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_leads_pipeline_stage_id_foreign` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `akuisisi_pipeline_stages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_leads_priority_id_foreign` FOREIGN KEY (`priority_id`) REFERENCES `akuisisi_priorities` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_leads_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `akuisisi_followups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `followup_date` date NOT NULL,
  `followup_status_id` smallint unsigned NOT NULL,
  `pipeline_stage_id` smallint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `next_followup_date` date DEFAULT NULL,
  `is_next_manual` tinyint(1) NOT NULL DEFAULT '0',
  `priority_id` smallint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `akuisisi_followups_created_by_foreign` (`created_by`),
  KEY `akuisisi_followups_updated_by_foreign` (`updated_by`),
  KEY `akuisisi_followups_lead_id_followup_date_index` (`lead_id`,`followup_date`),
  KEY `akuisisi_followups_followup_status_id_foreign` (`followup_status_id`),
  KEY `akuisisi_followups_pipeline_stage_id_foreign` (`pipeline_stage_id`),
  KEY `akuisisi_followups_priority_id_foreign` (`priority_id`),
  CONSTRAINT `akuisisi_followups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `akuisisi_followups_followup_status_id_foreign` FOREIGN KEY (`followup_status_id`) REFERENCES `akuisisi_followup_statuses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_followups_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `akuisisi_leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `akuisisi_followups_pipeline_stage_id_foreign` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `akuisisi_pipeline_stages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_followups_priority_id_foreign` FOREIGN KEY (`priority_id`) REFERENCES `akuisisi_priorities` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `akuisisi_followups_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master data (lookup) -- nilai persis sama database/seeders/AkuisisiMasterDataSeeder.php.
-- Tanpa ini, menu Database Lead gak bisa dipakai sama sekali (dropdown Channel dll
-- bakal kosong, padahal channel_id itu NOT NULL). Idempotent (upsert by code), aman
-- dijalankan ulang kalau IT re-run script ini.

INSERT INTO `akuisisi_channels` (`code`, `name`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
  ('ADS', 'Ads', 1, 1, NOW(), NOW()),
  ('ADS_WA', 'Ads WA', 2, 1, NOW(), NOW()),
  ('LINK_BIO', 'Link Bio', 3, 1, NOW(), NOW()),
  ('DM_IG', 'DM IG', 4, 1, NOW(), NOW()),
  ('WEBINAR', 'Webinar', 5, 1, NOW(), NOW()),
  ('OFFLINE_EVENT', 'Offline Event', 6, 1, NOW(), NOW()),
  ('REFERRAL', 'Referral', 7, 1, NOW(), NOW()),
  ('OTHERS', 'Others', 8, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`), `is_active` = VALUES(`is_active`), `updated_at` = VALUES(`updated_at`);

INSERT INTO `akuisisi_business_statuses` (`code`, `name`, `score`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('BEAUTY', 'Sudah berbisnis kecantikan', 3, 1, NOW(), NOW()),
  ('OTHER', 'Sudah berbisnis lain', 2, 2, NOW(), NOW()),
  ('NONE', 'Belum berbisnis', 1, 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `score` = VALUES(`score`), `sort_order` = VALUES(`sort_order`), `updated_at` = VALUES(`updated_at`);

INSERT INTO `akuisisi_interest_levels` (`code`, `name`, `score`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('ASKING', 'Cuma Tanya-tanya', 1, 1, NOW(), NOW()),
  ('PRICELIST', 'Tanya Pricelist', 2, 2, NOW(), NOW()),
  ('PROPOSAL', 'Minta Proposal', 3, 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `score` = VALUES(`score`), `sort_order` = VALUES(`sort_order`), `updated_at` = VALUES(`updated_at`);

INSERT INTO `akuisisi_capital_statuses` (`code`, `name`, `score`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('READY', 'Siap modal', 3, 1, NOW(), NOW()),
  ('LT_5JT', 'Kurang dari 5 juta', 2, 2, NOW(), NOW()),
  ('NONE', 'Tidak punya modal', 1, 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `score` = VALUES(`score`), `sort_order` = VALUES(`sort_order`), `updated_at` = VALUES(`updated_at`);

INSERT INTO `akuisisi_pipeline_stages` (`code`, `name`, `goal`, `exit_criteria`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('PROFILING', 'Profiling', 'Hubungi lead, gali status bisnis, minat, dan modal.', 'Lead merespons, status bisnis & modal sudah diketahui, dan ingin tahu skema kemitraan.', 1, NOW(), NOW()),
  ('SKEMA', 'Skema Kemitraan', 'Jelaskan skema & pricelist, jawab pertanyaan lead.', 'Lead minta proposal, memilih paket, atau membahas budget secara konkret.', 2, NOW(), NOW()),
  ('PITCHING', 'Pitching', 'Kirim proposal dan dorong keputusan.', 'Selesai: Closing, atau gugur (Not Interested / Tidak Ada Modal).', 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `goal` = VALUES(`goal`), `exit_criteria` = VALUES(`exit_criteria`), `sort_order` = VALUES(`sort_order`), `updated_at` = VALUES(`updated_at`);

INSERT INTO `akuisisi_followup_statuses` (`code`, `name`, `stops_schedule`, `auto_schedule`, `is_archived`, `is_closed_deal`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('DISKUSI', 'Respon / Diskusi', 0, 1, 0, 0, 1, NOW(), NOW()),
  ('NO_RESPON', 'No Respon', 0, 1, 0, 0, 2, NOW(), NOW()),
  ('POSTPONED', 'Postponed', 0, 1, 0, 0, 3, NOW(), NOW()),
  ('CLOSING', 'Closing', 1, 0, 1, 1, 4, NOW(), NOW()),
  ('NOT_INTERESTED', 'Not Interested', 0, 0, 1, 0, 5, NOW(), NOW()),
  ('NO_CAPITAL', 'Tidak Ada Modal', 0, 0, 1, 0, 6, NOW(), NOW()),
  ('INVALID_NUMBER', 'Invalid Number', 1, 0, 1, 0, 7, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `stops_schedule` = VALUES(`stops_schedule`), `auto_schedule` = VALUES(`auto_schedule`), `is_archived` = VALUES(`is_archived`), `is_closed_deal` = VALUES(`is_closed_deal`), `sort_order` = VALUES(`sort_order`), `updated_at` = VALUES(`updated_at`);

INSERT INTO `akuisisi_priorities` (`code`, `name`, `min_score`, `max_score`, `interval_days`, `sort_order`, `created_at`, `updated_at`) VALUES
  ('HOT', 'Hot Lead', 8, 9, 2, 1, NOW(), NOW()),
  ('WARM', 'Warm Lead', 5, 7, 7, 2, NOW(), NOW()),
  ('COLD', 'Cold Lead', 3, 4, 30, 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `min_score` = VALUES(`min_score`), `max_score` = VALUES(`max_score`), `interval_days` = VALUES(`interval_days`), `sort_order` = VALUES(`sort_order`), `updated_at` = VALUES(`updated_at`);

SET @batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (migration, batch)
SELECT '2026_11_02_100000_create_akuisisi_leads_tables', @batch FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_02_100000_create_akuisisi_leads_tables');
