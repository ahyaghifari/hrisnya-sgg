# Analisis Sikartap — Persiapan Migrasi ke HRIS

Sumber: `another-app/sikartap` (PHP prosedural, tanpa framework, mysqli). Sistem Pengajuan Karyawan Tetap RSU — alur pelamar/karyawan kontrak menuju status tetap: verifikasi berkas → tes → wawancara → MCU → keputusan direktur → ide inovasi. Plus alur terpisah: perpanjangan kontrak.

Artifact visual (diagram alur, tabel skema, red flags): dibuat 2026-08-25, lihat riwayat percakapan — tidak disalin ulang di sini, dokumen ini fokus pada temuan tertulis + keputusan arsitektur migrasi.

## A. Arsitektur

- PHP native, tiap file `.php` = controller + view sekaligus, query `mysqli` langsung ditulis di tengah HTML, tanpa ORM.
- Satu `$conn` global (`config.php:16`), auth session-based (`check_login()`, `check_hrd()`, `check_direktur()`, dst — `config.php:35-102`).
- Sanitasi (`clean_input()`) dipakai tidak konsisten — sebagian titik query murni string-concat.
- Upload file fisik ke `uploads/`, path disimpan varchar, tanpa validasi MIME mendalam.
- **Snapshot kode tidak lengkap**: `config.php:104` require `unit_kategori.php` yang tidak ada di folder. `verifikasi_ide_inovasi.php` dan `verifikasi_berkas.php` direferensikan aktif dari `dashboard_hrd.php`/`dashboard_direktur.php`/`detail_pengajuan.php` tapi filenya hilang. **Wajib didapat dari server produksi** sebelum alur verifikasi berkas & ide inovasi dianggap final.

## B. Role/Aktor

`karyawan` (pelamar), `hrd`, `direktur`, `staff_lab`, `staff_radiologi`, `staff_perawat`, `dokter`, `kabag_adum`/`kabid_pelayanan`/`kabid_penunjang` (verifikator ide inovasi per-unit), `komisaris` (verifikasi ide inovasi tahap 2, independen dari direktur).

Red flag: kolom `users.role` di dump SQL cuma `enum('karyawan','hrd','direktur')` — 6 role lain tidak ada di enum, dump sudah usang dibanding kode aktif.

## C. Skema Database

Dump `database/db_pengajuan_karyawan.sql` (1270 baris, 1 Des 2025) **bukan sumber kebenaran** — kode aktif memakai tabel/kolom yang tidak ada di dump: `mcu_asesmen_perawat`, `mcu_hasil_laboratorium`, `mcu_hasil_radiologi`, `mcu_pemeriksaan_dokter`, `penilaian_wawancara`, kolom `is_diskualifikasi`/`alasan_diskualifikasi` di `pengajuan_karyawan_tetap`, kolom `status_atasan`/`status_komisaris` di `ide_inovasi`.

Tabel inti:
- `users` — data karyawan/pelamar + kredensial (bcrypt).
- `pengajuan_karyawan_tetap` — state machine multi-kolom: `status_berkas`, `status_tes`, `status_wawancara`, `status_mcu`, `status_hasil_direktur`, `status_ide_inovasi`, `berkas_lengkap`.
- `dokumen_administratif`, `status_dokumen` (approve/reject per-dokumen individual).
- `jadwal_tes`, `jadwal_wawancara`, `jadwal_mcu`.
- `ide_inovasi` (unique per pengajuan).
- `pengajuan_perpanjangan_kontrak`, `dokumen_perpanjangan` — alur terpisah.
- `email_queue` (queue async) vs `email_queue2` (duplikat struktur identik, 0 referensi kode — kandidat dihapus).
- `provinsi`/`kabupaten_kota`/`kecamatan`/`kelurahan` — banyak baris duplikat, indikasi `import_wilayah_indonesia.php` dijalankan berulang tanpa dedup.
- `users_backup`, `users_backup2` — bukan tabel operasional.

**Red flag serius**: seluruh FK di dump (`pengajuan_karyawan_tetap.user_id`, `pengajuan_perpanjangan_kontrak.user_id/diproses_oleh`, `user_settings.user_id`) mengarah ke `users_backup2(id)`, bukan `users(id)`. Kemungkinan bug ekspor phpMyAdmin — **wajib diverifikasi di database live** sebelum migrasi.

## D. Alur Pengajuan Karyawan Tetap (end-to-end)

1. Registrasi (`register.php`) — validasi field deklaratif via array `$required_fields`/`$required_kontrak`/`$required_nakes`.
2. Login (`login.php`) — NIP + password, redirect sesuai role.
3. Buat pengajuan baru — `INSERT INTO pengajuan_karyawan_tetap (user_id)`, semua status default `pending`.
4. Upload berkas administratif (`berkas_diupload.php`).
5. Verifikasi berkas oleh HRD (halaman hilang: `verifikasi_berkas.php`) — approve/reject per dokumen + keseluruhan. Gate: `status_berkas = approved` & `berkas_lengkap = ya`.
6. Tes bidang + psikotes — jadwal (`jadwal_tes`), input hasil → `status_tes`. Gate: lulus.
7. Wawancara — jadwal 2 pewawancara, form `form_penilaian_wawancara.php`, hasil ke `penilaian_wawancara` + `status_wawancara`. Gate: disarankan.
8. MCU paralel — perawat (`form_asesmen_perawat.php`), lab (`form_hasil_laboratorium.php`), radiologi (`form_hasil_radiologi.php`), dokter sebagai gate final (`form_pemeriksaan_dokter.php` → `rekomendasi` fit/unfit) → `status_mcu`.
9. Diskualifikasi (opsional, kapan saja) — AJAX `diskualifikasi.php`, guard HRD, sekali jalan tidak bisa diulang.
10. Keputusan direktur — syarat: `status_berkas=approved` & `status_tes=lulus` & `status_wawancara=disarankan` & `status_mcu=fit` (logika `get_keputusan_akhir()`, **diduplikasi identik** di `detail_pengajuan.php:16-49` dan `detail_pengajuan_direktur.php:17-40`). Per-individu (`detail_pengajuan_direktur.php`) atau bulk (`bulk_keputusan_direktur.php`, bagian dari FIX 21 — sebelumnya karyawan otomatis lulus begitu MCU fit tanpa menunggu direktur).
11. Lulus → lanjut ide inovasi (§ F). Gagal → catatan penolakan ditampilkan ke karyawan.

## E. Alur Perpanjangan Kontrak (terpisah)

Karyawan ajukan (`perpanjangan_kontrak.php`, upload 3 dokumen: kontrak terakhir, penilaian kinerja, rekomendasi) → HRD review (`perpanjangan_kontrak_hrd.php`, `detail_perpanjangan.php`) → keputusan single-step HRD (`proses_perpanjangan.php`, guard `status_pengajuan != pending`, tidak bisa diubah lagi). **Tidak melibatkan direktur** — beda dari alur karyawan tetap. Tidak ada trigger otomatis dari `users.tanggal_habis_kontrak`.

## F. Alur Ide Inovasi (berjenjang) — UPDATE 2026-08-25: 3 file hilang sudah didapat, ketemu bug fatal

Versi lama single-step: `review_ide_inovasi.php` — sudah jadi stub redirect ke `verifikasi_ide_inovasi.php` (baris 2-8), sisa ~150 baris di bawahnya dead code tak terjangkau.

Versi berjenjang: `dashboard_verifikator_inovasi.php` (dipakai role `kabag_adum`/`kabid_pelayanan`/`kabid_penunjang`/`komisaris`/`direktur`) scoping unit via `get_kategori_unit($asal_unit)` (`unit_kategori.php`, isinya peta unit→kategori `adum`/`pelayanan`/`penunjang` + `get_role_atasan_by_kategori()`/`get_label_role_verifikator()`), alur: Kabag/Kabid unit asal (`ide_inovasi.status_atasan`) → Komisaris & Direktur (independen satu sama lain, syarat cuma `status_atasan = approved`, cek `status_komisaris`/`status_direktur` masing-masing).

**BUG FATAL ditemukan**: `dashboard_verifikator_inovasi.php` nge-link tombol "Verifikasi" ke `verifikasi_ide_inovasi.php?id=...` buat SEMUA role (termasuk Kabag/Kabid/Komisaris). Tapi `verifikasi_ide_inovasi.php` sendiri cuma cek role `hrd`/`direktur` (baris 19-25) — role lain langsung di-redirect ke `dashboard.php`. File itu juga cuma nulis 1 kolom `ide_inovasi.status` (approved/rejected/pending), sama sekali tidak menyentuh `status_atasan`/`status_komisaris`/`status_direktur`.

**Konsekuensi**: Kabag/Kabid/Komisaris klik "Verifikasi" dari dashboard mereka sendiri → dead end, redirect balik, tidak bisa approve/reject apapun. Alur berjenjang ide inovasi **tidak pernah benar-benar berfungsi** di kode yang ada — pola sama persis dengan [[lms-review-esai-dead-end]] yang pernah ditemukan di modul lain. Tidak ada logic approve/reject tier Kabag→Komisaris→Direktur yang bisa dijadikan referensi kerja untuk migrasi — harus didesain dari nol (memperkuat keputusan § J: rewrite, bukan port).

`verifikasi_berkas.php` (sudah didapat): approve/reject per dokumen + approve/reject keseluruhan via HRD, kirim email lewat queue. Field dokumen wajib: `surat_permohonan`, `formulir_rekomendasi_atasan`, `formulir_rekomendasi_pejabat`, `penilaian_kinerja`, `ijazah_terakhir`, `ktp_kk` (6 dari 11 total field dokumen).

## G. Email (queue-based)

Desain solid: aksi insert row `pending` ke `email_queue`, bukan kirim langsung. 11 fungsi `queue_*_email()` di `email_queue_functions.php`. Consumer: `kirim_email.php` (manual HRD), `process_email_queue.php` (CLI/cron), `process_queue_ajax.php` (AJAX auto-refresh), `email_queue_monitor.php` (monitoring). Kredensial SMTP plaintext di `email_config.php:17`.

## H. File Dead Code / Duplikat (tidak perlu dimigrasi)

≈27 dari 79 file root (34%), diverifikasi via grep referensi silang:
- **Dashboard/detail 0 referensi**: `dashboard2.php`, `detail_pengajuan2.php`, `detail_pengajuan_direktur2.php`, `detail_pengajuan_direktur3.php`. (`dashboard_direktur2.php` perlu klarifikasi manual — tidak ada file yang me-link tapi kemungkinan bookmark production.)
- **Form bertingkat, versi tanpa angka aktif**: `form_asesmen_perawat2.php`, `form_hasil_laboratorium2/3.php`, `form_hasil_radiologi2/3/4.php`, `form_pemeriksaan_dokter2.php`, `form_penilaian_wawancara2/3.php`.
- **Email sisa iterasi**: `email_queue_functions1.php`, `email_functions.php`, `email_helper_with_queue.php`, `fix_email_from.php`, `preview_email_template.php`, `kirim_email2.php`, `kirim_email3.php`.
- **Tooling debug tanpa auth check**: `cek_config.php`, `hapus_cek_config.php`, `cek_data_wilayah.php`, `cek_jadwal_pengajuan.php`, `cek_kolom_asal_unit.php`, `cek_nilai_ide_inovasi.php`, `generate_password_direktur.php`, `import_wilayah_indonesia.php`.

## I. Red Flags

1. **Kredensial hardcoded plaintext** — DB password (`config.php:6-7`), SMTP password (`email_config.php:17`), DB credential terpisah (`import_wilayah_indonesia.php:16-17`). Zip 112MB di root kemungkinan berisi kredensial produksi asli — wajib rotate begitu audit selesai.
2. **`generate_password_direktur.php` tanpa auth** — bocorin password default `direktur123` + hash bcrypt + SQL INSERT siap pakai.
3. **IDOR di `download.php:6-16`** — cuma cek `check_login()`, bukan kepemilikan file. Nama file predictable (`user_id + field + time()`) — karyawan A berpotensi unduh dokumen karyawan B.
4. **Query string-concat** — sanitasi bergantung penuh disiplin developer manggil `clean_input()`/`intval()` di tiap titik, satu field baru yang lupa langsung jadi celah SQLi.
5. **FK ke tabel backup** (`users_backup2`) — lihat § C.
6. **Data wilayah terduplikasi masif** — jangan port mentah.
7. **`get_keputusan_akhir()` terduplikasi** di 2+ file — perubahan aturan kelulusan harus diingat diupdate di semua tempat.

## J. Keputusan Arsitektur Migrasi (dibahas 2026-08-25)

**Kesimpulan: bukan port kode, tapi rewrite modul baru** memakai pola arsitektur HRIS yang sudah ada, dengan Sikartap sebagai referensi bisnis-logic saja — bukan kode yang dipindah. Alasan: query legacy rawan SQLi, IDOR di download, kredensial hardcoded, dan 3 file inti hilang dari snapshot (blocker sampai didapat dari server produksi).

**Prasyarat sebelum mulai coding**:
1. Dump skema + data production terkini langsung dari server (dump yang ada ketinggalan — gak ada tabel MCU per-role, kolom diskualifikasi, kolom ide-inovasi berjenjang).
2. Source `verifikasi_ide_inovasi.php`, `verifikasi_berkas.php`, `unit_kategori.php` yang hilang.
3. Rotate semua kredensial di § I.1.

**Struktur folder — domain-specific vs shared entities**:
- **Boleh diisolasi di folder `Sikartap`** (`app/Models/Sikartap`, `app/Services/Sikartap`, `app/Livewire/Sikartap`, route grup terpisah di `config/portal-pages.php`): tabel domain-specific yang gak ada padanannya di HRIS — `PengajuanKaryawanTetap`, `DokumenAdministratif`, `JadwalTes`/`JadwalWawancara`/`JadwalMcu`, `McuAsesmenPerawat`/`McuHasilLaboratorium`/`McuHasilRadiologi`/`McuPemeriksaanDokter`, `IdeInovasi`, `PengajuanPerpanjanganKontrak` (**cek dulu tumpang tindih sama modul Kontrak existing** — `KontrakPage.php` sudah ada alur Perpanjangan Kontrak sendiri, jangan bikin 2 sistem perpanjangan kontrak paralel).
- **Jangan diisolasi, reuse yang sudah ada**: `User` (jangan bikin model User/karyawan versi Sikartap sendiri — itu ngulang masalah dua-sistem-dua-sumber-data yang justru lagi dibereskan), Approval Engine (alur berjenjang berkas→tes→wawancara→MCU→direktur dan Kabag→Komisaris→Direktur reuse `ApprovalActivities`/`ApprovalModuleEnum`, tinggal tambah module baru di enum — bukan bikin tabel approval sendiri), notifikasi (ganti `email_queue` custom dengan Laravel Notification/Queue native, cuma port daftar 11 jenis notifikasinya sebagai Notification classes), data wilayah (cek dulu HRIS udah punya master wilayah — kemungkinan besar udah ada, gak perlu port).

**Pemetaan modul → HRIS**:

| Modul Sikartap | Tabel sumber | Arah di HRIS |
|---|---|---|
| Data karyawan/pelamar | `users` | Reuse `User`/Kepegawaian existing, jangan duplikat |
| Pengajuan karyawan tetap | `pengajuan_karyawan_tetap` + turunan | Modul baru `Sikartap`, state machine per tahap, approval via Approval Engine existing |
| Perpanjangan kontrak | `pengajuan_perpanjangan_kontrak` | Cek tumpang tindih modul Kontrak existing dulu |
| Ide inovasi | `ide_inovasi` | Modul baru `Sikartap`, approval berjenjang via Approval Engine existing |
| Email notifikasi | `email_queue` | Laravel Notification/Queue native |
| Data wilayah | `provinsi`...`kelurahan` | Cek HRIS udah punya, kemungkinan besar tidak perlu diport |
| File upload | folder `uploads/` | Model Media/polymorphic attachment + policy-based auth, perbaiki IDOR § I.3 |

**Rekomendasi desain state machine**: `pengajuan_karyawan_tetap` punya 5+ kolom status independen lintas banyak role — pertimbangkan workflow eksplisit (`spatie/laravel-model-states` atau tabel riwayat approval terpisah) alih-alih replikasi pola "banyak enum di satu tabel", sekaligus menyelesaikan duplikasi `get_keputusan_akhir()` dengan satu sumber kebenaran status.
