<?php

namespace Tests\Unit\Models\CreditUnion;

use App\Models\CreditUnion\CreditUnionLoan;
use App\Models\CreditUnion\CreditUnionMember;
use PHPUnit\Framework\TestCase;

class MemberStatusLabelTest extends TestCase
{
    public function test_status_label_follows_master_table_codes(): void
    {
        $expected = [
            0 => 'Draft',
            1 => 'CU Account',
            2 => 'Regular Member',
            3 => 'Regular Non Payroll',
            4 => 'Irregular Member',
            5 => 'Outstanding Member',
            6 => 'Non-Active',
        ];

        foreach ($expected as $code => $label) {
            $member = new CreditUnionMember;
            $member->st_aktif = $code;

            $this->assertSame($label, $member->statusLabel(), "Status kode {$code} harus berlabel {$label}.");
        }
    }

    public function test_unknown_status_code_falls_back(): void
    {
        $member = new CreditUnionMember;
        $member->st_aktif = 99;

        $this->assertSame('Tidak Dikenal (99)', $member->statusLabel());
    }

    public function test_badge_class_groups_by_category(): void
    {
        $memberNonActive = new CreditUnionMember;
        $memberNonActive->st_aktif = 6;

        $memberRegular = new CreditUnionMember;
        $memberRegular->st_aktif = 2;

        $memberOther = new CreditUnionMember;
        $memberOther->st_aktif = 5;

        $this->assertStringContainsString('red', $memberNonActive->statusBadgeClass());
        $this->assertStringContainsString('green', $memberRegular->statusBadgeClass());
        $this->assertStringContainsString('amber', $memberOther->statusBadgeClass());
    }

    public function test_loan_settled_is_indicative_from_paid_and_totalloan(): void
    {
        $settled = new CreditUnionLoan;
        $settled->totalloan = 4_567_500;
        $settled->paid = 4_567_500;

        $running = new CreditUnionLoan;
        $running->totalloan = 4_567_500;
        $running->paid = 1_500_000;

        // Pinjaman tanpa tagihan tidak boleh dianggap lunas.
        $empty = new CreditUnionLoan;
        $empty->totalloan = 0;
        $empty->paid = 0;

        $overpaid = new CreditUnionLoan;
        $overpaid->totalloan = 100;
        $overpaid->paid = 150;

        $this->assertTrue($settled->isSettledIndicative());
        $this->assertFalse($running->isSettledIndicative());
        $this->assertFalse($empty->isSettledIndicative(), 'Total tagihan 0 tidak dianggap lunas.');
        $this->assertTrue($overpaid->isSettledIndicative());

        $this->assertSame('Lunas (indikatif)', $settled->statusLabel());
        $this->assertSame('Berjalan', $running->statusLabel());
    }
}
