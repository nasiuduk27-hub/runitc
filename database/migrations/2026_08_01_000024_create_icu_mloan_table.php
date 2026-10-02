<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';
    private const TABLE = 'icu_mloan';

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
CREATE TABLE `icu_mloan` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `pprd` char(6) NOT NULL,
  `trncd` char(2) NOT NULL,
  `trnno` char(12) NOT NULL DEFAULT '',
  `trndt` date NOT NULL DEFAULT '0000-00-00',
  `icu_rec_id` int(11) NOT NULL DEFAULT 0,
  `descr` varchar(50) NOT NULL,
  `principle` int(11) NOT NULL DEFAULT 0,
  `interamt` int(11) NOT NULL DEFAULT 0,
  `interest` decimal(5,2) NOT NULL DEFAULT 0.00,
  `int_overdue` int(11) NOT NULL DEFAULT 0,
  `bnk_charge` int(11) NOT NULL DEFAULT 0,
  `bnktrx_no` varchar(15) NOT NULL,
  `totalloan` int(11) NOT NULL DEFAULT 0,
  `paid` int(11) NOT NULL DEFAULT 0,
  `avgmon` int(11) NOT NULL DEFAULT 0,
  `avgint` int(11) NOT NULL DEFAULT 0,
  `monthly` int(11) NOT NULL DEFAULT 0,
  `term` int(11) NOT NULL DEFAULT 0,
  `startper` char(6) NOT NULL,
  `endper` char(6) NOT NULL,
  `remarks` varchar(30) NOT NULL,
  `statrec` tinyint(1) NOT NULL DEFAULT 0,
  `entdt` datetime NOT NULL DEFAULT current_timestamp(),
  `lupd` timestamp NOT NULL DEFAULT current_timestamp(),
  `entusr` varchar(5) NOT NULL DEFAULT '',
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
