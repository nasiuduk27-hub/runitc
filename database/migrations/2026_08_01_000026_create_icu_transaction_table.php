<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';
    private const TABLE = 'icu_transaction';

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
CREATE TABLE `icu_transaction` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `pprd` char(6) NOT NULL,
  `trncd` char(2) NOT NULL,
  `trnno` char(12) NOT NULL,
  `trndt` date NOT NULL DEFAULT '0000-00-00',
  `icu_rec_id` int(11) NOT NULL DEFAULT 0,
  `empno` varchar(15) NOT NULL,
  `descr` varchar(50) NOT NULL,
  `dbocr` char(1) NOT NULL DEFAULT '',
  `basic_amt` int(11) NOT NULL DEFAULT 0,
  `int_amt` int(11) NOT NULL DEFAULT 0,
  `amount` int(11) NOT NULL DEFAULT 0,
  `notes` varchar(200) NOT NULL,
  `entdt` datetime NOT NULL DEFAULT current_timestamp(),
  `entusr` varchar(3) NOT NULL,
  `lupd` timestamp NOT NULL DEFAULT current_timestamp(),
  `refno` varchar(20) NOT NULL,
  `statrec` int(11) NOT NULL DEFAULT 1,
  `statrec2` tinyint(4) NOT NULL,
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `trnno` (`trnno`)
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
