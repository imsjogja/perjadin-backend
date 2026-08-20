# Perjadin API v1 — Modul Inti

Semua endpoint berikut membutuhkan token Sanctum lokal. Modul ini hanya menyimpan ID dan snapshot transaksi pegawai; master pegawai selalu dibaca dari SIKKEPO Platform API.

## Referensi SIKKEPO

```http
GET /api/v1/references/units?q=kepegawaian&per_page=20
GET /api/v1/references/pegawai?q=nama&aktif=true&per_page=20
```

Kedua endpoint adalah proxy read-only yang menerapkan scope Platform API SIKKEPO. UI memakai `references/units` untuk memilih unit penerbit SPT dan `references/pegawai` untuk memilih penandatangan; UI tidak mengakses SIKKEPO secara langsung. Client SIKKEPO harus memiliki ability `data-unit:read` dan `data-pegawai:read`.

## SPT

```http
POST /api/v1/spts
```

Kirim `unit_id`, `dasar`, `dalam_rangka`, `issued_place`, `issued_date`, satu objek `destination`, dan `signatory.nip`. Nomor registrasi dan nomor SPT dibuat server secara atomik per tahun.

```json
{
  "unit_id": "uuid-unit",
  "dasar": "Surat tugas",
  "dalam_rangka": "Koordinasi",
  "issued_place": "Manokwari",
  "issued_date": "2026-08-20",
  "destination": {
    "transportation": "Pesawat",
    "departure_place": "Manokwari",
    "destination_place": "Jakarta",
    "duration_days": 3
  },
  "signatory": { "nip": "198001012010011001" }
}
```

Gunakan `GET /api/v1/spts` untuk daftar dan `GET /api/v1/spts/{id}` untuk detail.

## Pelaksana SPT

Surat Tugas memuat pelaksana tugas secara mandiri. Tambahkan pegawai setelah SPT dibuat tanpa membuat SPPD:

```http
GET  /api/v1/spts/{spt}/assignees
POST /api/v1/spts/{spt}/assignees
```

```json
{
  "nips": ["198001012010011002", "198001012010011003"]
}
```

Setiap NIP diverifikasi ke SIKKEPO, lalu disimpan sebagai snapshot immutable. Pegawai yang sama hanya dapat tercatat sekali dalam satu SPT. Setiap penambahan menaikkan `assignment_revision` serta mencatat waktu dan pengguna yang menambahkan; gunakan revisi terbaru saat membentuk ulang dokumen SPT. Penambahan ini tidak menerbitkan atau menghapus kewajiban SPPD.

## SPPD dan verifikasi

```http
POST  /api/v1/spts/{spt}/sppds
GET   /api/v1/sppds/{id}
PATCH /api/v1/sppds/{id}/verification
```

Pembuatan SPPD memerlukan `traveller_nip`, tanggal berangkat/pulang, sumber anggaran, tempat/tanggal terbit, serta `signatory.nip`; `followers` adalah daftar NIP opsional. Pegawai harus aktif dan berada dalam scope klien SIKKEPO. Pelaksana perjalanan dan seluruh pengikut harus sudah terdaftar sebagai pelaksana pada SPT. Pelaksana perjalanan tidak boleh juga menjadi pengikut.

Menurut PMK 113/PMK.05/2012 Pasal 6, SPT wajib memuat pelaksana tugas. SPD menjadi dasar untuk perjalanan lintas kota atau perjalanan dalam kota lebih dari delapan jam; perjalanan dalam kota sampai delapan jam dapat tanpa SPD. Kontrak SPT saat ini belum merekam klasifikasi perjalanan dan durasi jam, sehingga endpoint pelaksana tidak menetapkan kelayakan perjalanan tanpa SPPD.

Verifikasi hanya mengubah SPPD dari `draft` ke `verified` dan mencatat pengguna serta waktu verifikasi. Pengulangan verifikasi mengembalikan `409 invalid_sppd_status`. Kegagalan koneksi SIKKEPO mengembalikan `502 sikkepo_unavailable`.
