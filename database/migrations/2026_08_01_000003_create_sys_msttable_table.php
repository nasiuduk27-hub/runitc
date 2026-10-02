<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sys_msttable';

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
CREATE TABLE `sys_msttable` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `tbl_code` char(2) NOT NULL,
  `code` char(5) NOT NULL DEFAULT '',
  `urutan` tinyint(1) NOT NULL DEFAULT 0,
  `descr` varchar(50) NOT NULL,
  `notes` varchar(30) NOT NULL,
  `AddiNotes` char(5) NOT NULL DEFAULT '',
  `statrec` int(11) NOT NULL DEFAULT 1,
  `lupdt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rec_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci
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
