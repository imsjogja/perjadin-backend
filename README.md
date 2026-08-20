# Perjadin Backend

API Laravel 10 untuk proses perjalanan dinas. Repository ini hanya menyediakan backend; aplikasi antarmuka dipelihara terpisah.

## Menjalankan lokal

\`\`\`bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
\`\`\`

Konfigurasikan koneksi basis data aplikasi pada \`.env\`. Untuk pengembangan, gunakan \`php artisan test\` setelah dependensi terpasang.

## Integrasi SIKKEPO

Referensi pegawai diperoleh secara *read-only* dari SIKKEPO Platform API, bukan dari basis data Perjadin. Isi kredensial berikut hanya pada \`.env\`:

\`\`\`dotenv
SIKKEPO_BASE_URL=https://sikkepo.example
SIKKEPO_PLATFORM_CLIENT_ID=perjadin
SIKKEPO_PLATFORM_CLIENT_SECRET=...
\`\`\`

Endpoint Perjadin \`GET /api/v1/references/pegawai\` memerlukan token Sanctum dan meneruskan filter yang diizinkan ke \`GET /api/v1/platform/pegawai\` milik SIKKEPO. Rincian kontrak ada di [docs/api/sikkepo-platform-pegawai-contract.md](docs/api/sikkepo-platform-pegawai-contract.md).

Jangan menyimpan secret, token, maupun data pegawai sensitif di repository.
