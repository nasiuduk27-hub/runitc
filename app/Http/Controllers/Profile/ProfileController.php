<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private readonly MailService $mailService) {}

    public function index(Request $request): View
    {
        $userId = (int) auth_user_id();

        return view('profile.index', [
            'user' => $this->getUser($userId),
            'emails' => DB::connection('run')->table('sysitc_usermail')->where('user_recid', $userId)->orderByDesc('asdefault')->orderBy('email')->get(),
            'banks' => DB::connection('run')->table('sysitc_userbank')->where('user_recid', $userId)->orderBy('rec_id')->get(),
            'bankOptions' => DB::connection('run')->table('sys_msttable')->where('tbl_code', '51')->where('statrec', 1)->orderBy('descr')->pluck('descr', 'code'),
            'provinces' => DB::connection('run')->table('sys_provinsi')->select('rec_id', 'nama')->orderBy('nama')->get(),
            'cities' => DB::connection('run')->table('sys_kota')->select('rec_id', 'nama')->orderBy('nama')->get(),
            'photoUrl' => $this->photoUrl($userId),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->route('profile.index');
        }

        $userId = (int) auth_user_id();

        $validated = $request->validate([
            'account_nm' => ['required', 'string', 'max:150'],
            'dob' => ['nullable', 'date'],
            'sexmf' => ['nullable', 'string', 'max:1'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'kotakabupaten' => ['nullable', 'string', 'max:100'],
            'prov_cd' => ['nullable', 'string', 'max:20'],
            'banks' => ['nullable', 'array'],
            'banks.*.rec_id' => ['nullable', 'integer'],
            'banks.*.bank_code' => ['nullable', 'string', 'max:20'],
            'banks.*.account_name' => ['nullable', 'string', 'max:150'],
            'banks.*.account_no' => ['nullable', 'string', 'max:80'],
            'banks.*.is_default' => ['nullable', 'boolean'],
            'banks.*.delete' => ['nullable', 'boolean'],
            'bank_default_index' => ['nullable', 'integer'],
            'delete_photo' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        try {
            DB::connection('run')->transaction(function () use ($validated, $request, $userId): void {
                DB::connection('run')->table('sysitc_users')->where('rec_id', $userId)->update([
                    'account_nm' => $validated['account_nm'],
                    'dob' => $validated['dob'] ?? null,
                    'sexmf' => $validated['sexmf'] ?? '',
                    'whatsapp' => $validated['whatsapp'] ?? '',
                    'address' => $validated['address'] ?? '',
                    'kotakabupaten' => $validated['kotakabupaten'] ?? '',
                    'prov_cd' => $validated['prov_cd'] ?? '',
                ]);

                $this->saveBanks($validated['banks'] ?? [], $userId, (int) ($validated['bank_default_index'] ?? -1));
                $this->savePhoto($request, $userId);
            });

            $request->session()->put('user_name', $validated['account_nm']);
            $request->session()->put('account_nm', $validated['account_nm']);

            return back()->with('success_msg', 'Profile updated successfully!');
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error_msg', 'Error updating profile: '.$e->getMessage());
        }
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->route('profile.index');
        }

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'same:new_password'],
        ], [], ['new_password' => 'password', 'confirm_password' => 'konfirmasi password']);

        $userId = (int) auth_user_id();
        $user = DB::connection('run')->table('sysitc_users')->where('rec_id', $userId)->first();

        if (! $user) {
            return back()->with('error_msg', 'Data user tidak ditemukan.');
        }

        $login = DB::connection('run')->table('sysitc_login')->where('rec_id', $user->login_rec_id)->first();
        if (! $login) {
            return back()->with('error_msg', 'Data login tidak ditemukan.');
        }

        $passwordHash = md5($validated['new_password']);

        DB::connection('run')->table('sysitc_login')->where('rec_id', $user->login_rec_id)->update([
            'password_id' => $passwordHash,
        ]);

        try {
            DB::connection('mysql')
                ->table('sysitc_login')
                ->where('account_id', $login->account_id)
                ->update(['password_id' => $passwordHash]);
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success_msg', 'Password berhasil diperbarui!');
    }

    public function requestEmailChange(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->route('profile.index');
        }

        $validated = $request->validate(['new_email' => ['required', 'email']]);
        $userId = (int) auth_user_id();
        $newEmail = strtolower(trim($validated['new_email']));
        $user = $this->getUser($userId);

        if (! $user) {
            return back()->with('error_msg', 'Data user tidak ditemukan.');
        }

        $allowDuplicateEmail = in_array($newEmail, ['aska2707nas@gmail.com'], true);
        $exists = ! $allowDuplicateEmail && (
            DB::connection('run')->table('sysitc_login')->whereRaw('LOWER(email_id) = ?', [$newEmail])->exists()
            || DB::connection('run')->table('sysitc_usermail')->whereRaw('LOWER(email) = ?', [$newEmail])->where('user_recid', '<>', $userId)->exists()
        );

        if ($exists) {
            return back()->with('error_msg', 'Email baru sudah digunakan oleh akun lain.');
        }

        $otp = (string) random_int(10000, 99999);
        $subject = 'Verifikasi Perubahan Email - ITCONE';
        $body = 'Halo '.ucwords(strtolower((string) ($user->account_nm ?? 'User'))).",\n\n"
            ."Kami menerima permintaan perubahan email akun ITCONE Anda.\n"
            ."Berikut adalah 5 digit kode OTP Anda:\n\n"
            ."OTP CODE = {$otp}\n\n"
            ."Kode ini berlaku selama 5 menit. Jika Anda tidak meminta perubahan email, abaikan email ini.\n\n"
            ."Salam,\nTim ITCONE";

        if (! $this->mailService->send($newEmail, (string) ($user->account_nm ?? 'User'), $subject, $body)) {
            return back()->withInput()->with('error_msg', 'Gagal mengirim OTP ke email baru. Silakan coba lagi.');
        }

        $request->session()->put([
            'change_email_new' => $newEmail,
            'change_email_old' => strtolower(trim((string) ($user->email_id ?? ''))),
            'change_email_otp' => $otp,
            'change_email_exp' => time() + 300,
        ]);

        return redirect()->route('profile.verify-email')->with('success_msg', 'OTP perubahan email telah dikirim ke email baru.');
    }

    public function verifyEmailForm(): View
    {
        return view('profile.verify-email');
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->route('profile.verify-email');
        }

        $inputOtp = trim((string) $request->input('full_token', ''));
        $sessionOtp = (string) $request->session()->get('change_email_otp', '');
        $expiresAt = (int) $request->session()->get('change_email_exp', 0);
        $newEmail = (string) $request->session()->get('change_email_new', '');
        $userId = (int) auth_user_id();

        if ($newEmail === '' || $sessionOtp === '' || time() > $expiresAt || $inputOtp !== $sessionOtp) {
            return back()->with('error_msg', 'Kode OTP salah atau sudah kedaluwarsa.');
        }

        $user = DB::connection('run')->table('sysitc_users')->where('rec_id', $userId)->first();
        if (! $user) {
            return redirect()->route('profile.index')->with('error_msg', 'Data user tidak ditemukan.');
        }

        DB::connection('run')->transaction(function () use ($user, $userId, $newEmail): void {
            DB::connection('run')->table('sysitc_login')->where('rec_id', $user->login_rec_id)->update(['email_id' => $newEmail]);

            $mail = DB::connection('run')->table('sysitc_usermail')->where('user_recid', $userId)->where('asdefault', 1)->first();
            if ($mail) {
                DB::connection('run')->table('sysitc_usermail')->where('rec_id', $mail->rec_id)->update(['email' => $newEmail]);
            } else {
                DB::connection('run')->table('sysitc_usermail')->insert(['user_recid' => $userId, 'email' => $newEmail, 'asdefault' => 1]);
            }
        });

        $oldEmail = (string) $request->session()->get('change_email_old', '');
        if ($oldEmail !== '' && filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
            $subject = 'Notifikasi Perubahan Email - ITCONE';
            $body = 'Halo '.ucwords(strtolower((string) ($user->account_nm ?? 'User'))).",\n\n"
                ."Email akun ITCONE Anda telah diganti menjadi {$newEmail}.\n"
                ."Jika perubahan ini bukan dilakukan oleh Anda, segera hubungi Administrator.\n\n"
                ."Salam,\nTim ITCONE";

            $this->mailService->send($oldEmail, (string) ($user->account_nm ?? 'User'), $subject, $body);
        }

        $request->session()->forget(['change_email_new', 'change_email_old', 'change_email_otp', 'change_email_exp']);

        return redirect()->route('profile.index')->with('success_msg', 'Email berhasil diganti.');
    }

    private function getUser(int $userId): ?object
    {
        return DB::connection('run')->selectOne(
            'SELECT log.account_id, log.email_id, mst.* FROM sysitc_users mst JOIN sysitc_login log ON mst.login_rec_id = log.rec_id WHERE mst.rec_id = ? LIMIT 1',
            [$userId]
        );
    }

    private function saveBanks(array $banks, int $userId, int $defaultIndex): void
    {
        $defaultRecId = 0;
        $firstSavedRecId = 0;

        foreach ($banks as $index => $bank) {
            $bankRecId = (int) ($bank['rec_id'] ?? 0);
            $bankCode = trim((string) ($bank['bank_code'] ?? ''));
            $accountName = trim((string) ($bank['account_name'] ?? ''));
            $accountNo = trim((string) ($bank['account_no'] ?? ''));

            if (! empty($bank['delete'])) {
                if ($bankRecId > 0) {
                    DB::connection('run')->table('sysitc_userbank')->where('rec_id', $bankRecId)->where('user_recid', $userId)->delete();
                }

                continue;
            }

            if ($bankCode === '' && $accountName === '' && $accountNo === '') {
                continue;
            }

            if ($bankCode === '' || $accountName === '' || $accountNo === '') {
                throw new \RuntimeException('Bank, nama rekening, dan nomor rekening wajib diisi lengkap.');
            }

            if ($bankRecId > 0) {
                DB::connection('run')->table('sysitc_userbank')->where('rec_id', $bankRecId)->where('user_recid', $userId)->update([
                    'bnkcd' => $bankCode,
                    'accnm' => $accountName,
                    'accno' => $accountNo,
                ]);
            } else {
                $bankRecId = (int) DB::connection('run')->table('sysitc_userbank')->insertGetId([
                    'user_recid' => $userId,
                    'bnkcd' => $bankCode,
                    'accnm' => $accountName,
                    'accno' => $accountNo,
                    'asdefault' => 0,
                ]);
            }

            $firstSavedRecId = $firstSavedRecId ?: $bankRecId;
            if ((int) $index === $defaultIndex || ! empty($bank['is_default'])) {
                $defaultRecId = $bankRecId;
            }
        }

        $defaultRecId = $defaultRecId ?: $firstSavedRecId;

        DB::connection('run')->table('sysitc_userbank')->where('user_recid', $userId)->update(['asdefault' => 0]);
        if ($defaultRecId > 0) {
            DB::connection('run')->table('sysitc_userbank')->where('rec_id', $defaultRecId)->where('user_recid', $userId)->update(['asdefault' => 1]);
        }
    }

    private function savePhoto(Request $request, int $userId): void
    {
        $targetDir = public_path('assets/personal');
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if ($request->boolean('delete_photo')) {
            $this->deletePhoto($userId);
        }

        if (! $request->hasFile('photo')) {
            return;
        }

        $this->deletePhoto($userId);
        $file = $request->file('photo');
        $file->move($targetDir, 'user_'.$userId.'.'.$file->getClientOriginalExtension());
    }

    private function deletePhoto(int $userId): void
    {
        foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
            $file = public_path('assets/personal/user_'.$userId.'.'.$ext);
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function photoUrl(int $userId): string
    {
        foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
            $file = public_path('assets/personal/user_'.$userId.'.'.$ext);
            if (is_file($file)) {
                return asset('assets/personal/user_'.$userId.'.'.$ext).'?v='.filemtime($file);
            }
        }

        return asset('assets/personal/nopicture.png');
    }
}
