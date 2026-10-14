<?php

namespace Tests\Unit\Http\Controllers\CbtOps;

use App\Http\Controllers\CbtOps\FilingDriveController;
use PDO;
use ReflectionMethod;
use Tests\TestCase;

class FilingDriveFolderWhereTest extends TestCase
{
    /**
     * Menjalankan buildFolderWhereNew tanpa database (hanya membentuk SQL + params).
     *
     * @return array{0: string, 1: array}
     */
    private function folderWhere(string $folder, int $userId = 7): array
    {
        $controller = new FilingDriveController(new PDO('sqlite::memory:'), []);
        $method = new ReflectionMethod($controller, 'buildFolderWhereNew');

        return $method->invoke($controller, $folder, $userId, [], 0);
    }

    public function test_my_drive_is_active_only(): void
    {
        [$where, $params] = $this->folderWhere('my_drive');

        $this->assertStringContainsString('f.userid = ?', $where);
        $this->assertStringContainsString("f.status = 'active'", $where);
        $this->assertStringNotContainsString('archived', $where);
        $this->assertSame([7], $params);
    }

    public function test_archive_folder_is_owner_scoped_and_archived_only(): void
    {
        [$where, $params] = $this->folderWhere('archive');

        $this->assertStringContainsString('f.userid = ?', $where);
        $this->assertStringNotContainsString("'active'", $where);
        $this->assertSame([7, 'archived'], $params);
    }
}
