<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';
    private const TABLE = 'icu_member';

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
CREATE TABLE `icu_member` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `itc_user_id` int(11) NOT NULL DEFAULT 0,
  `pprdk` varchar(6) NOT NULL DEFAULT '',
  `icuno` char(7) NOT NULL,
  `icunm` varchar(40) NOT NULL DEFAULT 'not available',
  `alias_nm` varchar(10) NOT NULL DEFAULT '',
  `joindt` date NOT NULL DEFAULT '0000-00-00',
  `st_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `temp_trx` int(11) NOT NULL DEFAULT 0,
  `otvalue` int(11) NOT NULL DEFAULT 0,
  `swajib` int(11) NOT NULL DEFAULT 0,
  `outstanding` int(11) NOT NULL DEFAULT 0,
  `stat_trx` int(11) NOT NULL DEFAULT 0,
  `refno` varchar(15) NOT NULL DEFAULT ' ',
  `entusr` varchar(5) NOT NULL DEFAULT 'AUTO',
  `entdt` datetime NOT NULL DEFAULT current_timestamp(),
  `lupd` timestamp NOT NULL DEFAULT current_timestamp(),
  `koreksi` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `icuno_unique` (`icuno`)
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
