<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sys_filing';

    public function up(): void
    {
        if (Schema::connection(self::CONN)->hasTable(self::TABLE)) {
            return;
        }

        $conn = DB::connection(self::CONN);
        $sqlMode = (string) $conn->selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode;

        try {
            $conn->statement("SET SESSION sql_mode = ''");
            $conn->statement(<<<'SQL'
CREATE TABLE `sys_filing` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `file_code` varchar(50) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_root` varchar(100) NOT NULL DEFAULT 'filing_storage',
  `storage_dir` varchar(500) NOT NULL,
  `storage_name` varchar(255) NOT NULL,
  `storage_path` varchar(700) NOT NULL,
  `zip_size` bigint(20) NOT NULL DEFAULT 0,
  `file_count` int(11) NOT NULL DEFAULT 0,
  `total_uncompressed_size` bigint(20) NOT NULL DEFAULT 0,
  `declared_file_type` varchar(50) DEFAULT NULL,
  `detected_file_type` varchar(50) DEFAULT NULL,
  `access_mode` enum('private','public_internal','custom') NOT NULL DEFAULT 'private',
  `security_level` enum('normal','restricted','confidential') NOT NULL DEFAULT 'normal',
  `uploaded_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `status` enum('active','archived','trashed','deleted','expired','blocked') NOT NULL DEFAULT 'active',
  `is_share_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `expired_at` datetime DEFAULT NULL,
  `expired_action` enum('none','archive','trash','delete') NOT NULL DEFAULT 'trash',
  `expired_processed_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `trashed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `uq_file_code` (`file_code`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_expired_at` (`expired_at`),
  KEY `idx_access_mode` (`access_mode`),
  KEY `idx_security_level` (`security_level`),
  KEY `idx_storage_path` (`storage_path`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        } finally {
            $conn->statement('SET SESSION sql_mode = ?', [$sqlMode]);
        }
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists(self::TABLE);
    }
};
