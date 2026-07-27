# Rencana Perubahan Filing System

## Tujuan

Modul Filing System akan dipindahkan dari struktur tabel lama ke struktur tabel baru yang lebih sederhana.

Tabel baru yang digunakan:

- `file_system`
- `file_shareto`
- `sys_msttable`
- `sysitc_serialno`

Tabel lama yang saat ini masih digunakan oleh sistem:

- `sys_filing`
- `sys_filing_access`
- `sys_filing_share`
- `sys_filing_audit`

## Struktur Tabel Baru

### `file_system`

Tabel `file_system` akan menjadi tabel utama untuk menyimpan metadata file.

| Field           | Fungsi                     |
| --------------- | -------------------------- |
| `rec_id`        | ID file                    |
| `trxno`         | Nomor file                 |
| `trxdt`         | Tanggal transaksi/upload   |
| `remind_exp`    | Penanda reminder expired   |
| `expiredt`      | Tanggal expired            |
| `warningdt`     | Tanggal warning expired    |
| `file_name`     | Nama file tampil           |
| `folder_loc`    | Kode lokasi folder         |
| `upload_flnm`   | Nama file fisik di storage |
| `file_type`     | Tipe file                  |
| `client_nm`     | Nama client                |
| `file_notes`    | Catatan file               |
| `file_size`     | Ukuran file asli           |
| `file_zip_size` | Ukuran file ZIP            |
| `userid`        | User upload                |
| `depcd`         | Kode department            |
| `create_dt`     | Tanggal dibuat             |
| `userid_upd`    | User update                |
| `lupdt`         | Tanggal update             |

### `file_shareto`

Tabel `file_shareto` akan digunakan untuk menyimpan target share atau permission file.

| Field        | Fungsi                            |
| ------------ | --------------------------------- |
| `rec_id`     | ID share                          |
| `filesys_id` | ID file dari `file_system.rec_id` |
| `share_cat`  | Kategori share                    |
| `othercode`  | Kode target share                 |

Kategori `share_cat`:

| share_cat | Arti |
|---|---|
| 0 | Private |
| 1 | Personal |
| 2 | Department |
| 3 | Company |
| 4 | All User |

Penggunaan `othercode` mengikuti kategori share:

| share_cat | Isi `othercode` |
|---|---|
| 0 | Kosong atau `0` karena hanya private/owner |
| 1 | ID user tujuan |
| 2 | Kode department |
| 3 | Kode company |
| 4 | Kosong atau `0` karena berlaku untuk semua user |

## Master Lokasi Folder

Lokasi folder file tidak lagi ditentukan langsung dari kode program.

Folder aktif akan dibaca dari tabel `sys_msttable` dengan kondisi:

```sql
tbl_code = '81'
AND statrec = 1
```

Field yang digunakan:

| Field       | Fungsi                       |
| ----------- | ---------------------------- |
| `notes`     | Path folder penyimpanan file |
| `AddiNotes` | Kode lokasi folder           |
| `statrec`   | Penanda folder aktif         |

Contoh data:

| code | notes              | AddiNotes | statrec |
| ---- | ------------------ | --------- | ------- |
| CID  | `/filesys/cid26b/` | AC        | 1       |

Artinya, file baru akan disimpan ke folder aktif tersebut.

## Nomor File

Nomor file akan dibuat menggunakan tabel `sysitc_serialno` dengan mengikuti pola function lama FoxPro `GetSernoWeb`.

Pada function lama, data serial dibaca menggunakan `key_code`, bukan langsung menggunakan `owner`.

Data yang digunakan:

```sql
owner = 'FSY'
key_code = '80'
```

Catatan:

- `owner = 'FSY'` digunakan sebagai kategori/pemilik serial Filing System.
- `key_code = '80'` digunakan sebagai kode serial yang dibaca oleh function serial lama.

Contoh data serial:

| Field             | Value |
| ----------------- | ----- |
| `owner`           | FSY   |
| `key_code`        | 80    |
| `pre_serno`       | FSY   |
| `mid_serno`       | 25L   |
| `last_number`     | 16    |
| `length_last_no`  | 5     |
| `tipe_serno`      | 2     |
| `sparerator`      | -     |
| `mid_sparerator`  | .     |
| `last_sparerator` |       |

### Pola Function Lama `GetSernoWeb`

Function FoxPro lama membentuk nomor dengan alur berikut:

1. Ambil `pre_serno` sepanjang `pre_length`.
2. Ambil `mid_serno` sepanjang `mid_length`.
3. Gabungkan `pre_serno`, `mid_serno`, dan nomor urut menggunakan `sparerator`.
4. Nomor urut berasal dari `last_number`.
5. Panjang nomor urut mengikuti `length_last_no`.

Field `mid_sparerator` dan `last_sparerator` tidak digunakan oleh function `GetSernoWeb` yang lama.

Dengan data saat ini:

```text
pre_serno = FSY
mid_serno = 25L
last_number = 16
length_last_no = 5
sparerator = -
```

Nomor yang dibentuk dari `last_number = 16` adalah:

```text
FSY-25L-00016
```

Jika proses upload menaikkan `last_number` menjadi `17`, maka nomor berikutnya adalah:

```text
FSY-25L-00017
```

### Catatan Concurrency

Function `GetSernoWeb` hanya membentuk nomor dari data serial yang tersedia. Agar upload bersamaan tidak menghasilkan nomor yang sama, proses generate nomor perlu melakukan lock dan increment pada `sysitc_serialno`.

Flow aman yang disarankan:

1. Mulai transaction.
2. Lock row `sysitc_serialno` berdasarkan `key_code = '80'`.
3. Ambil `last_number`.
4. Naikkan `last_number`.
5. Bentuk nomor file sesuai pola `GetSernoWeb`.
6. Simpan nomor ke `file_system.trxno`.
7. Commit transaction.

Dengan flow tersebut, jika tiga user upload bersamaan, hasil nomor tetap berbeda:

| User   | Nomor         |
| ------ | ------------- |
| User A | FSY-25L-00017 |
| User B | FSY-25L-00018 |
| User C | FSY-25L-00019 |

Nomor ini akan disimpan ke field:

```text
file_system.trxno
```

## Alur Upload File

Proses upload:

1. User memilih file.
2. Sistem membaca folder aktif dari `sys_msttable`.
3. Sistem membuat nomor file dari `sysitc_serialno`.
4. Jika user upload 1 file, file disimpan dalam bentuk asli dan tidak dibuat ZIP.
5. Jika user upload lebih dari 1 file, sistem membuat 1 file ZIP berisi semua file tersebut.
6. File hasil akhir dikirim ke folder storage aktif.
7. Metadata file disimpan ke `file_system`.

Aturan penyimpanan file:

| Kondisi Upload | File Yang Disimpan | Contoh Nama Fisik |
|---|---|---|
| 1 file PDF | File asli | `FSY-25L-00017.pdf` |
| 1 file JPG | File asli | `FSY-25L-00018.jpg` |
| Lebih dari 1 file | File ZIP | `FSY-25L-00019.zip` |

Nama fisik file di storage dibuat dari nomor serial `trxno` ditambah ekstensi file.

Contoh:

```text
trxno = FSY-25L-00017
file asli = invoice.pdf
upload_flnm = FSY-25L-00017.pdf
```

Untuk upload lebih dari 1 file:

```text
trxno = FSY-25L-00019
upload_flnm = FSY-25L-00019.zip
```

Catatan penting:

- File tunggal tidak perlu di-ZIP.
- File lebih dari satu disimpan sebagai ZIP.
- Sistem download tidak melakukan extract otomatis.
- Jika file tersimpan sebagai ZIP, user akan mendownload ZIP tersebut dan melakukan extract sendiri.
- Field `upload_flnm` saat ini bertipe `char(15)`, sedangkan contoh `FSY-25L-00017.pdf` memiliki panjang lebih dari 15 karakter.
- Jika pola nama fisik menggunakan `trxno + ekstensi`, field `upload_flnm` perlu diperpanjang, misalnya menjadi `varchar(50)`.

Data yang disimpan ke `file_system`:

| Field           | Isi                          |
| --------------- | ---------------------------- |
| `trxno`         | Nomor dari `sysitc_serialno` |
| `trxdt`         | Tanggal upload               |
| `folder_loc`    | `sys_msttable.AddiNotes`     |
| `upload_flnm`   | Nama file fisik di storage   |
| `file_name`     | Nama file tampil             |
| `file_type`     | Ekstensi/tipe file           |
| `file_size`     | Ukuran file asli             |
| `file_zip_size` | Ukuran file hasil akhir yang disimpan |
| `userid`        | User yang upload             |
| `file_notes`    | Catatan/keyword              |

Contoh penyimpanan untuk 1 file:

| Field | Isi |
|---|---|
| `trxno` | FSY-25L-00017 |
| `file_name` | invoice.pdf |
| `upload_flnm` | FSY-25L-00017.pdf |
| `file_type` | PDF |
| `file_size` | Ukuran file asli |
| `file_zip_size` | Sama dengan ukuran file yang disimpan |

Contoh penyimpanan untuk lebih dari 1 file:

| Field | Isi |
|---|---|
| `trxno` | FSY-25L-00019 |
| `file_name` | dokumen_client.zip |
| `upload_flnm` | FSY-25L-00019.zip |
| `file_type` | ZIP |
| `file_size` | Total ukuran semua file asli |
| `file_zip_size` | Ukuran file ZIP |

## Alur List File

List file akan dibaca dari tabel:

```text
file_system
```

User dapat melihat file jika memenuhi salah satu kondisi berikut:

1. User adalah pemilik file.
2. User mendapatkan akses dari tabel `file_shareto`.
3. File dibagikan ke department/group yang sesuai dengan user.

Query list perlu membaca:

- `file_system`
- `file_shareto`
- `sysitc_users`
- `sys_msttable`

## Alur Download File

Proses download:

1. Sistem menerima ID file.
2. Sistem membaca metadata dari `file_system`.
3. Sistem mengecek hak akses user.
4. Sistem membaca lokasi folder dari `sys_msttable`.
5. Sistem membentuk path file fisik.
6. Sistem mengambil file dari storage.
7. File dikirim ke user sesuai bentuk file yang tersimpan.

Catatan:

- Jika yang tersimpan adalah file asli, user mendownload file asli tersebut.
- Jika yang tersimpan adalah ZIP, user mendownload file ZIP tersebut.
- Sistem tidak melakukan extract otomatis saat download.

Data yang digunakan untuk membentuk path file:

| Sumber         | Field         |
| -------------- | ------------- |
| `file_system`  | `folder_loc`  |
| `file_system`  | `upload_flnm` |
| `sys_msttable` | `AddiNotes`   |
| `sys_msttable` | `notes`       |

Relasi:

```text
file_system.folder_loc = sys_msttable.AddiNotes
```

Path akhir:

```text
sys_msttable.notes + file_system.upload_flnm
```

### Contoh Kasus Download

Contoh data pada `file_system`:

| Field         | Value          |
| ------------- | -------------- |
| `rec_id`      | 15             |
| `trxno`       | FSY-25L-00015  |
| `file_name`   | KONTRAK_CLIENT |
| `folder_loc`  | AC             |
| `upload_flnm` | FSY-25L-00015.zip |
| `file_type`   | ZIP            |
| `userid`      | 120            |

Contoh data pada `sys_msttable`:

| Field       | Value            |
| ----------- | ---------------- |
| `tbl_code`  | 81               |
| `code`      | CID              |
| `notes`     | /filesys/cid26b/ |
| `AddiNotes` | AC               |
| `statrec`   | 1                |

Proses yang terjadi saat user download file dengan `rec_id = 15`:

1. Sistem membaca data file dari `file_system` berdasarkan `rec_id = 15`.
2. Sistem mendapatkan `folder_loc = AC` dan `upload_flnm = FSY-25L-00015.zip`.
3. Sistem mencari folder di `sys_msttable` dengan `tbl_code = 81` dan `AddiNotes = AC`.
4. Sistem mendapatkan path folder `/filesys/cid26b/` dari field `notes`.
5. Sistem membentuk path file fisik menjadi `/filesys/cid26b/FSY-25L-00015.zip`.
6. Sistem mengambil file tersebut dari storage/FTP.
7. File dikirim ke user dengan nama download yang sesuai, misalnya `KONTRAK_CLIENT.zip`.

Hasil path file fisik:

```text
/filesys/cid26b/FSY-25L-00015.zip
```

Contoh query untuk mengambil data download:

```sql
SELECT
    fs.rec_id,
    fs.trxno,
    fs.file_name,
    fs.folder_loc,
    fs.upload_flnm,
    fs.userid,
    mt.notes AS folder_path
FROM file_system fs
LEFT JOIN sys_msttable mt
    ON mt.tbl_code = '81'
    AND mt.AddiNotes = fs.folder_loc
WHERE fs.rec_id = 15
LIMIT 1;
```

Jika hasil query tersebut adalah:

| Field         | Value            |
| ------------- | ---------------- |
| `folder_path` | /filesys/cid26b/ |
| `upload_flnm` | FSY-25L-00015.zip |

Maka path file yang dibaca oleh sistem adalah:

```text
/filesys/cid26b/FSY-25L-00015.zip
```

## Alur Share File

Tabel yang digunakan:

```text
file_shareto
```

Tabel ini akan menggantikan fungsi utama dari `sys_filing_access`.

Struktur share:

| Field        | Fungsi                            |
| ------------ | --------------------------------- |
| `filesys_id` | ID file dari `file_system.rec_id` |
| `share_cat`  | Kategori share                    |
| `othercode`  | Kode target share                 |

Kategori share:

| share_cat | Arti |
| --------- | ---- |
| 0 | Private |
| 1 | Personal |
| 2 | Department |
| 3 | Company |
| 4 | All User |

Aturan akses yang disarankan:

| share_cat | Aturan akses |
|---|---|
| 0 | File hanya bisa diakses oleh owner (`file_system.userid`) |
| 1 | File bisa diakses oleh user tertentu sesuai `othercode` |
| 2 | File bisa diakses oleh user dengan department yang sama dengan `othercode` |
| 3 | File bisa diakses oleh user dengan company yang sama dengan `othercode` |
| 4 | File bisa diakses oleh semua user internal |

Dengan aturan ini, proses list dan download harus mengecek owner file dan kategori share sebelum file ditampilkan atau dikirim ke user.

## Fitur Yang Belum Ter-cover Oleh Tabel Baru

Struktur tabel baru lebih sederhana dibanding struktur tabel lama. Beberapa fitur pada sistem lama belum memiliki field atau tabel pengganti secara langsung.

| Fitur                     | Status         | Catatan                                                                            |
| ------------------------- | -------------- | ---------------------------------------------------------------------------------- |
| Archive file              | Belum tersedia | Belum ada field `status` untuk membedakan file aktif dan arsip                     |
| Trash / recycle bin       | Belum tersedia | Belum ada field `trashed_at`, `deleted_at`, atau status trash                      |
| Permanent delete tracking | Belum tersedia | Belum ada penanda file sudah dihapus permanen                                      |
| Security level            | Belum tersedia | Belum ada field untuk `normal`, `restricted`, atau `confidential`                  |
| Access mode               | Belum jelas    | Belum ada field untuk `private`, `public_internal`, `custom`, atau `share_link`    |
| Share link / share code   | Belum cukup    | `file_shareto` belum menyimpan share code, password, expiry, dan batas akses       |
| Audit log                 | Belum tersedia | Belum ada tabel untuk mencatat aktivitas upload, download, share, edit, dan delete |
| Download/access count     | Belum tersedia | Belum ada field untuk menghitung jumlah akses/download                             |
| Revoke share              | Belum tersedia | `file_shareto` belum memiliki `is_active` atau `revoked_at`                        |
| Permission detail         | Belum lengkap  | Belum ada field `can_view`, `can_download`, `can_share`, dan `can_manage`          |
| Expired action            | Belum tersedia | Ada tanggal expired, tetapi belum ada aksi setelah expired                         |
| Expired processed flag    | Belum tersedia | Belum ada penanda bahwa file expired sudah diproses                                |
| ZIP inspection            | Belum tersedia | Belum ada field untuk menyimpan jumlah file di dalam ZIP                           |
| Full storage path         | Tidak langsung | Lokasi file harus dibentuk dari `folder_loc` dan master folder `sys_msttable`      |
| Nama file panjang         | Terbatas       | `file_name` hanya `varchar(20)`                                                    |
| Nama file fisik           | Terbatas       | `upload_flnm` hanya `char(15)`                                                     |
| Notes/keywords panjang    | Terbatas       | `file_notes` hanya `varchar(200)`                                                  |

## Dampak Terhadap Fitur Existing

Karena beberapa field belum tersedia, fitur existing perlu disesuaikan.

| Fitur Existing             | Dampak                                                            |
| -------------------------- | ----------------------------------------------------------------- |
| Tab Aktif / Arsip / Sampah | Tidak bisa berjalan penuh tanpa field status                      |
| Tombol Arsipkan            | Tidak bisa berjalan tanpa status archive                          |
| Tombol Pindah ke Sampah    | Tidak bisa berjalan tanpa status trash                            |
| Tombol Restore             | Tidak bisa berjalan tanpa status trash/archive                    |
| Tombol Hapus Permanen      | Bisa dilakukan, tetapi tidak ada tracking status                  |
| Filter security level      | Tidak bisa digunakan tanpa field security level                   |
| Filter access mode         | Tidak bisa digunakan tanpa field access mode                      |
| Share code publik          | Tidak bisa berjalan penuh dengan struktur `file_shareto` saat ini |
| Audit page                 | Tidak bisa berjalan tanpa tabel audit                             |
| Info drawer detail file    | Perlu disederhanakan sesuai field baru                            |
| ZIP inspection             | Perlu disesuaikan karena data ZIP detail tidak disimpan           |

## Rekomendasi Tahapan Implementasi

### Tahap 1: List dan Download

Prioritas awal adalah membaca data dari `file_system`.

Target:

- List file tampil dari tabel baru
- Download membaca file dari folder baru
- Permission dasar berjalan

Tahap ini paling aman karena belum mengubah proses upload.

### Tahap 2: Upload

Upload dipindahkan ke tabel baru.

Target:

- Generate nomor dari `sysitc_serialno`
- Ambil folder aktif dari `sys_msttable`
- Upload file ke storage
- Insert metadata ke `file_system`

### Tahap 3: Share Internal

Share dipindahkan ke `file_shareto`.

Target:

- Share ke user
- Share ke department
- Share ke group/role
- Cek akses saat list dan download

### Tahap 4: Penyesuaian Fitur Lama

Setelah alur utama stabil, fitur berikut perlu diputuskan:

- Archive
- Trash
- Security level
- Share code/link
- Audit log
- Expired file

## Pertanyaan Yang Perlu Dipastikan

1. Sumber data untuk `depcd`.
2. Isi field `client_nm`.
3. Apakah fitur archive dan trash masih wajib digunakan.
4. Apakah share code/link publik masih wajib digunakan.
5. Apakah audit log masih diperlukan.
6. Apakah security level masih diperlukan.
7. Apakah nama file boleh dipotong karena `file_name` hanya `varchar(20)`.
8. Apakah `upload_flnm` dengan panjang `char(15)` cukup untuk nama file fisik.
9. Apakah format nomor file benar menggunakan pola function FoxPro `GetSernoWeb`.

## Kesimpulan

Struktur tabel baru dapat digunakan untuk menyederhanakan Filing System.

Namun beberapa fitur pada sistem lama belum memiliki tempat langsung di tabel baru. Oleh karena itu, implementasi sebaiknya dilakukan bertahap, dimulai dari list dan download, kemudian upload, lalu share internal.

Keputusan tambahan diperlukan untuk fitur archive, trash, share code, security level, dan audit log.
