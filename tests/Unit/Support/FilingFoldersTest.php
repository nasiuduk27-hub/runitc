<?php

namespace Tests\Unit\Support;

use App\Support\FilingSystem\FilingFolders;
use Tests\TestCase;

class FilingFoldersTest extends TestCase
{
    public function test_definition_order_matches_sidebar_and_count_order(): void
    {
        $this->assertSame(
            ['my_drive', 'archive', 'shared', 'department', 'company', 'trash'],
            FilingFolders::keys()
        );
    }

    public function test_archive_is_the_only_gated_folder(): void
    {
        $this->assertSame(
            ['my_drive', 'shared', 'department', 'company', 'trash'],
            FilingFolders::allowed(false)
        );
        $this->assertSame(
            ['my_drive', 'archive', 'shared', 'department', 'company', 'trash'],
            FilingFolders::allowed(true)
        );
    }

    public function test_sidebar_defs_keep_labels_icons_and_title_fallback(): void
    {
        $defs = FilingFolders::driveDefs();

        $this->assertSame(['my_drive', 'archive', 'shared', 'department', 'company'], array_keys($defs));
        $this->assertSame('Arsip', $defs['archive']['label']);
        $this->assertSame('Share With Me', $defs['shared']['title']);
        $this->assertSame('fa-box-archive', $defs['archive']['icon']);
        $this->assertTrue($defs['archive']['gated']);
        $this->assertSame('Sampah', FilingFolders::trashDef()['label']);
        $this->assertSame('My Drive', FilingFolders::title('group'));
    }
}
