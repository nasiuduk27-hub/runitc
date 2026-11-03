# Rekonsiliasi payroll bulanan

Bendahara dapat membandingkan tagihan final ke HRD, potongan aktual payroll, penerimaan bank, dan nominal yang telah dibukukan ke anggota. Admin Credit Union membuat satu berkas Excel untuk satu periode dan satu perusahaan. Semua angka dalam ekspor ini berasal dari dua CSV yang diunggah. Membuat laporan tidak memposting transaksi ke database.

## Membuat laporan

1. Buka **Credit Union → Monthly Processing → Rekonsiliasi payroll dari CSV** menggunakan akun admin CU.
2. Unduh contoh CSV anggota dan bank. Ganti data contoh dengan data yang telah diperiksa. Pastikan kode perusahaan berasal dari daftar karyawan HRD. Arsipkan versi tagihan yang benar-benar dikirim.
3. Pilih periode dan perusahaan, unggah kedua CSV, lalu isi **Rujukan sumber data** dengan versi payroll dan bukti bank yang digunakan.
4. Klik **Unduh rekonsiliasi Excel**. Buka lembar **Ringkasan** untuk melihat selisih, **Anggota** untuk menelusuri nominal per orang, dan **Bank** untuk memeriksa mutasi pendukung. Aplikasi pembuka perlu menghitung ulang rumus Excel.

## Format CSV

Simpan berkas sebagai UTF-8 dengan pemisah titik koma. Pertahankan nama dan urutan kolom dari contoh. Masukkan rupiah sebagai bilangan bulat tanpa simbol atau pemisah ribuan. Setiap CSV dibatasi 2 MB dan setiap lembar dibatasi 5.000 rekaman yang cocok dengan periode serta perusahaan pilihan. Kedua CSV harus memuat data untuk kombinasi tersebut. ID anggota dan referensi bank diseragamkan menjadi huruf kapital pada hasil ekspor Excel.

| Berkas | Satu baris mewakili | Kolom sesuai urutan |
| --- | --- | --- |
| [Anggota](../../resources/templates/credit-union/contoh-anggota.csv) | Satu anggota per perusahaan dan periode. Gabungkan beberapa angsuran milik anggota yang sama. | `periode;perusahaan;id_anggota;nama;simpanan;pokok;bunga_biaya;lunas_sebelum;payroll;posting;catatan` |
| [Bank](../../resources/templates/credit-union/contoh-bank.csv) | Satu mutasi atau alokasi dari mutasi gabungan. Gunakan referensi unik untuk tiap alokasi. | `periode;perusahaan;referensi;nominal;bukti` |

**Tagihan bersih** dihitung dari simpanan, pokok, dan bunga beserta biaya, dikurangi `lunas_sebelum`. Kolom terakhir ini memuat pelunasan sebelum tagihan final dikirim. Jika nominal komponen sudah dikurangi pelunasan tersebut, isi `lunas_sebelum` dengan 0. Kolom `payroll` berasal dari konfirmasi HRD. Kolom `posting` hanya memuat mutasi anggota yang terkait dengan potongan tersebut.

**Penerimaan bank bersih** menjumlahkan nominal bertanda positif untuk penerimaan dan negatif untuk pembalikan. Gunakan periode tagihan walaupun transfer diterima pada bulan berikutnya. Jika satu transfer membayar beberapa perusahaan, simpan bukti pembagian dan pastikan jumlah alokasinya sama dengan mutasi asal. Masukkan 0 hanya jika bukti mengonfirmasi nihil.

## Memeriksa hasil

Dengan CSV contoh, periode `202610`, dan perusahaan `AAA`, hasil yang diharapkan adalah sebagai berikut. Jika kode contoh tidak tersedia di master perusahaan lingkungan uji, sesuaikan kode pada baris contoh di kedua CSV.

| Tahap | Nilai contoh | Selisih terhadap tahap berikutnya |
| --- | ---: | ---: |
| Tagihan bersih | Rp700.000 | Rp20.000 belum terpotong oleh HRD |
| Realisasi payroll | Rp680.000 | Rp5.000 belum diterima di bank |
| Penerimaan bank | Rp675.000 | Rp5.000 belum dibukukan ke anggota |
| Pembukuan anggota | Rp670.000 | — |

Jika hasilnya **Telusuri selisih**, periksa anggota dan mutasi yang bersangkutan. Dua anggota dapat mempunyai selisih berlawanan yang saling menutup di ringkasan. Jika unggahan ditolak, gunakan pesan validasi untuk memperbaiki CSV lalu pilih ulang kedua berkas.

## Pengujian pengembang

Jalankan tes fitur khusus dari akar repo setelah dependensi Composer tersedia. Tes ini mengarahkan koneksi `mysql` dan `run` ke SQLite dalam memori.

```shell
php vendor/bin/phpunit tests/Feature/CreditUnion/PayrollReconciliationExportTest.php
```

Tes mencakup akses tamu dan anggota biasa, unduhan contoh oleh admin, penyaringan periode dan perusahaan, nominal kosong, ID ganda, serta penyalinan rumus melewati 30 baris awal template. [Tes fitur](../../tests/Feature/CreditUnion/PayrollReconciliationExportTest.php) · [Template Excel](../../resources/templates/credit-union/payroll-reconciliation.xlsx)
