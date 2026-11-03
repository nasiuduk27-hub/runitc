<?php

namespace Tests\Feature\CreditUnion;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class PayrollReconciliationExportTest extends TestCase
{
    private const ENDPOINT = '/credit-union/monthly-processing/rekonsiliasi';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        foreach (['mysql', 'run'] as $connection) {
            config(["database.connections.$connection" => ['driver' => 'sqlite', 'database' => ':memory:']]);
            DB::purge($connection);
        }
        $run = DB::connection('run');
        $run->statement('CREATE TABLE sysitc_usracc (user_rec_id INTEGER, access_code TEXT, access_account TEXT)');
        $run->statement('CREATE TABLE sysitc_grpacc (grpaccess TEXT, grpacc TEXT, grpdesc TEXT)');
        $run->statement("INSERT INTO sysitc_usracc VALUES (10, '03', '410')");
        $run->statement("INSERT INTO sysitc_grpacc VALUES ('03', '410', 'CU Admin')");
        $main = DB::connection('mysql');
        $main->statement('CREATE TABLE icu_member (itc_user_id INTEGER, st_aktif INTEGER)');
        $main->statement('INSERT INTO icu_member VALUES (11, 2)');
        $main->statement('CREATE TABLE sys_msttable (tbl_code TEXT, statrec INTEGER, code TEXT, descr TEXT)');
        $main->statement("INSERT INTO sys_msttable VALUES ('54', 1, 'AAA', 'Contoh A')");
    }

    public function test_akses_unduhan_memerlukan_admin(): void
    {
        $this->post(self::ENDPOINT)->assertRedirect(route('login'));
        $this->withSession(['user_id' => 11])->post(self::ENDPOINT)->assertForbidden();
        $this->get('/credit-union/monthly-processing/contoh/anggota')->assertForbidden();
        $this->withSession(['user_id' => 10])->get('/credit-union/monthly-processing/contoh/bank')->assertDownload('contoh-bank.csv');
    }

    public function test_excel_mengisolasi_periode_dan_perusahaan_serta_mempertahankan_rumus(): void
    {
        $members = $this->sample('anggota');
        for ($i = 1; $i <= 29; $i++) {
            $members .= "\n202610;AAA;EX-{$i};=1+1;10;0;0;0;10;10;";
        }
        $response = $this->withSession(['user_id' => 10])->post(self::ENDPOINT, $this->payload($members));
        $response->assertOk()->assertDownload('Rekon-202610-AAA.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'cu-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = (new Xlsx)->load($path);
            $detail = $book->getSheetByName('Anggota');
            $this->assertSame('CU-001', $detail->getCell('A8')->getValue());
            $this->assertSame('CU-002', $detail->getCell('A9')->getValue());
            $this->assertSame('EX-29', $detail->getCell('A38')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $detail->getCell('B38')->getDataType());
            $this->assertSame('=1+1', $detail->getCell('B38')->getValue());
            $this->assertStringContainsString('C38', $detail->getCell('G38')->getValue());
            $this->assertEquals(300000, $detail->getCell('G8')->getCalculatedValue());
            $this->assertEquals(10, $detail->getCell('G38')->getCalculatedValue());
            $this->assertEquals(675000, $book->getSheetByName('Bank')->getCell('B8')->getValue());
            $this->assertNull($book->getSheetByName('Bank')->getCell('A9')->getValue());
            $this->assertSame('Uji lokal', $book->getSheetByName('Ringkasan')->getCell('B7')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_input_tidak_lengkap_atau_ganda_tidak_menghasilkan_laporan(): void
    {
        $this->withSession(['user_id' => 10]);
        $members = $this->sample('anggota');
        $this->post(self::ENDPOINT, $this->payload($members."\n202610;AAA;cu-001;Duplikat;10;0;0;0;10;10;"))
            ->assertSessionHasErrors('anggota');
        $this->post(self::ENDPOINT, $this->payload(str_replace(';300000;300000;', ';;300000;', $members)))
            ->assertSessionHasErrors('anggota');
        $this->post(self::ENDPOINT, $this->payload($members, str_replace(';AAA;', ';ZZZ;', $this->sample('bank'))))
            ->assertSessionHasErrors('bank');
    }

    private function sample(string $kind): string
    {
        return trim(file_get_contents(resource_path("templates/credit-union/contoh-{$kind}.csv")));
    }

    private function payload(string $members, ?string $bank = null): array
    {
        return [
            'period' => '202610', 'cmpcd' => 'AAA', 'asal_data' => 'Uji lokal',
            'anggota' => UploadedFile::fake()->createWithContent('anggota.csv', $members),
            'bank' => UploadedFile::fake()->createWithContent('bank.csv', $bank ?? $this->sample('bank')),
        ];
    }
}
