<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi Fase 3B: meja keputusan minta akses.
 *
 * - Setujui file = pemilik saja; hak diberi aditif murni (tambah baris
 *   file_shareto share_cat=1; baris grup share_cat=5 tidak tersentuh).
 * - Setujui grup = Pengelola; peminta masuk viewer (TIDAK menurunkan peran
 *   anggota eksisting), idempoten, target nonaktif gagal dengan pesan jelas.
 * - Tolak & setujui mengirim notifikasi balasan lewat sys_notifications.
 * - Keputusan teraudit (sys_filing_audit untuk file, sys_audit_log untuk grup).
 *
 * Koneksi `run` (dan `mysql` yang di-pin CreditUnionMember) dialihkan ke
 * sqlite in-memory supaya test tidak pernah menyentuh database produksi.
 * ID user unik per test terhadap cache statis permission service.
 */
class AccessRequestDecideTest extends TestCase
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
            $table->string('client_nm', 100)->default('');
            $table->string('file_notes', 500)->default('');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('file_zip_size')->default(0);
            $table->unsignedInteger('userid')->default(0);
            $table->unsignedInteger('userid_upd')->default(0);
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

        Schema::connection('run')->create('file_shareto', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filesys_id')->default(0);
            $table->tinyInteger('share_cat')->default(1);
            $table->string('othercode', 15)->default('0');
            $table->unsignedInteger('created_by')->default(0);
            $table->timestamp('create_dt')->nullable();
        });

        Schema::connection('run')->create('file_access_request', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->string('target_type', 10)->default('file');
            $table->unsignedInteger('target_id');
            $table->unsignedInteger('requester_id');
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('created_at')->nullable();
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
            $table->unique(['group_id', 'member_type', 'member_value'], 'uq_filegrp_member');
        });

        Schema::connection('run')->create('sys_filing_audit', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('filing_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 50)->default('');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::connection('run')->create('sys_notifications', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('recipient_user_id');
            $table->unsignedInteger('sender_user_id')->nullable();
            $table->string('type', 50)->default('');
            $table->string('title', 150)->default('');
            $table->text('message')->nullable();
            $table->string('target_url', 255)->nullable();
            $table->string('ref_table', 80)->nullable();
            $table->unsignedInteger('ref_id')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('read_at')->nullable();
        });

        Schema::connection('run')->create('sys_audit_log', function (Blueprint $table): void {
            $table->increments('rec_id');
            $table->unsignedInteger('actor_user_id');
            $table->string('action', 100)->default('');
            $table->string('target_type', 80)->nullable();
            $table->unsignedInteger('target_id')->nullable();
            $table->text('metadata_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('created_at')->nullable();
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
    }

    private function seedUser(int $id, string $name): void
    {
        DB::connection('run')->table('sysitc_users')->insert([
            'rec_id' => $id,
            'account_nm' => $name,
            'alias_nm' => $name,
            'cmpcd' => '0',
        ]);
    }

    private function seedFile(int $ownerId, string $name = 'dokumen-keputusan.pdf'): int
    {
        return (int) DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-TEST-0002',
            'file_name' => $name,
            'folder_loc' => '',
            'upload_flnm' => $name,
            'file_type' => 'PDF',
            'file_notes' => 'uji fase 3b',
            'file_size' => 1024,
            'file_zip_size' => 512,
            'userid' => $ownerId,
            'status' => 'active',
            'security_level' => 'normal',
            'access_mode' => 'private',
            'expired_action' => 'trash',
        ], 'rec_id');
    }

    private function seedGroup(int $ownerId, string $name = 'Grup Keputusan'): int
    {
        return (int) DB::connection('run')->table('sys_filegrp')->insertGetId([
            'group_name' => $name,
            'description' => 'grup uji fase 3b',
            'created_by' => $ownerId,
            'status' => 'active',
            'created_at' => now(),
        ], 'rec_id');
    }

    private function seedMember(int $groupId, int $userId, string $role): void
    {
        DB::connection('run')->table('sys_filegrp_member')->insert([
            'group_id' => $groupId,
            'user_id' => $userId,
            'member_type' => 'user',
            'member_value' => (string) $userId,
            'role' => $role,
            'added_at' => now(),
        ]);
    }

    private function seedRequest(string $type, int $targetId, int $requesterId, ?string $message = null): int
    {
        return (int) DB::connection('run')->table('file_access_request')->insertGetId([
            'target_type' => $type,
            'target_id' => $targetId,
            'requester_id' => $requesterId,
            'message' => $message,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ], 'rec_id');
    }

    private function login(int $userId, string $name): void
    {
        $user = new LegacyUser($userId, $name, 'user'.$userId);

        Auth::guard('legacy')->setUser($user);
        Auth::setUser($user);

        $this->withSession([
            'user_id' => $userId,
            'user_rec_id' => $userId,
            'account_id' => 'user'.$userId,
            'user_name' => $name,
            'account_nm' => $name,
            'auth_db' => 'run',
        ]);
    }

    private function notificationFor(int $recipientId, string $type): ?object
    {
        return DB::connection('run')->table('sys_notifications')
            ->where('recipient_user_id', $recipientId)
            ->where('type', $type)
            ->orderByDesc('rec_id')
            ->first();
    }

    public function test_notifikasi_masuk_ke_pemilik_saat_permintaan_file_dibuat(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner, 'berkas-notifikasi.pdf');
        $token = str_pad('fase3b-notif', 43, 'x');
        DB::connection('run')->table('file_share_link')->insert([
            'filesys_id' => $fileId,
            'share_code_preview' => 'LNK-****-notif',
            'share_code_hash' => hash('sha256', $token),
            'link_mode' => 1,
            'created_by' => $owner,
            'is_active' => 1,
            'allow_download' => 1,
        ]);
        $this->login($requester, 'Peminta Uji');

        $this->post('/filing-system/s/'.$token.'/minta-akses', ['message' => 'Saya butuh berkas ini'])
            ->assertSessionHas('success_msg');

        $request = DB::connection('run')->table('file_access_request')->first();
        $this->assertNotNull($request);

        $notification = $this->notificationFor($owner, 'filing_access_request');
        $this->assertNotNull($notification);
        $this->assertSame($requester, (int) $notification->sender_user_id);
        $this->assertSame('file_access_request', $notification->ref_table);
        $this->assertSame((int) $request->rec_id, (int) $notification->ref_id);
        $this->assertSame(route('filing-system.requests.index'), $notification->target_url);
        $this->assertStringContainsString('Peminta Uji', (string) $notification->message);
        $this->assertStringContainsString('berkas-notifikasi.pdf', (string) $notification->message);
    }

    public function test_pemilik_melihat_dan_menyetujui_permintaan_file(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner, 'berkas-disetujui.pdf');
        $requestId = $this->seedRequest('file', $fileId, $requester, 'Mohon akses');

        $this->login($owner, 'Pemilik Tujuan');

        $this->get('/filing-system/permintaan')
            ->assertOk()
            ->assertSee('berkas-disetujui.pdf')
            ->assertSee('Peminta Uji');

        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')
            ->assertRedirect(route('filing-system.requests.index'))
            ->assertSessionHas('success_msg');

        $request = DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->first();
        $this->assertSame('approved', $request->status);
        $this->assertSame($owner, (int) $request->decided_by);
        $this->assertNotNull($request->decided_at);

        $share = DB::connection('run')->table('file_shareto')
            ->where('filesys_id', $fileId)->where('share_cat', 1)->where('othercode', (string) $requester)->first();
        $this->assertNotNull($share);

        $audit = DB::connection('run')->table('sys_filing_audit')
            ->where('filing_id', $fileId)->where('action', 'access_request_approve')->first();
        $this->assertNotNull($audit);
        $this->assertSame($owner, (int) $audit->user_id);

        $notification = $this->notificationFor($requester, 'filing_access_decided');
        $this->assertNotNull($notification);
        $this->assertStringContainsString('disetujui', strtolower((string) $notification->message));
        $this->assertSame(route('filing-system.index', ['folder' => 'shared']), $notification->target_url);
    }

    public function test_setujui_file_aditif_murni_mempertahankan_baris_lain(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $group = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        DB::connection('run')->table('file_shareto')->insert([
            ['filesys_id' => $fileId, 'share_cat' => 5, 'othercode' => (string) $group, 'created_by' => $owner],
            ['filesys_id' => $fileId, 'share_cat' => 2, 'othercode' => 'ITD', 'created_by' => $owner],
        ]);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        $this->login($owner, 'Pemilik Tujuan');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('success_msg');

        $rows = DB::connection('run')->table('file_shareto')->where('filesys_id', $fileId)
            ->orderBy('share_cat')->get();
        $this->assertCount(3, $rows);
        $cats = $rows->pluck('share_cat')->map(fn ($c) => (int) $c)->all();
        sort($cats);
        $this->assertSame([1, 2, 5], $cats);
    }

    public function test_setujui_file_private_mengubah_access_mode_ke_custom(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        $this->assertSame('private', DB::connection('run')->table('file_system')->where('rec_id', $fileId)->value('access_mode'));

        $this->login($owner, 'Pemilik Tujuan');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('success_msg');

        $this->assertSame('custom', DB::connection('run')->table('file_system')->where('rec_id', $fileId)->value('access_mode'));
    }

    public function test_aturan_company_scoped_tetap_custom_bukan_public_internal(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner, 'berkas-company.pdf');
        DB::connection('run')->table('file_shareto')->insert([
            'filesys_id' => $fileId,
            'share_cat' => 3,
            'othercode' => 'CMP1',
            'created_by' => $owner,
        ]);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        $this->login($owner, 'Pemilik Tujuan');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('success_msg');

        // Cat 3 (Company) adalah aturan scoped; hanya cat 4 (Semua Karyawan)
        // yang bermakna public_internal (FilingPermissionService:454).
        $this->assertSame('custom', DB::connection('run')->table('file_system')->where('rec_id', $fileId)->value('access_mode'));
        $this->assertSame(1, DB::connection('run')->table('file_shareto')->where('filesys_id', $fileId)->where('share_cat', 3)->count());
        $this->assertSame(1, DB::connection('run')->table('file_shareto')->where('filesys_id', $fileId)->where('share_cat', 1)->count());
    }

    public function test_badge_hitungan_permintaan_yang_berhak_diputuskan(): void
    {
        $ownerA = $this->nextId();
        $ownerB = $this->nextId();
        $requester1 = $this->nextId();
        $requester2 = $this->nextId();
        $this->seedUser($ownerA, 'Pemilik A');
        $this->seedUser($ownerB, 'Pemilik B');
        $this->seedUser($requester1, 'Peminta Satu');
        $this->seedUser($requester2, 'Peminta Dua');

        $fileA = $this->seedFile($ownerA, 'berkas-badge-a.pdf');
        $fileB = $this->seedFile($ownerB, 'berkas-badge-b.pdf');
        $groupA = $this->seedGroup($ownerA, 'Grup Badge');

        $this->seedRequest('file', $fileA, $requester1);
        $this->seedRequest('file', $fileA, $requester2);
        $this->seedRequest('group', $groupA, $requester1);
        $this->seedRequest('file', $fileB, $requester1);

        $repo = new \App\Repositories\FilingSystem\FilingAccessRequest(DB::connection('run')->getPdo());
        $this->assertSame(3, $repo->pendingCountForDecider($ownerA, [$groupA]));
        $this->assertSame(1, $repo->pendingCountForDecider($ownerB, []));

        $this->login($ownerA, 'Pemilik A');
        $this->get('/filing-system/permintaan')->assertOk()->assertSee('Permintaan Akses');
    }

    public function test_bukan_pemilik_tidak_bisa_menyetujui_file(): void
    {
        $owner = $this->nextId();
        $other = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($other, 'Bukan Pemilik');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        $this->login($other, 'Bukan Pemilik');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertStatus(403);

        $request = DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->first();
        $this->assertSame('pending', $request->status);
        $this->assertSame(0, DB::connection('run')->table('file_shareto')->where('filesys_id', $fileId)->count());
    }

    public function test_setujui_grup_menambah_anggota_viewer_dan_teraudit(): void
    {
        $owner = $this->nextId();
        $manager = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($manager, 'Pengelola Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $groupId = $this->seedGroup($owner, 'Grup Sasaran');
        $this->seedMember($groupId, $manager, 'manager');
        $requestId = $this->seedRequest('group', $groupId, $requester);

        $this->login($manager, 'Pengelola Uji');

        $this->get('/filing-system/permintaan')->assertOk()->assertSee('Grup Sasaran');

        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('success_msg');

        $member = DB::connection('run')->table('sys_filegrp_member')
            ->where('group_id', $groupId)->where('member_value', (string) $requester)->first();
        $this->assertNotNull($member);
        $this->assertSame('viewer', $member->role);

        $request = DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->first();
        $this->assertSame('approved', $request->status);
        $this->assertSame($manager, (int) $request->decided_by);

        $audit = DB::connection('run')->table('sys_audit_log')
            ->where('action', 'FILING_GROUP_ACCESS_REQUEST_APPROVE')->where('target_id', $groupId)->first();
        $this->assertNotNull($audit);
        $this->assertSame($manager, (int) $audit->actor_user_id);

        $notification = $this->notificationFor($requester, 'filing_access_decided');
        $this->assertNotNull($notification);
        $this->assertSame(route('filing-system.groups.show', $groupId), $notification->target_url);
    }

    public function test_setujui_grup_tidak_menurunkan_peran_anggota_eksisting(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($requester, 'Peminta Uji');
        $groupId = $this->seedGroup($owner);
        $this->seedMember($groupId, $requester, 'contributor');
        $requestId = $this->seedRequest('group', $groupId, $requester);

        $this->login($owner, 'Pemilik Grup');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('success_msg');

        $members = DB::connection('run')->table('sys_filegrp_member')
            ->where('group_id', $groupId)->where('member_value', (string) $requester)->get();
        $this->assertCount(1, $members);
        $this->assertSame('contributor', $members->first()->role);
    }

    public function test_setujui_idempoten_tidak_menggandakan_hak(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        $this->login($owner, 'Pemilik Tujuan');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('success_msg');

        $second = $this->post('/filing-system/permintaan/'.$requestId.'/setujui');
        $second->assertRedirect(route('filing-system.requests.index'));

        $this->assertSame(1, DB::connection('run')->table('file_shareto')
            ->where('filesys_id', $fileId)->where('share_cat', 1)->count());
    }

    public function test_tolak_mengirim_notifikasi_dan_teraudit(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        $this->login($owner, 'Pemilik Tujuan');
        $this->post('/filing-system/permintaan/'.$requestId.'/tolak')->assertSessionHas('success_msg');

        $request = DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->first();
        $this->assertSame('rejected', $request->status);
        $this->assertSame($owner, (int) $request->decided_by);

        $this->assertSame(0, DB::connection('run')->table('file_shareto')->where('filesys_id', $fileId)->count());

        $audit = DB::connection('run')->table('sys_filing_audit')
            ->where('filing_id', $fileId)->where('action', 'access_request_reject')->first();
        $this->assertNotNull($audit);

        $notification = $this->notificationFor($requester, 'filing_access_decided');
        $this->assertNotNull($notification);
        $this->assertStringContainsString('ditolak', strtolower((string) $notification->message));
    }

    public function test_file_nonaktif_gagal_disetujui_dengan_pesan_jelas(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        DB::connection('run')->table('file_system')->where('rec_id', $fileId)->update(['status' => 'trashed']);

        $this->login($owner, 'Pemilik Tujuan');
        $response = $this->post('/filing-system/permintaan/'.$requestId.'/setujui');

        $response->assertSessionHas('error_msg');
        $this->assertSame('pending', DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->value('status'));
        $this->assertSame(0, DB::connection('run')->table('file_shareto')->where('filesys_id', $fileId)->count());
    }

    public function test_grup_terarsip_gagal_disetujui_dengan_pesan_jelas(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($requester, 'Peminta Uji');
        $groupId = $this->seedGroup($owner);
        $requestId = $this->seedRequest('group', $groupId, $requester);

        DB::connection('run')->table('sys_filegrp')->where('rec_id', $groupId)->update(['archived_at' => date('Y-m-d H:i:s')]);

        $this->login($owner, 'Pemilik Grup');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('error_msg');

        $this->assertSame('pending', DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->value('status'));
        $this->assertSame(0, DB::connection('run')->table('sys_filegrp_member')->where('group_id', $groupId)->count());
    }

    public function test_confidential_gagal_disetujui_saat_keputusan(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Tujuan');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $requestId = $this->seedRequest('file', $fileId, $requester);

        DB::connection('run')->table('file_system')->where('rec_id', $fileId)->update(['security_level' => 'confidential']);

        $this->login($owner, 'Pemilik Tujuan');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertSessionHas('error_msg');

        $this->assertSame('pending', DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->value('status'));
        $this->assertSame(0, DB::connection('run')->table('file_shareto')->where('filesys_id', $fileId)->count());
    }

    public function test_inbox_hanya_memuat_permintaan_yang_berhak_diputuskan(): void
    {
        $ownerA = $this->nextId();
        $ownerB = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($ownerA, 'Pemilik A');
        $this->seedUser($ownerB, 'Pemilik B');
        $this->seedUser($requester, 'Peminta Uji');
        $fileA = $this->seedFile($ownerA, 'berkas-punya-a.pdf');
        $fileB = $this->seedFile($ownerB, 'berkas-punya-b.pdf');
        $this->seedRequest('file', $fileA, $requester);
        $this->seedRequest('file', $fileB, $requester);

        $this->login($ownerA, 'Pemilik A');
        $this->get('/filing-system/permintaan')
            ->assertOk()
            ->assertSee('berkas-punya-a.pdf')
            ->assertDontSee('berkas-punya-b.pdf');
    }

    public function test_kontributor_grup_tidak_bisa_menyetujui(): void
    {
        $owner = $this->nextId();
        $contributor = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($contributor, 'Kontributor Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $groupId = $this->seedGroup($owner);
        $this->seedMember($groupId, $contributor, 'contributor');
        $requestId = $this->seedRequest('group', $groupId, $requester);

        $this->login($contributor, 'Kontributor Uji');
        $this->post('/filing-system/permintaan/'.$requestId.'/setujui')->assertStatus(403);

        $this->assertSame('pending', DB::connection('run')->table('file_access_request')->where('rec_id', $requestId)->value('status'));
    }
}
