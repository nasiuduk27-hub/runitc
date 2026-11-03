# Proses dan akun akuntansi koperasi RUNITC

Pedoman kerja untuk bendahara, pemeriksa, dan pengembang modul Credit Union. Mulailah dari bukti peristiwa ekonomi, tentukan periode pengakuannya, lalu pilih pasangan debit dan kredit. **Ekspor Excel payroll adalah kertas kerja rekonsiliasi.** Satu unduhan, satu persetujuan pengajuan, atau satu perubahan status layar tidak dengan sendirinya menimbulkan jurnal.

**Status dokumen:** rancangan pemetaan untuk ditinjau pengurus dan akuntan koperasi. Rujukan implementasi adalah repo pada commit `aa93554`. Kode akun empat digit di bawah merupakan usulan internal. Buku besar, saldo awal, perjanjian dengan HRD, AD/ART, serta kebijakan akuntansi yang disahkan belum tersedia untuk dicocokkan. Jika koperasi sudah mempunyai bagan akun di sistem lain, buat tabel padanan ke akun resmi tersebut.

## 1. Dasar pencatatan dan batas penerapan

Contoh mengasumsikan **KSP konvensional** di Indonesia. Periksa bentuk hukum, apakah unit ini KSP atau USP, otoritas pengawas, dan standar yang telah diterapkan. Permenkop UKM 2/2024 Pasal 4 mengatur penggunaan SAK Indonesia untuk Entitas Privat bagi kegiatan usaha simpan pinjam, dengan pengecualian bagi yang sudah memakai SAK Indonesia. Ayat (5) mengatur koperasi di sektor jasa keuangan menurut otoritas yang berwenang. SAK Entitas Privat efektif 1 Januari 2025. Untuk USP, verifikasi pula penyajian modal yang ditempatkan koperasi induk sebelum memakai akun ekuitas contoh. [Peraturan dan lampiran resmi](https://www.peraturan.go.id/files/permenkop-kukm-no-2-tahun-2024.pdf) · [Penjelasan IAI tentang SAK Entitas Privat](https://web.iaiglobal.or.id/SAK-IAI/Tentang%20SAK%20Entitas%20Privat%20%28EP%29)

Bayangkan satu kuitansi mempunyai dua sisi. Ketika anggota membayar pokok pinjaman Rp100.000, koperasi mendebit bank Rp100.000 dan mengkredit piutang pinjaman Rp100.000. Kas bertambah, piutang berkurang, dan hasil usaha tetap. Beberapa kejadian lain, seperti bunga yang telah menjadi hak koperasi, dapat memerlukan jurnal sebelum kas diterima.

| Prinsip | Penerapan di RUNITC |
| --- | --- |
| Pembukuan berpasangan | Jumlah debit harus sama dengan jumlah kredit pada setiap jurnal. Aset dan beban umumnya bertambah di debit. Liabilitas, ekuitas, dan pendapatan umumnya bertambah di kredit. Akun pengurang aset memiliki saldo normal kredit. |
| Akrual dan batas periode | Simpan tanggal kejadian ekonomi, tanggal bank, periode payroll, dan waktu input secara terpisah. Tentukan tanggal jurnal dari hak atau kewajiban yang timbul. Simpan bukti untuk perbedaan tanggal. |
| Substansi kontrak | Klasifikasi simpanan mengikuti hak penarikan dan ketentuan produk. Pisahkan pembayaran pokok, bunga, dan biaya berdasarkan akad serta alokasi pembayaran. |
| Jejak bukti | Setiap jurnal mempunyai nomor bukti, sumber, referensi anggota atau tagihan, pembuat, dan pemeriksa. Pembetulan atas jurnal yang sudah disahkan menggunakan jurnal pembalik atau penyesuaian yang merujuk jurnal asal. |

## 2. Bagan akun minimum yang diusulkan

Gunakan nomor anggota, perusahaan, produk simpanan, dan kontrak pinjaman sebagai **rincian buku pembantu**. Misalnya, semua piutang pokok masuk ke akun 1201, sedangkan jumlah milik setiap peminjam tetap bisa ditelusuri per kontrak. Dengan demikian satu akun kontrol dapat dicocokkan dengan seluruh anggotanya.

| Kode | Akun | Kelompok dan saldo normal | Dasar pemeriksaan |
| --- | --- | --- | --- |
| 1101 | Kas tunai | Aset · debit | Hitung fisik dan buku kas jika koperasi menerima uang tunai |
| 1102 | Bank operasional | Aset · debit | Rekening koran per rekening |
| 1110 | Piutang setoran payroll dari pemberi kerja | Aset · debit | Potongan yang sudah dikonfirmasi dan menjadi hak tagih koperasi, dirinci per perusahaan dan referensi payroll |
| 1201 | Piutang pokok pinjaman anggota | Aset · debit | Saldo pokok per kontrak, setelah pembayaran dan koreksi |
| 1202 | Piutang bunga pinjaman | Aset · debit | Perhitungan bunga terakru dikurangi pelunasan bunga |
| 1208 | Penyesuaian biaya perolehan diamortisasi | Penyesuaian aset · kredit untuk provisi integral yang diterima | Jadwal suku bunga efektif per kontrak. Biaya transaksi langsung yang memenuhi syarat dapat menghasilkan penyesuaian debit |
| 1209 | Penyisihan pinjaman tidak tertagih | Pengurang aset · kredit | Daftar pinjaman dan perhitungan penurunan nilai yang disetujui |
| 2101 | Simpanan anggota yang dapat ditarik | Liabilitas · kredit | Saldo per anggota dan produk, berikut syarat penarikannya |
| 2102 | Dana masuk belum teridentifikasi | Liabilitas sementara · kredit | Mutasi bank nyata yang belum diketahui penerima alokasinya. Sertakan umur saldo dan penanggung jawab |
| 2103 | Utang SHU kepada anggota | Liabilitas · kredit | Daftar hak anggota setelah keputusan pembagian yang sah |
| 2104 | Utang bunga simpanan | Liabilitas · kredit | Bunga simpanan yang telah menjadi kewajiban dan belum dikreditkan atau dibayar |
| 3101 / 3102 | Simpanan pokok / simpanan wajib sebagai modal | Ekuitas · kredit | Register modal anggota dan AD/ART |
| 3201 | Cadangan dari alokasi SHU | Ekuitas · kredit | Keputusan RAT dan mutasi cadangan per tahun |
| 3301 | SHU berjalan atau belum dibagi | Ekuitas · kredit untuk surplus, debit untuk defisit | Hasil penutupan pendapatan dan beban, lalu keputusan RAT |
| 4101 / 4102 | Pendapatan bunga pinjaman / bunga bank | Pendapatan · kredit | Jadwal pengakuan bunga pinjaman / bukti bunga rekening |
| 4103 | Pendapatan jasa administrasi tersendiri | Pendapatan · kredit | Bukti jasa yang benar-benar sudah diberikan dan kebijakan imbal jasa |
| 5101 / 5102 | Beban bunga simpanan / administrasi bank | Beban · debit | Perhitungan jasa simpanan / biaya pada rekening koran |
| 5103 / 5104 | Beban penurunan nilai pinjaman / beban operasional | Beban · debit | Penilaian ketertagihan / faktur atau bukti beban |
| 5105 / 1301 | Beban pajak final / pajak dibayar di muka | Beban · debit / aset · debit | Pilih sesuai sifat potongan pajak dan bukti potong yang berlaku |

**Keputusan produk sebelum menerapkan akun 2101 dan 3102.** Lampiran I membedakan simpanan yang menimbulkan kewajiban pembayaran dari simpanan pokok dan wajib KSP yang memenuhi definisi modal dan tidak dapat diambil selama masih menjadi anggota. RUNITC memakai kode 18/19 untuk setoran, menghitung saldo yang tersedia, dan menyediakan pengajuan penarikan. Karena itu, periksa jenis produk asal untuk setiap saldo historis. Jangan memetakan semua transaksi kode 19 ke modal atau mengizinkan penarikan dana modal hanya karena saldo agregat mencukupi. [Lampiran I, bagian liabilitas dan ekuitas](https://www.peraturan.go.id/files/permenkop-kukm-no-2-tahun-2024.pdf) · [Aturan saldo RUNITC](../../app/Services/CreditUnion/SavingsService.php)

## 3. Proses operasional dan pasangan jurnal

Tabel ini memakai sudut pandang koperasi. Setiap baris menjelaskan **kapan** pembukuan dilakukan. Tindakan dalam aplikasi bisa terjadi lebih dahulu atau lebih lambat. Simpan ID tindakan tersebut bersama bukti sumber.

| Peristiwa dan syarat | Debit | Kredit | Bukti atau kontrol |
| --- | --- | --- | --- |
| Anggota membayar setoran modal | Bank 1102 atau kas 1101 | 3101 atau 3102 sesuai produk | Bukti terima, persyaratan keanggotaan dan register modal |
| Anggota menyetor simpanan yang dapat ditarik | 1102 atau 1101 | Simpanan anggota 2101 | Mutasi aktual dan buku pembantu produk |
| Penarikan simpanan dibayar langsung | 2101 | 1102 atau 1101 | Permintaan, persetujuan pihak lain, bukti bayar. Pada saat pengajuan, catat reservasi operasional. Jika persetujuan melahirkan utang bayar terpisah sebelum pencairan, reklasifikasi kewajiban terlebih dahulu |
| Pengajuan pinjaman disetujui dan menunggu transfer | Belum ada jurnal pencairan | Belum ada jurnal pencairan | Simpan keputusan dan komitmen sesuai kebijakan. Pastikan belum ada kas keluar atau piutang yang diakui dua kali |
| Pinjaman dicairkan penuh tanpa biaya | Piutang pokok 1201 | Bank 1102 | Kontrak, bukti transfer, dan kaitan pengajuan ke master pinjaman |
| Bunga pinjaman telah diperoleh dalam periode | Piutang bunga 1202 atau penyesuaian nilai tercatat sesuai jadwal efektif | Pendapatan bunga 4101 | Perhitungan per kontrak. Bunga untuk seluruh tenor tidak langsung menjadi pendapatan saat pencairan |
| Anggota membayar cicilan langsung | Bank 1102 atau kas 1101 | 1201 untuk pokok dan 1202 untuk bunga yang telah diakru | Rincian alokasi per angsuran. Jika bunga baru diperoleh pada saat pembayaran dan belum diakru, kredit 4101 untuk bagian itu |
| Simpanan dipakai untuk mengurangi pokok | Simpanan yang tersedia 2101 | Piutang pokok 1201 | Persetujuan penggunaan saldo dan alokasi ke kontrak. Tidak ada arus kas |
| Biaya administrasi bank dibebankan | Beban bank 5102 | Bank 1102 | Cocokkan satu kali dengan mutasi rekening |
| Bank mengkredit bunga setelah memotong pajak | Bank 1102 sebesar neto, ditambah 5105 atau 1301 sebesar potongan | Bunga bank 4102 sebesar bruto | Contoh bruto Rp100.000, potongan Rp20.000, kenaikan bank Rp80.000. Tarif dan status pajak tidak ditentukan dari contoh |
| Bunga atas simpanan anggota terutang | Beban bunga simpanan 5101 | Utang bunga 2104 | Saat dibayar, debit 2104 dan kredit bank. Jika ditambahkan ke saldo anggota, kredit 2101 sebagai gantinya |
| Nilai pinjaman menurun berdasarkan penilaian | Beban penurunan nilai 5103 | Penyisihan 1209 | Dokumentasikan bukti objektif dan perhitungan. Saat pinjaman yang sudah disisihkan penuh dihapus sesuai kewenangan, debit 1209 dan kredit 1201. Jejak penagihan dipelihara sesuai kebijakan |
| SHU positif sudah ditutup dan RAT menetapkan pembagiannya | SHU belum dibagi 3301 | Cadangan 3201, utang SHU 2103, dan pos lain sesuai keputusan | Saat membayar bagian anggota, debit 2103 dan kredit 1102. Persentase mengikuti keputusan yang berlaku |

### Biaya pinjaman, pembayaran parsial, dan perubahan jadwal

- **Biaya pinjaman.** Lampiran I mengatur pengukuran pinjaman dan bunga dengan metode suku bunga efektif. Misalnya, kontrak mencatat pokok Rp1.000.000 dan menahan provisi integral Rp20.000 saat pencairan. Dalam contoh tanpa biaya transaksi lain, jurnal awal adalah debit 1201 Rp1.000.000, kredit 1102 Rp980.000 dan kredit 1208 Rp20.000. Nilai tercatat awal pinjaman Rp980.000. Amortisasikan penyesuaian tersebut menurut jadwal efektif. Jika biaya benar-benar untuk jasa terpisah, dokumentasikan jasanya dan akui sesuai waktu penyerahan. Label `include`, `exclude`, atau kolom `others` hanya menjelaskan cara penagihan. [Lampiran I, ketentuan pinjaman](https://www.peraturan.go.id/files/permenkop-kukm-no-2-tahun-2024.pdf) · [Posting pinjaman di RUNITC](../../app/Services/CreditUnion/LoanPostingService.php)
- **Pembayaran parsial.** RUNITC mengalokasikan pembayaran ke angsuran tertua, lalu ke bunga, biaya lain, dan pokok. Misalnya, pembayaran Rp75.000 mencakup Rp20.000 bunga terakru, Rp5.000 jasa yang baru selesai, dan Rp50.000 pokok. Debit bank Rp75.000, kredit 1202 Rp20.000, 4103 Rp5.000, dan 1201 Rp50.000. Bila biaya sudah diakru, kredit piutang biaya terkait sesuai bagan akun resmi. [Alokasi pembayaran](../../app/Services/CreditUnion/LoanPaymentService.php)
- **Penjadwalan ulang.** Bandingkan jadwal lama, jadwal baru, dana yang benar-benar diterima dan dasar persetujuan. Penundaan satu cicilan saja tidak membuktikan adanya penerimaan kas. Pengurus menilai dampak modifikasi terhadap nilai tercatat dan ketertagihan. Gunakan jurnal terpisah untuk dana baru, pemakaian simpanan, atau penyesuaian nilai yang memang terjadi. [Perubahan jadwal pinjaman](../../app/Services/CreditUnion/LoanSkipService.php)

## 4. Contoh lengkap dari potongan gaji sampai bank

**Asumsi contoh.** Rincian final HRD membuktikan bahwa pemotongan melunasi kewajiban anggota dan menimbulkan hak tagih koperasi kepada pemberi kerja. Bunga sudah diakru, simpanan adalah produk yang dapat ditarik, dan tidak ada biaya. Jika perjanjian baru membebaskan anggota saat uang diterima koperasi, pertahankan tagihan anggota sampai titik tersebut dan perlakukan daftar payroll sebelumnya sebagai bukti operasional.

| Waktu dan bukti | Debit | Kredit | Pemeriksaan |
| --- | --- | --- | --- |
| 28 Okt · daftar tagihan dikirim Rp700.000 | — | — | Arsipkan versi final. Pengiriman daftar saja belum menambah kas atau membuat piutang HRD |
| 31 Okt · HRD mengonfirmasi potongan Rp680.000 | 1110 Rp680.000 | 2101 Rp200.000, 1201 Rp400.000, 1202 Rp80.000 | Rincian anggota harus berjumlah Rp680.000. Sisa tagihan Rp20.000 ditelusuri menurut anggotanya |
| 2 Nov · rekening bank menerima Rp675.000 | 1102 Rp675.000 | 1110 Rp675.000 | Sisa piutang HRD Rp5.000. Penerimaan ini tidak menambah simpanan atau pendapatan untuk kedua kalinya |
| 4 Nov · sisa transfer teridentifikasi Rp5.000 | 1102 Rp5.000 | 1110 Rp5.000 | Saldo 1110 untuk referensi tersebut menjadi nol |

**Hubungan dengan contoh Excel.** Jika rincian yang sudah diposting di RUNITC baru Rp670.000, selisih realisasi HRD dengan buku pembantu adalah Rp10.000. Selisih bank dengan posting per 2 November adalah Rp5.000. Kedua angka mengukur hal berbeda. Temukan baris anggota yang belum atau keliru dibukukan, lalu perbaiki buku pembantu dengan merujuk jurnal payroll yang sama. Bila buku besar hanya mengambil data dari posting anggota, catat bagian yang belum dialokasikan menurut bukti dan kebijakan akun perantara. Hindari menduplikasi Rp680.000 dengan menjurnal seluruh batch lagi. [Panduan rekonsiliasi payroll](rekonsiliasi-payroll.md)

**Jika bank menerima dana lebih dahulu tanpa identitas tagihan:** debit 1102 dan kredit 2102. Setelah bukti menunjukkan pembayaran piutang HRD yang sudah diakui, debit 2102 dan kredit 1110 dengan referensi mutasi awal. Jika tidak ada piutang HRD yang pernah diakui, alokasikan 2102 langsung ke akun produk atau piutang anggota yang sesuai. Jangan memakai pendapatan lain-lain untuk menutup selisih yang belum diketahui asalnya.

## 5. Jembatan dari tabel aplikasi ke buku besar

| Sumber RUNITC | Data yang dapat dipakai | Pemeriksaan sebelum membuat jurnal |
| --- | --- | --- |
| `icu_transaction` | Nomor transaksi, anggota, periode, kode, arah mutasi, pokok dan bunga | Kode 18/19 sisi D menambah saldo simpanan menurut rumus aplikasi. Kode 19/22 sisi C menguranginya. Kode 20 dipakai untuk angsuran. Tentukan produk dan lawan akun dari bukti. Huruf `dbocr` tidak cukup untuk menentukan kedua sisi jurnal |
| `cu_loan_applications`, `icu_mloan`, `icu_dloan`, alokasi pembayaran | Keputusan, pinjaman aktual, jadwal dan pembagian pembayaran | Cocokkan `posted_loan_rec_id`, bukti pencairan, nilai pokok tersisa, dan jadwal efektif. Baris jadwal masa depan bukan pendapatan periode ini |
| `icu_mtrx2hrd`, `icu_bank_trx`, `cu_bank_transaction_details` | Tagihan payroll, referensi penerimaan, rincian bruto, pajak dan neto tertentu | Simpan versi tagihan yang dikirim dan bukti realisasi HRD. Verifikasi perusahaan per anggota. Cocokkan nomor referensi bank dan tanggal aktual dengan rekening koran |
| `cu_reconciliations` dan riwayat tindakan | Nilai aktual, nilai pembanding, alasan koreksi dan keputusan | Periksa apakah koreksi mengubah mutasi atau hanya angka `outstanding` pada master. Bentuk jurnal hanya jika ada peristiwa akuntansi dengan bukti dan belum tercatat |

Untuk implementasi buku besar, simpan ID jurnal, tanggal buku, entitas, nomor bukti, ID dan jenis peristiwa sumber, baris akun, nominal debit/kredit, dimensi anggota/kontrak/perusahaan, serta pemeriksa. Pastikan satu peristiwa dengan referensi yang sama hanya diposting sekali meski permintaan diulang. Nomor referensi pembalik harus menunjuk jurnal asal. [Alur operasional modul](modul-koperasi.md) · [Sumber koreksi rekonsiliasi](../../app/Services/CreditUnion/CreditUnionReconciliationService.php)

## 6. Tutup periode dan hasil yang diserahkan

| Urutan | Pelaksana dan pekerjaan | Hasil yang dapat diperiksa |
| --- | --- | --- |
| 1. Tetapkan batas periode | Bendahara mengarsipkan rekening koran, daftar HRD, mutasi anggota, pencairan, dan kasus koreksi terbuka per tanggal tutup | Daftar bukti bernomor, termasuk transaksi lintas bulan |
| 2. Cocokkan akun kontrol | Samakan bank dengan rekening koran setelah pos dalam perjalanan. Cocokkan 1110 per perusahaan, 1201 per kontrak, 2101 per produk/anggota, dan 3101/3102 dengan register modal | Daftar selisih memuat penyebab, umur, penanggung jawab, dan tindak lanjut. Jangan saling hapus selisih antaranggota |
| 3. Buat penyesuaian | Bendahara menyiapkan bunga terakru, amortisasi biaya integral, beban yang masih harus dibayar, serta penyisihan berdasarkan bukti. Pemeriksa meninjau perhitungan dan tanggal | Jurnal berimbang dengan bukti, persetujuan, dan hubungan ke kertas kerja |
| 4. Susun neraca saldo | Pemeriksa mencocokkan total debit dan kredit, saldo awal, mutasi, dan saldo akhir. Telusuri saldo sementara yang lama | Neraca saldo setelah penyesuaian. Kesamaan debit dan kredit belum membuktikan klasifikasi produk dan kelengkapan transaksi |
| 5. Sahkan dan arsipkan | Pengurus meninjau angka serta pengecualian. Simpan versi disetujui dan kendalikan pembukaan kembali periode | Paket bulanan, daftar koreksi setelah tutup, serta identitas pemberi persetujuan |

Untuk KSP/USP yang menggunakan SAK Entitas Privat, paket laporan keuangan meliputi posisi keuangan, perhitungan hasil usaha, perubahan ekuitas, arus kas, dan catatan atas laporan keuangan. Bentuk laporan posisi keuangan dari saldo aset, liabilitas dan ekuitas; bentuk hasil usaha dari pendapatan serta beban periode. Rekonsiliasi perubahan ekuitas dengan setoran modal, SHU dan keputusan RAT. Susun arus kas dari mutasi kas aktual, dan jelaskan kebijakan, estimasi serta rincian material dalam catatan. Rekap pinjaman dan lima tab laporan operasional RUNITC menjadi bukti pendukung. [Permenkop UKM 2/2024, Pasal 8 dan Lampiran I](https://www.peraturan.go.id/files/permenkop-kukm-no-2-tahun-2024.pdf) · [Laporan operasional di RUNITC](../../app/Http/Controllers/CreditUnion/CreditUnionReportController.php)

## 7. Keputusan sebelum mulai membukukan

1. **Pengurus dan akuntan:** sahkan kerangka pelaporan, bagan akun, jenis simpanan yang termasuk modal, dan hak penarikan setiap produk menurut AD/ART dan perjanjian.
2. **Bendahara dan HRD:** tetapkan kapan kewajiban anggota dianggap lunas, dokumen yang membuktikannya, serta pihak yang menanggung kekurangan potong atau transfer.
3. **Pengelola pinjaman dan akuntan:** tetapkan kebijakan bunga efektif, biaya integral versus jasa terpisah, penurunan nilai dan penjadwalan ulang. Dokumentasikan perlakuan pajak bank berdasarkan bukti potong.
4. **Tim implementasi:** lakukan uji satu bulan pada salinan data. Buktikan setiap jurnal seimbang, saldo kontrol sama dengan buku pembantu, transaksi lintas bulan muncul pada periode yang tepat, dan pengulangan posting tidak menggandakan jurnal.
