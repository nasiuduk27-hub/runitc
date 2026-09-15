<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Cooperative\CooperativeLoan;
use App\Models\Cooperative\CooperativeMember;
use App\Models\Cooperative\CooperativeTransaction;
use App\Services\MailService;
use App\Support\CooperativeAccess;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class MemberController extends Controller
{
    public function __construct(private readonly MailService $mailService) {}

    /** Kode role CU Member pada sysitc_grpacc (grpaccess/grpacc). */
    private const ROLE_CU_MEMBER = ['01', '01'];

    /** Nomor anggota sentinel legacy yang tidak boleh dipakai. */
    private const ICUNO_SENTINELS = ['CU-8888', 'CU-9999'];

    public function index(Request $request): View
    {
        $members = CooperativeMember::query()
            ->search($request->query('q'))
            ->status($request->query('status'))
            ->orderBy('icuno')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => (int) CooperativeMember::query()->count(),
            'regular' => (int) CooperativeMember::query()->where('st_aktif', CooperativeMember::STATUS_REGULAR_MEMBER)->count(),
            'outstanding' => (int) CooperativeMember::query()->where('st_aktif', CooperativeMember::STATUS_OUTSTANDING_MEMBER)->count(),
            'non_active' => (int) CooperativeMember::query()->where('st_aktif', CooperativeMember::STATUS_NON_ACTIVE)->count(),
        ];

        return view('cooperative.members.index', [
            'members' => $members,
            'stats' => $stats,
            'statusLabels' => CooperativeMember::STATUS_LABELS,
            'isCoopAdmin' => CooperativeAccess::isAdmin((int) auth_user_id()),
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
            ],
        ]);
    }

    public function detail(Request $request): View
    {
        $member = CooperativeMember::query()->findOrFail((int) $request->query('rec_id'));

        $loans = $member->loans()
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->get();

        $transactions = $member->transactions()
            ->orderByDesc('trndt')
            ->orderByDesc('rec_id')
            ->limit(15)
            ->get();

        $transactionTotals = [
            'debit' => (int) $member->transactions()->where('dbocr', 'D')->sum('amount'),
            'credit' => (int) $member->transactions()->where('dbocr', 'C')->sum('amount'),
            'count' => (int) $member->transactions()->count(),
        ];

        return view('cooperative.members.detail', [
            'member' => $member,
            'loans' => $loans,
            'loanSummary' => $this->loanSummary($loans),
            'transactions' => $transactions,
            'transactionTotals' => $transactionTotals,
            'directionLabels' => CooperativeTransaction::DIRECTION_LABELS,
        ]);
    }

    public function create(Request $request): View
    {
        $linkedUserIds = CooperativeMember::query()
            ->where('itc_user_id', '>', 0)
            ->pluck('itc_user_id');

        $userOptions = DB::connection('run')->table('sysitc_users as u')
            ->leftJoin('sysitc_login as l', 'l.rec_id', '=', 'u.login_rec_id')
            ->where('u.status', 1)
            ->when($linkedUserIds->isNotEmpty(), fn ($query) => $query->whereNotIn('u.rec_id', $linkedUserIds))
            ->orderBy('u.account_nm')
            ->get(['u.rec_id', 'u.account_nm', 'l.account_id'])
            ->map(fn ($row): array => [
                'rec_id' => (int) $row->rec_id,
                'label' => trim((string) $row->account_nm).' ('.trim((string) ($row->account_id ?? '')).')',
            ])
            ->all();

        return view('cooperative.members.create', [
            'userOptions' => $userOptions,
            'nextIcuno' => $this->peekNextIcuno(),
            'defaultSwajib' => 100000,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CooperativeAccess::isAdmin($userId), 403);

        $data = $request->validate([
            'user_rec_id' => ['required', 'integer', 'min:1'],
            'icunm' => ['required', 'string', 'max:40'],
            'alias_nm' => ['nullable', 'string', 'max:10'],
            'joindt' => ['required', 'date', 'before_or_equal:today'],
            'swajib' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'refno' => ['nullable', 'string', 'max:15'],
        ]);

        // Akun RUNITC harus aktif dan belum terhubung ke anggota lain.
        $account = DB::connection('run')->table('sysitc_users')
            ->where('rec_id', (int) $data['user_rec_id'])
            ->where('status', 1)
            ->first(['rec_id', 'account_nm']);

        if (! $account) {
            return back()->withInput()->withErrors(['user_rec_id' => 'Akun RUNITC tidak ditemukan atau tidak aktif.']);
        }

        if (CooperativeMember::query()->where('itc_user_id', $account->rec_id)->exists()) {
            return back()->withInput()->withErrors(['user_rec_id' => 'Akun tersebut sudah terhubung dengan anggota lain.']);
        }

        try {
            [$memberRecId, $icuno] = DB::connection('mysql')->transaction(function () use ($data, $account): array {
                $icuno = CooperativeMember::generateIcuno();
                $now = now();

                $memberRecId = (int) DB::connection('mysql')->table('icu_member')->insertGetId([
                    'itc_user_id' => (int) $account->rec_id,
                    'pprdk' => '',
                    'icuno' => $icuno,
                    'icunm' => trim((string) $data['icunm']),
                    'alias_nm' => trim((string) ($data['alias_nm'] ?? '')),
                    'joindt' => (string) $data['joindt'],
                    'st_aktif' => CooperativeMember::STATUS_REGULAR_MEMBER,
                    'temp_trx' => 0,
                    'otvalue' => 0,
                    'swajib' => (int) $data['swajib'],
                    'outstanding' => 0,
                    'stat_trx' => 0,
                    'refno' => trim((string) ($data['refno'] ?? '')),
                    'entusr' => mb_substr((string) session('account_nm', session('user_name', 'RUN')), 0, 5),
                    'entdt' => $now,
                    'lupd' => $now,
                    'koreksi' => 0,
                ], 'rec_id');

                return [$memberRecId, $icuno];
            });
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['icunm' => 'Gagal menyimpan anggota: '.$exception->getMessage()]);
        }

        // Assign role CU Member di koneksi run; kegagalan tidak membatalkan anggota.
        $roleGranted = true;
        $roleError = null;

        try {
            DB::connection('run')->transaction(function () use ($account, $userId): void {
                $exists = DB::connection('run')->table('sysitc_usracc')
                    ->where('user_rec_id', $account->rec_id)
                    ->where('access_code', self::ROLE_CU_MEMBER[0])
                    ->where('access_account', self::ROLE_CU_MEMBER[1])
                    ->exists();

                if (! $exists) {
                    DB::connection('run')->table('sysitc_usracc')->insert([
                        'user_rec_id' => (int) $account->rec_id,
                        'grpaccess' => self::ROLE_CU_MEMBER[0],
                        'access_code' => self::ROLE_CU_MEMBER[0],
                        'access_account' => self::ROLE_CU_MEMBER[1],
                        'updby_userid' => $userId,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            $roleGranted = false;
            $roleError = $exception->getMessage();
        }

        try {
            $this->writeAudit($request, $memberRecId, [
                'icuno' => $icuno,
                'itc_user_id' => (int) $account->rec_id,
                'st_aktif' => CooperativeMember::STATUS_REGULAR_MEMBER,
                'swajib' => (int) $data['swajib'],
                'role_granted' => $roleGranted,
            ]);
        } catch (Throwable) {
            // Audit bersifat best-effort.
        }

        $message = 'Anggota '.$icuno.' berhasil ditambahkan sebagai Regular Member.'
            .($roleGranted
                ? ' Role CU Member otomatis diberikan.'
                : ' Namun role CU Member gagal diberikan'.($roleError !== null ? ' ('.$roleError.')' : '').' — assign manual lewat Admin > User Role.');

        return redirect()
            ->route('cooperative.members.detail', ['rec_id' => $memberRecId])
            ->with('success', $message);
    }

    /**
     * Menampilkan halaman sinkron akun untuk member belum terhubung.
     */
    public function sync(Request $request, int $memberRecId): View
    {
        $userId = (int) auth_user_id();
        abort_unless(CooperativeAccess::isAdmin($userId), 403);

        $member = CooperativeMember::query()->findOrFail($memberRecId);
        if ($member->itc_user_id > 0) {
            return redirect()->route('cooperative.members.detail', ['rec_id' => $memberRecId])
                ->with('error', 'Anggota ini sudah terhubung dengan akun RUNITC.');
        }

        // Ambil kandidat akun RUNITC aktif yang belum dipakai di anggota lain
        $linkedUserIds = CooperativeMember::query()
            ->where('itc_user_id', '>', 0)
            ->pluck('itc_user_id');

        $userOptions = DB::connection('run')->table('sysitc_users as u')
            ->leftJoin('sysitc_login as l', 'l.rec_id', '=', 'u.login_rec_id')
            ->where('u.status', 1)
            ->when($linkedUserIds->isNotEmpty(), fn ($query) => $query->whereNotIn('u.rec_id', $linkedUserIds))
            ->orderBy('u.account_nm')
            ->get(['u.rec_id', 'u.account_nm', 'l.account_id'])
            ->map(fn ($row): array => [
                'rec_id' => (int) $row->rec_id,
                'label' => trim((string) $row->account_nm).' ('.trim((string) ($row->account_id ?? '')).')',
            ])
            ->all();

        return view('cooperative.members.sync', [
            'member' => $member,
            'userOptions' => $userOptions,
            'ref_token' => null,
        ]);
    }

    /**
     * Mengirim OTP ke email pemilik akun target.
     */
    public function doSync(Request $request, int $memberRecId): RedirectResponse
    {
        $userId = (int) auth_user_id();
        abort_unless(CooperativeAccess::isAdmin($userId), 403);

        $member = CooperativeMember::query()->findOrFail($memberRecId);
        if ($member->itc_user_id > 0) {
            return back()->withErrors(['general' => 'Anggota ini sudah terhubung.']);
        }

        $validated = $request->validate([
            'target_user_id' => ['required', 'integer', 'min:1'],
        ]);

        $targetUser = DB::connection('run')->table('sysitc_users as u')
            ->join('sysitc_login as l', 'l.rec_id', '=', 'u.login_rec_id')
            ->where('u.rec_id', $validated['target_user_id'])
            ->where('u.status', 1)
            ->first(['u.rec_id', 'u.account_nm', 'l.email_id']);

        if (! $targetUser) {
            return back()->withErrors(['target_user_id' => 'Akun RUNITC tidak ditemukan atau tidak aktif.']);
        }

        if (empty($targetUser->email_id)) {
            return back()->withErrors(['target_user_id' => 'Akun RUNITC tidak memiliki email yang valid untuk verifikasi.']);
        }

        // Generate OTP 5 digit
        $otpCode = random_int(10000, 99999);
        $otpExpiredAt = now()->addMinutes(5)->format('Y-m-d H:i:s');

        // Simpan OTP di tabel coop_sync_request
        $refToken = bin2hex(random_bytes(32));

        DB::connection('run')->table('coop_sync_request')->insert([
            'member_rec_id' => $memberRecId,
            'member_icuno' => $member->icuno,
            'member_name' => $member->icunm,
            'target_user_id' => $validated['target_user_id'],
            'target_email' => $targetUser->email_id,
            'otp_code' => $otpCode,
            'ref_token' => $refToken,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ]);

        // Kirim email ke pemilik akun
        $subject = 'Verifikasi Sinkron Akun - RUN-ITC';
        $body = 'Halo '.ucwords(strtolower((string) $targetUser->account_nm)).',\n\n'
            .'Admin koperasi sedang melakukan sinkronisasi akun Anda dengan anggota kooperasi.\n\n'
            .'Kode OTP verifikasi: '.$otpCode.'\n\n'
            .'Kode ini hanya berlaku selama 5 menit. Jangan berikan kode ini kepada siapapun.\n\n'
            .'Jika Anda tidak melakukan permintaan ini, abaikan email ini.\n\n'
            .'Best Regards,\nRUN-ITC Team';

        $sent = $this->mailService->send($targetUser->email_id, $targetUser->account_nm, $subject, $body);

        if (! $sent) {
            DB::connection('run')->table('coop_sync_request')
                ->where('ref_token', $refToken)
                ->update(['status' => 'expired']);

            return back()->withErrors([
                'target_user_id' => 'Gagal mengirim email OTP ke '.$targetUser->email_id.' Periksa konfigurasi SMTP dan coba lagi.',
            ]);
        }

        // Redirect ke halaman verifikasi dengan ref_token
        return redirect()->route('cooperative.members.sync.verify', ['ref_token' => $refToken])
            ->with('success', 'Kode OTP telah dikirim ke email pemilik akun.');
    }

    /**
     * Menampilkan halaman verifikasi OTP oleh pemilik akun.
     */
    public function syncVerify(string $ref_token): View|RedirectResponse
    {
        $syncRequest = DB::connection('run')->table('coop_sync_request')
            ->where('ref_token', $ref_token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $syncRequest) {
            return redirect()->route('cooperative.dashboard')
                ->with('error', 'Permintaan sinkron tidak valid atau telah kadaluwarsa.');
        }

        return view('cooperative.members.sync-verify', [
            'ref_token' => $ref_token,
            'member_icuno' => $syncRequest->member_icuno,
            'member_name' => $syncRequest->member_name,
            'target_account' => $syncRequest->target_email,
            'expires_at' => Carbon::parse($syncRequest->expires_at)->diffForHumans(),
        ]);
    }

    /**
     * Memproses verifikasi OTP oleh pemilik akun.
     */
    public function syncVerifyStore(Request $request, string $ref_token): RedirectResponse
    {
        $syncRequest = DB::connection('run')->table('coop_sync_request')
            ->where('ref_token', $ref_token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $syncRequest) {
            return back()->with('error', 'Permintaan sinkron tidak valid atau telah kadaluwarsa.');
        }

        $inputOtp = trim((string) $request->input('full_token'));

        if ($inputOtp !== $syncRequest->otp_code) {
            return back()->with('error', 'Kode OTP salah.');
        }

        // Validasi OTP: konsumsi dan tandai status
        DB::connection('run')->table('coop_sync_request')
            ->where('ref_token', $ref_token)
            ->update([
                'status' => 'verified',
                'verified_at' => now(),
            ]);

        // 1. Update icu_member.itc_user_id (koneksi mysql)
        $member = DB::connection('mysql')->table('icu_member')->where('rec_id', $syncRequest->member_rec_id)->first();
        if ($member) {
            DB::connection('mysql')->table('icu_member')
                ->where('rec_id', $syncRequest->member_rec_id)
                ->update(['itc_user_id' => $syncRequest->target_user_id]);
        }

        // 2. Pastikan role CU Member (01/01) ada di sysitc_usracc (idempotent)
        $roleExists = DB::connection('run')->table('sysitc_usracc')
            ->where('user_rec_id', $syncRequest->target_user_id)
            ->where('access_code', '01')
            ->where('access_account', '01')
            ->exists();

        if (! $roleExists) {
            DB::connection('run')->table('sysitc_usracc')->insert([
                'user_rec_id' => $syncRequest->target_user_id,
                'grpaccess' => '01',
                'access_code' => '01',
                'access_account' => '01',
                'updby_userid' => (int) auth_user_id(),
            ]);
        }

        // 3. Audit log
        DB::connection('run')->table('sys_audit_log')->insert([
            'actor_user_id' => (int) auth_user_id(),
            'action' => 'cooperative.member.synced',
            'target_type' => 'coop_icu_member',
            'target_id' => $syncRequest->member_rec_id,
            'metadata_json' => json_encode([
                'icuno' => $syncRequest->member_icuno,
                'itc_user_id' => $syncRequest->target_user_id,
                'synced_by' => (int) auth_user_id(),
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        // 4. Hapus permintaan sinkron
        DB::connection('run')->table('coop_sync_request')
            ->where('ref_token', $ref_token)
            ->update(['status' => 'cancelled']);

        return redirect()->route('cooperative.dashboard')
            ->with('success', 'Akun RUNITC berhasil disinkronisasi dengan member CU-'.$syncRequest->member_icuno.' dan role CU Member diberikan.');
    }

    /**
     * Nomor anggota berikutnya untuk pratinjau form.
     */
    private function peekNextIcuno(): string
    {
        try {
            return CooperativeMember::generateIcuno();
        } catch (RuntimeException) {
            return '-';
        }
    }

    private function writeAudit(Request $request, int $memberRecId, array $metadata): void
    {
        DB::connection('run')->table('sys_audit_log')->insert([
            'actor_user_id' => (int) auth_user_id(),
            'action' => 'cooperative.member.created',
            'target_type' => 'coop_icu_member',
            'target_id' => $memberRecId,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, CooperativeLoan>  $loans
     * @return array{count: int, total_principle: int, total_settled: int, indicative_outstanding: int}
     */
    private function loanSummary($loans): array
    {
        return [
            'count' => $loans->count(),
            'total_principle' => (int) $loans->sum('principle'),
            'total_settled' => (int) $loans->sum('paid'),
            // Indikatif: field outstanding existing belum dikonfirmasi maknanya (bagian 34 dokumen),
            // jadi sisa pokok dihitung dari principle - paid per pinjaman, tanpa nilai negatif.
            'indicative_outstanding' => (int) $loans->sum(fn (CooperativeLoan $loan): int => max(0, $loan->principle - $loan->paid)),
        ];
    }
}
