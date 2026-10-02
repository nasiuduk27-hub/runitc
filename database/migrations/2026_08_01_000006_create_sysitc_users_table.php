<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sysitc_users';

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
CREATE TABLE `sysitc_users` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `login_rec_id` int(11) NOT NULL,
  `cmpcd` int(11) NOT NULL DEFAULT 0,
  `device_id` int(11) NOT NULL,
  `account_nm` char(30) NOT NULL,
  `alias_nm` varchar(10) NOT NULL,
  `acc3chrnm` char(3) NOT NULL DEFAULT '',
  `whatsapp` varchar(15) NOT NULL DEFAULT '',
  `address` varchar(50) NOT NULL,
  `prov_cd` varchar(2) NOT NULL,
  `kotakabupaten` varchar(20) NOT NULL,
  `dob` date NOT NULL,
  `sexmf` varchar(1) NOT NULL,
  `entdt` datetime NOT NULL DEFAULT current_timestamp(),
  `updby_userid` int(11) NOT NULL DEFAULT 0,
  `lupdt` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) NOT NULL COMMENT '0 = Non-Active\r\n1 = Active User\r\n2 = Staff\r\n3 = Admin\r\n4 = Super Admin\r\n',
  `unlock_by_sys` tinyint(1) NOT NULL DEFAULT 0,
  `lastchk_by_sys` timestamp NOT NULL DEFAULT current_timestamp(),
  `rec_id_smart` int(11) NOT NULL DEFAULT 0,
  `token_smart` varchar(250) NOT NULL,
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
