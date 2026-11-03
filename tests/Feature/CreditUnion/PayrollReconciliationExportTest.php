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

    public function test_ringkasan_kasus_contoh_dan_penyeragaman_huruf_besar_id(): void
    {
        $members = str_replace('CU-001', 'cu-001', $this->sample('anggota'));
        $bank = str_replace('DEMO-AAA-01', 'demo-aaa-01', $this->sample('bank'));
        $response = $this->withSession(['user_id' => 10])->post(self::ENDPOINT, $this->payload($members, $bank));
        $response->assertOk()->assertDownload('Rekon-202610-AAA.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'cu-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = (new Xlsx)->load($path);
            $summary = $book->getSheetByName('Ringkasan');
            $detail = $book->getSheetByName('Anggota');
            $bankSheet = $book->getSheetByName('Bank');

            // ID anggota dan referensi bank diseragamkan ke huruf kapital
            $this->assertSame('CU-001', $detail->getCell('A8')->getValue());
            $this->assertSame('CU-002', $detail->getCell('A9')->getValue());
            $this->assertSame('DEMO-AAA-01', $bankSheet->getCell('A8')->getValue());

            // Assertion angka Ringkasan kasus contoh
            $this->assertEquals(700000, $summary->getCell('B10')->getCalculatedValue());
            $this->assertEquals(680000, $summary->getCell('B11')->getCalculatedValue());
            $this->assertEquals(675000, $summary->getCell('B12')->getCalculatedValue());
            $this->assertEquals(670000, $summary->getCell('B13')->getCalculatedValue());
            $this->assertSame('Telusuri selisih', $summary->getCell('B21')->getCalculatedValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_excel_mengisolasi_periode_dan_perusahaan_serta_mempertahankan_rumus_varian_31_anggota(): void
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
            $summary = $book->getSheetByName('Ringkasan');
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
            $this->assertSame('Uji lokal', $summary->getCell('B7')->getValue());

            // Varian 31 anggota: 2 anggota contoh + 29 anggota x 10 = 700.290 tagihan, 680.290 payroll, 670.290 posting
            $this->assertEquals(700290, $summary->getCell('B10')->getCalculatedValue());
            $this->assertEquals(680290, $summary->getCell('B11')->getCalculatedValue());
            $this->assertEquals(675000, $summary->getCell('B12')->getCalculatedValue());
            $this->assertEquals(670290, $summary->getCell('B13')->getCalculatedValue());
            $this->assertSame('Telusuri selisih', $summary->getCell('B21')->getCalculatedValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_csv_berakhir_crlf_dan_ber_bom_tetap_lolos_dan_hasilnya_benar(): void
    {
        $bom = "\xEF\xBB\xBF";
        $crlfMembers = $bom.str_replace("\n", "\r\n", $this->sample('anggota'))."\r\n";
        $crlfBank = $bom.str_replace("\n", "\r\n", $this->sample('bank'))."\r\n";

        $response = $this->withSession(['user_id' => 10])->post(self::ENDPOINT, [
            'period' => '202610',
            'cmpcd' => 'AAA',
            'asal_data' => 'Uji CRLF dan BOM',
            'anggota' => UploadedFile::fake()->createWithContent('anggota.csv', $crlfMembers),
            'bank' => UploadedFile::fake()->createWithContent('bank.csv', $crlfBank),
        ]);

        $response->assertOk()->assertDownload('Rekon-202610-AAA.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'cu-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = (new Xlsx)->load($path);
            $summary = $book->getSheetByName('Ringkasan');
            $detail = $book->getSheetByName('Anggota');
            $bankSheet = $book->getSheetByName('Bank');

            $this->assertSame('CU-001', $detail->getCell('A8')->getValue());
            $this->assertSame('CU-002', $detail->getCell('A9')->getValue());
            $this->assertSame('DEMO-AAA-01', $bankSheet->getCell('A8')->getValue());
            $this->assertEquals(700000, $summary->getCell('B10')->getCalculatedValue());
            $this->assertEquals(680000, $summary->getCell('B11')->getCalculatedValue());
            $this->assertEquals(675000, $summary->getCell('B12')->getCalculatedValue());
            $this->assertEquals(670000, $summary->getCell('B13')->getCalculatedValue());
            $this->assertSame('Telusuri selisih', $summary->getCell('B21')->getCalculatedValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_nominal_bank_negatif_pembalikan_diproses_dan_tertotal_dengan_benar(): void
    {
        $bank = "periode;perusahaan;referensi;nominal;bukti\n202610;AAA;DEMO-AAA-01;700000;Penerimaan payroll\n202610;AAA;DEMO-AAA-02;-25000;Pembalikan kelebihan";
        $response = $this->withSession(['user_id' => 10])->post(self::ENDPOINT, $this->payload($this->sample('anggota'), $bank));
        $response->assertOk()->assertDownload('Rekon-202610-AAA.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'cu-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = (new Xlsx)->load($path);
            $summary = $book->getSheetByName('Ringkasan');
            $bankSheet = $book->getSheetByName('Bank');

            $this->assertEquals(700000, $bankSheet->getCell('B8')->getValue());
            $this->assertSame('Cocok', $bankSheet->getCell('D8')->getCalculatedValue());
            $this->assertEquals(-25000, $bankSheet->getCell('B9')->getValue());
            $this->assertSame('Cocok', $bankSheet->getCell('D9')->getCalculatedValue());
            $this->assertEquals(675000, $summary->getCell('B12')->getCalculatedValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_lunas_sebelum_melebihi_tagihan_ditolak_dengan_pesan_validasi(): void
    {
        $this->withSession(['user_id' => 10]);
        // Baris 1: Header (rekaman 1). Baris 2: Data (rekaman 2). Simpanan=100k, Pokok=0, Bunga=0, Lunas=150k (melebihi tagihan 100k).
        $members = "periode;perusahaan;id_anggota;nama;simpanan;pokok;bunga_biaya;lunas_sebelum;payroll;posting;catatan\n202610;AAA;CU-001;Anggota Lebih;100000;0;0;150000;0;0;Pelunasan lebih";
        $response = $this->post(self::ENDPOINT, $this->payload($members));
        $response->assertSessionHasErrors([
            'anggota' => 'CSV anggota: pelunasan melebihi tagihan pada rekaman 2.',
        ]);
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
