<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use App\Http\Controllers\CbtOps\FilingDriveController;
use App\Repositories\FilingSystem\FileSystemDrive;
use App\Services\FilingSystem\FilingLinkService;
use App\Support\FilingSystem\FilingFolders;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pengujian Fitur Link-ke-Shared (Langkah 0):
 * File dari link token tampil di folder Shared penerima yang sedang login.
 */
class ShareLinkAccessTest extends TestCase
{
    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.run' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'runitc.filing_use_new_tables' => true,
        ]);
        DB::purge('run');

        $this->createSchema();
    }

    private function nextId(): int
    {
        self::$seq++;

        return 990000 + self::$seq * 10;
    }

    private function token(): string
    {
        return str_pad('link-shared-token-'.(++self::$seq), 43, 'a');
    }

    private function createSchema(): void
    {
        Schema::connection('run')->create('file_system', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('trxno', 30)->default('');
            $table->date('trxdt')->nullable();
            $table->string('file_name', 255)->default('');
            $table->string('folder_loc', 50)->default('');
            $table->string('upload_flnm', 255)->default('');
            $table->string('file_type', 20)->default('PDF');
            $table->string('file_type_code', 20)->nullable();
            $table->string('client_nm', 100)->default('');
            $table->string('file_notes', 500)->default('');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('file_zip_size')->default(0);
            $table->unsignedInteger('userid')->default(0);
            $table->string('depcd', 20)->default('');
            $table->string('status', 20)->default('active');
            $table->string('security_level', 20)->default('normal');
            $table->string('access_mode', 20)->default('private');
            $table->timestamp('expired_at')->nullable();
            $table->string('expired_action', 20)->default('trash');
            $table->timestamp('expired_processed_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('create_dt')->nullable();
            $table->timestamp('lupdt')->nullable();
        });

        Schema::connection('run')->create('file_share_link', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->string('share_code_preview', 20)->nullable();
            $table->string('share_code_hash', 64)->nullable();
            $table->unsignedTinyInteger('link_mode')->default(1);
            $table->unsignedInteger('created_by')->default(0);
            $table->string('password_hash', 255)->nullable();
            $table->unsignedInteger('max_access')->default(0);
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('expired_at')->nullable();
            $table->boolean('allow_download')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::connection('run')->create('file_link_access', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('link_id')->default(0);
            $table->unsignedInteger('file_id')->default(0);
            $table->unsignedInteger('user_id')->default(0);
            $table->timestamp('first_opened_at')->useCurrent();
            $table->timestamp('last_opened_at')->useCurrent();

            $table->unique(['link_id', 'user_id'], 'uniq_link_user');
            $table->index(['user_id', 'file_id'], 'idx_user_file');
        });

        Schema::connection('run')->create('file_shareto', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->tinyInteger('share_cat')->default(1);
            $table->string('othercode', 15)->default('0');
            $table->unsignedInteger('created_by')->default(0);
            $table->timestamp('create_dt')->nullable();
        });

        Schema::connection('run')->create('sys_filegrp', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('group_name', 100)->default('');
            $table->string('description', 1000)->nullable();
            $table->unsignedInteger('created_by')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->dateTime('archived_at')->nullable();
        });

        Schema::connection('run')->create('sys_filegrp_member', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('group_id');
            $table->unsignedInteger('user_id')->default(0);
            $table->string('member_type', 20)->default('user');
            $table->string('member_value', 100)->default('');
            $table->string('role', 20)->default('contributor');
            $table->unsignedInteger('added_by')->nullable();
            $table->dateTime('added_at')->nullable();
        });

        Schema::connection('run')->create('sys_filing_audit', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filing_id')->default(0);
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 50)->default('');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::connection('run')->create('sysitc_users', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('account_nm', 100)->default('');
            $table->string('alias_nm', 50)->default('');
            $table->string('cmpcd', 20)->default('0');
        });

        Schema::connection('run')->create('sysitc_usracc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('user_rec_id');
            $table->string('access_code', 10);
            $table->string('access_account', 20);
        });

        Schema::connection('run')->create('sysitc_grpacc', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('grpaccess', 10);
            $table->string('grpacc', 20);
            $table->string('grpdesc', 100);
        });

        Schema::connection('mysql')->create('icu_member', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('itc_user_id')->default(0);
        });

        Schema::connection('run')->create('sys_msttable', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('tbl_grp', 10);
            $table->string('tbl_cd', 10);
            $table->string('tbl_nm', 100);
            $table->string('notes', 255)->nullable();
        });
    }

    private function insertUser(int $id, string $name): void
    {
        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => $id,
            'account_nm' => $name,
            'alias_nm' => $name,
            'cmpcd' => 'CMP1',
        ]);
    }

    private function insertFile(int $id, int $ownerId, string $name = 'dokumen.pdf'): void
    {
        DB::connection('run')->table('file_system')->insert([
            'rec_id' => $id,
            'file_name' => $name,
            'upload_flnm' => 'storage/'.$name,
            'userid' => $ownerId,
            'file_type' => 'PDF',
            'file_size' => 1024,
            'file_zip_size' => 1024,
            'status' => 'active',
            'security_level' => 'normal',
            'access_mode' => 'private',
            'create_dt' => '2026-10-16 10:00:00',
            'lupdt' => '2026-10-16 10:00:00',
        ]);
    }

    private function insertLink(int $linkId, int $fileId, int $ownerId, string $token, array $overrides = []): void
    {
        $linkService = new FilingLinkService;
        DB::connection('run')->table('file_share_link')->insert(array_merge([
            'rec_id' => $linkId,
            'filesys_id' => $fileId,
            'share_code_preview' => $linkService->previewToken($token),
            'share_code_hash' => $linkService->hashToken($token),
            'link_mode' => FilingLinkService::MODE_TOKEN,
            'created_by' => $ownerId,
            'max_access' => 0,
            'access_count' => 0,
            'allow_download' => true,
            'is_active' => true,
            'created_at' => '2026-10-16 10:00:00',
        ], $overrides));
    }

    private function actingAsLegacy(int $userId): void
    {
        Auth::guard('legacy')->setUser(new LegacyUser($userId, 'User '.$userId));
        session(['user_id' => $userId]);
    }

    /** 1. Token link opened by logged-in user records to file_link_access */
    public function test_token_link_opened_by_logged_in_user_records_to_file_link_access(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $token = $this->token();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner);
        $this->insertLink($linkId, $fileId, $owner, $token);

        $this->actingAsLegacy($viewer);

        $response = $this->get(route('filing-system.link.show', ['token' => $token]));
        $response->assertOk();
        $response->assertViewHas('state', 'ready');

        $this->assertDatabaseHas('file_link_access', [
            'link_id' => $linkId,
            'file_id' => $fileId,
            'user_id' => $viewer,
        ], 'run');
    }

    /** 2. Token link opened by guest does not record */
    public function test_token_link_opened_by_guest_does_not_record(): void
    {
        $owner = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $token = $this->token();

        $this->insertUser($owner, 'Owner');
        $this->insertFile($fileId, $owner);
        $this->insertLink($linkId, $fileId, $owner, $token);

        $response = $this->get(route('filing-system.link.show', ['token' => $token]));
        $response->assertOk();
        $response->assertViewHas('state', 'ready');

        $this->assertSame(0, DB::connection('run')->table('file_link_access')->count());
    }

    /** 3. Token link opened by owner does not record */
    public function test_token_link_opened_by_owner_does_not_record(): void
    {
        $owner = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $token = $this->token();

        $this->insertUser($owner, 'Owner');
        $this->insertFile($fileId, $owner);
        $this->insertLink($linkId, $fileId, $owner, $token);

        $this->actingAsLegacy($owner);

        $response = $this->get(route('filing-system.link.show', ['token' => $token]));
        $response->assertOk();

        $this->assertSame(0, DB::connection('run')->table('file_link_access')->count());
    }

    /** 4. Password protected link only records after password passed */
    public function test_password_protected_link_only_records_after_password_passed(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $token = $this->token();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner);
        $this->insertLink($linkId, $fileId, $owner, $token, [
            'password_hash' => password_hash('rahasia123', PASSWORD_BCRYPT),
        ]);

        $this->actingAsLegacy($viewer);

        // GET awal minta password -> belum tercatat
        $response = $this->get(route('filing-system.link.show', ['token' => $token]));
        $response->assertOk();
        $response->assertViewHas('state', 'password');
        $this->assertSame(0, DB::connection('run')->table('file_link_access')->count());

        // POST password salah -> belum tercatat
        $badPost = $this->post(route('filing-system.link.password', ['token' => $token]), ['password' => 'salah']);
        $badPost->assertOk();
        $this->assertSame(0, DB::connection('run')->table('file_link_access')->count());

        // POST password benar -> redirect ke show -> tercatat
        $goodPost = $this->post(route('filing-system.link.password', ['token' => $token]), ['password' => 'rahasia123']);
        $goodPost->assertRedirect(route('filing-system.link.show', ['token' => $token]));

        $follow = $this->get(route('filing-system.link.show', ['token' => $token]));
        $follow->assertOk();
        $follow->assertViewHas('state', 'ready');

        $this->assertDatabaseHas('file_link_access', [
            'link_id' => $linkId,
            'file_id' => $fileId,
            'user_id' => $viewer,
        ], 'run');
    }

    /** 5. Reopening link updates last_opened_at without duplicate row */
    public function test_reopening_link_updates_last_opened_at_without_duplicate_row(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $token = $this->token();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner);
        $this->insertLink($linkId, $fileId, $owner, $token);

        $this->actingAsLegacy($viewer);

        $this->get(route('filing-system.link.show', ['token' => $token]));
        $this->assertSame(1, DB::connection('run')->table('file_link_access')->count());

        $this->get(route('filing-system.link.show', ['token' => $token]));
        $this->assertSame(1, DB::connection('run')->table('file_link_access')->count());
    }

    /** 6. Mode 1 link does not record */
    public function test_mode_1_link_does_not_record(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $token = $this->token();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner);
        $this->insertLink($linkId, $fileId, $owner, $token, [
            'link_mode' => FilingLinkService::MODE_RIGHTS,
        ]);

        // Beri viewer hak di file_shareto supaya mode 1 ready
        DB::connection('run')->table('file_shareto')->insert([
            'filesys_id' => $fileId,
            'share_cat' => 1,
            'othercode' => (string) $viewer,
            'created_by' => $owner,
        ]);

        $this->actingAsLegacy($viewer);

        $response = $this->get(route('filing-system.link.show', ['token' => $token]));
        $response->assertOk();
        $response->assertViewHas('state', 'ready');

        $this->assertSame(0, DB::connection('run')->table('file_link_access')->count());
    }

    /** 7. Shared folder lists file from active link */
    public function test_shared_folder_lists_file_from_active_link(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $token = $this->token();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner, 'laporan-keuangan.pdf');
        $this->insertLink($linkId, $fileId, $owner, $token);

        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $linkId,
            'file_id' => $fileId,
            'user_id' => $viewer,
            'first_opened_at' => '2026-10-16 10:00:00',
            'last_opened_at' => '2026-10-16 10:00:00',
        ]);

        $this->actingAsLegacy($viewer);

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), []);
        $result = $controller->fetchListNew(['folder' => FilingFolders::SHARED]);

        $this->assertCount(1, $result['items']);
        $this->assertSame($fileId, (int) $result['items'][0]['rec_id']);
        $this->assertSame('link', $result['items'][0]['source_type']);
        $this->assertSame($linkId, $result['items'][0]['link_id']);
    }

    /** 8. Shared folder excludes revoked, expired, or exhausted link */
    public function test_shared_folder_excludes_revoked_expired_or_exhausted_link(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');

        // File 1: link dicabut
        $f1 = $this->nextId();
        $l1 = $this->nextId();
        $this->insertFile($f1, $owner, 'revoked.pdf');
        $this->insertLink($l1, $f1, $owner, $this->token(), [
            'is_active' => false,
            'revoked_at' => '2026-10-16 09:00:00',
        ]);
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $l1, 'file_id' => $f1, 'user_id' => $viewer,
        ]);

        // File 2: link kedaluwarsa
        $f2 = $this->nextId();
        $l2 = $this->nextId();
        $this->insertFile($f2, $owner, 'expired.pdf');
        $this->insertLink($l2, $f2, $owner, $this->token(), [
            'expired_at' => '2020-01-01 00:00:00',
        ]);
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $l2, 'file_id' => $f2, 'user_id' => $viewer,
        ]);

        // File 3: kuota habis
        $f3 = $this->nextId();
        $l3 = $this->nextId();
        $this->insertFile($f3, $owner, 'exhausted.pdf');
        $this->insertLink($l3, $f3, $owner, $this->token(), [
            'max_access' => 5,
            'access_count' => 5,
        ]);
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $l3, 'file_id' => $f3, 'user_id' => $viewer,
        ]);

        $this->actingAsLegacy($viewer);

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), []);
        $result = $controller->fetchListNew(['folder' => FilingFolders::SHARED]);

        $this->assertCount(0, $result['items']);
        $this->assertSame(0, $controller->countFolderFilesNew($viewer)[FilingFolders::SHARED]);
    }

    /** 9. Shared folder deduplicates with regular shareto */
    public function test_shared_folder_deduplicates_with_regular_shareto(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner, 'shared-and-linked.pdf');
        $this->insertLink($linkId, $fileId, $owner, $this->token());

        // Diberi shareto personal
        DB::connection('run')->table('file_shareto')->insert([
            'filesys_id' => $fileId,
            'share_cat' => 1,
            'othercode' => (string) $viewer,
            'created_by' => $owner,
        ]);

        // Sekaligus tercatat di file_link_access
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $linkId,
            'file_id' => $fileId,
            'user_id' => $viewer,
        ]);

        $this->actingAsLegacy($viewer);

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), []);
        $result = $controller->fetchListNew(['folder' => FilingFolders::SHARED]);

        $this->assertCount(1, $result['items']);
        $this->assertSame(1, $result['pagination']['total_items']);
        // Karena ada di shareto, source_type tetap 'drive'
        $this->assertSame('drive', $result['items'][0]['source_type']);
        $this->assertSame(1, $controller->countFolderFilesNew($viewer)[FilingFolders::SHARED]);
    }

    /** 10. Shared badge count matches item list count */
    public function test_shared_badge_count_matches_item_list_count(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');

        // File 1: via link
        $f1 = $this->nextId();
        $l1 = $this->nextId();
        $this->insertFile($f1, $owner, 'doc1.pdf');
        $this->insertLink($l1, $f1, $owner, $this->token());
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $l1, 'file_id' => $f1, 'user_id' => $viewer,
        ]);

        // File 2: via shareto
        $f2 = $this->nextId();
        $this->insertFile($f2, $owner, 'doc2.pdf');
        DB::connection('run')->table('file_shareto')->insert([
            'filesys_id' => $f2, 'share_cat' => 1, 'othercode' => (string) $viewer,
        ]);

        $this->actingAsLegacy($viewer);

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), []);
        $list = $controller->fetchListNew(['folder' => FilingFolders::SHARED]);
        $counts = $controller->countFolderFilesNew($viewer);

        $this->assertCount(2, $list['items']);
        $this->assertSame(2, $counts[FilingFolders::SHARED]);
    }

    /** 11. File inactive or deleted disappears from shared */
    public function test_file_inactive_or_deleted_disappears_from_shared(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner, 'trashed.pdf');
        $this->insertLink($linkId, $fileId, $owner, $this->token());

        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $linkId, 'file_id' => $fileId, 'user_id' => $viewer,
        ]);

        // Status diubah menjadi trashed
        DB::connection('run')->table('file_system')->where('rec_id', $fileId)->update(['status' => 'trashed']);

        $this->actingAsLegacy($viewer);

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), []);
        $list = $controller->fetchListNew(['folder' => FilingFolders::SHARED]);
        $counts = $controller->countFolderFilesNew($viewer);

        $this->assertCount(0, $list['items']);
        $this->assertSame(0, $counts[FilingFolders::SHARED]);
    }

    /** 12. Internal open route validates liveness and auth */
    public function test_internal_open_route_validates_liveness_and_auth(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $stranger = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertUser($stranger, 'Stranger');
        $this->insertFile($fileId, $owner, 'internal-doc.pdf');
        $this->insertLink($linkId, $fileId, $owner, $this->token());

        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $linkId, 'file_id' => $fileId, 'user_id' => $viewer,
        ]);

        // User asing (belum tercatat) -> 404
        $this->actingAsLegacy($stranger);
        $resStranger = $this->get(route('filing-system.link.open-internal', ['linkId' => $linkId]));
        $resStranger->assertNotFound();

        // User tercatat -> 200, state ready, kuota TIDAK bertambah
        $this->actingAsLegacy($viewer);
        $resViewer = $this->get(route('filing-system.link.open-internal', ['linkId' => $linkId]));
        $resViewer->assertOk();
        $resViewer->assertViewHas('state', 'ready');
        $resViewer->assertViewHas('downloadUrl', route('filing-system.link.download-internal', ['linkId' => $linkId]));

        // Bukti Opsi A: access_count tetap 0
        $this->assertSame(0, (int) DB::connection('run')->table('file_share_link')->where('rec_id', $linkId)->value('access_count'));
    }

    /** 13. Internal download route rejects unrecorded user and zero allow_download */
    public function test_internal_download_route_rejects_unrecorded_user_and_zero_allow_download(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $stranger = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertUser($stranger, 'Stranger');
        $this->insertFile($fileId, $owner);
        $this->insertLink($linkId, $fileId, $owner, $this->token(), [
            'allow_download' => false,
        ]);

        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $linkId, 'file_id' => $fileId, 'user_id' => $viewer,
        ]);

        // Stranger -> 404
        $this->actingAsLegacy($stranger);
        $resStranger = $this->get(route('filing-system.link.download-internal', ['linkId' => $linkId]));
        $resStranger->assertNotFound();

        // Viewer tapi allow_download = 0 -> 403
        $this->actingAsLegacy($viewer);
        $resViewer = $this->get(route('filing-system.link.download-internal', ['linkId' => $linkId]));
        $resViewer->assertStatus(403);
    }

    /** 14. Link-sourced row payload and UI restrictions */
    public function test_link_sourced_row_payload_and_ui_restrictions(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner, 'contract.pdf');

        // Multi-link test: dua link hidup untuk file yang sama
        // Link 1: allow_download = 0
        $l1 = $this->nextId();
        $this->insertLink($l1, $fileId, $owner, $this->token(), ['allow_download' => false]);
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $l1, 'file_id' => $fileId, 'user_id' => $viewer,
        ]);

        // Link 2: allow_download = 1
        $l2 = $this->nextId();
        $this->insertLink($l2, $fileId, $owner, $this->token(), ['allow_download' => true]);
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $l2, 'file_id' => $fileId, 'user_id' => $viewer,
        ]);

        $this->actingAsLegacy($viewer);

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), []);
        $result = $controller->fetchListNew(['folder' => FilingFolders::SHARED]);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];

        // Payload checks
        $this->assertSame('link', $item['source_type']);
        $this->assertTrue($item['permissions']['can_view']);
        $this->assertFalse($item['permissions']['can_manage']);
        $this->assertFalse($item['permissions']['can_share']);
        // Agregasi MAX: can_download bernilai true karena ada Link 2
        $this->assertTrue($item['permissions']['can_download']);
        $this->assertSame(1, $item['user_can_download']);

        // UI rendering checks (Daftar & Petak)
        $view = $this->view('filing-system._list', [
            'items' => [$item],
            'pagination' => ['total_items' => 1],
            'folder' => 'shared',
            'viewMode' => 'list',
        ]);
        $htmlList = (string) $view;

        // Checkbox harus disabled
        $this->assertStringContainsString('disabled', $htmlList);
        $this->assertStringContainsString('File dari tautan tidak mendukung aksi massal', $htmlList);
        // Link internal harus hadir
        $this->assertStringContainsString(route('filing-system.link.open-internal', ['linkId' => $item['link_id']]), $htmlList);
        // Tidak boleh ada openInfoDrawer
        $this->assertStringNotContainsString('openInfoDrawer('.$fileId.')', $htmlList);
    }

    /** 15. File shared via group/company and also opened via link retains drive source in origin folder */
    public function test_file_shared_via_group_or_company_and_opened_via_link_retains_drive_source_in_origin_folder(): void
    {
        $owner = $this->nextId();
        $viewer = $this->nextId();
        $fileId = $this->nextId();
        $linkId = $this->nextId();
        $groupId = $this->nextId();

        $this->insertUser($owner, 'Owner');
        $this->insertUser($viewer, 'Viewer');
        $this->insertFile($fileId, $owner, 'group-doc.pdf');
        $this->insertLink($linkId, $fileId, $owner, $this->token());

        // File dibagikan ke grup (share_cat = 5)
        DB::connection('run')->table('file_shareto')->insert([
            'filesys_id' => $fileId,
            'share_cat' => 5,
            'othercode' => (string) $groupId,
            'created_by' => $owner,
        ]);

        // Grup aktif dan viewer terdaftar sebagai anggota
        DB::connection('run')->table('sys_filegrp')->insert([
            'rec_id' => $groupId,
            'group_name' => 'Grup Proyek',
            'created_by' => $owner,
            'status' => 'active',
        ]);
        DB::connection('run')->table('sys_filegrp_member')->insert([
            'group_id' => $groupId,
            'user_id' => $viewer,
            'member_type' => 'user',
            'member_value' => (string) $viewer,
            'role' => 'contributor',
        ]);

        // Viewer juga pernah membuka file ini lewat link token
        DB::connection('run')->table('file_link_access')->insert([
            'link_id' => $linkId,
            'file_id' => $fileId,
            'user_id' => $viewer,
        ]);

        $this->actingAsLegacy($viewer);

        $controller = new FilingDriveController(DB::connection('run')->getPdo(), []);

        // Di folder asal (group), baris tetap 'drive' dengan izin penuh grup
        $groupList = $controller->fetchListNew(['folder' => 'group', 'group_id' => $groupId]);
        $this->assertCount(1, $groupList['items']);
        $item = $groupList['items'][0];
        $this->assertSame('drive', $item['source_type']);
        $this->assertNull($item['link_id']);
        $this->assertTrue($item['permissions']['can_view']);
        $this->assertTrue($item['permissions']['can_download']);

        // UI rendering di folder asal: bukan baris link, checkbox aktif, info drawer ada
        $view = $this->view('filing-system._list', [
            'items' => [$item],
            'pagination' => ['total_items' => 1],
            'folder' => 'group',
            'groupId' => $groupId,
            'viewMode' => 'list',
        ]);
        $htmlList = (string) $view;
        $this->assertStringNotContainsString('File dari tautan tidak mendukung aksi massal', $htmlList);
        $this->assertStringContainsString('openInfoDrawer('.$fileId.')', $htmlList);
    }
}
