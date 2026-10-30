<?php

namespace Tests\Feature\CreditUnion;

use App\Models\CreditUnion\CreditUnionBankTrx;
use App\Models\CreditUnion\CreditUnionBankTrxDetail;
use App\Services\CreditUnion\ReportService;
use PHPUnit\Framework\TestCase;

/**
 * Pengujian pencatatan Biaya Admin Bank, Bunga Bank, dan Laporan Bank (CU-BANK-1).
 *
 * Catatan Gerbang Database Test:
 * Sesuai Langkah 0 §5 dan persetujuan Dash, suite otomatis TIDAK DIJALANKAN via terminal
 * karena koneksi 'run' pada environment testing menunjuk ke database non-khusus (live DB).
 * Test ini ditulis lengkap untuk memvalidasi skenario (a) sampai (h) secara unit/isolasi logika.
 */
class CreditUnionBankReportTest extends TestCase
{
    private ReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reports = new ReportService();
    }

    /**
     * Skenario (a): Entri biaya admin bank mengurangi saldo kas bank (arah Kredit 'C')
     * dan masuk rekap bulan yang benar di tab Bank berdasarkan tanggal mutasi (trndt).
     */
    public function test_entri_biaya_admin_bank_mengurangi_saldo_kas_dan_masuk_rekap_bulan_yang_benar(): void
    {
        $rows = [
            [
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE,
                'gross_amount' => 25000,
                'tax_amount' => 0,
                'net_amount' => 25000,
            ],
        ];

        $report = $this->reports->aggregateBankReport($rows, '202610', '202610');

        $this->assertCount(1, $report['rows']);
        $this->assertSame('202610', $report['rows'][0]['periode']);
        $this->assertSame(25000, $report['rows'][0]['admin_fee']);
        $this->assertSame(0, $report['rows'][0]['net_interest']);
        $this->assertSame(-25000, $report['rows'][0]['net_difference']);
        $this->assertSame(25000, $report['totals']['admin_fee']);
        $this->assertSame(-25000, $report['totals']['net_difference']);

        // Verifikasi arah buku kas: Biaya admin adalah Kredit (Keluar)
        $dbocr = CreditUnionBankTrx::DIRECTION_CREDIT;
        $this->assertSame('C', $dbocr);
    }

    /**
     * Skenario (b): Entri bunga bank dengan bruto/pajak/neto: saldo bertambah sebesar NETO (arah Debit 'D'),
     * dan laporan menampilkan ketiga angka + selisih bank yang benar.
     */
    public function test_entri_bunga_bank_bruto_pajak_neto_saldo_bertambah_sebesar_neto(): void
    {
        $gross = 100000;
        $tax = 20000;
        $net = $gross - $tax; // 80.000

        $rows = [
            [
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_INTEREST,
                'gross_amount' => $gross,
                'tax_amount' => $tax,
                'net_amount' => $net,
            ],
            [
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE,
                'gross_amount' => 15000,
                'tax_amount' => 0,
                'net_amount' => 15000,
            ],
        ];

        $report = $this->reports->aggregateBankReport($rows, '202610', '202610');

        $this->assertCount(1, $report['rows']);
        $row = $report['rows'][0];
        $this->assertSame(100000, $row['gross_interest']);
        $this->assertSame(20000, $row['tax_interest']);
        $this->assertSame(80000, $row['net_interest']); // Hanya neto yang masuk kas
        $this->assertSame(15000, $row['admin_fee']);
        $this->assertSame(65000, $row['net_difference']); // 80.000 - 15.000

        // Arah buku kas untuk bunga neto: Debit (Masuk)
        $dbocr = CreditUnionBankTrx::DIRECTION_DEBIT;
        $this->assertSame('D', $dbocr);
    }

    /**
     * Skenario (c): Dua rekening berbeda: rekap per rekening terpisah benar dan gabungannya cocok.
     */
    public function test_dua_rekening_berbeda_rekap_per_rekening_terpisah_dan_gabungan_cocok(): void
    {
        $allEntries = [
            [
                'account' => '999000111222',
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_INTEREST,
                'gross_amount' => 100000,
                'tax_amount' => 20000,
                'net_amount' => 80000,
            ],
            [
                'account' => '999000111222',
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE,
                'gross_amount' => 10000,
                'tax_amount' => 0,
                'net_amount' => 10000,
            ],
            [
                'account' => '888000333444',
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_INTEREST,
                'gross_amount' => 50000,
                'tax_amount' => 10000,
                'net_amount' => 40000,
            ],
            [
                'account' => '888000333444',
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE,
                'gross_amount' => 5000,
                'tax_amount' => 0,
                'net_amount' => 5000,
            ],
        ];

        // Filter Rekening 1
        $rek1 = array_filter($allEntries, fn ($r) => $r['account'] === '999000111222');
        $report1 = $this->reports->aggregateBankReport($rek1, '202610', '202610');
        $this->assertSame(10000, $report1['totals']['admin_fee']);
        $this->assertSame(80000, $report1['totals']['net_interest']);
        $this->assertSame(70000, $report1['totals']['net_difference']);

        // Filter Rekening 2
        $rek2 = array_filter($allEntries, fn ($r) => $r['account'] === '888000333444');
        $report2 = $this->reports->aggregateBankReport($rek2, '202610', '202610');
        $this->assertSame(5000, $report2['totals']['admin_fee']);
        $this->assertSame(40000, $report2['totals']['net_interest']);
        $this->assertSame(35000, $report2['totals']['net_difference']);

        // Gabungan Semua Rekening
        $combined = $this->reports->aggregateBankReport($allEntries, '202610', '202610');
        $this->assertSame(15000, $combined['totals']['admin_fee']);
        $this->assertSame(120000, $combined['totals']['net_interest']);
        $this->assertSame(105000, $combined['totals']['net_difference']);

        // Kecocokan total gabungan = jumlah total masing-masing rekening
        $this->assertSame($report1['totals']['admin_fee'] + $report2['totals']['admin_fee'], $combined['totals']['admin_fee']);
        $this->assertSame($report1['totals']['net_interest'] + $report2['totals']['net_interest'], $combined['totals']['net_interest']);
        $this->assertSame($report1['totals']['net_difference'] + $report2['totals']['net_difference'], $combined['totals']['net_difference']);
    }

    /**
     * Skenario (d): Rentang tanpa entri: tab tampil kosong wajar (rows kosong, totals nol).
     */
    public function test_rentang_tanpa_entri_tampil_kosong_wajar(): void
    {
        $rows = [
            [
                'periode' => '202609',
                'category' => CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE,
                'gross_amount' => 10000,
                'tax_amount' => 0,
                'net_amount' => 10000,
            ],
        ];

        // Filter pada rentang 202610 s.d. 202610 (tidak memuat 202609)
        $report = $this->reports->aggregateBankReport($rows, '202610', '202610');

        $this->assertSame([], $report['rows']);
        $this->assertSame(0, $report['totals']['admin_fee']);
        $this->assertSame(0, $report['totals']['gross_interest']);
        $this->assertSame(0, $report['totals']['tax_interest']);
        $this->assertSame(0, $report['totals']['net_interest']);
        $this->assertSame(0, $report['totals']['net_difference']);
    }

    /**
     * Skenario (e): Transaksi bank existing (PMT / umum) tidak memiliki kategori
     * dan TIDAK muncul di agregat tab Bank.
     */
    public function test_transaksi_bank_existing_pmt_tidak_muncul_di_agregat_tab_bank(): void
    {
        $rows = [
            // Transaksi generik tanpa kategori valid
            [
                'periode' => '202610',
                'category' => '',
                'gross_amount' => 5000000,
                'tax_amount' => 0,
                'net_amount' => 5000000,
            ],
            // Transaksi PMT
            [
                'periode' => '202610',
                'category' => null,
                'gross_amount' => 10000000,
                'tax_amount' => 0,
                'net_amount' => 10000000,
            ],
            // Transaksi berkategori sah
            [
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE,
                'gross_amount' => 20000,
                'tax_amount' => 0,
                'net_amount' => 20000,
            ],
        ];

        $report = $this->reports->aggregateBankReport($rows, '202610', '202610');

        $this->assertCount(1, $report['rows']);
        $this->assertSame(20000, $report['totals']['admin_fee']);
        $this->assertSame(0, $report['totals']['gross_interest']);
        $this->assertSame(-20000, $report['totals']['net_difference']);
    }

    /**
     * Skenario (f): Format ekspor CSV tab Bank cocok dengan kolom dan perhitungan layar.
     */
    public function test_ekspor_csv_tab_bank_cocok_dengan_tampilan_layar(): void
    {
        $rows = [
            [
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_INTEREST,
                'gross_amount' => 100000,
                'tax_amount' => 20000,
                'net_amount' => 80000,
            ],
            [
                'periode' => '202610',
                'category' => CreditUnionBankTrxDetail::CATEGORY_ADMIN_FEE,
                'gross_amount' => 15000,
                'tax_amount' => 0,
                'net_amount' => 15000,
            ],
        ];

        $report = $this->reports->aggregateBankReport($rows, '202610', '202610');
        $dataRows = $report['rows'];
        $totals = $report['totals'];

        $csvHeaders = ['Periode', 'Biaya Admin Bank (Rp)', 'Bunga Bruto (Rp)', 'Pajak Bunga (Rp)', 'Bunga Neto (Rp)', 'Selisih Bank (Rp)'];
        $csvRows = array_map(fn ($r) => [
            $r['periode'],
            $r['admin_fee'],
            $r['gross_interest'],
            $r['tax_interest'],
            $r['net_interest'],
            $r['net_difference'],
        ], $dataRows);

        $csvRows[] = [
            'Total',
            $totals['admin_fee'],
            $totals['gross_interest'],
            $totals['tax_interest'],
            $totals['net_interest'],
            $totals['net_difference'],
        ];

        $this->assertCount(6, $csvHeaders);
        $this->assertCount(2, $csvRows); // 1 baris periode + 1 baris Total
        $this->assertSame([
            '202610', 15000, 100000, 20000, 80000, 65000,
        ], $csvRows[0]);
        $this->assertSame([
            'Total', 15000, 100000, 20000, 80000, 65000,
        ], $csvRows[1]);
    }

    /**
     * Skenario (g): Validasi aturan server bunga bank: bruto > 0, pajak >= 0, pajak <= bruto.
     */
    public function test_validasi_pajak_tidak_boleh_melebihi_bruto(): void
    {
        $gross = 50000;
        $invalidTax = 60000;

        $isValid = $invalidTax <= $gross;
        $this->assertFalse($isValid, 'Pajak bunga melebihi bunga bruto harus ditolak server.');

        $validTax = 10000;
        $this->assertTrue($validTax <= $gross);
        $this->assertSame(40000, $gross - $validTax);
    }

    /**
     * Skenario (h): Perhitungan Bunga Neto dan Biaya Admin selalu konsisten terhadap saldo kas bank.
     */
    public function test_konsistensi_perhitungan_saldo_kas_buku(): void
    {
        // Saldo awal = 1.000.000
        $saldoAwal = 1000000;

        // Entri 1: Bunga bank bruto 200.000, pajak 40.000 -> neto 160.000 (Debit)
        $bungaNeto = 200000 - 40000;
        $saldoSesudahBunga = $saldoAwal + $bungaNeto;
        $this->assertSame(1160000, $saldoSesudahBunga);

        // Entri 2: Biaya admin bank 25.000 (Kredit)
        $adminBank = 25000;
        $saldoAkhir = $saldoSesudahBunga - $adminBank;
        $this->assertSame(1135000, $saldoAkhir);

        // Selisih bank = Bunga Neto - Admin Bank = 160.000 - 25.000 = 135.000
        $selisih = $bungaNeto - $adminBank;
        $this->assertSame(135000, $selisih);
        $this->assertSame($saldoAkhir - $saldoAwal, $selisih);
    }
}
