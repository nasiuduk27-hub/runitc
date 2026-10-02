<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sysitc_serialno';

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
CREATE TABLE `sysitc_serialno` (
  `rlock` tinyint(1) NOT NULL DEFAULT 0,
  `reclock_byID` int(11) NOT NULL DEFAULT 0,
  `owner` varchar(5) NOT NULL,
  `dbocr` char(1) NOT NULL DEFAULT '',
  `grp_trx` char(1) NOT NULL,
  `key_code` char(5) NOT NULL,
  `pre_serno` varchar(10) NOT NULL,
  `mid_serno` varchar(10) NOT NULL,
  `last_number` int(11) NOT NULL,
  `length_last_no` tinyint(4) NOT NULL DEFAULT 0,
  `pre_length` tinyint(4) NOT NULL DEFAULT 0,
  `mid_length` tinyint(4) NOT NULL DEFAULT 0,
  `tipe_serno` char(1) NOT NULL,
  `sparerator` varchar(1) NOT NULL DEFAULT '',
  `mid_sparerator` varchar(1) NOT NULL,
  `last_sparerator` varchar(1) NOT NULL DEFAULT '',
  `Notes` varchar(30) NOT NULL,
  `lupdt` timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `key_code_unique` (`key_code`)
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
