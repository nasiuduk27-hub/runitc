<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sys_filing_audit';

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
CREATE TABLE `sys_filing_audit` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `filing_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rec_id`),
  KEY `idx_filing_id` (`filing_id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_created_at` (`created_at`)
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
