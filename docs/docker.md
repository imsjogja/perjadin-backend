# Docker Lokal

Backend berjalan sebagai Laravel PHP-FPM di belakang Nginx, dengan MySQL lokal. Semua data database disimpan pada volume `perjadin_mysql_data`.

```bash
cp .env.local.example .env.local
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Docker Compose memuat `.env.local` ke container `app` dan `db`. Atur `APP_*`, `PERJADIN_ADMIN_*`, koneksi database, serta kredensial SIKKEPO pada file tersebut; file ini diabaikan Git. API tersedia di `http://localhost:8000/api/v1`; MySQL host tersedia di port `33068`.

Seeder membuat akun awal berdasarkan `PERJADIN_ADMIN_EMAIL` dan `PERJADIN_ADMIN_PASSWORD`. Nilai awal pada `.env.local.example` adalah `admin@perjadin.local` dan `perjadin-admin`; jangan gunakan kata sandi contoh tersebut di produksi.

Jangan gunakan konfigurasi ini untuk produksi: `APP_DEBUG` aktif, kunci aplikasi hanya untuk lingkungan lokal, dan kredensial database bersifat publik. Setelah mengubah `.env.local`, jalankan `docker compose up -d --force-recreate app db`. Untuk menghentikan layanan, jalankan `docker compose down`. Tambahkan `-v` hanya bila memang ingin menghapus volume database lokal.
