<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONN = 'run';
    private const TABLE = 'tad_supervisor';

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
CREATE TABLE `tad_supervisor` (
  `rec_id` int(11) NOT NULL AUTO_INCREMENT,
  `itc_usr_id` int(11) NOT NULL DEFAULT 0,
  `spv_name` varchar(30) NOT NULL DEFAULT 'Spv Name',
  `spv_alias` varchar(10) NOT NULL DEFAULT '',
  `phone` varchar(15) NOT NULL DEFAULT '',
  `email` varchar(100) DEFAULT NULL,
  `gender` enum('L','P') NOT NULL DEFAULT 'L' COMMENT 'L=Laki-laki, P=Perempuan',
  `address` varchar(200) NOT NULL DEFAULT '',
  `city_id` varchar(10) DEFAULT NULL,
  `prov_cd` varchar(2) DEFAULT NULL COMMENT 'Kode Provinsi Homebase',
  `photo_path` varchar(255) DEFAULT NULL COMMENT 'Path Foto Profil',
  `lvl_spv` int(11) NOT NULL DEFAULT 0,
  `skills_notes` text DEFAULT NULL,
  `captain` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Suspend',
  `spv_category` enum('REMOTE','ONSITE','TCA_SSW') DEFAULT NULL COMMENT 'Kategori Pengawasan',
  `entdt` datetime NOT NULL DEFAULT current_timestamp(),
  `lupd` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci
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
