<?php

namespace Tests\Unit\Services\Filing;

use App\Services\FilingSystem\FilingShareRules;
use PHPUnit\Framework\TestCase;

class FilingShareRulesTest extends TestCase
{
    private static function has(array $rules, int $cat, string $value): bool
    {
        foreach ($rules as $rule) {
            if ((int) $rule['share_cat'] === $cat && (string) $rule['othercode'] === $value) {
                return true;
            }
        }

        return false;
    }

    public function test_existing_group_share_survives_a_modal_save(): void
    {
        $submitted = [
            ['share_cat' => 1, 'othercode' => '9'],
            ['share_cat' => 4, 'othercode' => '0'],
        ];
        $existing = [
            ['share_cat' => 0, 'othercode' => '1'],
            ['share_cat' => 5, 'othercode' => '7'],
        ];

        $merged = FilingShareRules::mergePreservingGroupShares($submitted, $existing);

        $this->assertTrue(self::has($merged, 5, '7'), 'Baris grup harus tetap ada setelah simpan modal.');
        $this->assertTrue(self::has($merged, 1, '9'));
        $this->assertTrue(self::has($merged, 4, '0'));
        $this->assertFalse(self::has($merged, 0, '1'), 'Baris non-grup existing tidak boleh dihidupkan kembali.');
    }

    public function test_group_share_in_submission_is_ignored_and_restored_from_existing(): void
    {
        $submitted = [['share_cat' => 5, 'othercode' => '99']];
        $existing = [['share_cat' => 5, 'othercode' => '7']];

        $merged = FilingShareRules::mergePreservingGroupShares($submitted, $existing);

        $this->assertFalse(self::has($merged, 5, '99'), 'cat 5 dari masukan modal tidak boleh diterima.');
        $this->assertTrue(self::has($merged, 5, '7'));
        $this->assertCount(1, $merged);
    }

    public function test_multiple_group_rows_are_preserved_and_deduplicated(): void
    {
        $merged = FilingShareRules::mergePreservingGroupShares(
            [['share_cat' => 1, 'othercode' => '9']],
            [
                ['share_cat' => 5, 'othercode' => '7'],
                ['share_cat' => 5, 'othercode' => '7'],
                ['share_cat' => 5, 'othercode' => '8'],
            ]
        );

        $this->assertTrue(self::has($merged, 5, '7'));
        $this->assertTrue(self::has($merged, 5, '8'));
        $this->assertSame(3, count($merged));
    }

    public function test_merge_is_idempotent(): void
    {
        $submitted = [['share_cat' => 1, 'othercode' => '9']];
        $existing = [['share_cat' => 5, 'othercode' => '7']];

        $once = FilingShareRules::mergePreservingGroupShares($submitted, $existing);
        $twice = FilingShareRules::mergePreservingGroupShares($once, $existing);

        $this->assertSame($once, $twice);
    }

    public function test_reads_extra_columns_and_object_rows_without_caring_about_shape(): void
    {
        $submitted = [
            (object) ['share_cat' => '1', 'othercode' => '9', 'created_by' => 42, 'extra' => 'x'],
        ];
        $existing = [
            (object) ['share_cat' => '5', 'value' => '7', 'label' => 'Grup — PROJECT2026'],
        ];

        $merged = FilingShareRules::mergePreservingGroupShares($submitted, $existing);

        $this->assertTrue(self::has($merged, 1, '9'));
        $this->assertTrue(self::has($merged, 5, '7'));
    }
}
