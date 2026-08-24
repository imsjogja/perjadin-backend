# Docker Lokal

Backend berjalan sebagai Laravel PHP-FPM di belakang Nginx, dengan MySQL lokal. Semua data database disimpan pada volume `perjadin_mysql_data`.

```bash
cp compose.env.example .env
cp .env.local.example .env.local
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Docker Compose membaca konfigurasi infrastruktur dari `.env`: nama project, port backend, gateway tunnel IMS, dan subnet jaringan. File `.env.local` hanya dimuat ke container `app`; atur `APP_*`, `PERJADIN_ADMIN_*`, koneksi database, serta kredensial SIKKEPO di sana. Kedua file diabaikan Git. API tersedia di `http://localhost:8000/api/v1`; MySQL host tersedia di port `33068`.

Seeder membuat akun awal berdasarkan `PERJADIN_ADMIN_EMAIL` dan `PERJADIN_ADMIN_PASSWORD`. Nilai awal pada `.env.local.example` adalah `admin@perjadin.local` dan `perjadin-admin`; jangan gunakan kata sandi contoh tersebut di produksi.

Jangan gunakan konfigurasi ini untuk produksi: `APP_DEBUG` aktif, kunci aplikasi hanya untuk lingkungan lokal, dan kredensial database bersifat publik. Setelah mengubah `.env.local`, jalankan `docker compose up -d --force-recreate app db`. Untuk menghentikan layanan, jalankan `docker compose down`. Tambahkan `-v` hanya bila memang ingin menghapus volume database lokal.

Untuk merecreate backend produksi setelah mengubah `.env.local`:

```bash
docker compose up -d --force-recreate app nginx
docker compose exec -T app php artisan config:clear
```
