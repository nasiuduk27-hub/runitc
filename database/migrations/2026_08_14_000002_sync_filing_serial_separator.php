<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    public function up(): void
    {
        // Sinkronkan row serial Filing System (key_code='80', owner='FSY')
        // agar pola nomor sesuai GetSernoWeb: FSY-25L-00017
        DB::connection(self::CONN)->table('sysitc_serialno')
            ->where('key_code', '80')
            ->where('owner', 'FSY')
            ->update([
                'sparerator' => '-',
            ]);
    }

    public function down(): void
    {
        DB::connection(self::CONN)->table('sysitc_serialno')
            ->where('key_code', '80')
            ->where('owner', 'FSY')
            ->update([
                'sparerator' => '',
            ]);
    }
};
