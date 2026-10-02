<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sys_menus';

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
CREATE TABLE `sys_menus` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `mst_id` int(11) NOT NULL DEFAULT 0,
  `title` varchar(70) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT '',
  `icon` varchar(50) NOT NULL DEFAULT '',
  `section_key` varchar(80) DEFAULT NULL,
  `section_label` varchar(120) DEFAULT NULL,
  `section_sort` int(11) NOT NULL DEFAULT 0,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`rec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci
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
