<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sysitc_userbank';

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
CREATE TABLE `sysitc_userbank` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_recid` int(11) NOT NULL DEFAULT 0,
  `bnkcd` varchar(15) NOT NULL,
  `accno` varchar(15) NOT NULL DEFAULT '',
  `accnm` varchar(40) NOT NULL DEFAULT '',
  `asdefault` tinyint(4) NOT NULL DEFAULT 0,
  `entdt` timestamp NOT NULL DEFAULT current_timestamp(),
  `lupdt` timestamp NOT NULL DEFAULT current_timestamp(),
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
