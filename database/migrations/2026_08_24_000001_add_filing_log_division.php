<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        $exists = DB::connection(self::CONN)->table('sys_msttable')
            ->where('tbl_code', '55')
            ->where('code', 'LOG')
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection(self::CONN)->table('sys_msttable')->insert([
            'tbl_code' => '55',
            'code' => 'LOG',
            'urutan' => 0,
            'descr' => 'LOGISTIK DIVISION',
            'notes' => '',
            'AddiNotes' => '',
            'statrec' => 1,
        ]);
    }

    public function down(): void
    {
        DB::connection(self::CONN)->table('sys_msttable')
            ->where('tbl_code', '55')
            ->where('code', 'LOG')
            ->delete();
    }
};
