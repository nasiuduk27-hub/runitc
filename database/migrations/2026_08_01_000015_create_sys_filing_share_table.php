<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sys_filing_share';

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
CREATE TABLE `sys_filing_share` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `filing_id` int(11) NOT NULL,
  `share_code_preview` varchar(30) DEFAULT NULL,
  `share_code_hash` char(64) NOT NULL,
  `created_by` int(11) NOT NULL,
  `expired_at` datetime DEFAULT NULL,
  `max_access` int(11) DEFAULT NULL,
  `access_count` int(11) NOT NULL DEFAULT 0,
  `requires_password` tinyint(1) NOT NULL DEFAULT 0,
  `password_hash` varchar(255) DEFAULT NULL,
  `allow_download` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`rec_id`),
  KEY `idx_share_hash` (`share_code_hash`),
  KEY `idx_filing_id` (`filing_id`)
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
