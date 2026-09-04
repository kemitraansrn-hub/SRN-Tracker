# Panduan Deploy SRN Link Center ke Hosting Live

Aplikasi Laravel 13 (PHP) + MySQL. Dokumen ini buat tim IT yang akan pindahkan
aplikasi ini dari mesin development ke server hosting produksi.

## 1. Requirement Server

- **PHP 8.3** atau lebih baru, dengan ekstensi: `bcmath`, `ctype`, `curl`, `dom`,
  `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`,
  `session`, `simplexml`, `tokenizer`, `xml`, `zip`
- **MySQL 8.0+** (atau MariaDB setara)
- **Composer 2.x**
- **Node.js 18+** dan npm (cuma dipakai sekali waktu build asset CSS/JS, tidak
  perlu jalan terus di server)
- Web server **Apache** atau **Nginx**, document root harus mengarah ke folder
  **`public/`** (bukan root project) — sama seperti kebiasaan Laravel pada
  umumnya
- Sertifikat **SSL/HTTPS** (aplikasi ini menyimpan data bisnis & login user,
  wajib HTTPS, jangan HTTP polos)

## 2. Ambil Kode

Kode sumbernya ada di GitHub (repo **private**):

```
https://github.com/kemitraansrn-hub/SRN-Tracker.git
```

Tim IT perlu diundang sebagai collaborator dulu ke repo ini (dari GitHub, menu
Settings → Collaborators), baru bisa `git clone`.

```bash
git clone https://github.com/kemitraansrn-hub/SRN-Tracker.git
cd SRN-Tracker
```

## 3. Install Dependency

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

`npm run build` cukup dijalankan sekali (atau tiap ada perubahan CSS/JS) — hasil
build-nya (folder `public/build`) yang dipakai runtime, node.js sendiri tidak
perlu jalan permanen di server.

## 4. Environment (`.env`)

Copy `.env.example` jadi `.env`, lalu isi nilai yang sebenarnya:

```bash
cp .env.example .env
php artisan key:generate
```

Yang **wajib diisi manual** (jangan pakai nilai contoh):

| Variabel | Isi dengan |
|---|---|
| `APP_URL` | Domain produksi asli, contoh `https://srn.perusahaan.com` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Kredensial database MySQL produksi (buat user baru khusus, jangan pakai root) |
| `SESSION_SECURE_COOKIE` | `true` (karena situs pakai HTTPS) |
| `MAIL_MAILER` dll (opsional) | Kalau mau email beneran terkirim (reset password dll), isi SMTP asli. Sekarang di-set `log` (email cuma dicatat di log, tidak benar-benar terkirim) |

**Jangan** commit file `.env` ke git — sudah otomatis di-`.gitignore`.

## 5. Setup Database

```bash
php artisan migrate --force
php artisan storage:link
```

`storage:link` wajib — dipakai buat foto profil user (`storage/app/public`).

### Migrasi data yang sudah ada (opsional tapi disarankan)

Aplikasi ini sudah dipakai beberapa minggu dengan data asli (mitra, order,
target, dll). Kalau mau data itu ikut pindah ke server baru (bukan mulai dari
kosong), import dump database yang sudah disiapkan:

```bash
# Restore database
gunzip < srn-db-TANGGAL.sql.gz | mysql -u [user] -p [nama_database]

# Restore file upload (foto profil, logo MOU, dokumen Action Plan)
unzip srn-uploads-TANGGAL.zip -d storage/app/
```

File `srn-db-*.sql.gz` dan `srn-uploads-*.zip` itu dihasilkan otomatis oleh
fitur backup aplikasi ini sendiri (menu **Backup Data**, admin-only) — minta ke
saya (atau ambil dari admin) versi paling baru sebelum migrasi.

## 6. Permission Folder

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache   # sesuaikan user web server
```

## 7. Optimisasi Produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Kalau `.env` diubah lagi setelah ini, wajib jalankan `php artisan config:clear`
dulu sebelum `config:cache` ulang — kalau tidak, perubahan `.env` tidak akan
kebaca (config-nya sudah di-cache versi lama).

## 8. Web Server — Contoh Config Nginx

```nginx
server {
    listen 443 ssl;
    server_name srn.perusahaan.com;
    root /path/ke/SRN-Tracker/public;

    ssl_certificate     /path/ke/cert.pem;
    ssl_certificate_key /path/ke/key.pem;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Kalau pakai Apache, aktifkan `mod_rewrite` dan arahkan `DocumentRoot` ke folder
`public/` — Laravel sudah menyertakan `public/.htaccess` bawaan, tidak perlu
config tambahan selain itu.

## 9. Backup Otomatis Harian

Aplikasi ini sudah punya command backup bawaan
(`app/Console/Commands/BackupData.php`) yang men-dump database + file upload
ke `storage/app/backups`, dengan retensi otomatis (hapus yang lebih tua dari
`BACKUP_RETENTION_DAYS` hari, default 30).

Tambahkan **cron job** di server (linux, `crontab -e`):

```cron
0 2 * * * cd /path/ke/SRN-Tracker && php artisan app:backup >> storage/logs/backup.log 2>&1
```

Itu bikin backup jalan tiap jam 2 pagi. Sangat disarankan juga arahkan
`BACKUP_MIRROR_PATH` di `.env` ke folder yang di-sync ke penyimpanan di luar
server ini (S3, Google Drive via rclone, dll) — backup yang cuma ada di server
yang sama tidak banyak menolong kalau server itu sendiri yang bermasalah.

## 10. Cek Checklist Sebelum Live

- [ ] `APP_ENV=production` dan `APP_DEBUG=false` (jangan sampai `true` di
      produksi — bisa membocorkan detail teknis/error ke publik)
- [ ] HTTPS aktif, `SESSION_SECURE_COOKIE=true`
- [ ] `php artisan migrate --force` sudah jalan tanpa error
- [ ] `php artisan storage:link` sudah dijalankan
- [ ] Login admin bisa dicoba (akun awal: lihat tabel `users`, atau minta
      admin buat akun baru lewat `php artisan tinker` kalau tabel masih kosong)
- [ ] Cron backup harian sudah terpasang & sudah dites jalan sekali manual
- [ ] Import data lama (kalau diperlukan) sudah diverifikasi jumlah barisnya
      cocok dengan sumbernya

## Kontak

Kalau tim IT ada pertanyaan soal struktur kode/fitur spesifik, bisa hubungi
admin aplikasi (kemitraan.srn@gmail.com) yang paling paham konteks bisnisnya.
