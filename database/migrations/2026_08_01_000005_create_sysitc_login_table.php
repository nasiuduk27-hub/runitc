<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'sysitc_login';

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
CREATE TABLE `sysitc_login` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` varchar(30) NOT NULL,
  `password_id` varchar(32) NOT NULL,
  `email_id` varchar(30) NOT NULL,
  `account_nm` varchar(30) NOT NULL,
  `whatsapp` varchar(15) NOT NULL DEFAULT '',
  `entdt` date NOT NULL DEFAULT '0000-00-00',
  `lupdt` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` char(1) NOT NULL DEFAULT '1',
  `token` varchar(255) DEFAULT NULL,
  `token_exp` datetime DEFAULT NULL,
  `token_used` datetime DEFAULT NULL,
  `lastlogin` datetime NOT NULL,
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
