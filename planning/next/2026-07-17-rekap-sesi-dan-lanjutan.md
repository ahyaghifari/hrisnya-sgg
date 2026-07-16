# Rekap Sesi 2026-07-17 & Rencana Lanjutan — Payroll Registry Rebuild

## Context

Rekap/handoff untuk menutup sesi besar redesign payroll (Fase 0 + Fase 1 + redesign UI Komponen Gaji). Ini lanjutan dari analisis payroll #3-#5 (2026-07-16, lihat `knowledge/markdown/analisis-payroll-{3,4,5}-*.md`) yang menyimpulkan arsitektur **Registry + Strategy + Config**, lalu benar-benar dieksekusi jadi kode di sesi ini.

**Status paling penting yang harus diketahui**: semua kode sudah ditulis dan **dijalankan ke database LOKAL** (migrate sukses, `shield:generate` sukses, generate payroll uji coba pakai transaksi+rollback tervalidasi manual angkanya benar). **TAPI:**
- ⛔ **BELUM di-`git commit` sama sekali** — semua perubahan masih di working tree branch `dev` (cek `git status`).
- ⛔ **BELUM di-deploy ke production.**
- ⛔ **`php artisan test` BELUM dijalankan ulang** sejak semua perubahan ini (user eksplisit minta jangan jalankan test dulu sesi ini).
- ℹ️ Ada 1 file (`resources/views/components/page-header.blade.php`) yang termodifikasi tapi **BUKAN dari kerjaan sesi ini** — kelihatan seperti eksperimen manual user sendiri di IDE, isinya `xl:3-xl` (bukan class Tailwind valid, kemungkinan typo/belum selesai). Jangan diasumsikan bagian dari payroll work, cek ke user dulu sebelum disentuh/direvert.

## Ringkasan Apa yang Sudah Dikerjakan

### 1. Fase 0 — Fondasi Data (`planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md`)
- `m_kelas.level` (int, di-backfill otomatis dari `name` — 39/39 baris sukses).
- `m_pangkats` + 4 kolom (`start_kelas_level`, `max_kelas_level`, `class_increment_rate`, `bpjs_basis`) — di-backfill cuma buat Holding & RS Banjarbaru (12/18 baris; RS Barabai sengaja `NULL`, datanya belum dikonfirmasi).
- Tabel baru kosong: `pangkat_salaries`, `position_allowances`, `transport_allowances`, `tenure_allowances` (angka riil belum diisi — itu kerjaan finance lewat UI, bukan migration).
- Tabel `settings` (config generik lintas modul, pola company_id nullable + fallback global — dicontek dari pola `AttendancePolicies` yang sudah ada) + `PayrollSettingsSeeder`.

### 2. Fase 1 — Registry Rebuild (`planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md`)
- **`salary_components`/`salary_component_assignments`/`salary_component_employee_types` dihapus total** (katalog freeform lama), diganti `PayrollComponentEnum` (20 case tertutup) + tabel `company_payroll_components` (saklar ON/OFF per company).
- **17 Calculator class** (`app/Services/Payroll/Calculators/`) — 1 kelas per komponen, logic dipindah dari `PayrollService` lama (Gaji Pokok additive, Tunjangan Jabatan/Transportasi/Masa Kerja lookup tabel, BPJS, Keterlambatan+grace, Izin flat, Lembur shift-based existing, Jasa Pelayanan, Insentif, Fee Claim+Pajak Dokter, Koperasi/Bon manual, Cuti no-op).
- `PayrollService::generatePayroll()` turun dari **~840 baris jadi ~120 baris** (orchestrator tipis, baca Registry lalu panggil Calculator).
- 2 tabel baru: `service_fee_pools` (pool Jasa Pelayanan bulanan, dulu numpang di assignment lama), `payroll_manual_lines` (Koperasi/Bon).
- 3 temuan/bug yang ditemukan & ditutup DI LUAR rencana awal (dicatat detail di dokumen Fase 1): FK `fee_items.salary_component_id` yang harus di-drop dulu sebelum tabel lama bisa dihapus, `Tunjangan Kelebihan Jam Kerja` yang kelewat dari desain enum awal, dan `Lembur` yang ternyata TIDAK semua ditunda (cuma rumus khusus Holding yang ditunda, mekanisme shift-based existing tetap dipertahankan).

### 3. Redesign UI Komponen Gaji (belum ada dokumen planning terpisah — keputusan desain interaktif sepanjang sesi, dicatat di sini)
- **3 lapis setting**: `GlobalPayrollSettingEnum` (UMP, rate BPJS — selalu global, panel gradient navy di bawah halaman) / `PayrollComponentEnum::requiredSettings()` (wajib per komponen, selalu company-scoped, expand otomatis pas komponen diaktifkan) / "Pengaturan Lainnya" (sisa setting belum terklaim, ekstensibilitas).
- Layout: kiri daftar company (urut kode tabel, bukan alfabetis) — kanan 2 kolom **Pendapatan/Potongan** di desktop (numpuk di mobile).
- Simpan **terpisah per scope**: 1 tombol buat company yang lagi dipilih, 1 tombol lagi khusus panel Global (tidak lagi 1 tombol nyimpen semuanya sekaligus).
- Checkbox custom (`.custom-checkbox` di `app.css`) — bukan `accent-color` native (renderingnya tidak konsisten antar browser), warna per komponen lewat CSS custom property `--accent`.
- Halaman "Pengaturan" terpisah (`/keuangan/pengaturan`) **dihapus total** — fungsinya sudah menyatu ke Komponen Gaji.

## Belum Dikerjakan / Ditunda (urutan prioritas)

1. **Commit & push ke `dev`** — belum dilakukan sama sekali. Cek `git status` dulu (banyak file dihapus/ditambah, pastikan tidak ada yang kebawa tidak sengaja), lalu commit dengan pesan yang jelas (bisa dipecah beberapa commit: Fase 0, Fase 1, redesign UI).
2. **Jalankan `php artisan test`** sebelum push — belum pernah dijalankan sejak Fase 1. Ada regresi lama yang sudah diketahui tidak terkait (6 gagal pre-existing per sesi sebelumnya), tapi WAJIB dicek ulang karena `PayrollService` & banyak model di-refactor besar-besaran sesi ini — kemungkinan ada test lama yang menyentuh `salary_components`/model lama yang sekarang sudah hilang dan perlu diperbarui/dihapus.
3. **Isi data riil** — semua tabel config (`pangkat_salaries`, `position_allowances`, `transport_allowances`, `tenure_allowances`, `company_payroll_components`) masih KOSONG di luar data uji yang di-rollback. Perlu: (a) finance isi angka riil per pangkat/kelas/company, (b) admin toggle komponen mana yang aktif per company lewat UI baru, (c) isi `bpjs_basis` per pangkat (masih semua `NULL`).
4. **Deploy ke production** — HANYA setelah poin 1-3 beres DAN sudah direview. Ingat prinsip keamanan yang sudah disepakati: migration Fase 1 (drop tabel lama) WAJIB 1 paket sama kode barunya, backup dulu, `payrolls.is_paid=true` tidak boleh tersentuh.
5. **Pertanyaan terbuka yang masih menahan 3 komponen** (`analisis-payroll-3` §8, `analisis-payroll-4` §7): rumus Izin Seharian, aturan Izin Meninggalkan Jam Kerja, arti TL/TM + aturan >2 jam Lembur khusus Holding. Case enum-nya sudah ada tapi Calculator belum dibangun — UI toggle sudah menandai "Belum tersedia" buat 2 yang pertama.
6. **Fitur import Insentif RSU Banjarbaru** — workstream terpisah, belum disentuh sama sekali (`analisis-payroll-4` §7.6).
7. **Cleanup `m_employee_types`** (data tidak konsisten: "Karyawan Tetap" vs "Tetap", dst) — masih "dicatat dulu", belum dieksekusi (`analisis-payroll-5` §7.3). `company_payroll_components.eligible_employee_statuses` masih pakai ID `m_employee_types` apa adanya, ikut kena dampak kalau cleanup ini jalan nanti.
8. **File `page-header.blade.php`** — cek ke user, sepertinya modifikasi manual yang belum selesai (`xl:3-xl` bukan class Tailwind valid).

## Cara Cepat Lanjut Sesi Berikutnya

- Baca dokumen ini dulu, lalu `planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md` §7 (verifikasi) buat pola testing yang sudah dipakai (transaksi+rollback, jangan generate payroll sungguhan tanpa sengaja).
- `git status` dulu sebelum ngapa-ngapain — banyak file baru/dihapus, pastikan tidak ada yang ke-skip pas nanti commit.
- DB lokal sudah ke-migrate — kalau mau ulang dari nol buat testing, migration Fase 0+1 semuanya reversible lewat `down()` KECUALI migration drop `salary_components` dkk (`down()` sengaja dikosongkan, baca komentarnya).
- Kalau lanjut ke Fase 2 (isi data riil via UI / bangun Calculator yang masih ditunda), pastikan jawaban pertanyaan terbuka di poin 5 di atas sudah ada — jangan menebak rumus.

## Referensi
- [`knowledge/markdown/analisis-payroll-3-skema-komponen-real.md`](../../knowledge/markdown/analisis-payroll-3-skema-komponen-real.md) — arsitektur awal.
- [`knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md`](../../knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md) — data Excel & jawaban user, daftar pertanyaan terbuka terkini.
- [`knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md`](../../knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md) — klasifikasi Enum/Hardcode/Config.
- [`planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md`](../2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md) — Fase 0.
- [`planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md`](../2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md) — Fase 1.
