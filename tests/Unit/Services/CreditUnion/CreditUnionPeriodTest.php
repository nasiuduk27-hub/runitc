<?php

namespace Tests\Unit\Services\Cooperative;

use App\Services\Cooperative\CooperativePeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CooperativePeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-26 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_current_returns_yyyym_of_now(): void
    {
        $this->assertSame('202608', CooperativePeriod::current());
    }

    public function test_add_months_is_calendar_correct_across_year(): void
    {
        $this->assertSame('202701', CooperativePeriod::addMonths('202612', 1));
        $this->assertSame('202613', '202613', 'Sanity: string biasa tidak boleh dioperasikan sebagai angka.');
        $this->assertSame('202801', CooperativePeriod::addMonths('202612', 13));
        $this->assertSame('202608', CooperativePeriod::addMonths('202608', 0));
        $this->assertSame('202607', CooperativePeriod::addMonths('202608', -1));
    }

    public function test_label_formats_indonesian_month(): void
    {
        $this->assertSame('Agu 2026', CooperativePeriod::label('202608'));
        $this->assertSame('Jan 2027', CooperativePeriod::label('202701'));
        $this->assertSame('Des 2012', CooperativePeriod::label('201212'));
    }

    public function test_short_label_returns_month_only(): void
    {
        $this->assertSame('Agu', CooperativePeriod::shortLabel('202608'));
        $this->assertSame('Des', CooperativePeriod::shortLabel('202512'));
    }

    public function test_long_label_formats_full_indonesian_month(): void
    {
        $this->assertSame('Agustus 2026', CooperativePeriod::longLabel('202608'));
        $this->assertSame('Januari 2027', CooperativePeriod::longLabel('202701'));
        $this->assertSame('Desember 2012', CooperativePeriod::longLabel('201212'));
    }

    public static function invalidPeriodProvider(): array
    {
        return [
            'huruf' => ['abcdef'],
            'terlalu pendek' => ['20261'],
            'bulan 00' => ['202600'],
            'bulan 13' => ['202613'],
            'kosong' => [''],
        ];
    }

    #[DataProvider('invalidPeriodProvider')]
    public function test_invalid_period_passes_through_unchanged(string $periode): void
    {
        $this->assertFalse(CooperativePeriod::isValid($periode));
        $this->assertSame($periode, CooperativePeriod::label($periode));
        $this->assertSame($periode, CooperativePeriod::addMonths($periode, 1));
    }
}
