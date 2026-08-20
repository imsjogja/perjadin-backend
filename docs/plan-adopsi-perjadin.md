# Rencana Adopsi Proses Bisnis Perjadin

## 1. Tujuan dan Batasan

Dokumen ini menjadi rencana migrasi proses bisnis Perjalanan Dinas dari aplikasi CodeIgniter legacy ke `perjadin-backend` berbasis Laravel. Repository ini hanya menyediakan REST API dan layanan dokumen; UI akan dikembangkan pada repository terpisah.

Perjadin tidak menjadi pemilik master pegawai. Data pegawai bersumber dari SIKKEPO melalui Platform API machine-to-machine. Controller dan view CodeIgniter tidak dipindahkan secara langsung; aturan bisnis diekstrak ke service, action, policy, dan test Laravel.

## 2. Scope MVP

### Termasuk

- autentikasi API dan RBAC Perjadin;
- referensi khusus perjalanan: tujuan, transportasi, dasar, rekening, komponen biaya, dan pejabat penandatangan;
- SPT, tujuan SPT, pejabat SPT, dan penomoran dokumen;
- SPPD per pegawai, pengikut, pejabat, serta relasi ke SPT;
- verifikasi SPPD, kwitansi, rekap periode, dan filter unit;
- PDF SPT, SPPD, kwitansi, dan visum;
- kontrak OpenAPI, collection Postman, audit log, dan deployment API.

### Tidak termasuk

- UI, Blade, aset frontend, dan navigasi aplikasi;
- CRUD atau tabel master pegawai lokal;
- impor pegawai ke database Perjadin;
- migrasi endpoint legacy yang tidak terkait proses Perjadin;
- pengelolaan menu dan konfigurasi internal SIKKEPO.

## 3. Kontrak SIKKEPO Platform API

Kontrak ini harus dibuat terlebih dahulu pada repository `sikkepo-lara10`.

### Autentikasi

```http
POST /api/v1/platform/token
```

Perjadin menggunakan client credentials. Client dibuat dengan:

```text
client_code: perjadin
ability: data-pegawai:read
unit_scope: sesuai kewenangan Perjadin
```

### Endpoint Pegawai Baru

```http
GET /api/v1/platform/pegawai
```

Route ditempatkan pada grup Platform API dan dilindungi oleh `auth:sanctum`, audit platform, actor platform, serta ability `data-pegawai:read`. Endpoint hanya read-only dan menerapkan scope unit client.

Parameter yang didukung:

```text
q, nip, unit_id, aktif, updated_since,
sort, direction, per_page, page
```

`aktif=true` menjadi nilai default. Pencarian hanya boleh menggunakan kolom yang diizinkan dan selalu dibatasi scope unit.

Contoh respons:

```json
{
  "data": [
    {
      "pegawai_id": "uuid-pegawai-sikkepo",
      "nip": "198001012010011001",
      "nama": "Nama Pegawai",
      "tipe": "pns",
      "aktif": true,
      "gelar_depan": null,
      "gelar_belakang": null,
      "unit": { "id": "uuid", "kode": "1.02.01", "nama": "Nama Unit" },
      "jabatan": { "id": "uuid", "kode": "JBT-01", "nama": "Kepala Subbagian" },
      "golongan": { "id": "uuid", "kode": "III/c", "nama": "Penata" },
      "eselon": { "id": "uuid", "kode": "IV/a", "nama": "Eselon IV.a" },
      "kelas": { "id": "uuid", "kode": "7", "nama": "Kelas 7" },
      "updated_at": "2026-08-20T10:00:00+07:00"
    }
  ],
  "links": {},
  "meta": { "current_page": 1, "per_page": 25, "total": 1, "scope": "restricted" }
}
```

Data sensitif seperti gaji, PIN, password, dan token tidak boleh dikembalikan. Endpoint lama `/api/pegawai` dan `/api/v1/ketidakhadiran/pegawai` bukan kontrak final Perjadin.

## 4. Arsitektur Integrasi

```text
UI Perjadin
    ↓
Perjadin API /api/v1
    ↓
SikkepoPlatformClient → token cache, timeout, retry, audit lokal
    ↓
SIKKEPO Platform API /api/v1/platform/pegawai
```

Perjadin menyimpan `sikkepo_pegawai_id` sebagai ID eksternal, bukan foreign key ke tabel pegawai lokal. Saat SPT/SPPD dibuat, simpan snapshot immutable berupa NIP, nama, unit, jabatan, golongan, dan eselon agar dokumen historis tetap konsisten ketika master SIKKEPO berubah.

## 5. Jalur Delivery

| Fase | Deliverable | Estimasi |
|---|---|---:|
| 1. Kontrak lintas-repo | OpenAPI, field dictionary, ability, unit scope, error contract, Postman | 3–5 hari |
| 2. Implementasi SIKKEPO | Route, request validation, query scope, resource, audit, rate limit, test | 7–10 hari |
| 3. Fondasi Perjadin | Laravel scaffold, auth, RBAC, `SikkepoPlatformClient`, cache, health check | 5–7 hari |
| 4. Referensi & transaksi | Referensi perjalanan, snapshot pegawai, SPT, SPPD, sequence nomor | 16–21 hari |
| 5. Keuangan & dokumen | Verifikasi, kwitansi, rekap, PDF, storage privat | 9–12 hari |
| 6. UAT & rilis | Contract/integration test, migrasi data, deployment, dokumentasi UI | 6–8 hari |

Estimasi moderat adalah **52 person-days dalam 9 minggu**. Rentang perencanaan: 44–64 person-days atau 8–11 minggu, bergantung pada kesiapan kontrak SIKKEPO dan validasi format dokumen. Biaya dihitung dengan rumus `person-days × tarif harian tim × 15% kontingensi`.

## 6. Endpoint Perjadin

```http
GET  /api/v1/references/pegawai
GET  /api/v1/references/pegawai/{sikkepo_pegawai_id}

POST /api/v1/spts
GET  /api/v1/spts
GET  /api/v1/spts/{id}
PATCH /api/v1/spts/{id}

POST /api/v1/spts/{spt}/sppds
PATCH /api/v1/sppds/{id}
PATCH /api/v1/sppds/{id}/verification
POST /api/v1/receipts
GET  /api/v1/reports/travels
GET  /api/v1/documents/{type}/{id}/pdf
```

Endpoint `references/pegawai` adalah proxy read-only terproteksi. UI tidak mengakses SIKKEPO secara langsung.

## 7. Keamanan dan Pengujian

- gunakan HTTPS, token platform berumur pendek, ability minimum, dan scope unit;
- simpan secret hanya pada environment/secret manager;
- audit request, request ID, status, durasi, dan client;
- gunakan validasi request, Policy, rate limiting, dan response error baku;
- uji timeout, token kedaluwarsa, pegawai di luar scope, pegawai nonaktif, dan perubahan data SIKKEPO;
- lakukan unit test untuk sequence, snapshot, kalkulasi biaya, dan policy;
- lakukan integration test terhadap mock SIKKEPO serta contract test OpenAPI;
- lakukan UAT dengan perbandingan transaksi dan PDF terhadap aplikasi legacy.

## 8. Kriteria Keberhasilan

1. Client `perjadin` dapat memperoleh token Platform API dengan ability dan scope unit yang benar.
2. Pegawai di luar scope atau berstatus nonaktif tidak dapat dipakai membuat SPPD.
3. Tidak ada tabel master pegawai atau CRUD pegawai di Perjadin.
4. Snapshot transaksi mampu menghasilkan PDF yang sama secara substantif meskipun data SIKKEPO berubah.
5. Nomor SPT, SPPD, dan kwitansi unik per jenis/tahun melalui transaksi dan unique constraint.
6. Kontrak API terdokumentasi dan dapat digunakan UI terpisah tanpa mengetahui struktur database.

## 9. Prasyarat dan Risiko

Sebelum implementasi transaksi dimulai, kedua tim harus menyepakati kontrak endpoint, kemampuan client, hierarki unit, contoh respons, aturan status aktif, contoh PDF, dan dump data legacy teranonimkan. Risiko terbesar adalah perubahan kontrak lintas-repo, ketidaklengkapan atribut pegawai, perbedaan aturan penomoran, dan ketidaksesuaian format PDF. Mitigasinya adalah contract test, snapshot transaksi, sequence service, serta UAT paralel dengan legacy.
