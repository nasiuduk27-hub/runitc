<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONN = 'run';

    private const MEMBER_SECTION = 'credit_union';

    private const MEMBER_PARENT_TITLE = 'Credit Union';

    /**
     * Menu anggota yang harus selalu menampilkan data pribadi, termasuk untuk
     * admin yang juga anggota. Ditandai dengan query view=mine.
     *
     * @var list<string>
     */
    private const PERSONAL_URLS = [
        '/credit-union/savings',
        '/credit-union/loans',
        '/credit-union/applications',
        '/credit-union/skips',
    ];

    public function up(): void
    {
        $db = DB::connection(self::CONN);
        $parentId = $this->memberParentId($db);

        if ($parentId === null) {
            return;
        }

        foreach (self::PERSONAL_URLS as $url) {
            $db->table('sys_menus')
                ->where('mst_id', $parentId)
                ->where('url', $url)
                ->update(['url' => $url.'?view=mine']);
        }
    }

    public function down(): void
    {
        $db = DB::connection(self::CONN);
        $parentId = $this->memberParentId($db);

        if ($parentId === null) {
            return;
        }

        foreach (self::PERSONAL_URLS as $url) {
            $db->table('sys_menus')
                ->where('mst_id', $parentId)
                ->where('url', $url.'?view=mine')
                ->update(['url' => $url]);
        }
    }

    private function memberParentId($db): ?int
    {
        $parent = $db->table('sys_menus')->where('mst_id', 0)
            ->where('section_key', self::MEMBER_SECTION)
            ->where('title', self::MEMBER_PARENT_TITLE)
            ->where('url', '#')
            ->first();

        return $parent !== null ? (int) $parent->rec_id : null;
    }
};
