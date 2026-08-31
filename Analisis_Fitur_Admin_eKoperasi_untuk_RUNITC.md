# Analisis Fitur Admin eKoperasi sebagai Referensi Modul Koperasi RUNITC

## Informasi Dokumen

- **Sumber referensi:** Rekaman demo Admin eKoperasi
- **Sistem tujuan:** RUNITC
- **Jenis aplikasi:** Koperasi internal perusahaan
- **Teknologi utama RUNITC:** Laravel 12 (Blade + Tailwind) dan MySQL/MariaDB
- **Tanggal analisis:** 5 Agustus 2026

---

## 1. Ringkasan Eksekutif

Berdasarkan rekaman fitur Admin eKoperasi, sistem tersebut tidak hanya menangani simpanan dan pinjaman, tetapi menggabungkan beberapa fungsi utama koperasi dalam satu aplikasi, yaitu:

1. Pengelolaan anggota.
2. Pencatatan simpanan.
3. Pengajuan dan pencairan pinjaman.
4. Pembayaran angsuran.
5. Pencatatan kas masuk dan keluar.
6. Transfer antar kas atau rekening.
7. Jurnal akuntansi.
8. Buku besar.
9. Laporan laba rugi dan neraca.
10. Perhitungan Sisa Hasil Usaha atau SHU.
11. Pengelolaan master data.
12. Pengaturan akun dan identitas koperasi.

Struktur tersebut dapat dijadikan referensi untuk membuat modul koperasi internal di RUNITC. Namun, RUNITC sebaiknya tidak menyalin aplikasi eKoperasi secara identik. Sistem perlu disesuaikan dengan kondisi internal PT International Test Center, akun pengguna RUNITC, struktur organisasi, approval, keamanan transaksi, dan kemungkinan integrasi payroll.

---

# 2. Konsep Modul Koperasi RUNITC

Modul koperasi sebaiknya menjadi salah satu modul internal di dalam RUNITC.

```text
RUNITC
├── Modul Operasional
├── Modul CBT
├── Filing System
├── Modul Administrasi
└── Modul Koperasi
```

Pengguna tidak perlu membuat akun koperasi baru. Sistem menggunakan akun, profil, divisi, dan role access yang sudah tersedia di RUNITC.

Konsep utamanya:

```text
User RUNITC
    ↓
Keanggotaan Koperasi
    ↓
Simpanan dan Pinjaman
    ↓
Kas dan Akuntansi
    ↓
Laporan dan SHU
```

---

# 3. Peran Pengguna

## 3.1 Super Admin RUNITC

Super Admin bertanggung jawab terhadap konfigurasi teknis dan hak akses.

Fitur yang dapat diakses:

- Mengaktifkan atau menonaktifkan modul koperasi.
- Menentukan Admin Koperasi.
- Mengatur role dan permission.
- Melihat audit log.
- Mengatur integrasi dengan modul lain.
- Melakukan konfigurasi teknis sistem.

Super Admin tidak harus menangani transaksi koperasi sehari-hari.

---

## 3.2 Admin Koperasi

Admin Koperasi bertanggung jawab terhadap administrasi dan operasional koperasi.

Fitur yang dapat diakses:

- Dashboard koperasi.
- Data anggota.
- Simpanan.
- Pinjaman.
- Angsuran.
- Kas.
- Laporan.
- Pengaturan produk.
- Perhitungan SHU.
- Approval transaksi sesuai kewenangan.

---

## 3.3 Operator atau Bendahara

Operator melakukan pencatatan transaksi sehari-hari.

Fitur yang dapat dilakukan:

- Mencatat setoran simpanan.
- Mencatat pembayaran angsuran.
- Memproses pencairan yang sudah disetujui.
- Memasukkan kas masuk.
- Memasukkan kas keluar.
- Mencetak bukti transaksi.
- Mengimpor transaksi.

Batasan yang disarankan:

- Tidak dapat menyetujui transaksi yang dibuat sendiri.
- Tidak dapat mengubah konfigurasi bunga.
- Tidak dapat menghapus transaksi yang telah diposting.
- Tidak dapat menutup periode pembukuan tanpa kewenangan.

---

## 3.4 Anggota

Anggota adalah pegawai atau user RUNITC yang telah terdaftar sebagai anggota koperasi.

Fitur anggota:

- Melihat saldo simpanan.
- Melihat riwayat transaksi.
- Mengajukan pinjaman.
- Melihat status pengajuan.
- Melihat sisa pinjaman.
- Melihat jadwal angsuran.
- Mengajukan penarikan simpanan.
- Mengunduh bukti transaksi.
- Melihat hasil atau estimasi SHU.
- Memperbarui rekening dengan proses verifikasi.

---

# 4. Analisis Fitur Admin eKoperasi

## 4.1 Beranda atau Dashboard

Dashboard eKoperasi menampilkan ringkasan kondisi koperasi.

Informasi yang terlihat antara lain:

- Total anggota.
- Total peminjam.
- Total pengguna.
- Total transaksi pinjaman.
- Total tagihan.
- Sisa tagihan.
- Komposisi simpanan.
- Total simpanan.
- Total penarikan.
- Posisi kas saat ini.

### Rekomendasi untuk RUNITC

Dashboard RUNITC sebaiknya menampilkan:

- Jumlah anggota aktif.
- Jumlah anggota nonaktif.
- Total saldo simpanan.
- Total pinjaman aktif.
- Total sisa pokok pinjaman.
- Total kas dan bank.
- Pengajuan pinjaman menunggu persetujuan.
- Pengajuan penarikan menunggu persetujuan.
- Angsuran jatuh tempo bulan ini.
- Total tunggakan.
- Transaksi terbaru.
- Grafik simpanan per bulan.
- Grafik pencairan pinjaman.
- Grafik pembayaran angsuran.

---

# 5. Transaksi Kas

Menu transaksi kas pada demo terdiri atas:

1. Pemasukan.
2. Pengeluaran.
3. Transfer kas.

---

## 5.1 Pemasukan

Menu pemasukan digunakan untuk mencatat kas masuk selain setoran simpanan dan pembayaran pinjaman.

Fitur yang terlihat:

- Pilihan akun.
- Pilihan kas.
- Tanggal transaksi.
- Tambah pemasukan.
- Import transaksi.
- Cetak PDF.
- Ekspor Excel.

Contoh pemasukan:

- Pendapatan administrasi.
- Pendapatan bunga.
- Pendapatan denda.
- Pendapatan jasa.
- Pendapatan lain-lain.

---

## 5.2 Pengeluaran

Menu pengeluaran digunakan untuk mencatat beban atau kas keluar koperasi.

Contoh pengeluaran:

- Biaya administrasi.
- Biaya operasional.
- Biaya kegiatan koperasi.
- Biaya perlengkapan.
- Biaya pegawai koperasi.
- Biaya bank.
- Dana sosial.
- Pengeluaran lain-lain.

Fitur yang terlihat:

- Filter akun.
- Filter kas.
- Filter periode.
- Tambah pengeluaran.
- Import transaksi.
- Cetak PDF.
- Ekspor Excel.

---

## 5.3 Transfer Kas

Transfer kas digunakan untuk memindahkan dana antar rekening koperasi.

Contoh:

```text
Kas Tunai → Bank Operasional
Bank Operasional → Kas Tunai
Bank A → Bank B
```

Transfer tidak boleh dianggap sebagai pendapatan atau beban.

Sistem harus membuat dua mutasi:

```text
Kas sumber berkurang
Kas tujuan bertambah
```

Kedua mutasi harus menggunakan satu nomor referensi transfer yang sama.

---

## 5.4 Jenis Kas yang Disarankan

RUNITC dapat menggunakan beberapa jenis kas:

- Kas Tunai.
- Bank Operasional.
- Bank Simpanan.
- Bank Pinjaman.
- Rekening Dana Sosial.
- Rekening lainnya sesuai keputusan koperasi.

---

# 6. Simpanan

Menu simpanan terdiri atas:

1. Setoran anggota.
2. Penarikan simpanan.

---

## 6.1 Jenis Simpanan

Jenis simpanan yang umum digunakan:

### Simpanan Pokok

- Dibayar satu kali saat menjadi anggota.
- Nominal ditentukan koperasi.
- Tidak dapat ditarik selama masih aktif sebagai anggota.

### Simpanan Wajib

- Dibayar secara berkala.
- Umumnya dibayar setiap bulan.
- Dapat diintegrasikan dengan potongan gaji.

### Simpanan Sukarela

- Dapat disetor kapan saja.
- Dapat ditarik melalui proses pengajuan.
- Nominal bersifat fleksibel.

### Simpanan Tambahan

Sistem dapat mendukung jenis lain:

- Simpanan Hari Raya.
- Simpanan Pendidikan.
- Simpanan Berjangka.
- Simpanan Hari Tua.
- Simpanan Khusus.

---

## 6.2 Setoran Simpanan

Alur yang disarankan:

```text
Operator memasukkan transaksi
    ↓
Sistem membuat nomor transaksi
    ↓
Transaksi diverifikasi
    ↓
Transaksi diposting
    ↓
Saldo simpanan bertambah
    ↓
Kas koperasi bertambah
    ↓
Bukti transaksi diterbitkan
```

Sumber setoran dapat berupa:

- Potongan gaji.
- Transfer bank.
- Tunai.
- Import transaksi massal.
- Penyesuaian resmi.

---

## 6.3 Penarikan Simpanan

Penarikan tidak boleh langsung mengurangi saldo ketika pengajuan dibuat.

Alur yang disarankan:

```text
Anggota mengajukan penarikan
    ↓
Sistem memeriksa saldo tersedia
    ↓
Operator melakukan verifikasi
    ↓
Bendahara menyetujui
    ↓
Dana dibayarkan
    ↓
Transaksi diposting
    ↓
Saldo simpanan berkurang
```

Status penarikan:

```text
draft
submitted
verified
approved
rejected
paid
cancelled
```

Validasi penarikan:

- Saldo mencukupi.
- Jenis simpanan dapat ditarik.
- Anggota berstatus aktif.
- Tidak ada pengajuan ganda.
- Tidak melanggar saldo minimum.
- Rekening tujuan telah diverifikasi.

---

# 7. Pinjaman

Menu pinjaman pada demo terdiri atas:

1. Data Pengajuan.
2. Data Pinjaman.
3. Bayar Angsuran.
4. Pinjaman Lunas.

---

## 7.1 Data Pengajuan

Menu Data Pengajuan digunakan untuk mengelola permohonan pinjaman anggota.

Filter yang terlihat:

- Tanggal awal.
- Tanggal akhir.
- Status pengajuan.

Status pengajuan yang disarankan:

```text
draft
submitted
under_review
revision_required
approved
rejected
cancelled
disbursed
```

---

## 7.2 Alur Pengajuan Pinjaman

```text
Anggota mengajukan pinjaman
    ↓
Sistem memeriksa kelayakan awal
    ↓
Admin memverifikasi data
    ↓
Approver melakukan persetujuan
    ↓
Dokumen perjanjian dibuat
    ↓
Bendahara mencairkan dana
    ↓
Jadwal angsuran dibuat
    ↓
Pinjaman menjadi aktif
```

---

## 7.3 Validasi Kelayakan

Validasi yang disarankan:

- Anggota berstatus aktif.
- Masa keanggotaan memenuhi syarat.
- Simpanan wajib tidak menunggak.
- Tidak memiliki pinjaman bermasalah.
- Total pinjaman tidak melebihi batas.
- Angsuran tidak melebihi persentase gaji.
- Tenor sesuai produk pinjaman.
- Rekening pencairan telah diverifikasi.
- Dokumen wajib telah lengkap.

---

## 7.4 Data Pinjaman

Informasi yang terlihat pada demo:

- Nomor referensi.
- Tanggal pinjam.
- Jenis pinjaman.
- Nama anggota.
- Total tagihan.
- Status lunas.
- Aksi.

Fitur pendukung:

- Import pinjaman.
- Tambah pinjaman.
- Filter anggota.
- Filter jenis pinjaman.
- Pencarian.
- Total keseluruhan tagihan.

---

## 7.5 Produk Pinjaman

Setiap produk pinjaman sebaiknya memiliki konfigurasi:

- Nama produk.
- Kode produk.
- Minimum pinjaman.
- Maksimum pinjaman.
- Minimum tenor.
- Maksimum tenor.
- Pilihan tenor.
- Metode bunga.
- Persentase bunga.
- Biaya administrasi.
- Biaya provisi.
- Biaya asuransi.
- Denda keterlambatan.
- Masa keanggotaan minimum.
- Batas rasio angsuran terhadap gaji.
- Status aktif.

---

## 7.6 Metode Perhitungan Pinjaman

Demo memiliki simulasi:

- Flat.
- Efektif.
- Anuitas.

Logika harus disimpan pada backend.

Contoh struktur service:

```php
LoanCalculationService
├── calculateFlat()
├── calculateEffective()
└── calculateAnnuity()
```

### Metode Flat

Bunga dihitung dari pokok awal selama tenor pinjaman.

### Metode Efektif

Bunga dihitung berdasarkan sisa pokok pinjaman.

### Metode Anuitas

Total cicilan dibuat relatif tetap, sedangkan komposisi pokok dan bunga berubah.

---

# 8. Pembayaran Angsuran

Pembayaran angsuran dapat berasal dari:

- Potongan gaji.
- Transfer bank.
- Pembayaran tunai.
- Import payroll.
- Pelunasan dipercepat.

Alur yang disarankan:

```text
Pembayaran diterima
    ↓
Sistem mencocokkan pinjaman
    ↓
Pembayaran dialokasikan
    ↓
Jadwal angsuran diperbarui
    ↓
Saldo pokok berkurang
    ↓
Kas bertambah
    ↓
Jurnal dibuat
    ↓
Bukti pembayaran diterbitkan
```

Urutan alokasi pembayaran dapat dikonfigurasi:

```text
Denda
→ Bunga
→ Biaya
→ Pokok
```

Status angsuran:

```text
unpaid
partial
paid
overdue
waived
```

---

# 9. Pinjaman Lunas

Pinjaman yang telah selesai harus dipisahkan dari pinjaman aktif.

Data pinjaman lunas dapat mencakup:

- Nomor pinjaman.
- Nama anggota.
- Tanggal pencairan.
- Tanggal pelunasan.
- Pokok pinjaman.
- Total bunga.
- Total pembayaran.
- Jenis pelunasan.
- Status dokumen.
- Surat keterangan lunas.

Pinjaman yang telah lunas tidak boleh dihapus karena dibutuhkan untuk histori dan audit.

---

# 10. Laporan Koperasi

Demo menyediakan laporan yang cukup lengkap.

---

## 10.1 Laporan Anggota

Informasi yang terlihat:

- Nomor anggota.
- Nama anggota.
- Tanggal registrasi.
- Departemen.
- Jabatan.
- Status pernah meminjam.
- Status keanggotaan.

Filter:

- Tanggal registrasi.
- Departemen.
- Jabatan.
- Status peminjaman.
- Status anggota.

---

## 10.2 Kas Anggota

Laporan Kas Anggota dapat digunakan untuk melihat seluruh transaksi keuangan anggota.

Informasi yang disarankan:

- Saldo awal.
- Simpanan masuk.
- Penarikan.
- Pencairan pinjaman.
- Pembayaran angsuran.
- SHU.
- Koreksi.
- Saldo akhir.

---

## 10.3 Laporan Pemasukan

Filter:

- Akun.
- Jenis kas.
- Tanggal awal.
- Tanggal akhir.
- Status transaksi.

Output:

- Tampilan web.
- PDF.
- Excel.

---

## 10.4 Laporan Pengeluaran

Filter dan format sama dengan laporan pemasukan.

Informasi:

- Nomor transaksi.
- Tanggal.
- Akun beban.
- Kas sumber.
- Keterangan.
- Nominal.
- Pembuat.
- Penyetuju.
- Status.

---

## 10.5 Jurnal Umum

Setiap transaksi finansial harus menghasilkan jurnal.

Informasi jurnal:

- Nomor jurnal.
- Tanggal.
- Referensi transaksi.
- Akun debit.
- Akun kredit.
- Nilai.
- Keterangan.
- Status posting.

---

## 10.6 Buku Besar

Buku besar ditampilkan per akun.

Kolom:

- Tanggal.
- Nomor referensi.
- Jenis transaksi.
- Keterangan.
- Debit.
- Kredit.
- Saldo.

---

## 10.7 Laba Rugi

Laporan laba rugi menampilkan:

- Pendapatan bunga.
- Pendapatan administrasi.
- Pendapatan denda.
- Pendapatan lain-lain.
- Biaya operasional.
- Biaya administrasi.
- Biaya pegawai.
- Biaya bank.
- Beban lainnya.
- Hasil usaha berjalan.

---

## 10.8 Neraca Saldo

Kelompok akun:

- Aktiva.
- Kewajiban.
- Modal.
- Pendapatan.
- Beban.

Neraca saldo menjadi dasar pembuatan laporan neraca dan laba rugi.

---

## 10.9 Laporan Kas Simpanan

Informasi yang disarankan:

- Anggota.
- Divisi.
- Jenis simpanan.
- Saldo awal.
- Setoran.
- Penarikan.
- Saldo akhir.
- Periode laporan.

---

## 10.10 Laporan Kas Pinjaman

Informasi:

- Pinjaman aktif.
- Pokok awal.
- Angsuran pokok.
- Angsuran bunga.
- Denda.
- Sisa pokok.
- Status pinjaman.
- Tanggal jatuh tempo.

---

## 10.11 Laporan Jatuh Tempo

Menampilkan:

- Nama anggota.
- Nomor pinjaman.
- Angsuran ke.
- Tanggal jatuh tempo.
- Pokok.
- Bunga.
- Denda.
- Total tagihan.
- Jumlah hari terlambat.
- Status pembayaran.

---

## 10.12 Laporan Bukti Bayar

Filter:

- Periode.
- Anggota.
- Jenis transaksi.
- Status transaksi.

Output:

- Bukti pembayaran individual.
- Rekap pembayaran.
- PDF.
- Excel.

---

# 11. Perhitungan SHU

Demo menampilkan perhitungan:

- SHU sebelum pajak.
- Pajak PPh.
- SHU setelah pajak.
- Dana cadangan.
- Jasa anggota.
- Dana pengurus.
- Dana karyawan.
- Dana pendidikan.
- Dana sosial.

Rumus SHU tidak boleh ditulis permanen di source code.

Konfigurasi harus dapat diubah berdasarkan keputusan RAT.

Contoh:

```text
SHU Setelah Pajak
├── Dana Cadangan
├── Jasa Modal Anggota
├── Jasa Transaksi Anggota
├── Dana Pengurus
├── Dana Karyawan
├── Dana Pendidikan
└── Dana Sosial
```

Sistem harus menyimpan snapshot perhitungan agar hasil periode sebelumnya tidak berubah ketika persentase baru diterapkan.

---

# 12. Kepegawaian

Demo memiliki modul Kepegawaian.

Untuk RUNITC, fitur ini tidak perlu dibuat ulang karena data pegawai sudah tersedia.

Data yang diambil dari RUNITC:

- User ID.
- Employee ID.
- Nama.
- Email.
- Nomor telepon.
- Divisi.
- Jabatan.
- Status pegawai.
- Tanggal bergabung.

Data khusus koperasi:

- Nomor anggota.
- Tanggal menjadi anggota.
- Status keanggotaan.
- Rekening bank.
- Persetujuan potong gaji.
- Tanggal keluar.
- Alasan keluar.

---

# 13. Master Data

Master data yang terlihat:

1. Jenis Simpanan.
2. Jenis Pinjaman.
3. Jenis Akun.
4. Jenis Kas.
5. Lama Angsuran.
6. Data Departemen.
7. Data Pekerjaan.
8. Data Barang.
9. Data Anggota.
10. Data Pengguna.

---

## 13.1 Jenis Simpanan

Kolom yang disarankan:

- Kode.
- Nama.
- Kategori.
- Nominal minimum.
- Nominal tetap.
- Frekuensi.
- Wajib atau opsional.
- Dapat ditarik.
- Saldo minimum.
- Akun kewajiban.
- Status aktif.

---

## 13.2 Jenis Pinjaman

Kolom:

- Kode produk.
- Nama produk.
- Batas minimum.
- Batas maksimum.
- Metode bunga.
- Persentase bunga.
- Tenor.
- Administrasi.
- Provisi.
- Asuransi.
- Denda.
- Syarat keanggotaan.
- Status aktif.

---

## 13.3 Jenis Akun

Chart of accounts dapat menggunakan kelompok:

```text
1.xxx — Aktiva
2.xxx — Kewajiban
3.xxx — Modal
4.xxx — Pendapatan
5.xxx — Beban
```

Contoh:

```text
1.1.01 Kas Tunai
1.1.02 Bank Operasional
1.2.01 Piutang Pinjaman
2.1.01 Simpanan Pokok
2.1.02 Simpanan Wajib
2.1.03 Simpanan Sukarela
3.1.01 Modal Koperasi
4.1.01 Pendapatan Bunga
4.1.02 Pendapatan Administrasi
5.1.01 Beban Operasional
```

---

## 13.4 Jenis Kas

Data:

- Kode kas.
- Nama kas.
- Nomor rekening.
- Nama bank.
- Kode akun.
- Saldo awal.
- Status aktif.

---

## 13.5 Lama Angsuran

Tenor dapat dibuat sebagai master atau relasi dengan produk pinjaman.

Contoh:

```text
Pinjaman Reguler → 3, 6, 12 bulan
Pinjaman Pendidikan → 6, 12, 18, 24 bulan
Pinjaman Darurat → 1, 2, 3 bulan
```

---

## 13.6 Departemen dan Pekerjaan

Data departemen dan pekerjaan tidak perlu dibuat ganda.

Gunakan data dari modul user atau HR RUNITC.

---

## 13.7 Data Barang

Data barang mengindikasikan kemungkinan dukungan terhadap kredit barang atau toko koperasi.

Fitur ini dapat ditunda apabila koperasi RUNITC hanya berfokus pada simpan pinjam.

---

# 14. Pengaturan Sistem

## 14.1 Identitas Koperasi

Data:

- Nama koperasi.
- Nomor badan hukum.
- Nama pimpinan.
- Nomor telepon.
- Nomor HP.
- Email.
- Website.
- Alamat.
- Kota atau kabupaten.
- Logo.
- Nomor NPWP.
- Informasi rekening utama.

---

## 14.2 Pengaturan Akun

Pengaturan akun digunakan untuk memetakan transaksi ke jurnal.

Contoh mapping:

- Akun piutang pinjaman.
- Akun pendapatan bunga.
- Akun pendapatan administrasi.
- Akun pendapatan denda.
- Akun asuransi.
- Akun simpanan pokok.
- Akun simpanan wajib.
- Akun simpanan sukarela.
- Akun kas pencairan.
- Akun kas pembayaran.

Contoh jurnal pencairan:

```text
Debit  : Piutang Pinjaman Anggota
Kredit : Kas atau Bank
```

Contoh jurnal angsuran:

```text
Debit  : Kas atau Bank
Kredit : Piutang Pokok
Kredit : Pendapatan Bunga
Kredit : Pendapatan Administrasi
```

---

# 15. Simulasi Kredit

Input simulasi:

- Jumlah pinjaman.
- Tenor.
- Bunga per tahun.
- Metode perhitungan.

Output:

- Cicilan bulanan.
- Total pokok.
- Total bunga.
- Total pembayaran.
- Tabel angsuran.
- Rincian pokok dan bunga.

Catatan:

> Hasil simulasi bersifat estimasi dan bukan persetujuan pinjaman.

---

# 16. Struktur Menu yang Disarankan

## 16.1 Menu Admin

```text
Koperasi
├── Dashboard
├── Keanggotaan
│   ├── Daftar Anggota
│   ├── Pengajuan Keanggotaan
│   ├── Anggota Aktif
│   ├── Anggota Nonaktif
│   └── Riwayat Status
├── Simpanan
│   ├── Setoran
│   ├── Penarikan
│   ├── Rekening Simpanan
│   ├── Simpanan Pokok
│   ├── Simpanan Wajib
│   └── Simpanan Sukarela
├── Pinjaman
│   ├── Pengajuan
│   ├── Verifikasi
│   ├── Persetujuan
│   ├── Pencairan
│   ├── Pinjaman Aktif
│   ├── Bayar Angsuran
│   ├── Jatuh Tempo
│   ├── Tunggakan
│   └── Pinjaman Lunas
├── Kas dan Keuangan
│   ├── Pemasukan
│   ├── Pengeluaran
│   ├── Transfer Kas
│   ├── Jurnal Umum
│   ├── Buku Besar
│   └── Tutup Periode
├── SHU
│   ├── Konfigurasi
│   ├── Simulasi
│   ├── Perhitungan
│   ├── Finalisasi
│   └── Distribusi
├── Laporan
│   ├── Anggota
│   ├── Simpanan
│   ├── Pinjaman
│   ├── Angsuran
│   ├── Tunggakan
│   ├── Jatuh Tempo
│   ├── Pemasukan
│   ├── Pengeluaran
│   ├── Jurnal
│   ├── Buku Besar
│   ├── Neraca
│   ├── Laba Rugi
│   ├── SHU
│   └── Bukti Bayar
├── Master Data
│   ├── Jenis Simpanan
│   ├── Produk Pinjaman
│   ├── Tenor
│   ├── Chart of Accounts
│   ├── Rekening Kas
│   └── Metode Pembayaran
└── Pengaturan
    ├── Identitas Koperasi
    ├── Mapping Akun
    ├── Nomor Dokumen
    ├── Approval
    ├── Notifikasi
    ├── Hak Akses
    └── Audit Log
```

---

## 16.2 Menu Anggota

```text
Koperasi Saya
├── Ringkasan
├── Keanggotaan Saya
├── Simpanan Saya
├── Pinjaman Saya
├── Ajukan Pinjaman
├── Jadwal Angsuran
├── Ajukan Penarikan
├── Riwayat Transaksi
├── SHU Saya
└── Dokumen
```

---

# 17. Prinsip Keamanan Transaksi

## 17.1 Maker-Checker

Pengguna yang membuat transaksi tidak boleh menyetujui transaksi yang sama.

Contoh:

```text
Operator membuat pengeluaran
Bendahara memverifikasi
Ketua menyetujui
```

---

## 17.2 Status Transaksi

Status umum:

```text
draft
pending
verified
approved
posted
rejected
cancelled
reversed
```

---

## 17.3 Transaksi Posted Tidak Boleh Diedit

Transaksi yang sudah diposting tidak dapat:

- Diubah nominalnya.
- Diubah tanggalnya.
- Dihapus.
- Dipindahkan ke anggota lain.

Kesalahan diperbaiki menggunakan reversal.

---

## 17.4 Audit Log

Audit log harus menyimpan:

- User.
- Waktu.
- Alamat IP.
- Modul.
- Aksi.
- Data sebelum.
- Data sesudah.
- Nomor referensi.
- Alasan perubahan.

---

## 17.5 Penguncian Periode

Periode pembukuan yang telah ditutup tidak dapat menerima transaksi baru.

Pembukaan kembali periode memerlukan:

- Hak akses khusus.
- Alasan.
- Approval.
- Audit log.

---

# 18. Prinsip Database

## 18.1 Jangan Menyimpan Saldo sebagai Satu-satunya Sumber

Saldo harus berasal dari transaksi.

Contoh yang tidak disarankan:

```sql
UPDATE coop_members
SET saving_balance = 5000000
WHERE id = 10;
```

Struktur yang benar:

```text
Anggota
    ↓
Rekening Simpanan
    ↓
Transaksi Setoran dan Penarikan
    ↓
Saldo
```

Kolom saldo dapat digunakan sebagai cache, tetapi sumber kebenaran tetap transaksi.

---

## 18.2 Nomor Transaksi

Format yang disarankan:

```text
MBR-202608-000001
SAV-202608-000001
WDR-202608-000001
LAP-202608-000001
LOAN-202608-000001
PAY-202608-000001
CSH-202608-000001
TRF-202608-000001
JRN-202608-000001
SHU-2026-000001
```

Nomor harus dibuat di dalam database transaction agar tidak ganda.

---

# 19. Rekomendasi Tabel Database

Gunakan prefix `coop_`.

## 19.1 Keanggotaan

```text
coop_members
coop_member_status_history
coop_member_bank_accounts
coop_member_documents
```

---

## 19.2 Simpanan

```text
coop_saving_types
coop_saving_accounts
coop_saving_transactions
coop_saving_withdrawal_requests
```

---

## 19.3 Pinjaman

```text
coop_loan_products
coop_loan_product_tenors
coop_loan_applications
coop_loan_approvals
coop_loans
coop_loan_schedules
coop_loan_payments
coop_loan_payment_allocations
coop_loan_collaterals
```

---

## 19.4 Kas dan Akuntansi

```text
coop_cash_accounts
coop_cash_transactions
coop_cash_transfers
coop_chart_of_accounts
coop_journal_entries
coop_journal_lines
coop_accounting_periods
```

---

## 19.5 SHU

```text
coop_shu_periods
coop_shu_components
coop_shu_calculations
coop_shu_member_results
```

---

## 19.6 Administrasi

```text
coop_settings
coop_number_sequences
coop_attachments
coop_notifications
coop_activity_logs
coop_approval_flows
coop_approval_steps
coop_approval_actions
```

---

# 20. Struktur Folder Laravel 12

Modul koperasi mengikuti konvensi Laravel yang sudah dipakai RUNITC (pola modul FilingSystem).

```text
app/Http/Controllers/Cooperative/
├── DashboardController.php
├── MemberController.php
├── SavingController.php
├── WithdrawalController.php
├── LoanApplicationController.php
├── LoanController.php
├── InstallmentController.php
├── CashController.php
├── AccountingController.php
├── ReportController.php
└── ShuController.php
app/Models/Cooperative/
├── CooperativeMember.php          (koneksi: mysql, tabel icu_member)
├── SavingType.php
├── SavingAccount.php
├── SavingTransaction.php
├── LoanProduct.php
├── LoanApplication.php
├── Loan.php                       (tabel icu_mloan)
├── LoanSchedule.php               (tabel icu_dloan)
├── LoanPayment.php
├── CashTransaction.php            (tabel icu_transaction)
└── JournalEntry.php
app/Services/Cooperative/
├── MemberService.php
├── SavingService.php
├── WithdrawalService.php
├── LoanCalculationService.php     (flat, efektif, anuitas)
├── LoanApprovalService.php
├── LoanDisbursementService.php
├── PaymentAllocationService.php
├── CashService.php
├── JournalService.php
├── ShuCalculationService.php
└── DocumentNumberService.php
routes/cooperative.php             (didaftarkan lewat require di routes/web.php)
resources/views/cooperative/
├── admin/
└── member/
tests/Unit/Services/Cooperative/
```

Fitur Simulasi Kredit adalah implementasi pertama pola ini:

```text
LoanSimulationService      → perhitungan flat, efektif, anuitas
LoanSimulationController   → index, calculate (AJAX JSON), export (CSV)
cooperative/loan-simulation/index.blade.php
routes: /cooperative/loan-simulation*
```

---

# 21. Tahapan Implementasi

## Fase 1 — Fondasi dan MVP

Fitur:

- Integrasi user RUNITC.
- Keanggotaan.
- Jenis simpanan.
- Setoran simpanan.
- Penarikan simpanan.
- Produk pinjaman.
- Pengajuan pinjaman.
- Approval pinjaman.
- Pencairan.
- Jadwal angsuran.
- Pembayaran angsuran.
- Dashboard.
- Laporan dasar.
- Audit log.

---

## Fase 2 — Kas dan Akuntansi

Fitur:

- Kas masuk.
- Kas keluar.
- Transfer kas.
- Chart of accounts.
- Mapping akun.
- Jurnal otomatis.
- Buku besar.
- Neraca saldo.
- Laba rugi.
- Neraca.
- Tutup periode.
- Reversal transaksi.

---

## Fase 3 — Otomatisasi

Fitur:

- Integrasi payroll.
- Potongan simpanan wajib.
- Potongan angsuran.
- Import transaksi massal.
- Reminder jatuh tempo.
- Notifikasi email.
- Upload bukti transfer.
- Rekonsiliasi pembayaran.

---

## Fase 4 — SHU dan RAT

Fitur:

- Konfigurasi komponen SHU.
- Simulasi SHU.
- Perhitungan final.
- Distribusi per anggota.
- Snapshot tahunan.
- Dokumen RAT.
- Laporan SHU.

---

## Fase 5 — Pengembangan Lanjutan

Fitur opsional:

- PWA.
- Aplikasi mobile.
- Payment gateway.
- Integrasi WhatsApp.
- Kredit barang.
- Toko koperasi.
- Digital signature.
- Integrasi bank.

---

# 22. Fitur yang Harus Masuk MVP

- Dashboard Admin.
- Dashboard Anggota.
- Integrasi user RUNITC.
- Data anggota.
- Jenis simpanan.
- Setoran simpanan.
- Penarikan simpanan.
- Produk pinjaman.
- Pengajuan pinjaman.
- Approval.
- Pencairan.
- Jadwal angsuran.
- Pembayaran angsuran.
- Pinjaman lunas.
- Jatuh tempo.
- Bukti transaksi.
- Ekspor PDF.
- Ekspor Excel.
- Audit log.

---

# 23. Fitur yang Dapat Ditunda

- Data barang.
- Kredit barang.
- Toko koperasi.
- Sistem kepegawaian terpisah.
- Aplikasi Android atau iOS.
- Payment gateway.
- WhatsApp API.
- Auto credit scoring kompleks.
- Rekonsiliasi bank otomatis.
- Multi-koperasi.
- Multi-tenant.

---

# 24. Kekurangan Demo yang Perlu Diperbaiki di RUNITC

Berdasarkan rekaman, beberapa hal belum terlihat atau perlu dibuat lebih baik:

- Approval bertingkat belum terlihat jelas.
- Maker-checker belum terlihat jelas.
- Audit trail belum terlihat.
- Reversal transaksi belum terlihat.
- Penguncian periode belum terlihat.
- Validasi kemampuan bayar belum terlihat.
- Upload dokumen pinjaman belum terlihat.
- Integrasi potong gaji belum terlihat.
- Tombol hapus pada master data masih tersedia.
- Beberapa halaman belum konsisten.
- Tabel membutuhkan horizontal scroll.
- Responsivitas perlu diperbaiki.
- Status transaksi perlu lebih eksplisit.

RUNITC sebaiknya mengambil konsep bisnis eKoperasi, tetapi meningkatkan keamanan, konsistensi, dan integrasinya.

---

# 25. Kesimpulan

Struktur utama sistem koperasi dapat digambarkan sebagai:

```text
Master Data
    ↓
Keanggotaan
    ↓
Simpanan dan Pinjaman
    ↓
Kas dan Pembayaran
    ↓
Jurnal Akuntansi
    ↓
Buku Besar
    ↓
Laba Rugi dan Neraca
    ↓
Perhitungan SHU
```

Urutan pengembangan yang paling aman:

```text
Keanggotaan
→ Simpanan
→ Pinjaman
→ Angsuran
→ Kas
→ Akuntansi
→ Laporan
→ SHU
```

Fondasi utama sistem bukan dashboard, melainkan:

1. Ledger transaksi.
2. Status dan approval.
3. Mapping akun.
4. Jurnal otomatis.
5. Audit log.
6. Reversal.
7. Penguncian periode.
8. Hak akses.

Modul koperasi RUNITC sebaiknya dibangun sebagai:

> Sistem koperasi internal yang terintegrasi dengan akun pegawai RUNITC, memiliki transaksi simpanan dan pinjaman yang dapat diaudit, approval berjenjang, integrasi kas dan akuntansi, laporan anggota, serta dukungan perhitungan SHU.

---

# 26. Temuan Database Existing ICU di itc_itconenew

RUNITC sudah memiliki struktur database koperasi existing di database `itc_itconenew`.
Tabel koperasi dapat dikenali dari prefix `icu`.

Tabel yang ditemukan:

- `icu_account`
- `icu_bank_trx`
- `icu_dloan`
- `icu_dtoken`
- `icu_dtrx2hrd`
- `icu_member`
- `icu_mloan`
- `icu_msttable`
- `icu_mtoken`
- `icu_mtrx2hrd`
- `icu_mtrxdraw`
- `icu_transaction`

Relasi utama yang terlihat:

```text
icu_member.rec_id
    ↓
icu_mloan.icu_rec_id
    ↓
icu_dloan.mst_rec_id

icu_member.rec_id
    ↓
icu_transaction.icu_rec_id
```

Catatan penting:

- Database tidak memiliki foreign key formal.
- Relasi harus dijaga oleh logic aplikasi.
- Validasi transaksi harus ketat karena database tidak mencegah data yatim secara otomatis.
- Sistem baru sebaiknya tidak langsung menghapus atau mengubah pola tabel lama sebelum proses bisnis dipastikan.
- Untuk tahap awal, module RUNITC sebaiknya membaca dan memanfaatkan tabel existing terlebih dahulu.

---

# 27. Mapping Tabel ICU ke Modul Koperasi RUNITC

## 27.1 Anggota

Tabel existing:

```text
icu_member
```

Kolom penting:

- `rec_id`: ID anggota ICU.
- `itc_user_id`: relasi ke user RUNITC atau user ITC.
- `pprdk`: periode referensi.
- `icuno`: nomor anggota koperasi.
- `icunm`: nama anggota.
- `alias_nm`: alias anggota.
- `joindt`: tanggal bergabung.
- `st_aktif`: status anggota.
- `temp_trx`: nilai transaksi sementara.
- `otvalue`: nilai lain atau outstanding tertentu yang perlu dikonfirmasi.
- `swajib`: nilai simpanan wajib.
- `outstanding`: outstanding pinjaman.
- `stat_trx`: status transaksi anggota.
- `refno`: referensi pegawai atau nomor HR.
- `entusr`: user input.
- `entdt`: tanggal input.
- `lupd`: waktu update terakhir.
- `koreksi`: indikator koreksi.

## 27.2 Pinjaman

Header pinjaman:

```text
icu_mloan
```

Detail jadwal cicilan:

```text
icu_dloan
```

Kolom penting `icu_mloan`:

- `rec_id`: ID master pinjaman.
- `pprd`: periode transaksi.
- `trncd`: kode transaksi.
- `trnno`: nomor transaksi pinjaman.
- `trndt`: tanggal pinjaman.
- `icu_rec_id`: referensi ke `icu_member.rec_id`.
- `descr`: deskripsi pinjaman.
- `principle`: pokok pinjaman.
- `interamt`: total bunga awal.
- `interest`: bunga tahunan.
- `int_overdue`: bunga overdue atau biaya tambahan.
- `bnk_charge`: biaya bank.
- `bnktrx_no`: nomor transaksi bank.
- `totalloan`: total pinjaman.
- `paid`: total terbayar.
- `avgmon`: pokok bulanan.
- `avgint`: bunga bulanan.
- `monthly`: tagihan bulanan.
- `term`: tenor awal.
- `startper`: periode mulai.
- `endper`: periode akhir.
- `remarks`: catatan.
- `statrec`: status record.

Kolom penting `icu_dloan`:

- `rec_id`: ID detail jadwal.
- `mst_rec_id`: referensi ke `icu_mloan.rec_id`.
- `periode`: periode cicilan format `YYYYMM`.
- `seqno`: urutan cicilan.
- `totseqno`: total cicilan.
- `descr`: deskripsi cicilan.
- `amount`: pokok cicilan.
- `rnd_amt`: nilai pembulatan pokok.
- `int_amt`: bunga cicilan.
- `rnd_int`: nilai pembulatan bunga.
- `others`: biaya lain.
- `outstand`: outstanding setelah cicilan.
- `remarks`: catatan.
- `dseqno`: urutan mundur atau sisa urutan cicilan.
- `paidst`: status bayar.
- `payno`: nomor pembayaran.
- `lupd`: waktu update terakhir.

## 27.3 Simpanan dan Transaksi Anggota

Tabel existing:

```text
icu_transaction
```

Kolom penting:

- `rec_id`: ID transaksi.
- `pprd`: periode transaksi.
- `trncd`: kode transaksi.
- `trnno`: nomor transaksi.
- `trndt`: tanggal transaksi.
- `icu_rec_id`: referensi ke anggota.
- `empno`: nomor pegawai atau referensi HR.
- `descr`: deskripsi transaksi.
- `dbocr`: debit atau credit.
- `basic_amt`: nominal pokok transaksi.
- `int_amt`: nominal bunga.
- `amount`: total nominal transaksi.
- `notes`: catatan.
- `refno`: nomor referensi.
- `statrec`: status record.
- `statrec2`: status tambahan.

## 27.4 Master Kode ICU

Master kode transaksi ada di:

```text
icu_msttable
```

Kode penting yang ditemukan:

- `tbl_code 02`, `code 0`: Draft.
- `tbl_code 02`, `code 1`: CU Account.
- `tbl_code 02`, `code 2`: Regular Member.
- `tbl_code 02`, `code 3`: Regular Non Payroll.
- `tbl_code 02`, `code 4`: Irregular Member.
- `tbl_code 02`, `code 5`: Outstanding Member.
- `tbl_code 02`, `code 6`: Non-Active Member.
- `tbl_code 04`, `code 1`: Loan.
- `tbl_code 04`, `code 2`: Drawing Money.
- `tbl_code 05`, `code 1`, `subcode 19`: Simpanan Bulanan.
- `tbl_code 05`, `code 2`, `subcode 18`: Additional Member Saving.
- `tbl_code 05`, `code 3`, `subcode 17`: Others Debt Note.
- `tbl_code 05`, `code 4`, `subcode 21`: Employee Loan.
- `tbl_code 05`, `code 5`, `subcode 20`: Cicilan Pinjaman.

---

# 28. Logic Pinjaman Berdasarkan File Excel pinjam.xlsx

Berdasarkan file `assets/personal/pinjam.xlsx`, logic pinjaman yang digunakan adalah bunga flat.

Contoh:

```text
Pokok pinjaman       : 1.000.000
Tenor awal           : 18 bulan
Bunga tahunan        : 6%
Bunga total kontrak  : 6% / 12 * 18 = 9%
Nilai bunga awal     : 1.000.000 * 9% = 90.000
Bunga bulanan        : 90.000 / 18 = 5.000
Pokok bulanan        : ROUND(1.000.000 / 18) = 55.556
Tagihan normal       : 55.556 + 5.000 = 60.556
```

Formula dasar:

```text
monthly_interest = principal_amount * annual_interest_rate / 12
principal_installment = ROUND(principal_amount / original_tenor)
normal_monthly_due = principal_installment + monthly_interest
```

Formula ini sama dengan pola field existing:

```text
icu_mloan.principle = principal_amount
icu_mloan.interest  = annual_interest_rate
icu_mloan.interamt  = initial_total_interest
icu_mloan.avgmon    = principal_installment
icu_mloan.avgint    = monthly_interest
icu_mloan.monthly   = normal_monthly_due
icu_mloan.term      = original_tenor
```

Catatan:

- Bunga dihitung dari pokok awal.
- Bunga tidak dihitung ulang dari sisa pokok berjalan.
- Ini berbeda dari metode efektif atau anuitas.
- Untuk tahap awal, metode flat existing lebih penting daripada metode efektif dan anuitas.

---

# 29. Refinancing atau Skip Pokok

Dalam diskusi, terdapat kebutuhan untuk menangani kondisi user melakukan refinancing atau skip pembayaran pokok pada bulan tertentu.

Aturan bisnis:

- User tidak membayar pokok pada periode skip.
- User tetap membayar bunga bulanan.
- Pokok yang tidak dibayar tidak hilang.
- Tenor aktual bertambah sesuai jumlah periode skip.
- Bunga aktual juga bertambah karena periode tambahan tetap dikenakan bunga bulanan flat.
- Bunga bulanan tetap berdasarkan bunga flat awal.
- Skip tidak boleh di-hardcode pada bulan tertentu, karena periode skip dapat terjadi kapan saja sesuai approval.

Contoh:

```text
Tenor awal        : 18 bulan
Skip pokok        : 2 bulan
Tenor aktual      : 20 bulan
Bunga bulanan     : 5.000
Bunga awal        : 18 x 5.000 = 90.000
Bunga tambahan    : 2 x 5.000 = 10.000
Total bunga bayar : 20 x 5.000 = 100.000
```

Representasi pada jadwal:

```text
Periode normal:
principal_due = pokok_bulanan
interest_due  = bunga_bulanan
total_due     = principal_due + interest_due

Periode skip/refinancing:
principal_due = 0
interest_due  = bunga_bulanan
total_due     = interest_due
remaining_principal tidak berkurang
```

Pada tabel existing `icu_dloan`, periode skip dapat direpresentasikan sebagai:

```text
amount   = 0
int_amt  = bunga_bulanan
others   = 0 atau biaya tambahan jika ada
remarks  = Refinancing atau Skip Principal
```

Namun perlu dipastikan kembali apakah `outstand` di sistem lama merepresentasikan:

- sisa total tagihan, atau
- sisa pokok pinjaman, atau
- sisa pokok + bunga.

Jika `outstand` adalah sisa total tagihan, maka periode skip tetap mengurangi outstanding sebesar bunga yang dibayar.
Jika `outstand` adalah sisa pokok, maka periode skip tidak boleh mengurangi outstanding.

---

# 30. Aturan Pembulatan Angsuran

Karena `avgmon` atau pokok bulanan menggunakan pembulatan, jadwal dapat menghasilkan selisih kecil pada cicilan terakhir.

Contoh dari Excel:

```text
Pokok pinjaman : 1.000.000
Tenor          : 18
Pokok bulanan  : ROUND(1.000.000 / 18) = 55.556
```

Jika pokok bulanan dikalikan jumlah periode, hasilnya dapat lebih besar dari pokok asli.

Rekomendasi:

- Cicilan normal memakai pokok bulanan hasil pembulatan.
- Cicilan pokok terakhir harus disesuaikan dengan sisa pokok.
- Sistem tidak boleh menghasilkan outstanding akhir negatif.
- Outstanding akhir harus tepat `0`.
- Catatan `remarks = Rounding` dapat digunakan untuk cicilan terakhir jika mengikuti pola data lama.

Formula:

```text
principal_due = min(rounded_principal_installment, remaining_principal)
```

---

# 31. Validasi Periode Pinjaman

Database existing menggunakan periode dalam format `YYYYMM`.

Contoh:

```text
202608
202609
202610
```

Sistem harus menggunakan perhitungan bulan kalender yang benar.

Contoh yang benar:

```text
202612
202701
```

Bukan:

```text
202612
202613
202614
```

Rekomendasi:

- Jangan menambah periode dengan operasi angka biasa.
- Gunakan fungsi tanggal untuk menambah bulan.
- Simpan periode tetap sebagai `YYYYMM` agar kompatibel dengan tabel lama.
- `endper` pada `icu_mloan` harus mengikuti periode jadwal terakhir aktual, bukan hanya `startper + term` jika ada skip pokok.

---

# 32. Strategi Implementasi Berdasarkan Database Existing

Karena tabel `icu%` sudah tersedia, terdapat dua pilihan strategi.

## 32.1 Menggunakan Tabel Existing

Kelebihan:

- Lebih cepat.
- Data lama tetap terbaca.
- Tidak perlu migrasi besar.
- Cocok untuk MVP.
- Selaras dengan proses yang sudah pernah berjalan.

Kekurangan:

- Nama kolom mengikuti sistem lama.
- Tidak ada foreign key.
- Beberapa status perlu ditafsirkan.
- Perlu hati-hati agar tidak merusak data historis.
- Beberapa kebutuhan modern seperti approval detail dan attachment belum terlihat eksplisit.

## 32.2 Membuat Tabel Baru `coop_`

Kelebihan:

- Struktur lebih bersih.
- Bisa memakai naming convention baru.
- Bisa dibuat lebih aman dengan foreign key dan audit detail.
- Lebih mudah untuk pengembangan jangka panjang.

Kekurangan:

- Perlu migrasi data.
- Perlu mapping dua arah dengan data lama.
- Risiko duplikasi sumber data.
- Membutuhkan validasi akuntansi dan histori lebih panjang.

## 32.3 Rekomendasi

Untuk tahap awal, gunakan pendekatan hybrid:

```text
MVP membaca dan memakai tabel icu existing
    ↓
Tambahkan wrapper model/service di RUNITC
    ↓
Jangan ubah struktur lama secara agresif
    ↓
Tambahkan tabel pendukung hanya jika memang tidak tersedia di icu
```

Tabel pendukung yang mungkin tetap dibutuhkan:

- log approval pinjaman
- attachment dokumen
- audit tambahan
- konfigurasi workflow
- catatan refinancing atau skip pokok jika tidak cukup disimpan di `icu_dloan.remarks`
- notifikasi internal RUNITC
- mapping menu dan permission RUNITC

---

# 33. Revisi MVP Berdasarkan Kondisi Existing

Karena database `icu%` sudah memiliki tabel anggota, pinjaman, detail cicilan, transaksi, dan master kode, MVP dapat lebih difokuskan pada UI dan workflow, bukan pembuatan schema dari nol.

Implementasi memakai Laravel: setiap tabel existing dibungkus Eloquent model dengan koneksi `mysql` (misalnya `LoanSchedule` untuk `icu_dloan`), sedangkan menu, hak akses, dan audit log tetap memakai koneksi `run`.

MVP yang lebih realistis:

1. Dashboard koperasi dari tabel `icu_member`, `icu_mloan`, `icu_dloan`, dan `icu_transaction`.
2. Daftar anggota dari `icu_member`.
3. Detail anggota termasuk simpanan, pinjaman, dan transaksi.
4. Daftar pinjaman dari `icu_mloan`.
5. Detail jadwal pinjaman dari `icu_dloan`.
6. Simulasi pinjaman flat sesuai Excel.
7. Generate jadwal pinjaman normal.
8. Generate jadwal dengan skip pokok atau refinancing.
9. Pembayaran angsuran dengan update `paidst` dan `payno`.
10. Laporan simpanan dan pinjaman dasar.
11. Audit log di RUNITC untuk aksi baru.
12. Integrasi menu dan hak akses RUNITC.

Fitur yang sebaiknya ditunda sampai proses lama dipahami penuh:

- Rebuild chart of accounts.
- Jurnal otomatis penuh.
- SHU.
- Integrasi payroll otomatis.
- Reversal akuntansi kompleks.
- Migrasi tabel `icu` ke `coop`.
- Payment gateway.
- Rekonsiliasi bank otomatis.

---

# 34. Risiko dan Pertanyaan Lanjutan

Beberapa hal perlu dikonfirmasi sebelum implementasi:

1. Apakah `icu_mloan.int_overdue` memang digunakan untuk bunga tambahan akibat refinancing atau skip pokok? so far belum pernah dipakai
2. Apakah `icu_dloan.outstand` dihitung dari total tagihan atau hanya sisa pokok?
   **Terjawab via inspeksi data (Agustus 2026): `outstand` = sisa POKOK pinjaman.** Pada data nyata, outstanding turun sebesar `amount` (pokok) saja setiap pembayaran, bukan `amount + int_amt`, dan mencapai tepat 0 pada cicilan terakhir.
3. Apakah `icu_dloan.paidst = 1` berarti lunas atau bayar penuh?
   **Temuan tambahan (Agustus 2026): seluruh 1.586 baris `icu_dloan` existing bernilai `paidst = 0`** dan seluruh 112 baris `icu_mloan` bernilai `statrec = 0`, sehingga keduanya belum terbukti dipakai sebagai penanda status. Makna nilai selain 0 tetap perlu konfirmasi bisnis.
4. Apakah `icu_mloan.statrec` dan `icu_transaction.statrec` memiliki daftar status baku? tidak baku, tapi ada di table sys_seqflow
   **Konfirmasi implementasi (Agustus 2026): flow `LN` di `sys_seqflow` dipakai untuk `icu_mloan.statrec`** — 0=Draft, 1=Waiting Approval, 2=Transfer List, 3=Loan Submitted, 4=Partial Payment, 5=Loan Completed, 6=Loan Rejected. Modul posting men-set 3, pembayaran membuatnya menjadi 4, dan lunas penuh menjadi 5.
5. Apakah nomor transaksi seperti `LON-26A-0114` harus mengikuti format lama?
   **Keputusan implementasi (Agustus 2026): ya, modul posting melanjutkan pola tersebut** dengan format `LON-{YY}{huruf bulan A-L}-{urut 4 digit}` dan urutan global melanjutkan nomor LON terbesar existing (contoh hasil: `LON-26H-0115`). Perlu konfirmasi apakah huruf bulan pada data lama benar-benar merepresentasikan bulan input.
6. Apakah transaksi pembayaran angsuran harus selalu masuk ke `icu_transaction` dengan `trncd = 20`? Ya
   **Implementasi (Agustus 2026): trncd=20, dbocr=D (uang masuk), statrec=1, statrec2=0**, nomor transaksi memakai pola legacy `PMT-{YY}{huruf bulan}-{urut}` yang melanjutkan urutan historis pada referensi `icu_bank_trx.req_frm_trxno`.
7. Apakah pencairan pinjaman harus membuat record di `icu_bank_trx`? ya, karna ada transaksi transfer antar bank, mungkin ada admin bank
   **Implementasi (Agustus 2026): modul posting membuat record `icu_bank_trx`** dengan pola nomor existing `RCV-{YY}{huruf bulan}-{urut}` dan `req_frm_trxno` berisi nomor pinjaman.
8. Apakah skip pokok butuh approval khusus? ya
   **Implementasi (Agustus 2026): modul Skip Pokok/Refinancing dengan approval maker-checker** — pengaju tidak dapat menyetujui sendiri. Penerapan mengubah `icu_dloan` dalam satu transaksi: baris target jadi `amount=0` + remarks "Skip Principal", N baris baru ditambahkan di ekor (pokok dibagi rata, bunga flat tetap), seluruh `outstand/totseqno/dseqno` direwalk, dan header diperbarui (`term`, `endper`).
9. Apakah anggota boleh memiliki lebih dari satu pinjaman aktif? boleh, namun tidak di rekomendasikan
10. Apakah bunga tambahan dari periode skip harus masuk `interamt`, `int_overdue`, atau cukup tercermin di detail `icu_dloan`? tidak tercatat sebagai penambah unsur hutan, karna bunga yang dibayarkan saat refinancing atau skip pokok di anggap sebagai "Biaya perpanjang pinjaman"
    **Diterapkan (Agustus 2026): `interamt` dan `totalloan` TIDAK diubah saat skip** — bunga bulan tambahan hanya muncul sebagai `int_amt` baris baru di detail jadwal, sesuai prinsip biaya perpanjang pinjaman.
11. Apakah field `paid` di `icu_mloan` diperbarui dari pembayaran real atau dihitung ulang dari detail `icu_dloan`? dari pembayaran real
    **Implementasi (Agustus 2026): `icu_mloan.paid` di-increment dari nominal pembayaran terverifikasi** (bukan dihitung ulang), dan baris `icu_dloan` yang tertutp penuh ditandai `paidst=1` + `payno`. **Partial payment sudah didukung**: alokasi parsial dicatat di `coop_loan_payment_allocations`, dengan urutan komponen bunga → biaya lain → pokok (bagian 8); baris jadwal ditandai lunas hanya saat alokasi kumulatif menutup tagihannya.
12. Apakah `icu_member.outstanding` harus ikut diperbarui saat pinjaman dibuat, dibayar, atau dilunasi? ya

---

# 35. Catatan Implementasi Teknis untuk RUNITC

Module koperasi dapat ditempatkan pada struktur Laravel 12 existing RUNITC tanpa menambah framework baru.

Struktur minimal yang disarankan untuk MVP:

```text
routes/cooperative.php                          (require dari routes/web.php)
resources/views/cooperative/
├── members/index.blade.php
├── members/detail.blade.php
├── loans/index.blade.php
├── loans/detail.blade.php
├── loans/create.blade.php
├── loans/schedule_preview.blade.php
├── installments/payment.blade.php
└── reports/index.blade.php

app/Http/Controllers/Cooperative/LoanSimulationController.php
app/Services/Cooperative/LoanSimulationService.php
```

Prinsip teknis:

- Tabel `icu%` berada di database utama, diakses melalui `DB::connection('mysql')` atau properti `$connection = 'mysql'` pada model Eloquent.
- Menu, session, role, audit log, dan notifikasi RUNITC memakai `DB::connection('run')`.
- Semua operasi finansial harus memakai `DB::transaction(...)`.
- Semua nominal harus divalidasi sebagai integer rupiah.
- Semua perubahan status harus tercatat pada audit log (`sys_audit_log`).
- Jangan menghapus transaksi historis.
- Koreksi harus memakai reversal atau record koreksi, bukan delete.

---

# 36. Kesimpulan Tambahan Setelah Review Database dan Excel

Berdasarkan database existing dan contoh Excel, modul koperasi RUNITC tidak perlu dimulai dari nol.
Fondasi data utama sudah tersedia di tabel `icu%`.

Prioritas implementasi sebaiknya berubah dari membuat schema baru menjadi:

```text
Pahami tabel icu existing
    ↓
Bangun wrapper model di RUNITC
    ↓
Tampilkan data anggota dan pinjaman
    ↓
Implementasikan simulasi dan jadwal flat loan
    ↓
Tambahkan handling skip pokok/refinancing
    ↓
Tambahkan approval, audit, dan akses menu RUNITC
```

Keputusan paling penting sebelum coding finansial adalah memastikan arti field existing seperti `outstand`, `paid`, `statrec`, `paidst`, `int_overdue`, dan pola nomor transaksi.
Setelah field tersebut dipastikan, logic Excel dapat diterjemahkan ke proses pinjaman di RUNITC dengan risiko lebih rendah.
