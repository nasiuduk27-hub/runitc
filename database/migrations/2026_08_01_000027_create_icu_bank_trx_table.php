<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';
    private const TABLE = 'icu_bank_trx';

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
CREATE TABLE `icu_bank_trx` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `pprdk` varchar(6) NOT NULL,
  `trnno` char(12) NOT NULL,
  `trndt` date NOT NULL,
  `req_frm_trxno` char(12) NOT NULL,
  `dbocr` char(1) NOT NULL DEFAULT '',
  `icu_rec_id` int(11) NOT NULL DEFAULT 0,
  `descr` varchar(100) NOT NULL,
  `amount` int(11) NOT NULL,
  `statrec` int(11) NOT NULL DEFAULT 0,
  `edit_enable` int(11) NOT NULL DEFAULT 1,
  `notes` varchar(50) NOT NULL,
  `confno` varchar(10) NOT NULL DEFAULT '',
  `confdt` date NOT NULL DEFAULT '0000-00-00',
  `lupd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `entusr` varchar(3) NOT NULL DEFAULT '',
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `trnno_unique` (`trnno`)
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
