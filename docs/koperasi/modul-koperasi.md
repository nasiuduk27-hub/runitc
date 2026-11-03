# Dokumentasi modul koperasi RUNITC

Peta implementasi untuk pengelola koperasi dan tim pengembang

Pembacaan kode pada 3 November 2026, commit `19ecd50`. Integrasi yang ditambahkan sesudah pembacaan ini dijelaskan di [panduan rekonsiliasi payroll bulanan](rekonsiliasi-payroll.md).

Modul Credit Union menghubungkan akun RUNITC dengan data simpan pinjam anggota. Saat admin menyetujui permohonan, aplikasi mencatat keputusan. Saat admin mengonfirmasi pencairan, aplikasi membuat pinjaman dan jadwal angsurannya. Perbedaan ini menentukan tabel yang perlu diperiksa ketika anggota menanyakan status pinjaman.

### Di mana harus mulai

| Jika yang ingin ditelusuri | Baca bagian |
| --- | --- |
| Siapa boleh melihat atau menyetujui transaksi | 2 · Akun dan hak akses |
| Hubungan antara pengajuan dan mutasi keuangan | 3 · Peta data |
| Saldo tersedia, penarikan, dan kode mutasi | 4 · Simpanan |
| Pencairan, biaya, angsuran, dan perubahan jadwal | 5 dan 6 · Pinjaman |
| Tagihan HRD, mutasi bank, dan selisih periode | 7 · Operasi bulanan |
| Laporan, temuan akses, dan cara menguji | 8 dan 9 · Laporan dan verifikasi |

### Dasar pembacaan

Pada cabang ini terdapat 16 controller, 16 service, 20 model dan 38 template Blade di direktori khusus koperasi. Pembacaan juga mencakup rute, pemeriksaan akses, migrasi, ekspor, dan tes terkait. Nama modul dalam URL adalah credit-union. Tautan lama berawalan /cooperative dialihkan ke alamat baru.

Dokumen menjelaskan perilaku yang terlihat di kode. Nilai konfigurasi produksi, saldo aktual, izin pengguna nyata, dan hasil eksekusi pengujian memerlukan pemeriksaan di lingkungan yang bersangkutan. Seluruh contoh angka di sini adalah ilustrasi.

Rujukan kode — [routes/credit-union.php L22](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/routes/credit-union.php#L22) · [app/Services/CreditUnion/LoanPostingService.php L33](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanPostingService.php#L33)

## 2   Akun dan hak akses

Akun RUNITC dan nomor anggota koperasi adalah dua identitas terpisah. Aplikasi mencari anggota melalui icu_member.itc_user_id. Seseorang dapat mempunyai peran admin sekaligus rekening anggota. Halaman personal mendukung pemeriksaan transaksi miliknya sendiri.

| Aktor atau kondisi | Perilaku yang diterapkan |
| --- | --- |
| Anggota tertaut | Pada pengajuan pribadi, server memakai ID anggota milik akun yang login. Anggota dapat melihat riwayat pribadi, mengatur nominal simpanan wajib, serta mengajukan penarikan atau pinjaman. |
| Admin CU atau Super Admin yang cocok dengan aturan | Dapat mengelola anggota, proses bulanan, transaksi manual, bank, dan rekonsiliasi. Untuk persetujuan tertentu, server juga memeriksa siapa pembuat dan pemilik pengajuan. |
| Anggota berstatus 6 | Middleware menolak akses modul dengan HTTP 403. Terdapat pengecualian untuk admin dan rute verifikasi sinkronisasi. Status 0 sampai 5 punya arti berbeda meskipun lolos pemeriksaan nonaktif. |
| Akun login tanpa anggota tertaut | Middleware status anggota bisa meloloskan akun ini. Setiap endpoint tetap perlu memeriksa peran atau kepemilikan. Misalnya, pengajuan pinjaman pribadi mensyaratkan anggota tertaut. |

### Menautkan akun anggota

1. Dari halaman anggota, admin memilih anggota dan akun RUNITC tujuan yang aktif serta memiliki email. Sistem mencatat permintaan pending di cu_sync_request, membuat token referensi, lalu mengirim kode OTP yang berlaku lima menit.

2. Saat formulir dikirim, server memeriksa token, status, kedaluwarsa, dan kecocokan OTP. Setelah berhasil, proses memperbarui icu_member.itc_user_id, memastikan peran CU Member 01/01, serta mencatat perubahan pada riwayat.

3. Periksa ID akun tujuan, hubungan ke anggota, dan akses dashboard pribadi. Jika proses terhenti setelah OTP diterima, bandingkan status permintaan dengan hubungan akun dan peran yang benar-benar tersimpan.

Pemisahan tugas berlaku per tindakan. Dalam keputusan pinjaman, admin yang membuat pengajuan atau memiliki pinjaman tidak boleh menyetujuinya. Penanda persetujuan HOD mempunyai pemeriksaan tersendiri pada bagian 5.

Rujukan kode — [app/Support/CreditUnionAccess.php L23](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Support/CreditUnionAccess.php#L23) · [app/Http/Middleware/CreditUnionMemberActive.php L16](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Middleware/CreditUnionMemberActive.php#L16) · [app/Http/Controllers/CreditUnion/MemberController.php L481](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/MemberController.php#L481) · [app/Http/Controllers/CreditUnion/LoanApplicationController.php L120](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/LoanApplicationController.php#L120)

## 3   Peta data dan kunci penelusuran

Bayangkan meja administrasi menyimpan formulir keputusan, sementara petugas pembukuan mencatat hasilnya di buku transaksi. Koneksi run menangani banyak formulir dan jejak tindakan. Koneksi mysql menyimpan data anggota, pinjaman, jadwal, dan mutasi lama. Keduanya bisa ditulis dalam satu aksi pengguna, dengan transaksi database yang terpisah.

| Objek | Lokasi dan hubungan yang berguna saat menelusuri data |
| --- | --- |
| Anggota dan akun | mysql.icu_member.rec_id menjadi ID anggota. Kolom itc_user_id menautkan anggota ke akun di run.sysitc_users. Gunakan ID untuk pencocokan karena nama dapat sama. |
| Pengajuan dan pinjaman | run.cu_loan_applications.member_rec_id menunjuk anggota. Setelah posting, posted_loan_rec_id merujuk mysql.icu_mloan.rec_id. Jadwal di mysql.icu_dloan memakai mst_rec_id untuk menunjuk pinjaman. |
| Pembayaran dan alokasi | run.cu_loan_payments merekam pembayaran. cu_loan_payment_allocations menyimpan payment_id, dloan_rec_id dan nominal yang dialokasikan. Satu pembayaran dapat mencakup beberapa angsuran. |
| Simpanan dan penarikan | run.cu_savings dan cu_savings_withdrawals menyimpan informasi proses. Nomor savings_trnno atau withdrawal_trnno menghubungkan pencatatan ke mutasi mysql.icu_transaction.trnno. |
| Tagihan HRD dan rekening bank | mysql.icu_mtrx2hrd.trxno dapat ditautkan dari mysql.icu_bank_trx.req_frm_trxno. Untuk kategori bank tertentu, run.cu_bank_transaction_details.bank_trx_id menunjuk ke baris bank. |
| Rekonsiliasi dan audit | run.cu_reconciliations menyimpan nilai pembanding, nilai aktual dan selisih. Tabel tindakan terkait merekam keputusan. Layar audit CU membaca run.sys_audit_log untuk aksi berawalan cu. |

### Membaca migrasi lama

Migrasi 15 September 2026 mengubah nama tabel aplikasi dari coop_ menjadi cu_ dan memperbarui menu terkait. Tabel lama berawalan icu_ tetap dipakai pada koneksi mysql. Data yang berada di dua koneksi harus dicocokkan melalui ID atau referensi transaksi di atas.

Rujukan kode — [database/migrations/2026_09_15_000001_rename_coop_tables_to_cu.php L20](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/database/migrations/2026_09_15_000001_rename_coop_tables_to_cu.php#L20) · [app/Services/CreditUnion/LoanPostingService.php L33](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanPostingService.php#L33) · [app/Services/CreditUnion/LoanPaymentService.php L197](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanPaymentService.php#L197) · [app/Http/Controllers/CreditUnion/CreditUnionAuditLogController.php L51](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/CreditUnionAuditLogController.php#L51)

## 4   Simpanan dan penarikan

Nominal simpanan wajib pada profil anggota, saldo tercatat, dan saldo yang bisa ditarik mempunyai arti berbeda. Mengubah swajib mengubah nominal setoran rutin anggota. Saldo berubah setelah ada mutasi yang masuk ke perhitungan.

| Perhitungan | Aturan pada implementasi |
| --- | --- |
| Saldo simpanan | Jumlah amount dengan dbocr D dan kode 18 atau 19, dikurangi jumlah dengan dbocr C dan kode 19 atau 22. Kode 19 muncul di kedua sisi, sehingga arah transaksi wajib dibaca. |
| Saldo tersedia | Maksimum antara nol dan saldo simpanan dikurangi total penarikan berstatus submitted serta saldo minimum mengendap dari pengaturan CU. |
| Setoran bulanan | Proses posting memakai swajib untuk periode YYYYMM, metode potong_gaji, tanggal pencatatan tanggal 28, dan kode 19 sisi D. Nomor transaksi disimpan bersama catatan setoran. |

Contoh. Jika saldo tercatat Rp1.000.000, permintaan yang masih submitted Rp250.000, dan minimum mengendap Rp100.000, nominal yang tersedia untuk pengajuan baru adalah Rp650.000.

### Dari permintaan ke mutasi

1. Anggota memasukkan nominal serta rekening tujuan. Sistem memeriksa saldo tersedia dan menyimpan salinan informasi rekening pada pengajuan penarikan.

2. Pemilik atau pembuat dapat membatalkan pengajuan yang masih menunggu. Admin lain dapat menyetujui atau menolak. Saat menyetujui, aplikasi memeriksa kembali saldo dan minimum mengendap.

3. Server mengubah submitted menjadi processing secara bersyarat agar dua keputusan untuk pengajuan yang sama tidak sama-sama diproses. Setelah posting berhasil, periksa nomor mutasi, status approved dan riwayat tindakan.

Jika muncul pesan bahwa transaksi sudah diposting tetapi pencatatan status gagal, simpan nomor transaksi dari pesan itu. Cocokkan mutasi di mysql dengan status di run sebelum mengulangi tindakan. Penguncian pada satu pengajuan juga berbeda dari penguncian saldo untuk dua pengajuan anggota yang sama.

Rujukan kode — [app/Services/CreditUnion/SavingsService.php L192](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/SavingsService.php#L192) · [app/Services/CreditUnion/SavingsService.php L85](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/SavingsService.php#L85) · [app/Http/Controllers/CreditUnion/SavingsController.php L124](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/SavingsController.php#L124) · [app/Http/Controllers/CreditUnion/SavingsController.php L199](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/SavingsController.php#L199)

## 5   Pengajuan dan pencairan pinjaman

Jalur pengajuan baru menetapkan metode pencairan transfer di server. Aplikasi menyimpan anggota, parameter simulasi, jadwal hasil perhitungan, email kepala divisi (HOD), dan data rekening saat diajukan. Salinan jadwal ini menjadi dasar posting pinjaman.

| Status awal | Tindakan dan hasil |
| --- | --- |
| submitted | Admin lain menyetujui menjadi approved atau menolak menjadi rejected. Pembuat atau pemilik dapat membatalkan menjadi cancelled. |
| approved | Admin lain memulai proses transfer menjadi transfer_processing atau membatalkan persetujuan. Pada tahap ini pengajuan masih terpisah dari pinjaman aktual. |
| transfer_processing | Admin lain dapat mengembalikan ke approved atau mengonfirmasi pencairan menjadi posted. Untuk pencairan via transfer, bukti JPG, JPEG, PNG, atau PDF wajib diunggah, maksimum 5 MB. |
| posted | Service membuat master icu_mloan dan baris jadwal icu_dloan, menaikkan outstanding anggota, lalu menautkan posted_loan_rec_id. Detail pengajuan menyediakan akses bukti bagi pihak yang berwenang. |

### Simulasi dan biaya

Metode flat memakai bunga bulanan yang tetap atas pokok awal. Metode efektif menghitung bunga atas sisa pokok. Anuitas menghitung angsuran berkala yang relatif tetap dengan komposisi pokok dan bunga yang berubah. Jadwal memakai pembulatan rupiah dan menyesuaikan baris terakhir.

Untuk rekening dengan kode Mandiri yang dikenali, perhitungan pengajuan menetapkan biaya admin Rp0. Untuk bank lain, nilai berasal dari pengaturan CU. Pada tipe include, biaya dikurangi dari dana cair. Pada exclude, biaya ditagihkan pada angsuran pertama melalui kolom others.

### Arti penanda HOD

Admin mencatat persetujuan HOD dengan waktu dan ID pelaku. Pengajuan baru mewajibkan email HOD. Pada jalur persetujuan dan posting yang ditelusuri, nilai hod_approved_at tidak menjadi syarat transisi. Jika SOP mewajibkannya sebelum pencairan, aturan operasional itu perlu diperiksa secara terpisah.

Rujukan kode — [app/Services/CreditUnion/LoanApplicationService.php L40](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanApplicationService.php#L40) · [app/Http/Controllers/CreditUnion/LoanApplicationController.php L547](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/LoanApplicationController.php#L547) · [app/Http/Controllers/CreditUnion/LoanApplicationController.php L848](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/LoanApplicationController.php#L848) · [app/Services/CreditUnion/LoanSimulationService.php L40](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanSimulationService.php#L40) · [app/Services/CreditUnion/CreditUnionBankService.php L61](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/CreditUnionBankService.php#L61)

## 6   Angsuran dan perubahan jadwal

Pembayaran angsuran memiliki status sendiri. Alur verifikasi biasa memulai pembayaran sebagai submitted. Keputusan dapat mengubahnya menjadi verified, rejected, atau cancelled sesuai pelaku dan aturan. Posting bulanan menjalankan jalur otomatis tersendiri.

### Cara pembayaran dialokasikan

Service mengambil baris jadwal yang belum lunas menurut urutan angsuran. Alokasi submitted dan verified yang sudah ada diperhitungkan saat mencari sisa. Pada setiap baris, urutan pembagian adalah bunga, biaya lain, lalu pokok. Alokasi parsial disimpan agar pembayaran berikutnya dapat memperhitungkan sisa.

Contoh untuk satu baris tanpa pembayaran sebelumnya. Jika bunga Rp20.000, biaya lain Rp5.000, dan pokok Rp100.000, pembayaran Rp75.000 menutup bunga, biaya, serta Rp50.000 pokok. Sisa tagihan baris itu Rp50.000. Periksa alokasi dan status jadwal sebelum menyatakan angsuran lunas.

| Mode perubahan | Apa yang berubah |
| --- | --- |
| skip | Menunda pokok pada jendela periode dan memperpanjang jadwal sesuai rencana yang dihitung. |
| accelerate | Memperpendek tenor dengan penghitungan ulang jadwal. Tinjau simulasi sebelum dan sesudah perubahan. |
| savings | Menggunakan simpanan untuk mengurangi pinjaman. Telusuri mutasi pengurangan simpanan bersama perubahan jadwal. |
| transfer | Verifikasi penerimaan dana terlebih dahulu hingga status paid. Admin yang menerapkan perubahan harus berbeda dari verifikator dana serta memenuhi aturan pembuat dan pemilik. |

### Transaksi historis

Admin juga memiliki rute input manual untuk pinjaman, simpanan, penarikan, dan pembayaran historis. Jalur pinjaman menyediakan template dan impor. Penyesuaian manual memiliki alur tersendiri. Saat menelusuri transaksi lama, periksa sumber manual dan periode yang dimasukkan sebelum menyimpulkan bahwa riwayatnya berasal dari formulir pengajuan reguler.

Rujukan kode — [app/Services/CreditUnion/LoanPaymentService.php L81](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanPaymentService.php#L81) · [app/Services/CreditUnion/LoanPaymentService.php L197](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanPaymentService.php#L197) · [app/Services/CreditUnion/LoanSkipService.php L581](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanSkipService.php#L581) · [app/Services/CreditUnion/ManualLoanService.php L48](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/ManualLoanService.php#L48) · [app/Services/CreditUnion/ManualSavingsService.php L28](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/ManualSavingsService.php#L28)

## 7   Proses bulanan dan rekonsiliasi

Pekerjaan bulanan mencakup tiga catatan yang dapat berbeda waktu, yaitu tagihan yang disiapkan untuk HRD, dana yang diterima di bank, dan transaksi yang dibukukan untuk anggota. Gunakan periode yang sama saat membandingkan ketiganya.

| Langkah admin | Hasil yang bisa diperiksa |
| --- | --- |
| 1 · Susun tagihan HRD | Monthly Processing menghitung tagihan untuk periode YYYYMM dan menyediakan ekspor XLSX. Simpan membuat atau memperbarui icu_mtrx2hrd untuk pasangan periode dan perusahaan. Simpan ulang dapat mengubah nominal pada nomor tagihan yang sama. |
| 2 · Catat mutasi bank | Transaksi Bank mencatat penerimaan atau pengeluaran di icu_bank_trx. Penerimaan dapat merujuk nomor tagihan HRD. Biaya admin bank dan bunga bank memiliki detail tambahan. Bunga bersih dihitung dari bruto dikurangi pajak. |
| 3 · Posting transaksi anggota | Tindakan postMonthly dari menu bank menjalankan setoran wajib yang memenuhi syarat dan angsuran belum lunas pada periode terpilih. Simpanan mensyaratkan status 1–5, swajib positif, kelayakan tanggal bergabung, dan belum tercatat pada periode itu. |
| 4 · Periksa hasil | Bandingkan jumlah simpanan, jumlah angsuran, nominal, dan daftar baris yang dilewati. Sebagian baris dapat selesai sebelum baris lain gagal. Periksa mutasi masing-masing sebelum mengulang proses. |

### Apa yang dikoreksi oleh rekonsiliasi

Bank bulanan membandingkan tagihan HRD dengan mutasi debit yang merujuk tagihan itu. Rekonsiliasi simpanan membandingkan saldo anggota dan membuat mutasi koreksi ketika disetujui. Rekonsiliasi pinjaman memperbarui angka outstanding pada master anggota. Perubahan tersebut tidak otomatis menyusun ulang jadwal angsuran.

Pengusul memasukkan nilai pembanding dan alasan. Admin lain memutuskan koreksi. Saat persetujuan, service mengklaim kasus berstatus submitted dan memeriksa ulang nilai aktual. Keputusan sekaligus untuk beberapa kasus memakai batch_ref. Telusuri hasil per kasus bila ada kegagalan.

Perhatian untuk beberapa perusahaan. Nomor tagihan tersimpan menurut periode dan perusahaan, tetapi perhitungan unpostedTotals menerima periode saja. Sebelum menyimpan tagihan periode yang sama untuk dua perusahaan, cocokkan daftar anggota dan nominal masing-masing di lingkungan uji.

Rujukan kode — [app/Services/CreditUnion/MonthlyProcessingService.php L144](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/MonthlyProcessingService.php#L144) · [app/Services/CreditUnion/MonthlyPostingService.php L36](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/MonthlyPostingService.php#L36) · [app/Http/Controllers/CreditUnion/BankTransactionController.php L260](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/BankTransactionController.php#L260) · [app/Services/CreditUnion/CreditUnionReconciliationService.php L311](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/CreditUnionReconciliationService.php#L311) · [app/Services/CreditUnion/CreditUnionReconciliationService.php L399](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/CreditUnionReconciliationService.php#L399)

## 8   Laporan dan hal yang perlu diverifikasi

Laporan menyediakan lima tab, yaitu simpanan, jatuh tempo, pinjaman, pendapatan bunga, dan bank. Controller yang sama menangani tampilan dan ekspor. Audit koperasi mempunyai layar terpisah dengan filter aksi, pencarian dan rentang tanggal. Pengaturan modul mencakup bunga, metode pinjaman, biaya admin, saldo minimum, serta rekening koperasi.

### Akses laporan lintas anggota

Rute GET /credit-union/reports berada di luar grup cu.admin. Pemeriksaan umum legacy.auth dan cu.member.active tetap berlaku. Controller laporan mengelompokkan data menurut anggota tanpa membatasi query simpanan ke anggota yang login. Akses URL dan ekspor dengan akun biasa perlu diuji terhadap kebijakan kerahasiaan koperasi.

### Definisi angka pada tab simpanan

Query laporan simpanan menjumlahkan debit dan kredit per anggota dalam rentang periode tanpa filter trncd. Sebaliknya, saldo di SavingsService hanya memperhitungkan kombinasi kode dan arah tertentu. Jika transaksi non-simpanan masuk dalam periode itu, total laporan dapat berbeda. Untuk menjelaskan selisih, kelompokkan kembali mutasi per kode dan arah.

### Jika posting pinjaman terputus

Saat pencairan, controller membuka transaksi run dan service melakukan transaksi mysql untuk master, jadwal dan outstanding. Status diklaim secara bersyarat dan nomor pinjaman dilindungi kunci pada koneksi mysql. Jika mysql sudah menyelesaikan transaksi lalu run gagal menyimpan referensi, data kedua sisi bisa berbeda. Periksa pengajuan, master pinjaman, jadwal, dan outstanding sebelum mencoba posting ulang.

### Batas implementasi terhadap rancangan eKoperasi

Dokumen analisis membahas jurnal umum, buku besar, neraca saldo, pembagian SHU dan RAT. Pada peta rute, controller, service dan tampilan koperasi yang dipindai, belum ditemukan alur khusus untuk proses tersebut. Laporan pendapatan bunga dan mutasi bank yang ada tidak cukup untuk menyatakan seluruh siklus akuntansi atau pembagian SHU sudah tersedia.

Rujukan kode — [routes/credit-union.php L126](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/routes/credit-union.php#L126) · [app/Http/Controllers/CreditUnion/CreditUnionReportController.php L48](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Http/Controllers/CreditUnion/CreditUnionReportController.php#L48) · [app/Services/CreditUnion/SavingsService.php L192](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/SavingsService.php#L192) · [app/Services/CreditUnion/LoanPostingService.php L33](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/app/Services/CreditUnion/LoanPostingService.php#L33) · [Analisis_Fitur_Admin_eKoperasi_untuk_RUNITC.md L708](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/Analisis_Fitur_Admin_eKoperasi_untuk_RUNITC.md#L708)

## 9   Verifikasi di lingkungan uji

Gunakan salinan database dengan data sintetis serta akun untuk anggota A, anggota B, admin pembuat, dan admin pemeriksa. Siapkan kedua koneksi mysql dan run. Skenario berikut menguji perilaku yang penting saat saldo, identitas, atau status keputusan berubah.

| Skenario | Pemeriksaan dan hasil yang diharapkan |
| --- | --- |
| Hak akses laporan | Masuk sebagai anggota A. Akses URL laporan dan ekspornya secara langsung, lalu cari anggota B. Untuk kebijakan akses pribadi, data B harus tidak tersedia. Catat respons aktual dan periksa juga akun login tanpa anggota tertaut. |
| Keputusan dan pencairan | Ajukan pinjaman untuk A. Uji penolakan keputusan oleh pembuat atau pemilik. Gunakan admin lain untuk menyetujui, mulai transfer, lalu unggah bukti. Cocokkan satu posted_loan_rec_id dengan satu master dan jadwal yang sesuai. |
| Penarikan serentak | Buat dua permintaan anggota yang sama dari sesi berbeda. Jalankan persetujuan berdekatan. Periksa saldo akhir, saldo minimum, jumlah mutasi dan status kedua permintaan. Uji juga klik ganda pada satu permintaan. |
| Pembayaran parsial | Bayar sebagian angsuran, lalu lakukan pembayaran berikutnya. Cocokkan komponen bunga, biaya dan pokok di kedua pembayaran, sisa per baris, status lunas dan outstanding anggota. |
| Periode dan posting ulang | Pilih dua perusahaan pada bulan yang sama. Bandingkan tagihan dengan daftar anggota yang seharusnya. Ulangi batch di data uji dan pastikan tidak muncul mutasi simpanan atau pembayaran kedua untuk tagihan yang sudah selesai. |
| Gagal di antara dua koneksi | Pada lingkungan terisolasi, simulasikan kegagalan sesudah commit mysql dan sebelum commit run. Cari pinjaman tanpa tautan atau status tanpa pasangan mutasi. Cocokkan kedua sisi sebelum mengizinkan percobaan ulang. |

### Pengujian yang tersedia di repo

Tes fitur mencakup pemrosesan transfer, bukti transfer, penanda HOD, keputusan pembayaran, dan laporan bank. Tes unit memeriksa antara lain alokasi, pembulatan jadwal, biaya Mandiri, perubahan tenor, periode dan aturan akses. Setelah dependensi uji tersedia, jalankan vendor/bin/phpunit tests/Feature/CreditUnion dan vendor/bin/phpunit tests/Unit/Services/CreditUnion dari akar repo. Kedua perintah ini menargetkan subdirektori tertentu.

Pada pemindaian ini vendor/autoload.php tidak tersedia. Tes aplikasi, simulasi gagal sebagian, dan akses langsung dengan akun nyata belum dijalankan. Hasil pembacaan kode dan hasil uji operasional perlu dicatat terpisah.

Rujukan kode — [tests/Feature/CreditUnion/LoanApplicationTransferTest.php L18](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/tests/Feature/CreditUnion/LoanApplicationTransferTest.php#L18) · [tests/Feature/CreditUnion/LoanApplicationTransferProofTest.php L22](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/tests/Feature/CreditUnion/LoanApplicationTransferProofTest.php#L22) · [tests/Feature/CreditUnion/CreditUnionBankReportTest.php L18](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/tests/Feature/CreditUnion/CreditUnionBankReportTest.php#L18) · [tests/Unit/Services/CreditUnion/LoanPaymentServiceTest.php L12](https://github.com/nasiuduk27-hub/runitc/blob/19ecd50662e32652136fb1a493d2ebe8f3858b52/tests/Unit/Services/CreditUnion/LoanPaymentServiceTest.php#L12)
