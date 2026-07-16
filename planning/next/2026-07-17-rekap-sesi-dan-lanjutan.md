# Rekap Sesi 2026-07-17 & Rencana Lanjutan — Payroll Registry Rebuild

## Context

Rekap/handoff untuk menutup sesi besar redesign payroll (Fase 0 + Fase 1 + redesign UI Komponen Gaji). Ini lanjutan dari analisis payroll #3-#5 (2026-07-16, lihat `knowledge/markdown/analisis-payroll-{3,4,5}-*.md`) yang menyimpulkan arsitektur **Registry + Strategy + Config**, lalu benar-benar dieksekusi jadi kode di sesi ini.

**Status paling penting yang harus diketahui (diperbarui 2026-07-17, sesi lanjutan sore)**:
- ✅ **Fase 0 + Fase 1 + fitur Cuti Bersama/Holidays + redesign UI Komponen Gaji SUDAH di-commit (4 commit) dan di-push ke `origin/dev`.** `resources/views/components/page-header.blade.php` juga ikut commit apa adanya (dikonfirmasi user, bukan bagian payroll — cuma numpang lewat commit yang sama).
- ⛔ **Fase 2 (BPJS + gabung Izin Terlambat/Pulang Cepat + Izin Seharian, lihat `planning/2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md`) SUDAH dieksekusi ke database LOKAL tapi BELUM di-commit** — cek `git status`, ini kerjaan sesi paling baru.
- ⛔ **BELUM di-deploy ke production** (berlaku untuk semua fase).
- ⛔ **`php artisan test` BELUM dijalankan ulang** sejak Fase 1 maupun Fase 2 (user eksplisit minta jangan jalankan test dulu).

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

### 4. Fase 2 — BPJS + Gabung Izin + Izin Seharian (`planning/2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md`, dieksekusi lokal, BELUM commit)

Lanjutan sore hari yang sama, dipicu klarifikasi user soal 4 hal yang tercatat di `analisis-payroll-4` §7B. **Hasil akhir beda dari rencana awal §2 dokumen Fase 2** — ada 1 putaran koreksi desain BPJS di tengah eksekusi (dicatat lengkap di §10 dokumen itu):

- **BPJS**: `bpjs_basis` (basis potongan per pangkat: `'ump'`/`'declared_value'`) dipindah dari kolom `m_pangkats` (Fase 0) ke tabel sendiri `pangkat_bpjs_bases` (1:1, pola sama `pangkat_salaries`) — user tidak mau kolom config payroll numpang di tabel master. Toggle company "basis berbeda?" yang sempat dibangun **dihapus lagi** — balik ke aturan simpel: pangkat yang belum di-mapping = otomatis TIDAK ada potongan BPJS (skip), bukan fallback UMP. **Tabel `pangkat_bpjs_bases` masih kosong total** — UI buat isi mapping-nya (rencana: di halaman Komponen Gaji, bagian expand BPJS) **belum dibangun sama sekali**, user eksplisit bilang "ditambahkannya nanti".
- **Izin Terlambat Masuk + Izin Pulang Cepat**: digabung jadi 1 `PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT` + 1 `IzinTerlambatPulangCepatCalculator` (menghitung gabungan 2 kategori absensi). Sisi absensi (`PermissionTypeCategoryEnum`) TETAP 2 tipe terpisah, tidak berubah.
- **Izin Seharian**: rumus dikonfirmasi user dari Excel (`T8 / DAY(EOMONTH(periode,0)) * jumlah_kejadian`), `IzinSeharianCalculator` dibangun & didaftarkan ke Registry. Orchestrator (`PayrollService::generatePayroll()`) di-reorder jadi: semua EARNING → Fee Claim (always-on) → semua DEDUCTION kecuali Izin Seharian → Izin Seharian TERAKHIR (butuh total pendapatan/T8 sudah final).
- **Izin Meninggalkan Jam Kerja**: dikonfirmasi FINAL tidak ada dampak payroll — case-nya dihapus total dari `PayrollComponentEnum` (tetap ada di `PermissionTypeCategoryEnum`, sisi absensi).
- Sudah disanity-check (transaction+rollback via tinker) — tidak ada exception, payroll `is_paid=true` tidak tersentuh. **BELUM ada test unit baru, belum diverifikasi manual di browser.**

## Belum Dikerjakan / Ditunda (urutan prioritas)

1. **Commit & push Fase 2 ke `dev`** — Fase 0/1/Holidays sudah commit+push, Fase 2 (BPJS/Izin) masih di working tree. Cek `git status`.
2. **Bangun UI mapping BPJS per pangkat** — halaman Komponen Gaji, bagian expand komponen BPJS Kesehatan/Ketenagakerjaan, list pangkat company terpilih + pilihan basis (`ump`/`declared_value`) per baris, simpan ke `pangkat_bpjs_bases`. Sampai ini dibangun, BPJS **tidak akan pernah menghasilkan potongan** untuk company manapun (bukan bug, cuma belum ada cara mengisi datanya).
3. **Jalankan `php artisan test`** sebelum push — belum pernah dijalankan sejak Fase 1 maupun Fase 2.
4. **Isi data riil** — semua tabel config (`pangkat_salaries`, `position_allowances`, `transport_allowances`, `tenure_allowances`, `pangkat_bpjs_bases`, `company_payroll_components`) masih KOSONG di luar data uji yang di-rollback.
5. **Deploy ke production** — HANYA setelah poin 1-4 beres DAN sudah direview. Ingat prinsip keamanan: migration Fase 1 (drop tabel lama) WAJIB 1 paket sama kode barunya, backup dulu, `payrolls.is_paid=true` tidak boleh tersentuh.
6. **Pertanyaan terbuka yang masih menahan Lembur khusus Holding** (`analisis-payroll-4` §7.8, §5.2, §5.4, §5.5): arti TL/TM, aturan >2 jam, basis angka 3,5jt/3jt, pembagi ×7.
7. **Fitur import Insentif RSU Banjarbaru** — workstream terpisah, belum disentuh sama sekali (`analisis-payroll-4` §7.6).
8. **Cleanup `m_employee_types`** (data tidak konsisten: "Karyawan Tetap" vs "Tetap", dst) — masih "dicatat dulu", belum dieksekusi (`analisis-payroll-5` §7.3).

## Cara Cepat Lanjut Sesi Berikutnya

- Baca dokumen ini dulu, lalu `planning/2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md` §10 (addendum koreksi BPJS — PENTING, beda dari §2 rencana awal dokumen yang sama).
- `git status` dulu sebelum ngapa-ngapain — Fase 2 belum commit.
- DB lokal sudah ke-migrate semua (Fase 0+1+2) — reversible lewat `down()` KECUALI migration drop `salary_components` dkk (Fase 1, `down()` sengaja dikosongkan).
- Kalau mau lanjut UI mapping BPJS (prioritas #2 di atas), baca `app/Livewire/Keuangan/SalaryComponentsPage.php` + `resources/views/livewire/keuangan/partials/component-card.blade.php` dulu — itu tempat komponen BPJS di-expand, mapping-nya nyambung ke situ.

## Referensi
- [`knowledge/markdown/analisis-payroll-3-skema-komponen-real.md`](../../knowledge/markdown/analisis-payroll-3-skema-komponen-real.md) — arsitektur awal.
- [`knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md`](../../knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md) — data Excel & jawaban user, §7B addendum kedua.
- [`knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md`](../../knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md) — klasifikasi Enum/Hardcode/Config.
- [`planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md`](../2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md) — Fase 0 (commit+push).
- [`planning/2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md`](../2026-07-17-payroll-fase-1-registry-rebuild-dan-calculator.md) — Fase 1 (commit+push).
- [`planning/2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md`](../2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md) — Fase 2 (dieksekusi lokal, belum commit), §10 = desain final BPJS.
