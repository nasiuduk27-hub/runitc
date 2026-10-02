<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';
    private const TABLE = 'sys_mstclient';

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
CREATE TABLE `sys_mstclient` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `login_id` int(11) NOT NULL DEFAULT 0,
  `clienttype` char(2) NOT NULL DEFAULT '--',
  `clientnm` varchar(50) NOT NULL DEFAULT '--',
  `code_mst` varchar(20) NOT NULL DEFAULT '',
  `refno` varchar(20) DEFAULT '---',
  `address` varchar(100) DEFAULT '---',
  `city` varchar(30) DEFAULT '---',
  `provcd` varchar(2) DEFAULT '--',
  `poscode` varchar(5) DEFAULT '---',
  `email` varchar(50) DEFAULT '---',
  `phone` varchar(15) DEFAULT '---',
  `urlweb` varchar(50) DEFAULT '---',
  `pic_name` varchar(40) DEFAULT '---',
  `pic_phone` varchar(15) DEFAULT '---',
  `client_pct` varchar(3) NOT NULL DEFAULT 'L01',
  `entusr` char(3) NOT NULL DEFAULT 'OTO',
  `entdt` timestamp NOT NULL DEFAULT current_timestamp(),
  `lupdt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rec_id`),
  KEY `mst index` (`rec_id`,`login_id`,`clienttype`,`refno`,`provcd`,`city`)
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
