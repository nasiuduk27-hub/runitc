# Public Document Root

Deployment memakai document root yang mengarah ke:

```text
/var/www/html/public
```

Ini mengikuti deployment Laravel standar: hanya isi `public/` yang bisa diakses dari web. File source seperti `config.php`, `composer.json`, `app/`, `classes/`, `controllers/`, `models/`, `modules/`, dan `storage/` tidak lagi dilayani langsung oleh web server.

Root `.htaccess` tetap dipertahankan sebagai fallback untuk environment lama yang masih memakai root project sebagai document root. Untuk deployment baru, gunakan `public/.htaccess`.

Checklist deployment manual di Synology/Web Station:

1. Ubah document root virtual host dari folder project root ke folder `public`.
2. Pastikan `AllowOverride All` aktif untuk folder `public`.
3. Pastikan PHP masih bisa membaca root project dan menulis ke `storage/` dan `bootstrap/cache/`.
4. Jalankan smoke test setelah switch: `composer test:smoke`.

Catatan: `assets/` sudah tersedia di `public/assets/`, jadi URL seperti `/assets/images/RUNITC_LOGO.png` tetap valid setelah document root dipindah.
