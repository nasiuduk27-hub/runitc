<?php

namespace Tests\Feature\FilingSystem;

use App\Auth\LegacyUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi Fase 3A: intake permintaan akses Filing System.
 *
 * - Link token mode 1: pemegang tanpa hak (wajib login) membuat permintaan;
 *   form mengirim token, bukan id file; tamu diarahkan lewat shim login.
 * - Grup: bukan-anggota grup aktif melihat halaman permintaan; terarsip tetap
 *   403 polos.
 * - Anti-spam: satu pending per peminta per target (INSERT anti-dup).
 * - confidential ditolak total; restricted tetap boleh.
 *
 * Koneksi `run` dialihkan ke sqlite in-memory (pola ShareLinkCreateTest);
 * skema ditulis tangan karena tabel filing tidak ada di database/migrations
 * default. ID user unik per test terhadap cache statis permission service.
 */
class AccessRequestCreateTest extends TestCase
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
            // View composer layout menyentuh CreditUnionMember yang ter-pin ke
            // koneksi mysql; arahkan juga ke sqlite supaya test tidak pernah
            // menyentuh database produksi dari .env lokal.
            'database.connections.mysql' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'runitc.filing_use_new_tables' => true,
        ]);
        DB::purge('run');
        DB::purge('mysql');

        $this->createSchema();
    }

    private function nextId(): int
    {
        self::$seq++;

        return 980000 + self::$seq * 10;
    }

    private function token(): string
    {
        return str_pad('fase3-uji-token', 43, 'x');
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

        // LayoutService memanggil CreditUnionAccess::memberForUser (model
        // ter-pin ke koneksi mysql) saat merender layout filing; sediakan
        // tabel kosong di koneksi mysql-sqlite supaya render tidak 500.
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

    private function seedFile(int $ownerId, string $security = 'normal'): int
    {
        return (int) DB::connection('run')->table('file_system')->insertGetId([
            'trxno' => 'FSY-TEST-0001',
            'file_name' => 'dokumen-akses.pdf',
            'folder_loc' => '',
            'upload_flnm' => 'dokumen-akses.pdf',
            'file_type' => 'PDF',
            'file_notes' => 'uji fase 3a',
            'file_size' => 1024,
            'file_zip_size' => 512,
            'userid' => $ownerId,
            'status' => 'active',
            'security_level' => $security,
            'access_mode' => 'private',
            'expired_action' => 'trash',
        ], 'rec_id');
    }

    private function seedLink(string $token, int $fileId, int $mode = 1, int $createdBy = 0): void
    {
        DB::connection('run')->table('file_share_link')->insert([
            'filesys_id' => $fileId,
            'share_code_preview' => 'LNK-****-'.substr($token, -4),
            'share_code_hash' => hash('sha256', $token),
            'link_mode' => $mode,
            'created_by' => $createdBy,
            'max_access' => 0,
            'access_count' => 0,
            'allow_download' => 1,
            'is_active' => 1,
        ]);
    }

    private function seedGroup(int $ownerId, string $name = 'Grup Uji', bool $archived = false): int
    {
        return (int) DB::connection('run')->table('sys_filegrp')->insertGetId([
            'group_name' => $name,
            'description' => 'grup uji fase 3a',
            'created_by' => $ownerId,
            'status' => 'active',
            'created_at' => now(),
            'archived_at' => $archived ? now() : null,
        ], 'rec_id');
    }

    private function seedMember(int $groupId, int $userId, string $role = 'viewer'): void
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

    private function linkUrl(string $token): string
    {
        return '/filing-system/s/'.$token;
    }

    public function test_tamu_di_halaman_no_access_melihat_tombol_masuk(): void
    {
        $owner = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);

        $response = $this->get($this->linkUrl($token));

        $response->assertStatus(403);
        $response->assertSee('Masuk untuk Minta Akses');
        $response->assertDontSee('name="message"', false);
    }

    public function test_shim_masuk_menyimpan_intended_dan_mengarahkan_ke_login(): void
    {
        $owner = $this->nextId();
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);

        $response = $this->get($this->linkUrl($token).'/masuk');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('url.intended', route('filing-system.link.show', ['token' => $token]));
    }

    public function test_tamu_post_minta_akses_diarahkan_ke_shim_tanpa_baris(): void
    {
        $owner = $this->nextId();
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);

        $response = $this->post($this->linkUrl($token).'/minta-akses');

        $response->assertRedirect(route('filing-system.link.login', ['token' => $token]));
        $this->assertSame(0, DB::connection('run')->table('file_access_request')->count());
    }

    public function test_login_kembali_ke_intended_bila_ada(): void
    {
        $requester = $this->nextId();
        $this->seedUser($requester, 'Peminta Uji');

        $intended = '/filing-system/s/'.str_pad('kembali', 43, 'x');
        $this->login($requester, 'Peminta Uji');
        $this->withSession(['url.intended' => $intended]);

        $this->get('/login')->assertRedirect($intended);
    }

    public function test_login_tanpa_intended_tetap_ke_dashboard(): void
    {
        $requester = $this->nextId();
        $this->seedUser($requester, 'Peminta Uji');
        $this->login($requester, 'Peminta Uji');

        $this->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_peminta_login_membuat_permintaan_file_dan_teraudit(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);

        $this->login($requester, 'Peminta Uji');

        $response = $this->post($this->linkUrl($token).'/minta-akses', ['message' => 'Tolong beri akses ya']);

        $response->assertRedirect($this->linkUrl($token));
        $response->assertSessionHas('success_msg');

        $row = DB::connection('run')->table('file_access_request')->where('requester_id', $requester)->first();
        $this->assertNotNull($row);
        $this->assertSame('file', $row->target_type);
        $this->assertSame($fileId, (int) $row->target_id);
        $this->assertSame('pending', $row->status);
        $this->assertSame('Tolong beri akses ya', $row->message);

        $audit = DB::connection('run')->table('sys_filing_audit')
            ->where('filing_id', $fileId)->where('action', 'access_request_create')->first();
        $this->assertNotNull($audit);
        $this->assertSame($requester, (int) $audit->user_id);

        $this->get($this->linkUrl($token))
            ->assertStatus(403)
            ->assertSee('menunggu keputusan pemilik file');
    }

    public function test_permintaan_kedua_untuk_target_sama_ditolak(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);
        $this->login($requester, 'Peminta Uji');

        $this->post($this->linkUrl($token).'/minta-akses')->assertSessionHas('success_msg');
        $second = $this->post($this->linkUrl($token).'/minta-akses');

        $second->assertSessionHas('error_msg');
        $this->assertSame(1, DB::connection('run')->table('file_access_request')->where('requester_id', $requester)->count());
    }

    public function test_pemilik_tidak_dapat_meminta_akses_file_sendiri(): void
    {
        $owner = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);
        $this->login($owner, 'Pemilik Uji');

        $response = $this->post($this->linkUrl($token).'/minta-akses');

        $response->assertSessionHas('error_msg');
        $this->assertSame(0, DB::connection('run')->table('file_access_request')->count());
    }

    public function test_pengguna_yang_sudah_punya_akses_tidak_dapat_meminta(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);
        DB::connection('run')->table('file_shareto')->insert([
            'filesys_id' => $fileId,
            'share_cat' => 1,
            'othercode' => (string) $requester,
            'created_by' => $owner,
        ]);
        $this->login($requester, 'Peminta Uji');

        $response = $this->post($this->linkUrl($token).'/minta-akses');

        $response->assertSessionHas('error_msg');
        $this->assertSame(0, DB::connection('run')->table('file_access_request')->count());
    }

    public function test_confidential_diblokir_total(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner, 'confidential');
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);
        $this->login($requester, 'Peminta Uji');

        $this->post($this->linkUrl($token).'/minta-akses')->assertSessionHas('error_msg');
        $this->assertSame(0, DB::connection('run')->table('file_access_request')->count());

        $this->get($this->linkUrl($token))
            ->assertStatus(403)
            ->assertSee('tidak menerima permintaan akses')
            ->assertDontSee('name="message"', false);
    }

    public function test_restricted_tetap_dapat_diminta(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner, 'restricted');
        $token = $this->token();
        $this->seedLink($token, $fileId, 1, $owner);
        $this->login($requester, 'Peminta Uji');

        $this->post($this->linkUrl($token).'/minta-akses')->assertSessionHas('success_msg');
        $this->assertSame(1, DB::connection('run')->table('file_access_request')->count());
    }

    public function test_token_mode_dua_tidak_menerima_permintaan(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Uji');
        $this->seedUser($requester, 'Peminta Uji');
        $fileId = $this->seedFile($owner);
        $token = $this->token();
        $this->seedLink($token, $fileId, 2, $owner);
        $this->login($requester, 'Peminta Uji');

        $this->post($this->linkUrl($token).'/minta-akses');
        $this->assertSame(0, DB::connection('run')->table('file_access_request')->count());
    }

    public function test_bukan_anggota_melihat_halaman_permintaan_grup(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($requester, 'Peminta Uji');
        $groupId = $this->seedGroup($owner, 'Grup Rahasia');
        $this->login($requester, 'Peminta Uji');

        $response = $this->get('/filing-system/groups/'.$groupId);

        $response->assertOk();
        $response->assertSee('Grup Rahasia');
        $response->assertSee('Pemilik Grup');
        $response->assertSee('Minta Akses');
    }

    public function test_grup_terarsip_tetap_403_tanpa_nama(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($requester, 'Peminta Uji');
        $groupId = $this->seedGroup($owner, 'Grup Terarsip', true);
        $this->login($requester, 'Peminta Uji');

        $response = $this->get('/filing-system/groups/'.$groupId);

        $response->assertStatus(403);
        $response->assertDontSee('Grup Terarsip');
    }

    public function test_permintaan_grup_dibuat_dan_duplikat_ditolak(): void
    {
        $owner = $this->nextId();
        $requester = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($requester, 'Peminta Uji');
        $groupId = $this->seedGroup($owner);
        $this->login($requester, 'Peminta Uji');

        $this->post('/filing-system/groups/'.$groupId.'/minta-akses', ['message' => 'Mohon izin'])
            ->assertSessionHas('success_msg');

        $row = DB::connection('run')->table('file_access_request')->first();
        $this->assertNotNull($row);
        $this->assertSame('group', $row->target_type);
        $this->assertSame($groupId, (int) $row->target_id);
        $this->assertSame('Mohon izin', $row->message);

        $this->post('/filing-system/groups/'.$groupId.'/minta-akses')->assertSessionHas('error_msg');
        $this->assertSame(1, DB::connection('run')->table('file_access_request')->count());
    }

    public function test_anggota_melihat_halaman_grup_biasa(): void
    {
        $owner = $this->nextId();
        $member = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $this->seedUser($member, 'Anggota Uji');
        $groupId = $this->seedGroup($owner, 'Grup Biasa');
        $this->seedMember($groupId, $member, 'viewer');
        $this->login($member, 'Anggota Uji');

        $response = $this->get('/filing-system/groups/'.$groupId);

        $response->assertOk();
        $response->assertDontSee('Kirim permintaan kepada pengelola grup');
    }

    public function test_pemilik_grup_tidak_dapat_minta(): void
    {
        $owner = $this->nextId();
        $this->seedUser($owner, 'Pemilik Grup');
        $groupId = $this->seedGroup($owner);
        $this->login($owner, 'Pemilik Grup');

        $this->post('/filing-system/groups/'.$groupId.'/minta-akses')->assertSessionHas('error_msg');
        $this->assertSame(0, DB::connection('run')->table('file_access_request')->count());
    }
}
