<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'mysql';
    private const TABLE = 'hrd_employee';

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
CREATE TABLE `hrd_employee` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `old_recId` int(11) NOT NULL DEFAULT 0,
  `rec_lock` tinyint(4) NOT NULL DEFAULT 0,
  `itc_user_id` int(11) NOT NULL DEFAULT 0,
  `as_hod` tinyint(1) NOT NULL DEFAULT 0,
  `spv_rec_id` int(11) NOT NULL DEFAULT 0,
  `cmpcd` char(3) NOT NULL DEFAULT '',
  `pprdk` char(6) NOT NULL,
  `resign_prd` varchar(6) NOT NULL DEFAULT ' ',
  `empno` char(11) NOT NULL DEFAULT '',
  `empnm` varchar(30) NOT NULL DEFAULT '',
  `aliasnm` varchar(15) NOT NULL DEFAULT '',
  `phone` varchar(15) NOT NULL DEFAULT '+62',
  `hpno` varchar(15) NOT NULL DEFAULT '+62',
  `birdt` date NOT NULL DEFAULT '0000-00-00',
  `stwrk` date NOT NULL DEFAULT '0000-00-00',
  `birpl` varchar(15) NOT NULL DEFAULT '',
  `rlgio` char(1) NOT NULL DEFAULT '',
  `ktpno` char(20) NOT NULL DEFAULT '',
  `enpwp` char(20) NOT NULL DEFAULT '',
  `depcd` char(5) NOT NULL DEFAULT '',
  `poscd` char(3) NOT NULL DEFAULT '',
  `levelcd` char(2) NOT NULL DEFAULT '',
  `empst` char(1) NOT NULL DEFAULT '',
  `expat` tinyint(1) NOT NULL DEFAULT 0,
  `sexmf` char(1) NOT NULL DEFAULT '',
  `marst` tinyint(1) NOT NULL DEFAULT 0,
  `child` tinyint(1) NOT NULL DEFAULT 0,
  `bnk_recid` int(11) NOT NULL DEFAULT 0,
  `email_recid` int(11) NOT NULL DEFAULT 0,
  `shfcd` char(3) NOT NULL DEFAULT '',
  `holdw` tinyint(1) NOT NULL DEFAULT 1,
  `empact` tinyint(1) NOT NULL DEFAULT 1,
  `entdt` datetime NOT NULL DEFAULT current_timestamp(),
  `lupdt` timestamp NOT NULL DEFAULT current_timestamp(),
  `entusr` int(11) NOT NULL DEFAULT 0,
  `mrtdc` tinyint(1) NOT NULL DEFAULT 0,
  `lokasi` char(2) NOT NULL DEFAULT '',
  `addrs` varchar(200) NOT NULL,
  `request_upd` tinyint(4) NOT NULL DEFAULT 0,
  `smartCartID` varchar(200) NOT NULL,
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `employee_unique` (`empno`)
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
