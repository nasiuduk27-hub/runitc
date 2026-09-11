# Panduan Alur Proses Koperasi RUNITC

Panduan langkah demi langkah (step by step) untuk seluruh menu modul **Koperasi Simpan Pinjam & Refinancing** di RUNITC. Setiap menu dilengkapi tujuan, hak akses, langkah penggunaan, aturan penting, dan **mini-contoh angka**. Bagian akhir berisi **satu kasus end-to-end** dari anggota baru sampai pinjaman lunas.

- Versi dokumen: 1.0
- Cakupan: Super Admin, CU Admin, dan CU Member
- Format periode di sistem: `YYYYMM` (contoh `202601` = Januari 2026)

---

## Daftar Isi

**Pendahuluan**
1. [Konsep & Hak Akses](#1-konsep--hak-akses)
2. [Istilah, Periode, dan Kode Transaksi](#2-istilah-periode-dan-kode-transaksi)
3. [Peta Menu & Alur Besar](#3-peta-menu--alur-besar)

**Bagian A — Persiapan & Master (Admin)**
4. [Pengaturan Koperasi](#a1-pengaturan-koperasi)
5. [Anggota](#a2-anggota)
6. [Simulasi Kredit](#a3-simulasi-kredit)

**Bagian B — Operasional Admin Simpan Pinjam**
7. [Pengajuan Pinjaman](#b1-pengajuan-pinjaman)
8. [Pinjaman](#b2-pinjaman)
9. [Bayar Angsuran & Simpanan](#b3-bayar-angsuran--simpanan)
10. [Simpanan](#b4-simpanan)
11. [Refinancing](#b5-refinancing)
12. [Transaksi Bank & Posting Bulanan](#b6-transaksi-bank--posting-bulanan)
13. [Monthly Processing](#b7-monthly-processing)
14. [Laporan](#b8-laporan)
15. [Audit Log](#b9-audit-log)
16. [Input Manual / Historis](#b10-input-manual--historis)

**Bagian C — Tampilan Anggota (CU Member)**
17. [Dashboard Anggota & Menu Personal](#c-dashboard-anggota--menu-personal)

**Bagian D — Contoh Kasus End-to-End**
18. [Kasus: Budi Santoso dari Daftar sampai Lunas](#d-contoh-kasus-end-to-end)

**Lampiran**
19. [Tabel Status & Transisi](#lampiran-1-tabel-status--transisi)
20. [Rumus Simulasi](#lampiran-2-rumus-simulasi)
21. [Aturan Maker-Checker](#lampiran-3-aturan-maker-checker)
22. [Peta Tabel Database](#lampiran-4-peta-tabel-database)

---

# Pendahuluan

## 1. Konsep & Hak Akses

Modul koperasi adalah **modul internal** di dalam RUNITC. Artinya:

- Pengguna **tidak membuat akun koperasi baru**. Semua memakai akun RUNITC yang sudah ada.
- Akun anggota koperasi ditautkan ke akun RUNITC melalui kolom `icu_member.itc_user_id`.
- Angka member adalah nomor internal koperasi berformat `CU-0001`, `CU-0002`, dst.

### Peran (Role)

| Peran | Cara dikenali | Bisa apa |
|---|---|---|
| **Super Admin** | Group `03/999` dengan deskripsi mengandung `SUPER ADMIN` | Semua menu koperasi |
| **CU Admin** | Group dengan deskripsi mengandung `CU ADMIN` (mis. `03/410`) | Semua menu admin koperasi |
| **CU Member** | Role `01/01` dan akun tertaut ke record anggota | Hanya data miliknya sendiri (simpanan, pinjaman, pengajuan, refinancing) |

> **Admin koperasi** = Super Admin **atau** CU Admin. Hampir semua aksi tulis (posting, approve, input manual, transaksi bank) hanya untuk admin koperasi.

### Prinsip maker-checker

Beberapa proses memisahkan **pembuat (maker)** dan **penyetuju (checker)**:

- Pembuat **tidak boleh** menyetujui/menolak/memposting pengajuannya sendiri.
- Berlaku untuk: Pengajuan Pinjaman, Pembayaran Angsuran, Penarikan Simpanan, dan Refinancing.

---

## 2. Istilah, Periode, dan Kode Transaksi

### Periode

Semua perhitungan bulanan memakai format `YYYYMM`. Penambahan bulan bersifat kalender-benar: `202612 + 1 = 202701` (bukan `202613`).

- Setoran simpanan wajib dan angsuran potong gaji memakai **tanggal 28** pada periode terpilih (autodebit).

### Kode transaksi (`trncd`)

| Kode | Arti | Debit/Kredit |
|---|---|---|
| `19` | Simpanan (setoran / penarikan) | `D` setoran, `C` penarikan |
| `20` | Angsuran pinjaman | `D` pembayaran |
| `21` | Pencairan pinjaman (header pinjaman) | — |

### Prefix nomor transaksi

| Prefix | Dipakai untuk | Contoh |
|---|---|---|
| `LON` | Pinjaman (`icu_mloan`) | `LON-26A-0001` |
| `PMT` | Angsuran (`icu_transaction`) & tagihan HRD (`icu_mtrx2hrd`) | `PMT-26A-0012` |
| `SAV` | Setoran simpanan | `SAV-26A-0007` |
| `WDR` | Penarikan / potong simpanan | `WDR-26A-0003` |
| `RCV` | Penerimaan / mutasi rekening bank koperasi | `RCV-26A-0009` |

Format: `{PREFIX}-{YY}{huruf bulan}-{urut 4 digit}`. Huruf bulan: `A`=Januari s.d. `L`=Desember. Contoh `26A` = Januari 2026.

---

## 3. Peta Menu & Alur Besar

| # | Menu | URL | Akses |
|---|---|---|---|
| 1 | Dashboard | `/cooperative/dashboard` | Admin (rekap semua) / Member (personal) |
| 2 | Anggota | `/cooperative/members` | Lihat: semua. Tambah/sinkron: admin |
| 3 | Simulasi Kredit | `/cooperative/loan-simulation` | Semua |
| 4 | Pengajuan Pinjaman | `/cooperative/applications` | Member: milik sendiri. Approve/posting: admin |
| 5 | Pinjaman | `/cooperative/loans` | Member: milik sendiri |
| 6 | Refinancing | `/cooperative/skips` | Member: milik sendiri. Terapkan: admin |
| 7 | Simpanan | `/cooperative/savings` | Member: personal. Approval: admin |
| 8 | Monthly Processing | `/cooperative/monthly-processing` | Admin |
| 9 | Transaksi Bank | `/cooperative/bank-transactions` | Admin |
| 10 | Bayar Angsuran & Simpanan | `/cooperative/payments` | Admin |
| 11 | Laporan | `/cooperative/reports` | Semua (data rekap seluruh koperasi) |
| 12 | Audit Log | `/cooperative/audit-log` | Admin |
| 13 | Pengaturan | `/cooperative/settings` | Admin |

### Alur besar siklus bulanan

```
[A. Persiapan]  Anggota  →  Pengaturan  →  Simulasi Kredit
                                        │
[B. Pinjaman]   Pengajuan Pinjaman ──approve──> posting ──> Pinjaman + jadwal
                                        │
[C. Rutin/bln]  Bayar Angsuran & Simpanan (checklist)  ATAU  Transaksi Bank → Posting Bulanan
                                        │
[D. Refinancing] Refinancing (Skip / Percepat / Potong Simpanan) ──approve──> jadwal berubah
                                        │
[E. Rekap]      Monthly Processing (tagihan HRD) → Transaksi Bank (penerimaan) → Laporan
                                        │
[F. Jejak]      Audit Log
```

---

# Bagian A — Persiapan & Master

## A1. Pengaturan Koperasi

**URL:** `/cooperative/settings` · **Akses:** Admin

**Tujuan:** Menetapkan nilai default yang dipakai saat pengajuan pinjaman baru dan batas minimum saldo simpanan.

### Langkah

1. Buka menu **Koperasi → Pengaturan**.
2. Isi:
   - **Bunga pinjaman default (%)** — dipakai sebagai default form pengajuan.
   - **Metode perhitungan default** — `Flat`, `Efektif`, atau `Anuitas`.
   - **Biaya admin default** — nominal biaya admin pada pengajuan baru.
   - **Saldo minimum simpanan** — saldo yang wajib mengendap dan tidak bisa ditarik.
3. Klik **Simpan**.
4. Perubahan tercatat di Audit Log (`cooperative.setting_updated`).

### Mini-contoh

Koperasi menetapkan bunga default `6%`, metode `Flat`, biaya admin `Rp 0`, saldo minimum `Rp 50.000`. Saat membuat pengajuan baru, form otomatis terisi 6% / Flat / 0, dan anggota tidak bisa menarik simpanan sampai saldo di atas Rp 50.000.

> **Catatan:** Nilai biaya admin dan saldo minimum diambil sistem dari pengaturan, bukan dari input form, saat pengajuan disimpan.

---

## A2. Anggota

**URL:** `/cooperative/members` · **Akses:** Lihat semua; tambah & sinkron hanya Admin

**Tujuan:** Mengelola data keanggotaan koperasi yang terhubung ke akun RUNITC.

### Status anggota

| Kode | Label |
|---|---|
| 0 | Draft |
| 1 | CU Account |
| 2 | **Regular Member** |
| 3 | Regular Non Payroll |
| 4 | Irregular Member |
| 5 | Outstanding Member |
| 6 | Non-Active |

Anggota baru dari menu ini selalu dibuat sebagai **Regular Member**.

### A2.1 Melihat & mencari anggota

1. Buka menu **Koperasi → Anggota**.
2. Gunakan kolom pencarian (nomor `CU-xxxx`, nama, atau `refno` pegawai) dan filter **status**.
3. Kartu statistik menampilkan Total, Regular, Outstanding, dan Non-Active.
4. Klik baris anggota untuk membuka **detail**: data anggota, daftar pinjaman, ringkasan pinjaman, dan 15 transaksi terakhir.

### A2.2 Menambah anggota baru

1. Buka **Anggota → Tambah Anggota**.
2. Pilih **Akun RUNITC** (hanya akun aktif yang belum tertaut ke anggota lain).
3. Isi **Nama Anggota** (`icunm`), **Alias** (opsional), **Tanggal Gabung**, **Simpanan Wajib** (default `Rp 100.000`), dan **Refno** (opsional).
4. Sistem menampilkan **nomor anggota berikutnya** (contoh `CU-0001`) sebagai pratinjau.
5. Klik **Simpan**. Sistem:
   - membuat record `icu_member` dengan status Regular Member,
   - memberi **role CU Member (01/01)** otomatis,
   - menulis Audit Log `cooperative.member.created`.
6. Jika pemberian role gagal, anggota tetap tersimpan dan muncul pesan untuk assign manual lewat **Admin → User Role**.

### A2.3 Sinkron akun anggota lama (verifikasi OTP)

Dipakai untuk anggota lama yang belum punya `itc_user_id`.

1. Buka **Anggota → detail anggota** yang belum tertaut → tombol **Sinkron**.
2. Pilih **akun RUNITC tujuan**.
3. Klik **Kirim OTP**. Sistem mengirim **kode 5 digit** ke email akun target (berlaku 5 menit).
4. Pemilik akun membuka halaman verifikasi (link `ref_token`) dan memasukkan kode OTP.
5. Jika benar: `icu_member.itc_user_id` diisi, role CU Member dipastikan ada, Audit Log `cooperative.member.synced` ditulis.

### Mini-contoh

HRD mendaftarkan pegawai baru **Budi Santoso** dengan akun RUNITC `budi.s`. Sistem memberi `CU-0001`, simpanan wajib `Rp 100.000`, status Regular Member, dan role CU Member. Budi kini bisa membuka menu koperasi versi personal.

> **Penting:** Tanpa `itc_user_id` (belum sinkron), anggota hanya bisa **dilihat admin**; anggota tidak bisa mengajukan pinjaman atau menarik simpanan.

---

## A3. Simulasi Kredit

**URL:** `/cooperative/loan-simulation` · **Akses:** Semua

**Tujuan:** Menghitung estimasi angsuran sebelum pengajuan resmi dibuat.

### Langkah

1. Buka menu **Koperasi → Simulasi Kredit**.
2. Isi **Jumlah Kredit (Rp)**, **Jangka Waktu (bulan)**, **Bunga per Tahun (%)**, dan **Jenis Kredit** (Flat / Efektif / Anuitas).
3. Klik **Hitung**. Muncul ringkasan (cicilan pertama, cicilan terakhir, total bunga, total pembayaran) dan tabel jadwal.
4. Klik **Export** untuk mengunduh jadwal dalam CSV.

Batas input: pokok `Rp 1 – Rp 10.000.000.000`, tenor `1 – 120` bulan, bunga `0 – 100%`.

### Mini-contoh

Budi ingin pinjam `Rp 12.000.000`, tenor `12` bulan, bunga `6%`, metode **Flat**:

- Bunga per bulan = 12.000.000 × 6% ÷ 12 = **Rp 60.000**
- Pokok per bulan = 12.000.000 ÷ 12 = **Rp 1.000.000**
- Cicilan per bulan = **Rp 1.060.000**
- Total bunga = **Rp 720.000**; Total pembayaran = **Rp 12.720.000**

---

# Bagian B — Operasional Admin Simpan Pinjam

## B1. Pengajuan Pinjaman

**URL:** `/cooperative/applications` · **Akses:** Member (milik sendiri) & Admin

**Tujuan:** Alur resmi pengajuan pinjaman dengan persetujuan berjenjang sebelum menjadi pinjaman aktual.

### Status pengajuan

| Status | Label | Transisi yang sah |
|---|---|---|
| `submitted` | Menunggu Persetujuan | → `approved`, `rejected`, `cancelled` |
| `approved` | Disetujui | → `cancelled` (oleh selain pembuat), atau → `posted` |
| `rejected` | Ditolak | final |
| `cancelled` | Dibatalkan | final |
| `posted` | Diposting ke Pinjaman | final |

### B1.1 Membuat pengajuan

1. Buka **Pengajuan Pinjaman → Buat Pengajuan**.
2. Admin memilih **Anggota**; CU Member otomatis atas dirinya (tidak bisa pilih orang lain).
3. Isi **Jumlah Pinjaman**, **Tenor (bulan)**, **Bunga per Tahun (%)**, **Jenis Kredit**, dan **Keperluan** (maks 100 karakter).
4. Isi **Bank pencairan** (metode pencairan ditetapkan sistem: **Transfer**). Jika bank diisi, data disimpan sebagai bank default user.
5. Form menampilkan **pratinjau angsuran** (bisa dihitung ulang lewat `recalculate`).
6. Klik **Simpan**. Status menjadi `submitted`; Audit Log `cooperative.loan_application.submitted`.

> **Biaya admin** mengikuti Pengaturan Koperasi dan tidak dapat diubah dari form.

### B1.2 Menyetujui / menolak / membatalkan

1. Buka detail pengajuan.
2. **Approve / Reject** hanya untuk **admin**, dan admin yang membuat pengajuan **tidak boleh** memutuskan pengajuannya sendiri.
3. **Cancel**: sebelum disetujui hanya **pembuat**; setelah disetujui hanya **selain pembuat**.
4. Isi catatan (opsional) lalu submit. Audit Log mencatat perubahan status.

### B1.3 Posting menjadi pinjaman

1. Pada pengajuan berstatus **Disetujui**, admin (selain pembuat) klik **Posting**.
2. Sistem menulis dalam satu transaksi database:
   - `icu_mloan` (header pinjaman) dengan nomor `LON-...`,
   - `icu_dloan` (jadwal angsuran) sebanyak tenor,
   - menambah `icu_member.outstanding` sebesar pokok efektif.
3. Status menjadi `posted`, tersimpan `posted_loan_rec_id`. Pengajuan terkunci.

Aturan penulisan jadwal mengikuti data lama:
- `interamt` = pokok × bunga/12 × tenor (metode flat),
- `avgmon` = pembulatan pokok/tenor; cicilan terakhir menyesuaikan sisa (keterangan `Rounding`),
- `outstand` menurun sebesar pokok dan tepat `0` di akhir,
- `dseqno` = urutan mundur, `paid` = 0, `statrec` = 0.

### Mini-contoh

Budi (CU-0001) mengajukan Rp 12.000.000 / 12 bulan / Flat 6%. Setelah disetujui dan diposting:

- `icu_mloan`: `principle` 12.000.000, `interamt` 720.000, `totalloan` 12.720.000, `term` 12, `startper` 202601, `endper` 202612.
- `icu_dloan`: 12 baris @ pokok 1.000.000 + bunga 60.000.
- `icu_member.outstanding` Budi bertambah 12.000.000.

---

## B2. Pinjaman

**URL:** `/cooperative/loans` · **Akses:** Member (milik sendiri) & Admin

**Tujuan:** Melihat daftar pinjaman aktual beserta jadwal angsuran.

### Langkah

1. Buka menu **Koperasi → Pinjaman**.
2. Filter dengan pencarian (nomor pinjaman/nama) dan **status**: Berjalan / Lunas.
3. Admin bisa memfilter per anggota; member otomatis hanya melihat miliknya.
4. Klik **Detail** untuk melihat jadwal angsuran per baris: periode, pokok, bunga, lainnya, sisa pokok, dan status bayar.

> **Status lunas bersifat indikatif**: dihitung dari `paid >= totalloan`. Sisa pokok indikatif = `principle − paid`.

### Mini-contoh

Pinjaman `LON-26A-0001` Budi tampil **Berjalan** dengan progres `paid 3.180.000 / totalloan 12.720.000 ≈ 25%`, sisa pokok indikatif `Rp 9.000.000`.

---

## B3. Bayar Angsuran & Simpanan

**URL:** `/cooperative/payments` · **Akses:** Admin

**Tujuan:** Layar ceklis batch bulanan untuk memposting **angsuran jatuh tempo** dan **simpanan wajib** sekaligus.

### Langkah

1. Buka menu **Bayar Angsuran & Simpanan**.
2. Pilih **Periode** (`YYYYMM`) dan cari anggota bila perlu.
3. Layar menampilkan daftar anggota berisi:
   - baris angsuran jatuh tempo (angsuran ke `n/total`, sisa tagihan),
   - simpanan wajib (nominal + penanda sudah/belum diposting).
4. Centang angsuran dan/atau simpanan yang ingin dibukukan.
5. Klik **Posting**. Sistem memproses tiap baris:
   - membuat record pembayaran (`coop_loan_payments`) + alokasi (`coop_loan_payment_allocations`),
   - menulis `icu_transaction` (`trncd` 20/19) dan memperbarui `icu_dloan`, `icu_mloan`, `icu_member`,
   - tanggal pembayaran = **tanggal 28** periode terpilih.
6. Muncul ringkasan: jumlah angsuran & simpanan yang berhasil diposting, plus baris yang dilewati.

### Aturan alokasi pembayaran

- Nominal dibebankan ke baris tertunggak **berurutan dari yang tertua**.
- Dalam satu baris, urutan komponen: **bunga → biaya lain → pokok**.
- Baris ditandai lunas (`paidst=1`) hanya bila alokasi menutup tagihan penuh; sisanya tercatat sebagai alokasi parsial.
- `icu_mloan.statrec`: `4` (Partial Payment) bila masih ada tunggakan, `5` (Loan Completed) bila lunas.
- `icu_member.outstanding` berkurang sebesar porsi pokok.

### Mini-contoh

Periode 202601, Budi centang angsuran ke-1 (Rp 1.060.000):

- Alokasi: bunga 60.000 → biaya lain 0 → pokok 1.000.000.
- `icu_transaction`: `PMT-26A-...`, `basic_amt` 1.000.000, `int_amt` 60.000, `amount` 1.060.000.
- `icu_dloan` baris 1 → `paidst=1`; `icu_mloan.paid` = 1.060.000; `statrec` = 4.
- `icu_member.outstanding` Budi turun 1.000.000.

> **Idempoten:** baris/anggota yang sudah dibukukan dilewati, sehingga tidak terjadi tagihan ganda.

---

## B4. Simpanan

**URL:** `/cooperative/savings` · **Akses:** Member (personal) & Admin

**Tujuan:** Melihat saldo simpanan, mengubah nominal simpanan wajib, dan mengajukan/menyetujui penarikan.

### B4.1 Melihat saldo & riwayat

1. Buka menu **Koperasi → Simpanan**.
2. Kartu saldo menampilkan total setoran (debit), penarikan (kredit), dan saldo berjalan.
3. **Saldo tersedia** = saldo − penarikan menunggu − saldo minimum mengendap.
4. Tab **Riwayat** menampilkan transaksi dan daftar penarikan.

### B4.2 Mengubah nominal simpanan wajib

1. Pada halaman Simpanan, ubah **Simpanan Wajib Bulanan**.
2. Klik **Simpan**. Perubahan berlaku untuk setoran yang **belum diposting**.
3. Audit Log `cooperative.member.savings_updated`.

### B4.3 Mengajukan penarikan simpanan

1. Klik **Tarik Simpanan**.
2. Isi **Nominal** dan **rekening tujuan** (bank, nama, nomor). Nominal tidak boleh melebihi saldo tersedia.
3. Submit. Status penarikan `submitted` (Menunggu Persetujuan).

### B4.4 Menyetujui penarikan (admin)

1. Admin membuka daftar penarikan, pilih pengajuan.
2. **Approve** (posting transaksi `WDR`, `dbocr = C`) / **Reject** / **Cancel** (hanya pengaju).
3. Pembuat tidak boleh menyetujui penarikannya sendiri.
4. Saat approve, saldo dicek ulang terhadap saldo minimum mengendap.

### Mini-contoh

Budi punya saldo Rp 300.000, saldo minimum Rp 50.000 → tersedia Rp 250.000. Budi mengajukan tarik Rp 200.000. Admin lain menyetujui → transaksi `WDR-26A-...` (`C`, Rp 200.000), saldo menjadi Rp 100.000.

---

## B5. Refinancing

**URL:** `/cooperative/skips` · **Akses:** Member (milik sendiri) & Admin (terapkan)

**Tujuan:** Menyesuaikan jadwal pinjaman berjalan. Ada **3 mode**.

| Mode | Efek | Tenor |
|---|---|---|
| **Skip Pokok** (`skip`) | Pokok pada rentang bulan di-nol-kan (bunga tetap), pokok dipindah ke baris baru di ekor | **+N** bulan |
| **Percepat** (`accelerate`) | N baris terakhir dihapus, pokok+bunga seluruh baris belum dibayar dihitung ulang ke baris lebih sedikit | **−N** bulan |
| **Potong Simpanan** (`savings`) | Saldo simpanan dipakai mengurangi pokok tiap angsuran normal | tetap |

### Status refinancing

| Status | Label | Transisi |
|---|---|---|
| `submitted` | Menunggu Persetujuan | → `applied`, `rejected`, `cancelled` |
| `applied` | Diterapkan ke Jadwal | final |
| `rejected` | Ditolak | final |
| `cancelled` | Dibatalkan | final |

### B5.1 Membuat pengajuan refinancing

1. Buka **Refinancing → Buat Pengajuan**.
2. Pilih **Pinjaman** (status berjalan).
3. Pilih **Mode**: Skip Pokok / Percepat / Potong Simpanan.
4. Isi parameter sesuai mode:
   - **Skip Pokok**: **Mulai Skip** (periode) + **Lama Skip** (1–12 bulan),
   - **Percepat**: **Lama Percepatan** (1–12 bulan),
   - **Potong Simpanan**: **Nominal Simpanan** (tidak boleh melebihi saldo tersedia).
5. Layar menampilkan **pratinjau jadwal sebelum & sesudah**.
6. Isi alasan (opsional), klik **Simpan**. Status `submitted`.

### B5.2 Menyetujui / menerapkan (admin)

1. Admin membuka detail pengajuan refinancing.
2. **Terapkan** (`apply`) hanya oleh **selain pengaju**; **Reject**/**Cancel** sesuai aturan.
3. Saat diterapkan, jadwal `icu_dloan` berubah dan `icu_mloan.term`/`endper` disesuaikan.

### Aturan bisnis penting

- **Skip Pokok**: baris target `amount=0` tetapi **bunga flat tetap** dibayar. Pokok tertunda dipindah ke N baris baru; tenor bertambah tepat N bulan. Bunga bulan tambahan = **Biaya perpanjang pinjaman** dan **tidak** menambah `interamt`/`totalloan` (hanya tampak di detail `icu_dloan`).
- **Percepat**: total pokok & bunga baris belum dibayar dipertahankan, dibagi ke lebih sedikit baris; baris skip (pokok 0) dilewati dan dipertahankan.
- **Potong Simpanan**: potongan dibagi rata ke angsuran normal belum dibayar (tanpa nilai negatif); saldo simpanan dipotong lewat posting `WDR`.
- Baris yang punya **alokasi pembayaran aktif** tidak boleh dijadikan target refinancing.

### Mini-contoh (Skip Pokok)

Budi baru bayar 3 dari 12 angsuran. Ia mengajukan **Skip Pokok** mulai `202604` selama **3 bulan**:

- 3 baris (202604–202606) pokoknya jadi 0, bunga tetap 60.000.
- Pokok tertunda = 3 × 1.000.000 = **Rp 3.000.000** → dipindah ke 3 baris baru `202701`, `202702`, `202703` (@ Rp 1.000.000).
- Tenor 12 → **15 bulan**; `endper` 202612 → 202703.
- Biaya perpanjang = 3 × 60.000 = **Rp 180.000** (tidak masuk `totalloan`).

---

## B6. Transaksi Bank & Posting Bulanan

**URL:** `/cooperative/bank-transactions` · **Akses:** Admin

**Tujuan:** Buku rekening koperasi (`icu_bank_trx`). Mencatat penerimaan potong gaji dan mutasi di luar simpan-pinjam (pencairan, biaya bank, koreksi, transfer antar rekening), serta menjalankan **Posting Bulanan** otomatis.

### B6.1 Melihat buku rekening

1. Buka menu **Transaksi Bank**.
2. Filter berdasarkan pencarian (`trnno`, `req_frm_trxno`, keterangan), arah (`D`/`C`), dan periode.
3. Halaman menampilkan total debit & kredit sesuai filter.

### B6.2 Menambah transaksi bank

1. Klik **Tambah Transaksi**.
2. Isi **Nomor Referensi HRD** (opsional; bila diisi harus ada di tagihan `icu_mtrx2hrd`), **Nominal**, **Arah** (Debit/Kredit), **Nomor Transaksi** (`RCV-...`), **Tanggal**, **Keterangan**, **Catatan**.
3. Klik **Simpan**. Nomor transaksi tidak boleh duplikat.

### B6.3 Edit / hapus

- **Edit** mengubah nominal, arah, tanggal, keterangan, catatan.
- **Hapus** menghapus baris dan menulis Audit Log `cooperative.bank_transaction.deleted`.

### B6.4 Posting Bulanan otomatis

1. Klik **Posting Bulanan**, pilih **Periode**.
2. Sistem memposting **seluruh simpanan wajib yang belum disetor** + **seluruh angsuran jatuh tempo** (`paidst=0`) pada periode itu secara otomatis dan idempoten.
3. Muncul ringkasan jumlah simpanan & angsuran yang diposting beserta totalnya.

### Mini-contoh

Koperasi menerima potong gaji Januari 2026 sebesar Rp 5.000.000 dari HRD. Admin mencatat **Transaksi Bank** `RCV-26A-0009` (Debit, referensi nomor tagihan HRD). Setelah itu menjalankan **Posting Bulanan** periode `202601` untuk membukukan simpanan & angsuran seluruh anggota.

---

## B7. Monthly Processing

**URL:** `/cooperative/monthly-processing` · **Akses:** Admin

**Tujuan:** Membuat rekap tagihan bulanan untuk HRD (simpanan + angsuran), menyimpan tagihan ke `icu_mtrx2hrd`, dan merekonsiliasi penerimaan.

### Langkah

1. Buka menu **Monthly Processing**.
2. Pilih **Periode** dan **Perusahaan** (`cmpcd`).
3. Klik **Generate** untuk menampilkan rekap: per anggota → simpanan, angsuran, biaya, total; baris yang sudah dibukukan ditandai agar tidak ditagih dua kali.
4. **Simpan** untuk menyimpan total tagihan ke `icu_mtrx2hrd` (nomor `PMT-...`).
5. **Export** untuk mengunduh rekap Excel.

### Rekonsiliasi

Panel rekonsiliasi menampilkan: **Tagihan** (`icu_mtrx2hrd`), **Diterima** (`icu_bank_trx` debit yang mereferensikan tagihan), **Belum Diterima**, dan **Detail Posted** (simpanan/angsuran yang sudah dibukukan).

> Selisih tidak harus nol: `icu_bank_trx` juga memuat mutasi di luar simpan-pinjam (biaya bank, koreksi, transfer). Yang penting tiap selisih bisa dijelaskan.

### Mini-contoh

Periode 202601: tagihan HRD Rp 8.500.000; diterima Rp 8.300.000; belum diterima Rp 200.000; detail posted Rp 8.500.000. Selisih Rp 200.000 berasal dari biaya bank yang tercatat di rekening — dapat dijelaskan.

---

## B8. Laporan

**URL:** `/cooperative/reports` · **Akses:** Semua (data rekap seluruh koperasi)

**Tujuan:** Laporan simpanan, jatuh tempo, dan rekap pinjaman, dengan export CSV.

### Tab laporan

| Tab | Isi | Parameter |
|---|---|---|
| **Simpanan** | Setoran & penarikan per anggota | Rentang periode `from`–`to`, pencarian |
| **Jatuh Tempo** | Angsuran belum dibayar s/d periode tertentu | `as_of`, pencarian |
| **Pinjaman** | Rekap pinjaman, terbayar, sisa indikatif, status LN | Pencarian, status |

### Langkah

1. Buka menu **Laporan**.
2. Pilih tab.
3. Atur filter periode/pencarian.
4. Klik **Export** untuk mengunduh CSV (berisi BOM UTF-8 agar rapi di Excel).

### Mini-contoh

Tab **Jatuh Tempo**, `as_of` `202603`: muncul angsuran ke-4 Budi periode 202604 sebagai **Belum Jatuh Tempo**; angsuran ke-1 s.d. ke-3 sudah lunas sehingga tidak muncul.

---

## B9. Audit Log

**URL:** `/cooperative/audit-log` · **Akses:** Admin

**Tujuan:** Melacak seluruh aktivitas modul koperasi.

### Langkah

1. Buka menu **Audit Log**.
2. Filter berdasarkan **Aksi**, **Pencarian** (pelaku, target, isi metadata), dan **rentang tanggal**.
3. Tabel menampilkan waktu, pelaku, aksi, target, dan metadata.

Contoh aksi yang tercatat: `cooperative.member.created`, `cooperative.member.synced`, `cooperative.loan_application.submitted/approved/posted`, `cooperative.savings_withdrawal.approved`, `cooperative.loan_skip.applied`, `cooperative.bank_transaction.deleted`, `cooperative.setting_updated`.

### Mini-contoh

Cari `cooperative.loan_application.posted` pada Januari 2026 → muncul entri pelaku admin, target `coop_loan_application`, metadata `loan_rec_id` hasil posting Budi.

---

## B10. Input Manual / Historis

**Akses:** Admin (tanpa menu sidebar; diakses lewat URL)

**Tujuan:** Memasukkan data **historis** (pinjaman/simpanan lama) ke sistem, terpisah dari alur pengajuan normal.

### B10.1 Pinjaman manual

**URL:** `/cooperative/manual-loans/create`

1. Pilih **Anggota** (atau isi nama untuk anggota historis baru) dan **Tanggal**.
2. Isi **Pokok**, **Tenor**, **Bunga**, **Metode**, **Status pembayaran** (`running`/`paid`), **Biaya admin**.
3. Pratinjau jadwal; klik **Simpan** untuk menulis `icu_mloan`+`icu_dloan` + `coop_manual_loan_sources`.
4. Ada juga **Import Excel** (unduh **template** dulu) dan **Penyesuaian manual** (skip/percepat yang langsung diterapkan, tanpa approval).

### B10.2 Simpanan & penarikan manual

**URL:** `/cooperative/manual-savings/create` · `/cooperative/manual-withdraw/create`

- **Simpanan manual**: boleh lebih dari satu transaksi per anggota per periode, langsung tercatat `icu_transaction` (`trncd` 19, `D`) + `coop_manual_savings`, **tanpa approval**.
- **Penarikan manual**: kredit (`C`) + `coop_manual_withdrawals`, langsung berstatus selesai.

### Mini-contoh

Memasukkan pinjaman lama milik anggota `Siti` (Rp 6.000.000, 12 bulan, 6%, sudah berjalan) tanpa pengajuan. Sistem membuat `LON-...`, jadwal, dan menandai baris yang sudah dibayar `paidst=1` (`payno` `HIST-...`).

> **Peringatan:** Jalur manual menulis langsung ke data produksi tanpa maker-checker. Gunakan hanya untuk migrasi data historis dan pastikan datanya benar.

---

# Bagian C — Dashboard Anggota & Menu Personal

**Akses:** CU Member (akun tertaut) & User Credit Union

Saat login sebagai anggota, menu koperasi menampilkan **data milik sendiri**. Jika akun belum tersinkron ke record anggota, halaman menampilkan pesan: *"Data anggota Anda belum tersinkron ke sistem koperasi. Hubungi admin koperasi."*

### C.1 Dashboard Anggota

- Ringkasan saldo simpanan (debit, kredit, jumlah transaksi),
- Grafik setoran 12 periode terakhir,
- Kartu pinjaman + progres pembayaran,
- Tagihan/angsuran periode berjalan,
- 10 transaksi terakhir.

### C.2 Simpanan Saya

- Lihat saldo & saldo tersedia, ubah **simpanan wajib bulanan**, dan **ajukan penarikan**.
- Riwayat setoran & penarikan pribadi.

### C.3 Pengajuan Pinjaman Saya

- **Buat Pengajuan** (anggota otomatis terpilih atas dirinya).
- Melihat status pengajuan sendiri (`submitted`, `approved`, dst).
- **Batalkan** pengajuan sendiri selama masih `submitted`.

### C.4 Pinjaman Saya

- Daftar pinjaman milik sendiri + detail jadwal angsuran & progres.

### C.5 Refinancing Saya

- Mengajukan Skip / Percepat / Potong Simpanan atas pinjaman sendiri.
- Melihat status; penerapan tetap oleh admin.

### C.6 Transaksi Saya

- Riwayat transaksi simpanan & angsuran pribadi (`/cooperative/transactions/my`).

### Mini-contoh

Budi login, membuka **Simpanan Saya**: saldo Rp 300.000, tersedia Rp 250.000. Ia mengajukan tarik Rp 200.000 → status "Menunggu Persetujuan admin".

---

# Bagian D — Contoh Kasus End-to-End

## Kasus: Budi Santoso dari Daftar sampai Lunas

**Tokoh:** Budi Santoso, pegawai baru, akun RUNITC `budi.s`.
**Tujuan:** Mendaftar sebagai anggota, menabung, meminjam, mengangsur, refinancing, lalu lunas.

### Fase 1 — Pendaftaran anggota (menu Anggota)

1. Admin membuka **Anggota → Tambah Anggota**.
2. Pilih akun `budi.s`, nama **Budi Santoso**, tanggal gabung **01-01-2026**, simpanan wajib **Rp 100.000**.
3. Sistem memberi nomor **CU-0001**, status **Regular Member**, role **CU Member**.
4. Hasil: `icu_member.rec_id = 1`, `swajib = 100.000`, `outstanding = 0`.

### Fase 2 — Simpanan bulanan (menu Simpanan / Bayar Angsuran)

1. Januari–Maret 2026, simpanan wajib Budi diposting lewat **Posting Bulanan** atau ceklis **Bayar Angsuran & Simpanan**.
2. Setiap bulan: transaksi `SAV-...` (`trncd` 19, `D`, Rp 100.000), tanggal 28.
3. Saldo simpanan per akhir Maret = **Rp 300.000**.

### Fase 3 — Pengajuan & posting pinjaman (menu Pengajuan Pinjaman)

1. Budi (atau admin) membuat pengajuan: pokok **Rp 12.000.000**, tenor **12 bulan**, bunga **6%**, metode **Flat**, keperluan "Renovasi rumah".
2. Hasil simulasi: cicilan **Rp 1.060.000/bulan**, total bunga **Rp 720.000**, total pembayaran **Rp 12.720.000**.
3. Admin (selain pembuat) **Approve**.
4. Admin **Posting** → terbentuk pinjaman `LON-26A-0001` + 12 baris jadwal `202601`–`202612`.
5. `icu_member.outstanding` Budi = **Rp 12.000.000**.

### Fase 4 — Bayar 3 angsuran (menu Bayar Angsuran & Simpanan)

Posting periode `202601`, `202602`, `202603` (masing-masing Rp 1.060.000):

| Setelah | `icu_mloan.paid` | `icu_member.outstanding` | statrec |
|---|---|---|---|
| Angsuran 1 | 1.060.000 | 11.000.000 | 4 |
| Angsuran 2 | 2.120.000 | 10.000.000 | 4 |
| Angsuran 3 | 3.180.000 | 9.000.000 | 4 |

### Fase 5 — Refinancing: Skip Pokok 3 bulan (menu Refinancing)

Budi mengajukan **Skip Pokok** mulai `202604` selama **3 bulan**; admin menerapkan.

- Baris `202604`, `202605`, `202606` → pokok `0`, bunga tetap `60.000`.
- Pokok tertunda **Rp 3.000.000** pindah ke baris baru `202701`, `202702`, `202703` (@ Rp 1.000.000).
- Tenor **12 → 15**; `endper` **202612 → 202703**; biaya perpanjang **Rp 180.000**.

### Fase 6 — Alternatif: Percepat atau Potong Simpanan

- **Percepat 2 bulan**: 2 baris terakhir (202702, 202703) dihapus, pokok+bunga baris belum dibayar dihitung ulang ke lebih sedikit baris; tenor **15 → 13**.
- **Potong Simpanan Rp 600.000**: potongan dibagi rata ke 9 baris angsuran normal belum dibayar; pokok tiap baris turun ± Rp 66.667; saldo simpanan dipotong lewat `WDR`; tenor tetap.

### Fase 7 — Pelunasan

1. Semua baris `icu_dloan` Budi dibayar (`paidst=1`).
2. `icu_mloan.statrec` menjadi **5 (Loan Completed)**, `paid` = `totalloan`.
3. `icu_member.outstanding` = **0**.
4. Pinjaman tampil **Lunas (indikatif)** di menu Pinjaman & Laporan.

### Fase 8 — Rekap & jejak (Monthly Processing, Transaksi Bank, Laporan, Audit Log)

1. **Monthly Processing** merekap tagihan Budi per periode untuk HRD.
2. **Transaksi Bank** mencatat penerimaan potong gaji (`RCV-...`) dan mencocokkannya dengan tagihan.
3. **Laporan** menampilkan rekap simpanan, jatuh tempo, dan pinjaman Budi.
4. **Audit Log** memuat seluruh jejak: `member.created`, `loan_application.submitted/approved/posted`, `loan_skip.applied`, dst.

---

# Lampiran

## Lampiran 1. Tabel Status & Transisi

### Pengajuan Pinjaman

| Dari | Boleh ke |
|---|---|
| `submitted` | `approved`, `rejected`, `cancelled` |
| `approved` | `cancelled` (selain pembuat), `posted` |
| `rejected` / `cancelled` / `posted` | final |

### Pembayaran Angsuran

| Dari | Boleh ke |
|---|---|
| `submitted` | `verified`, `rejected`, `cancelled` |
| `verified` / `rejected` / `cancelled` | final |

### Penarikan Simpanan

| Dari | Boleh ke |
|---|---|
| `submitted` | `approved`, `rejected`, `cancelled` |
| lainnya | final |

### Refinancing

| Dari | Boleh ke |
|---|---|
| `submitted` | `applied`, `rejected`, `cancelled` |
| lainnya | final |

---

## Lampiran 2. Rumus Simulasi

Diberikan pokok `P`, tenor `n` bulan, bunga per tahun `r%`.

- **Bunga bulanan dasar** = `P × r / 100 / 12`.
- **Flat**: bunga bulanan konstan dari pokok awal; pokok = `round(P/n)` dengan cicilan terakhir menyesuaikan sisa.
- **Efektif**: bunga dihitung dari **sisa pokok** berjalan tiap bulan.
- **Anuitas**: cicilan tetap = `P × i / (1 − (1+i)^(−n))` dengan `i = r/100/12`.

### Contoh perbandingan (P = 12.000.000, n = 12, r = 6%)

| Metode | Cicilan pertama | Catatan |
|---|---|---|
| Flat | Rp 1.060.000 | bunga tetap Rp 60.000/bln |
| Efektif | ± Rp 1.060.000 (menurun) | bunga menurun mengikuti sisa pokok |
| Anuitas | ± Rp 1.032.800 (tetap) | total bunga lebih kecil dari flat |

> Angka efektif/anuitas di atas pembulatan; hasil pasti mengikuti halaman Simulasi Kredit.

---

## Lampiran 3. Aturan Maker-Checker

| Proses | Pembuat tidak boleh | Pihak yang berhak |
|---|---|---|
| Pengajuan Pinjaman | Approve/Reject/Posting sendiri | Admin selain pembuat |
| Pembayaran Angsuran | Verifikasi sendiri | Admin selain pembuat |
| Penarikan Simpanan | Approve sendiri | Admin selain pembuat |
| Refinancing | Menerapkan sendiri | Admin selain pembuat |

Pembatalan: sebelum disetujui hanya **pembuat**; setelah disetujui hanya **selain pembuat** (khusus pengajuan pinjaman).

---

## Lampiran 4. Peta Tabel Database

### Tabel legacy (`mysql` / `itc_itconenew`) — prefix `icu`

| Tabel | Isi |
|---|---|
| `icu_member` | Data anggota (`icuno`, `icunm`, `swajib`, `outstanding`, `itc_user_id`) |
| `icu_mloan` | Header pinjaman |
| `icu_dloan` | Jadwal angsuran |
| `icu_transaction` | Transaksi simpanan (`19`) & angsuran (`20`) |
| `icu_bank_trx` | Buku rekening bank koperasi |
| `icu_mtrx2hrd` | Tagihan bulanan untuk HRD |
| `sys_msttable` | Master (bank `tbl_code 51`, perusahaan `54`, status `02`) |

### Tabel RUNITC (`run`)

| Tabel | Isi |
|---|---|
| `coop_loan_applications` | Pengajuan pinjaman |
| `coop_loan_payments` + `coop_loan_payment_allocations` | Pembayaran & alokasi |
| `coop_savings`, `coop_savings_withdrawals` | Simpanan wajib & penarikan |
| `coop_loan_skips` | Pengajuan refinancing |
| `coop_manual_loan_sources`, `coop_manual_savings`, `coop_manual_withdrawals` | Data input manual |
| `coop_sync_request` | Permintaan sinkron OTP |
| `system_settings` | Pengaturan koperasi |
| `sys_audit_log` | Jejak audit |
| `sys_menus` | Definisi menu sidebar |

---

*Dokumen ini disusun dari implementasi modul koperasi RUNITC. Alur dan angka mengikuti layanan pada `app/Services/Cooperative`, `app/Http/Controllers/Cooperative`, dan `routes/cooperative.php`.*
