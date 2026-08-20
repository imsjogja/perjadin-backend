# Kontrak Referensi Pegawai SIKKEPO

Perjadin mengonsumsi endpoint SIKKEPO berikut setelah autentikasi *client credentials*:

\`\`\`http
GET /api/v1/platform/pegawai
Authorization: Bearer {platform_access_token}
Accept: application/json
\`\`\`

Endpoint ini perlu ditambahkan pada **SIKKEPO Platform API** dengan ability \`data-pegawai:read\`. Akses hanya-baca, dibatasi ke unit yang diizinkan untuk klien \`perjadin\`, dan tidak boleh mengirim PIN, kata sandi, gaji, atau atribut sensitif lain.

## Parameter query

| Parameter | Keterangan |
| --- | --- |
| \`q\`, \`nip\` | Pencarian nama atau NIP |
| \`unit_id\` | UUID unit kerja dalam scope klien |
| \`aktif\` | \`true\` atau \`false\` |
| \`updated_since\` | Waktu ISO-8601 untuk sinkronisasi |
| \`sort\`, \`direction\` | \`nama\`, \`nip\`, atau \`updated_at\`; \`asc\`/\`desc\` |
| \`per_page\`, \`page\` | Pagination; maksimum 100 per halaman |

## Respons minimum

\`\`\`json
{
  "data": [{
    "pegawai_id": "uuid",
    "nip": "198001012010011001",
    "nama": "Nama Pegawai",
    "tipe": "ASN",
    "aktif": true,
    "unit": { "id": "uuid", "nama": "Sekretariat" },
    "jabatan": { "id": "uuid", "nama": "Analis" },
    "golongan": { "id": "uuid", "nama": "III/a" },
    "eselon": { "id": "uuid", "nama": "IV/a" },
    "kelas_jabatan": { "id": "uuid", "nama": "9" },
    "updated_at": "2026-08-20T10:00:00Z"
  }],
  "meta": { "current_page": 1, "per_page": 25, "total": 1 }
}
\`\`\`

Perjadin meneruskan respons ini melalui \`GET /api/v1/references/pegawai\` setelah memvalidasi parameter. Saat SIKKEPO tidak tersedia, Perjadin mengembalikan \`502\` dengan kode \`sikkepo_unavailable\`, tanpa mengekspos detail upstream.
