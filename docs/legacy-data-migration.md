# Migrasi Data Perjadin Legacy

Dokumen ini menjelaskan migrasi data dari CodeIgniter ke Perjadin Laravel tanpa mengubah database atau domain legacy.

## Infrastruktur

- Sumber legacy: database `perjadin-pabar`.
- Target native: database `perjadin_v2` pada MariaDB server IMS.
- Aplikasi tetap berjalan dengan database aktifnya sampai cutover disetujui.
- Akses Oracle ke MariaDB IMS harus menggunakan tunnel privat pada `127.0.0.1:33069`; port MariaDB IMS tidak dibuka publik.

Kredensial disimpan pada file lingkungan yang diabaikan Git. Jangan menyalin kredensial ke `.env.example`, dokumentasi, atau log.

## Persiapan Target

Jalankan migrasi Laravel terhadap koneksi target menggunakan akun migrator, kemudian gunakan akun aplikasi dengan hak DML terbatas setelah cutover.

```bash
php artisan migrate --force
```

Sebelum migrasi, ambil backup konsisten database Perjadin terbaru yang sedang aktif. Pada cutover final, restore backup tersebut ke `perjadin_v2`, lalu jalankan importer terhadap target tersebut.

## Mapping Wajib

Importer tidak mengarang UUID SIKKEPO. Lengkapi tabel berikut sebelum menjalankan import nyata:

- `legacy_unit_mappings`: `source_database`, `legacy_unit_id`, `sikkepo_unit_id`.
- `legacy_employee_mappings`: `source_database`, `legacy_employee_id`, `nip`, `sikkepo_pegawai_id`, dan snapshot SIKKEPO bila tersedia.

Nilai `source_database` harus sama dengan nilai `LEGACY_DB_DATABASE`, yaitu `perjadin-pabar`.

Pegawai atau unit tanpa mapping masuk karantina dan tidak membuat dokumen target. Mapping harus disetujui berdasarkan NIP dan referensi unit SIKKEPO, termasuk pegawai historis yang kini tidak aktif.

## Menjalankan Import

Validasi tanpa menulis data:

```bash
php artisan perjadin:import-legacy --dry-run
```

Uji batch kecil:

```bash
php artisan perjadin:import-legacy --limit=20
```

Import penuh:

```bash
php artisan perjadin:import-legacy
```

Command bersifat idempoten berdasarkan kombinasi database sumber, tabel sumber, dan ID sumber. Hasil disimpan pada:

- `legacy_import_batches`
- `legacy_import_records`
- `legacy_import_issues`

Dokumen yang sudah terimpor tidak diperbarui otomatis apabila sumber legacy berubah. Importer ini adalah migrasi historis, bukan replikasi dua arah.

## Aturan Mapping

| Legacy | Target |
| --- | --- |
| `perjadin_spt` | `spts`, `spt_bases` |
| `perjadin_spt_tujuan` | `spt_destinations` |
| `perjadin_spt_pejabat` | `spt_signatories` |
| `perjadin_sppd` | `sppds` |
| `perjadin_sppd_pengikut` | `sppd_followers` |
| `perjadin_sppd_pejabat` | `sppd_signatories` |
| `ref_transportasi` dan nilai SPPD | `document_references` |

`verifikasi=1` dimigrasikan menjadi `verified`; nilai lain menjadi `draft`. Nomor registrasi dan nomor dokumen harus unik per tahun sesuai constraint target. Konflik nomor, data pegawai hilang, atau metadata dokumen tidak valid masuk karantina.

## Validasi dan Cutover

1. Bandingkan jumlah SPT, SPPD, pengikut, dan referensi dengan sumber.
2. Tinjau seluruh `legacy_import_issues` dan selesaikan mapping atau konflik nomor.
3. Bandingkan sampel PDF SPT, SPPD, dan visum dengan dokumen legacy.
4. Masukkan aplikasi baru ke mode read-only singkat.
5. Backup database Perjadin terbaru, restore ke `perjadin_v2`, lalu jalankan import penuh.
6. Uji API dan PDF di target, kemudian ganti konfigurasi backend ke `perjadin_v2`.
7. Biarkan domain legacy tetap aktif sebagai arsip read-only.

Rollback dilakukan dengan mengarahkan backend kembali ke database sebelumnya. Database `perjadin-pabar` dan domain legacy tidak diubah atau dihapus oleh prosedur ini.
