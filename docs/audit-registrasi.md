# Audit Alur Registrasi & Input Data — RUN-ITC

Tanggal: 2026-10-02
Ruang lingkup: modul auth (registrasi, verifikasi OTP, reset password, login)
Metode: pembacaan kode + eksekusi logika PHP + introspeksi **read-only** ke database.

> **Legenda**
> - ✅ **Terbukti** — dibuktikan lewat eksekusi logika atau query read-only ke DB.
> - 📖 **Dugaan kode** — ditemukan dari pembacaan kode, belum dijalankan end-to-end.
>
> **Catatan keselamatan:** `.env` menunjuk ke database **produksi**. Tidak ada proses registrasi/tulis yang dijalankan; verifikasi DB murni `SELECT` / `SHOW`.

---

## Ringkasan Eksekutif

| # | Temuan | Keparahan | Status |
|---|--------|-----------|--------|
| 1 | Password disimpan dengan MD5 tanpa salt | Tinggi | ✅ |
| 2 | Validasi panjang tidak sinkron dengan skema DB → error mentah ke user | Tinggi | ✅ |
| 3 | Tidak ada rate limit; OTP 5 digit bisa di-brute force | Tinggi | ✅ |
| 4 | Masa berlaku OTP salah (~70 menit) | Tinggi | ✅ |
| 5 | Deteksi "sister company" tidak konsisten → registrasi pegawai internal rusak | Tinggi | ✅ |
| 6 | Email duplikat diizinkan, tanpa unique index | Tinggi | ✅ |
| 7 | Race condition: `account_id` tidak unique di DB `run` | Sedang–Tinggi | ✅ |
| 8 | XSS lewat `{!! !!}` dan `account_id` dalam pesan error | Sedang | 📖 |
| 9 | `dob` wajib di DB tapi opsional di service | Sedang | ✅ |
| 10 | Field wajib hanya di frontend; `sexmf`/`instansi` tak divalidasi | Sedang | ✅/📖 |
| 11 | Pesan exception mentah bocor ke user | Sedang | ✅ |
| 12 | Transaksi lintas-DB tidak atomik | Sedang | 📖 |
| 13 | Forgot-password hanya cari di DB `run` + enumerasi akun | Sedang | 📖 |
| 14 | LIKE wildcard tidak di-escape pada client-search | Rendah | 📖 |
| 15 | `authenticate()` berhenti awal pada multi-DB | Rendah | 📖 |

---

## Alur Registrasi (ringkas)

`register.blade.php` (form + validasi HTML)
→ `POST /register` (`routes/auth.php:18`)
→ `LoginController::submitRegister` (`app/Http/Controllers/Auth/LoginController.php:79`)
→ cek employee internal (`findEmployee`)
→ `AuthService::register` (`app/Services/AuthService.php:149`)
→ validasi manual → cek duplikat `account_id`
→ insert `sysitc_login` + `sysitc_users` + `sysitc_usermail` (+ `sys_mstclient`)
→ generate OTP 5 digit → kirim email + simpan bot notif
→ set session `verify_account_id`
→ `GET /verify-otp` → `POST /verify-otp`
→ `AuthService::verifyOtp` (`AuthService.php:387`) → set `status=1`.

Reset password: `forgot-password` → OTP → `reset-password`.

---

## Temuan

### 1. Password disimpan dengan MD5 tanpa salt — Tinggi ✅

**(a) Masalah**
Password di-hash `md5($password)` tanpa salt. MD5 cepat dan tidak layak untuk password; jika DB bocor, serangan rainbow/lookup real-time sangat mudah. Query login memakai `MD5(?)`.

**(b) Reproduksi**
`SHOW COLUMNS FROM sysitc_login` → `password_id varchar(32) NOT NULL` (persis panjang MD5). Sample baris `pwlen=32`. Kode: `insertLoginRecord`, `resetPassword`, autentikasi.

**(c) Tingkat keparahan**
Tinggi.

**(d) Lokasi**
`app/Services/AuthService.php:357, 482, 487, 535`.

**(e) Saran perbaikan**
Migrasi ke `Hash::make()` / `password_hash()` (bcrypt/argon2), rehash saat login, sediakan skema transisi untuk hash MD5 lama.

---

### 2. Validasi panjang tidak sinkron dengan skema DB → error mentah ke user — Tinggi ✅

**(a) Masalah**
Server hanya memvalidasi panjang minimum, tanpa batas maksimum, sedangkan kolom DB pendek dan `strict=true`. Akibatnya `Data too long` / `Column 'dob' cannot be null` dilempar sebagai exception dan ditampilkan mentah ke user.

Kolom terkonfirmasi:
- `sysitc_login.account_id varchar(30)`, `email_id varchar(30)`, `account_nm varchar(30)`
- `sysitc_users.account_nm char(30)`, `alias_nm varchar(10)`, `dob date NOT NULL`
- `sys_mstclient.clientnm varchar(50)`

**(b) Reproduksi**
Kirim `email_id` > 30 karakter (tetap lolos `filter_var`), atau `alias` > 10 karakter (form tidak punya `maxlength`), atau kosongkan `dob` → error DB.

**(c) Tingkat keparahan**
Tinggi (registrasi gagal dengan pesan DB bocor).

**(d) Lokasi**
`AuthService.php:149-219`; skema `sysitc_login` / `sysitc_users`.

**(e) Saran perbaikan**
Tambahkan aturan `max:` server sesuai lebar kolom; tangani error DB dengan pesan generik.

---

### 3. Tidak ada rate limit; OTP 5 digit bisa di-brute force — Tinggi ✅

**(a) Masalah**
Tidak ada throttle di login/verify/forgot. OTP salah tidak menghapus token sehingga bisa ditebak berulang. OTP hanya 5 digit (100.000 kombinasi) — ditambah tanpa pembatasan percobaan.

**(b) Reproduksi**
Grep `throttle|RateLimiter` di `app` dan `routes` → kosong. Token tidak diinvalidasi pada percobaan gagal.

**(c) Tingkat keparahan**
Tinggi.

**(d) Lokasi**
`routes/auth.php`, `AuthService.php:387-461`, `LoginController.php:237`.

**(e) Saran perbaikan**
Tambahkan middleware `throttle`, batas percobaan OTP per akun (mis. maks 5 lalu invalidasi token), dan lockout sementara.

---

### 4. Masa berlaku OTP salah (~70 menit) — Tinggi ✅

**(a) Masalah**
`token_exp` diset `now()+10 menit`, tetapi pengecekan kadaluarsa memakai `expiry < time() - 3600`, sehingga token baru dianggap kedaluwarsa setelah lebih dari 1 jam (valid ~70 menit). `submitVerify` juga tidak mengecek timeout sesi (hanya `showVerify` yang cek 5 menit).

**(b) Reproduksi**
Eksekusi PHP: pada T+20m dan T+70m hasil `expired=false`.

**(c) Tingkat keparahan**
Tinggi.

**(d) Lokasi**
`AuthService.php:422-428`; `LoginController.php:147-153, 158-175`.

**(e) Saran perbaikan**
Gunakan `strtotime($token_exp) < time()` (tanpa `-3600`); samakan TTL DB dengan UI; validasi ulang timeout di `submitVerify`.

---

### 5. Deteksi "sister company" tidak konsisten → registrasi pegawai internal rusak — Tinggi ✅

**(a) Masalah**
Controller menormalkan nama instansi (buang titik) dan mengenali `pt itc` / `pt international test center` sebagai internal, tetapi `AuthService` membandingkan string mentah dengan daftar **bertitik** (`pt. itc`, `pt. international test center`). Untuk input `"PT ITC"`: controller mewajibkan & memvalidasi Employee No, tetapi service menganggapnya eksternal → tidak menulis DB `mysql`, tidak menautkan `hrd_employee.itc_user_id`, malah membuat `sys_mstclient`.

**(b) Reproduksi**
Eksekusi PHP:
- `PT ITC` → `controller_required=true, service_sister=false`
- `PT. ITC` → keduanya `true`
- `PT International Test Center` → `controller_required=true, service_sister=false`
- `International Test Center` → keduanya `true`

**(c) Tingkat keparahan**
Tinggi.

**(d) Lokasi**
`LoginController.php:132-139` vs `AuthService.php:209-215, 246-282`.

**(e) Saran perbaikan**
Satu sumber kebenaran: pindahkan normalisasi ke service/util bersama dan pakai daftar yang konsisten.

---

### 6. Email duplikat diizinkan, tanpa unique index — Tinggi ✅

**(a) Masalah**
Hanya `account_id` yang dicek duplikat; email tidak. DB produksi punya duplikat nyata.

**(b) Reproduksi**
`SELECT email_id, COUNT(*) c FROM sysitc_login GROUP BY email_id HAVING c>1`:
- `igrelle.id@gmail.com` → 4
- `aska2707nas@gmail.com` → 4
- `zoom01itc@gmail.com` → 2
- `dhitadhie@gmail.com` → 2
- `febiolarachman03@gmail.com` → 2

`SHOW INDEX FROM sysitc_login` → tidak ada unique index email.

**(c) Tingkat keparahan**
Tinggi (ambigu reset password / abuse).

**(d) Lokasi**
`AuthService.php:222`, `:172-174`.

**(e) Saran perbaikan**
Cek duplikat email di server + tambah unique index.

---

### 7. Race condition: `account_id` tidak unique di DB `run` — Sedang–Tinggi ✅

**(a) Masalah**
Cek duplikat (`:222`) dilakukan di luar transaksi (`:242`). DB `run` hanya punya PRIMARY pada `rec_id`, sedangkan DB `mysql` justru punya unique `account_id`. Double-click/paralel dapat membuat dua akun.

**(b) Reproduksi**
`SHOW INDEX FROM sysitc_login`:
- `run` → hanya `PRIMARY` (`rec_id`)
- `mysql` → `account_id non_unique=0`

**(c) Tingkat keparahan**
Sedang–Tinggi.

**(d) Lokasi**
`AuthService.php:222-226, 242`.

**(e) Saran perbaikan**
Tambah unique index `account_id` di `run` + tangani `QueryException` duplikat (atau `lockForUpdate` dalam transaksi).

---

### 8. XSS lewat `{!! !!}` dan `account_id` dalam pesan error — Sedang 📖

**(a) Masalah**
View menampilkan `error_msg`/`success_msg` tanpa escape. Pesan duplikat menyisipkan `account_id` mentah: `"Maaf, Account ID <b>{$accountId}</b>..."`. `account_id` hanya dicek panjang (boleh HTML).

**(b) Reproduksi**
Daftarkan akun dengan `account_id` berisi `<img src=x onerror=...>` (≥ 6 karakter), lalu request mendaftar dengan ID sama → pesan error merender HTML/JS.

**(c) Tingkat keparahan**
Sedang.

**(d) Lokasi**
`resources/views/auth/register.blade.php:22`, `login.blade.php:42,48`, `verify-otp.blade.php:23`, `reset-password.blade.php:16`, `forgot-password.blade.php:31`; sumber `AuthService.php:225`.

**(e) Saran perbaikan**
Gunakan `{{ }}` (escape), whitelist `account_id` alfanumerik, jangan menyisipkan input ke string HTML.

---

### 9. `dob` wajib di DB tapi opsional di service — Sedang ✅

**(a) Masalah**
`sysitc_users.dob` adalah `date NOT NULL`, tetapi service mengizinkan `dob` kosong (`$dob = null`), sehingga insert gagal dengan error DB (sekaligus bocor, lihat #2).

**(b) Reproduksi**
Kirim request langsung tanpa `dob`. Terkonfirmasi 1 baris existing dengan `dob` kosong.

**(c) Tingkat keparahan**
Sedang.

**(d) Lokasi**
`AuthService.php:192-207`; skema `sysitc_users.dob`.

**(e) Saran perbaikan**
Wajibkan `dob` di server, atau buat kolom nullable dengan default valid.

---

### 10. Field wajib hanya di frontend; `sexmf`/`instansi` tak divalidasi — Sedang ✅/📖

**(a) Masalah**
`sexmf` tidak divalidasi (kolom `varchar(1) NOT NULL` → tersimpan string kosong). `instansi` bebas panjang/isi dan dapat membuat entri `sys_mstclient` liar via `is_individu` palsu. `account_id` tidak di-whitelist.

**(b) Reproduksi**
Kirim POST tanpa `sexmf`, atau `instansi` sangat panjang, atau `is_individu=1` palsu.

**(c) Tingkat keparahan**
Sedang.

**(d) Lokasi**
`AuthService.php:149-219, 247-256`; `register.blade.php:78, 109`.

**(e) Saran perbaikan**
Validasi server: `sexmf in:M,F`, batas panjang semua field, whitelist karakter `account_id`.

---

### 11. Pesan exception mentah bocor ke user — Sedang ✅

**(a) Masalah**
`return ['success' => false, 'message' => $e->getMessage()]` (`:307`) ditampilkan lewat `error_msg` (raw), membocorkan detail query/schema/nama tabel.

**(b) Reproduksi**
Picu error DB saat registrasi (mis. field melebihi lebar kolom).

**(c) Tingkat keparahan**
Sedang.

**(d) Lokasi**
`AuthService.php:306-308`; render `register.blade.php:22`.

**(e) Saran perbaikan**
Kembalikan pesan generik ke user, log detail di server (`report($e)`).

---

### 12. Transaksi lintas-DB tidak atomik — Sedang 📖

**(a) Masalah**
Commit dilakukan berurutan `run` lalu `mysql` (`:284-285`). Jika commit `mysql` gagal, data `run` sudah tersimpan → registrasi parsial (login ada di `run`, profil/login `mysql` tidak).

**(b) Reproduksi**
Simulasi kegagalan DB kedua pada registrasi sister company.

**(c) Tingkat keparahan**
Sedang.

**(d) Lokasi**
`AuthService.php:242-295`.

**(e) Saran perbaikan**
Idempotensi/kompensasi, atau satukan kedua koneksi ke satu transaksi jika memungkinkan.

---

### 13. Forgot-password hanya cari di DB `run` + enumerasi akun — Sedang 📖

**(a) Masalah**
Query hanya pada koneksi `run`, sehingga pengguna DB `mysql` tidak bisa reset. Pesan `"Account ID / Email tidak ditemukan."` berbeda dari sukses → enumerasi akun. Tanpa throttle → email bombing.

**(b) Reproduksi**
Minta reset dengan email valid/tidak valid, bandingkan respons.

**(c) Tingkat keparahan**
Sedang.

**(d) Lokasi**
`AuthService.php:89-105`.

**(e) Saran perbaikan**
Cari di kedua koneksi, samakan pesan (selalu "jika terdaftar, kode dikirim"), tambah throttle.

---

### 14. LIKE wildcard tidak di-escape pada client-search — Rendah 📖

**(a) Masalah**
`like '%'.$query.'%'` (`:342`); karakter `%`/`_` tidak di-escape sehingga user bisa menampilkan semua data klien (bukan SQLi karena memakai binding).

**(b) Reproduksi**
`GET /register/client-search?q=%%`.

**(c) Tingkat keparahan**
Rendah.

**(d) Lokasi**
`AuthService.php:340-347`; `LoginController.php:68-77`.

**(e) Saran perbaikan**
Escape `%` dan `_`, atau gunakan klausa `ESCAPE` dengan binding.

---

### 15. `authenticate()` berhenti awal pada multi-DB — Rendah 📖

**(a) Masalah**
Jika `account_id` ada di `run` (walau password salah), fungsi langsung `return false` dan tidak mencoba `mysql`, sehingga user valid di `mysql` dengan `account_id` sama tidak bisa login.

**(b) Reproduksi**
Buat `account_id` bentrok di kedua DB.

**(c) Tingkat keparahan**
Rendah.

**(d) Lokasi**
`AuthService.php:10-31`.

**(e) Saran perbaikan**
Eksplisitkan strategi lintas-DB (mis. cek status, bukan hanya keberadaan).

---

## Catatan Positif

- CSRF aktif via middleware web; hanya route filing yang dikecualikan (`bootstrap/app.php:27`).
- Query memakai query builder/binding — tidak ditemukan SQL injection.
- Session di-regenerate setelah login (`LoginController.php:298`).
- Tidak ada test otomatis untuk alur auth/registrasi (`tests/`).

---

## Metode Verifikasi

- Eksekusi logika PHP untuk membuktikan #4 (expiry OTP) dan #5 (mismatch instansi).
- Introspeksi **read-only** ke DB produksi: `SHOW COLUMNS`, `SHOW INDEX`, `SELECT` agregat (membuktikan #1, #2, #6, #7, #9, #10).
- Cek konektivitas DB (`Test-NetConnection` → `True`).
- Tidak menjalankan server/registrasi sungguhan untuk menghindari penulisan ke produksi.
- Suite test default memakai SQLite in-memory, tidak cocok dengan 4 koneksi MySQL hardcoded (`run`, `mysql`, `bot`), sehingga alur penuh tidak dapat direproduksi lokal tanpa DB.

## Lampiran: Bukti Skema DB

```
sysitc_login (run)
  account_id   varchar(30)  NOT NULL
  password_id  varchar(32)  NOT NULL
  email_id     varchar(30)  NOT NULL
  account_nm   varchar(30)  NOT NULL
  whatsapp     varchar(15)  NOT NULL
  status       char(1)      NOT NULL
  token        varchar(255) NULL
  token_exp    datetime     NULL
  token_used   datetime     NULL
  INDEX: PRIMARY(rec_id) saja  ← tidak ada unique account_id

sysitc_users
  account_nm  char(30)     NOT NULL
  alias_nm    varchar(10)  NOT NULL
  dob         date         NOT NULL
  sexmf       varchar(1)   NOT NULL
  whatsapp    varchar(15)  NOT NULL

sys_mstclient
  clientnm  varchar(50)  NOT NULL

hrd_employee
  empno        char(11)
  itc_user_id  int(11)

sysitc_login (mysql / main)
  INDEX: account_id non_unique=0  ← unique

Data duplikat email (run):
  igrelle.id@gmail.com        4
  aska2707nas@gmail.com       4
  zoom01itc@gmail.com         2
  dhitadhie@gmail.com         2
  febiolarachman03@gmail.com  2
```
