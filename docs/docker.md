# Docker Lokal

Backend berjalan sebagai Laravel PHP-FPM di belakang Nginx, dengan MySQL lokal. Semua data database disimpan pada volume `perjadin_mysql_data`.

```bash
cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Docker Compose dan container `app` membaca satu file `.env`. Atur nama project, port backend, gateway tunnel IMS, subnet jaringan, `APP_*`, `PERJADIN_ADMIN_*`, koneksi database, serta kredensial SIKKEPO di sana. File tersebut diabaikan Git. API tersedia di `http://localhost:8000/api/v1`; MySQL host tersedia di port `33068`.

Seeder membuat akun awal berdasarkan `PERJADIN_ADMIN_EMAIL` dan `PERJADIN_ADMIN_PASSWORD`. Nilai awal pada `.env.example` adalah `admin@perjadin.local` dan `password`; jangan gunakan kata sandi contoh tersebut di produksi.

Jangan gunakan konfigurasi ini untuk produksi: `APP_DEBUG` aktif, kunci aplikasi hanya untuk lingkungan lokal, dan kredensial database bersifat publik. Setelah mengubah `.env`, jalankan `docker compose up -d --force-recreate app nginx`. Untuk menghentikan layanan, jalankan `docker compose down`. Tambahkan `-v` hanya bila memang ingin menghapus volume database lokal.

Untuk merecreate backend produksi setelah mengubah `.env`:

```bash
docker compose up -d --force-recreate app nginx
docker compose exec -T app php artisan config:clear
```
