<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';
    private const TABLE = 'icu_dloan';

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
CREATE TABLE `icu_dloan` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `mst_rec_id` int(11) NOT NULL,
  `periode` char(6) NOT NULL,
  `seqno` int(11) NOT NULL DEFAULT 1,
  `totseqno` int(11) NOT NULL DEFAULT 1,
  `descr` varchar(40) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `rnd_amt` int(11) NOT NULL DEFAULT 0,
  `int_amt` int(11) NOT NULL DEFAULT 0,
  `rnd_int` int(11) NOT NULL DEFAULT 0,
  `others` int(11) NOT NULL DEFAULT 0,
  `outstand` int(11) NOT NULL DEFAULT 0,
  `remarks` varchar(20) NOT NULL,
  `dseqno` int(11) NOT NULL DEFAULT 1,
  `paidst` tinyint(1) NOT NULL DEFAULT 0,
  `payno` char(12) NOT NULL DEFAULT '',
  `lupd` timestamp NOT NULL DEFAULT current_timestamp(),
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
