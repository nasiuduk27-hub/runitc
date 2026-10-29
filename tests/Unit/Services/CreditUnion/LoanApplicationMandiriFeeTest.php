<?php

namespace Tests\Unit\Services\CreditUnion;

use App\Services\CreditUnion\CreditUnionBankService;
use App\Services\CreditUnion\CreditUnionSettingsService;
use App\Services\CreditUnion\LoanSimulationService;
use PHPUnit\Framework\TestCase;

/**
 * Pengujian aturan biaya admin nol untuk rekening pencairan Bank Mandiri (CU-FEE-MANDIRI-1).
 *
 * Catatan Gerbang: Suite otomatis tidak dijalankan via terminal karena koneksi DB 'run'
 * pada TestCase menunjuk ke database non-khusus (Langkah 0 §6). Test ini ditulis lengkap
 * dan terisolasi secara unit (tanpa koneksi DB aktif) untuk verifikasi skenario (a)-(h).
 */
class LoanApplicationMandiriFeeTest extends TestCase
{
    private LoanSimulationService $simulation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulation = new LoanSimulationService();
    }

    /**
     * Pengenalan Bank Mandiri: kecocokan persis pada kode master 'MAN' dan 'MANDIRI',
     * serta penolakan kode 'SMA' (Bank Syariah Mandiri) dan nama yang mengandung SYARIAH / TASPEN.
     */
    public function test_is_mandiri_exact_recognition_and_exclusions(): void
    {
        // Kode master Mandiri
        $this->assertTrue(CreditUnionBankService::isMandiri('MAN'));
        $this->assertTrue(CreditUnionBankService::isMandiri('man'));
        $this->assertTrue(CreditUnionBankService::isMandiri('MANDIRI'));

        // Bukan Bank Mandiri (kode master lain)
        $this->assertFalse(CreditUnionBankService::isMandiri('BCA'));
        $this->assertFalse(CreditUnionBankService::isMandiri('BRI'));
        $this->assertFalse(CreditUnionBankService::isMandiri('SMA')); // Bank Syariah Mandiri

        // Jalur teks (ketika kode kosong)
        $this->assertTrue(CreditUnionBankService::isMandiri(null, 'BANK MANDIRI'));
        $this->assertTrue(CreditUnionBankService::isMandiri('', 'Mandiri'));
        $this->assertTrue(CreditUnionBankService::isMandiri('', 'Mandiri - Bank Mandiri'));
        $this->assertTrue(CreditUnionBankService::isMandiri('', 'PT Bank Mandiri (Persero) Tbk'));

        // Pengecualian mutlak pada jalur teks: bank yang memuat kata Mandiri tapi bukan Bank Mandiri
        $this->assertFalse(CreditUnionBankService::isMandiri(null, 'BANK SYARIAH MANDIRI'));
        $this->assertFalse(CreditUnionBankService::isMandiri('', 'Mandiri Taspen'));
        $this->assertFalse(CreditUnionBankService::isMandiri('', 'Bank Mandiri Taspen'));
    }

    /**
     * Skenario (a): Mandiri + include -> dana diterima = pokok penuh, biaya admin = Rp0.
     */
    public function test_scenario_a_mandiri_include_full_disbursement_and_zero_fee(): void
    {
        $adminFee = CreditUnionBankService::resolveAdminFee('MAN');
        $this->assertSame(0, $adminFee);

        $result = $this->simulation->simulate(
            principalAmount: 200_000,
            tenorMonths: 10,
            annualRatePercent: 6.0,
            method: LoanSimulationService::METHOD_FLAT,
            adminFee: $adminFee,
            adminFeeType: 'include'
        );

        $this->assertSame(0, $result['summary']['admin_fee']);
        $this->assertSame(200_000, $result['summary']['requested_principal']);
        // Dana diterima penuh tanpa potongan biaya admin
        $this->assertSame(200_000, $result['summary']['net_disbursement']);
    }

    /**
     * Skenario (b): Mandiri + exclude -> cicilan pertama tanpa komponen biaya admin.
     */
    public function test_scenario_b_mandiri_exclude_first_installment_has_no_fee(): void
    {
        $adminFee = CreditUnionBankService::resolveAdminFee('MAN');
        $this->assertSame(0, $adminFee);

        $result = $this->simulation->simulate(
            principalAmount: 200_000,
            tenorMonths: 10,
            annualRatePercent: 6.0,
            method: LoanSimulationService::METHOD_FLAT,
            adminFee: $adminFee,
            adminFeeType: 'exclude'
        );

        $this->assertSame(0, $result['summary']['admin_fee']);
        $this->assertSame(200_000, $result['summary']['net_disbursement']);

        // Cicilan pertama tidak dibebankan komponen admin fee tambahan
        // Flat 200k / 10 = 20k pokok + 1k bunga = 21k (tanpa +adminFee)
        $this->assertSame(21_000, $result['schedule'][0]['total']);
        $this->assertSame(21_000, $result['summary']['first_installment']);
    }

    /**
     * Skenario (c): Bank lain + include -> biaya admin dipotong dari pencairan (perilaku sekarang).
     */
    public function test_scenario_c_other_bank_include_deducted_from_disbursement(): void
    {
        // Simulasi non-Mandiri dengan biaya admin 6.500
        $fee = 6_500;
        $result = $this->simulation->simulate(
            principalAmount: 200_000,
            tenorMonths: 10,
            annualRatePercent: 6.0,
            method: LoanSimulationService::METHOD_FLAT,
            adminFee: $fee,
            adminFeeType: 'include'
        );

        $this->assertSame(6_500, $result['summary']['admin_fee']);
        $this->assertSame(200_000, $result['summary']['requested_principal']);
        // Dana diterima dipotong 6.500 -> 193.500
        $this->assertSame(193_500, $result['summary']['net_disbursement']);
    }

    /**
     * Skenario (d): Bank lain + exclude -> biaya admin ditambahkan ke cicilan pertama (perilaku sekarang).
     */
    public function test_scenario_d_other_bank_exclude_added_to_first_installment(): void
    {
        $fee = 6_500;
        $result = $this->simulation->simulate(
            principalAmount: 200_000,
            tenorMonths: 10,
            annualRatePercent: 6.0,
            method: LoanSimulationService::METHOD_FLAT,
            adminFee: $fee,
            adminFeeType: 'exclude'
        );

        $this->assertSame(6_500, $result['summary']['admin_fee']);
        // Cicilan normal 21.000 + biaya admin 6.500 = 27.500 pada cicilan pertama
        $this->assertSame(27_500, $result['schedule'][0]['total']);
        $this->assertSame(27_500, $result['summary']['first_installment']);
        // Cicilan kedua kembali ke nominal reguler 21.000
        $this->assertSame(21_000, $result['schedule'][1]['total']);
    }

    /**
     * Skenario (e) & (g): Bank selain Mandiri yang memuat nama Mandiri (kode 'SMA' - Bank Syariah Mandiri).
     * Biaya admin TETAP dikenakan (tidak dinolkan).
     */
    public function test_scenario_e_and_g_bank_syariah_mandiri_retains_fee(): void
    {
        $this->assertFalse(CreditUnionBankService::isMandiri('SMA'));
        $this->assertFalse(CreditUnionBankService::isMandiri(null, 'BANK SYARIAH MANDIRI'));

        // resolveAdminFee untuk SMA mengembalikan default setting, bukan 0
        $fee = CreditUnionBankService::resolveAdminFee('SMA');
        $this->assertSame(CreditUnionSettingsService::defaultAdminFee(), $fee);
    }

    /**
     * Skenario (f): Format tampilan dan baris email untuk bank non-Mandiri tidak berubah.
     */
    public function test_scenario_f_non_mandiri_display_and_email_format(): void
    {
        // Display form / detail
        $display = CreditUnionBankService::formatAdminFeeDisplay(6_500, 'BCA');
        $this->assertSame('Rp 6.500', $display);

        // Baris email pengajuan & pencairan
        $emailLineInclude = CreditUnionBankService::formatAdminFeeEmailLine(6_500, 'include', 'BCA');
        $this->assertSame('Rp 6.500 (include)', $emailLineInclude);

        $emailLineExclude = CreditUnionBankService::formatAdminFeeEmailLine(6_500, 'exclude', 'BCA');
        $this->assertSame('Rp 6.500 (exclude)', $emailLineExclude);
    }

    /**
     * Skenario (h): Bank lain dengan biaya tersimpan 0 (mis. setting diubah menjadi 0).
     * Tampil "Rp 0" polos tanpa embel-embel Bank Mandiri.
     */
    public function test_scenario_h_other_bank_with_zero_fee_shows_plain_rp_zero(): void
    {
        // Bank BCA dengan biaya 0
        $display = CreditUnionBankService::formatAdminFeeDisplay(0, 'BCA');
        $this->assertSame('Rp 0', $display);

        $emailLine = CreditUnionBankService::formatAdminFeeEmailLine(0, 'include', 'BCA');
        $this->assertSame('Rp 0 (include)', $emailLine);

        // Sebaliknya, jika Bank Mandiri dengan biaya 0, muncul label khusus
        $mandiriDisplay = CreditUnionBankService::formatAdminFeeDisplay(0, 'MAN');
        $this->assertSame('Rp 0 — Bank Mandiri, tanpa biaya admin', $mandiriDisplay);

        $mandiriEmailLine = CreditUnionBankService::formatAdminFeeEmailLine(0, 'include', 'MAN');
        $this->assertSame('Rp 0 — Bank Mandiri, tanpa biaya admin', $mandiriEmailLine);
    }
}
