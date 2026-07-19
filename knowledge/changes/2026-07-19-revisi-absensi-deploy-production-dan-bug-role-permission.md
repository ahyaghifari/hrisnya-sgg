# Perubahan: Revisi Absensi, Deploy ke Production, dan Perbaikan Bug Role Permission (2026-07-19)

Lanjutan dari `planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md` (dokumen rencana lengkap, baca itu dulu untuk detail keputusan desain). Sesi ini: eksekusi penuh revisi Absensi, verifikasi keamanan migrasi terhadap salinan database production, **deploy sungguhan ke `dev` dan `main`**, plus investigasi + perbaikan 2 bug tidak terduga (gate Payroll Fase 4, form Role/Permission).

## Ringkasan Status Akhir

- **Revisi Absensi**: selesai dieksekusi & sudah di-deploy (commit `b2a4b72`).
- **Bug Role/Permission** (preload checkbox salah + `sistem_absensi` belum di-seed): ditemukan & diperbaiki (commit `02288cf`).
- **Kedua commit sudah di-push ke `dev` DAN `main`** — `main` = production, per `GIT-WORKFLOW.md`. Tidak bisa dipastikan dari sisi ini apakah pipeline CI/CD sungguhan sudah jalan (tidak ditemukan file `.github/workflows/` atau `bitbucket-pipelines.yml` di repo, padahal README menyebut Bitbucket Pipelines — remote repo aktualnya GitHub, ada ketidaksesuaian dokumentasi lama vs kenyataan, perlu dicek manual ke platform hosting).
- **Payroll Fase 4**: root cause 5 test gagal sudah ditemukan (bukan bug, gate baru yang test-nya belum diupdate) — **belum diperbaiki**, tabel `payroll_component_recipients` juga **masih kosong di production**, jadi Generate Payroll berikutnya akan diam-diam menghasilkan 0 baris kalau tidak diisi dulu.
- **Restrukturisasi Jadwal (Shift) Tahap 2**: dikonfirmasi **belum lengkap** (tidak ada edit/delete jadwal, fitur Tukar Shift hilang total) — tetap ikut dideploy atas keputusan eksplisit user yang menerima risikonya.

## 1. Revisi Absensi — Dieksekusi Penuh

Semua 9 item di `planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md` selesai:

1. Setting `attendance_period_cutoff_day` + `AttendanceSettingEnum` + `AttendancePeriodResolver` + `AttendanceSettingsSeeder` (dipisah dari `PayrollSettingsSeeder`).
2. `LeaveReqRepository::getLeaveUserBalance()` direfactor total — sisa Cuti Tahunan dihitung **live** dari request `approved` (bukan counter `LeaveUserBalances.used` yang dulu ditambah manual saat submit & tidak pernah dikurangi saat reject/cancel). Ini **menutup bug lama** yang sudah diketahui dari sesi sebelumnya (saldo cuti tidak reversible).
3. Enforcement `izin_max_per_periode` baru (`AttendaceReqService::assertWithinIzinPeriodeLimit()`) — gabung 4 kategori `PermissionTypeCategoryEnum` jadi 1 kuota.
4. Izin Sakit dihapus dari `AttendanceRequestTypeSeeder` (dikonfirmasi tidak masuk sistem).
5. Halaman baru: **Pengaturan Absensi** (`/presensi/pengaturan`), **Kalender Hari Libur** read-only untuk karyawan (`/presensi/kalender-libur`, belum ada link nav — sengaja).
6. **Hari Libur & Cuti Bersama** direlokasi dari grup Master (`sistem_master`) ke grup **Presensi & Absensi** (`sistem_absensi`) — route `master.hari-libur` → `presensi.hari-libur`.
7. Blok "Kuota Pemohon" ditambahkan di halaman detail approval (portal `AttendanceRequestDetail` DAN admin `AttendanceRequestForm`) — approver lihat sisa jatah pemohon saat memutuskan, bukan widget dashboard terpisah.
8. Form Filament Jenis Izin (`PermissionTypeForm.php`) ditambah field "Kategori" (dulu cuma ada di Jenis Cuti) — supaya company lain bisa setup limit Izin sendiri lewat UI.
9. Bug `HolidaysTable` (`->query()` tidak pernah di-set eksplisit, cuma jalan kalau dipakai di dalam konteks Filament Resource) — ditemukan saat user tes manual di browser, sudah diperbaiki.

## 2. Verifikasi terhadap Salinan Database Production

User backup database production, swap ke lokal, minta migrasi + tes dijalankan sebelum deploy sungguhan. Temuan penting:

- 32 migration pending dijalankan bersih, **data kritis utuh** (6 payroll `is_paid=true`, 489 users, 171 attendance_requests — semua tidak tersentuh).
- Drop tabel lama Payroll Fase 1 (`salary_components` 11 baris, `salary_component_assignments` 22 baris) — jumlahnya **persis cocok** dengan yang sudah dikonfirmasi aman dihapus di planning session sebelumnya, bukan keputusan baru.
- **Kolom `category` pada "Cuti Tahunan" masih kosong** di data production — limit `cuti_tahunan_max_per_bulan` (Holding = 5 hari) **tidak akan aktif** sampai admin isi manual lewat `/admin/leave-types`.
- **Flow approval "default" untuk company Holding sudah soft-deleted** (`m_approval_flows`, ada `deleted_at`) — cuma tersisa flow `specific` per role/departemen. User tanpa role yang cocok flow spesifik gagal submit pengajuan ("No active approval flow found") — gap lama, bukan dari revisi ini.
- Simulasi submit Cuti/Izin + approve/reject langsung di data production (data dummy, dibersihkan lagi setelahnya) membuktikan bug fix §1.2 & enforcement §1.3 bekerja benar.

Detail lengkap ada di `deployment/2026-07-19-post-migrate-revisi-absensi.md` — checklist yang harus dijalankan di server produksi sungguhan setelah kode sampai (seeder, permission, isi data master).

## 3. Payroll Fase 4 — Root Cause 5 Test Gagal (BELUM Diperbaiki)

User minta dicek kenapa `IncentiveImportTest`, `KoperasiPageTest`, `KoperasiSyncTest`, `PayrollPeriodGeneratedByTest`, `RegenerateSinglePayrollTest` gagal. Ditemukan: fitur baru "Penerima Komponen Gaji" (`PayrollComponentRecipient`, halaman `/keuangan/penerima-komponen-gaji`) menambah gate `isEligible()`/`isRegisteredAnywhere()` di **7 titik kode** (`PayrollService` generate/regenerate/runComponent, `KoperasiPage::saveAdd()`, `IncentiveImport`, `BonImport`, `PayrollDetailPage`) — kelima test itu tidak setup data `payroll_component_recipients`, jadi gate-nya selalu menolak.

**Diverifikasi lewat simulasi langsung di data production**: mekanismenya bekerja benar (sebelum diisi = `false`/diblokir, setelah 1 baris dibuat = `true`/terbuka) — bukan bug, cuma butuh data. **Tapi tabel ini kosong total di production** — kalau tidak diisi dulu lewat halaman "Penerima Komponen Gaji", Generate Payroll berikutnya akan **diam-diam menghasilkan 0 payroll** (job queue, notifikasi "berhasil" tetap muncul terlepas hasil sebenarnya).

**Belum dikerjakan**: perbaikan 5 test (perlu tambah `PayrollComponentRecipient::create(...)` di setup masing-masing) dan pengisian data `payroll_component_recipients` di production.

## 4. Restrukturisasi Jadwal (Shift) Tahap 2 — Gap Dikonfirmasi, Tetap Dideploy

Investigasi git status menemukan: semua Resource Filament lama untuk kelola Shift (`MasterDataShifts`, `ScheduleShifts`, `ShiftTemplates`, `ShiftSwapApprovals`, `UserShiftSwaps`, `UserShiftSchedules`) sudah dihapus, tapi pengganti di portal (`SchedulesPage`/`CreateSchedulePage`) **cuma bisa bikin jadwal baru** — tidak ada edit/delete, dan **fitur Tukar Shift hilang total tanpa pengganti** (baik sisi karyawan minta tukar maupun sisi atasan approve). Ini memang sudah dicatat sebagai belum dikerjakan di `planning/2026-07-18-restrukturisasi-jadwal-schedules.md` §5, bukan salah eksekusi.

User diberi pilihan eksplisit (tunda vs deploy sekalian) — **memilih deploy sekalian, menerima risikonya**. HR/admin kehilangan kemampuan kelola shift & approve tukar shift sampai Tahap 2 lanjut dikerjakan.

## 5. Housekeeping Admin

- **`AdminUserResource` dihapus total** (4 file) — prinsip project: role di-assign ke `User` manapun lewat `/admin/roles`, bukan lewat "akun admin" terpisah (lihat `knowledge/dev/konsep-user-role-bukan-akun-admin-terpisah.md`).
- **Tombol "Hapus Periode"** di `/admin/payroll-periods` — bersih total (payroll → item cascade DB, manual line cascade DB, periode itu sendiri), diblokir kalau ada payroll `is_paid=true`. Dibuat khusus supaya user bisa generate-hapus-generate ulang saat testing payroll tanpa nyampah data.
- **Tombol "Hapus"** di `/admin/attendance-requests` — bersih total (detail cuti/izin/lembur cascade DB, snapshot approval + activity + histori dicari manual lalu dihapus karena bukan FK asli). Tanpa guard status, khusus buat testing pengajuan berulang.

Keduanya diverifikasi lewat simulasi langsung (buat data dummy → hapus → cek semua tabel terkait jadi 0 → bersihkan).

## 6. Bug Role/Permission — Ditemukan Saat Troubleshoot, Sudah Diperbaiki

User lapor assign `sistem_keuangan`/`sistem_absensi` ke role "tidak tersimpan" lewat UI `/admin/roles`. Ditemukan 2 bug:

1. **`sistem_absensi` belum pernah ada di tabel `permissions`** — `CustomPermissionsSeeder` belum pernah dijalankan sejak case ini ditambahkan ke enum saat fitur Jadwal dibangun. Diperbaiki dengan menjalankan seeder-nya.
2. **`EditRole::mutateFormDataBeforeFill()` pakai logic grouping yang salah** — split underscore (`explode('_', $permission)`, format lama "action_resource"), padahal `RoleForm.php` generate field checkbox dari split titik dua (`explode(':', $permission->name)`, format Shield "Verb:Subject" saat ini). Akibatnya: **setiap buka Edit Role, semua checkbox tampil kosong** walau role sudah punya banyak permission — dan karena `syncPermissions()` mengganti total (bukan menambah), risiko nyata kalau admin cuma centang sedikit lalu Save, permission lain yang sudah ada bisa hilang tanpa disadari. Diperbaiki, grouping sekarang konsisten dengan `RoleForm.php`.

**Diverifikasi lewat browser sungguhan** (Playwright, login sebagai Super Admin) — edit role "HC Manager", checkbox permission yang sudah ada terbukti tampil tercentang (preload benar), centang `sistem_keuangan`+`sistem_absensi` baru, Simpan, cek database: kedua permission baru tersimpan **dan** permission lama (`approve_pengajuan_absensi`, total 143) **tetap ada**, tidak terhapus. Role "HC Manager" dikembalikan ke kondisi semula setelah tes (permission tes dicabut lagi).

Efek samping tes ini: password akun `ahyaghifari288@gmail.com` di database lokal sempat diganti sementara (`tes-sementara-123`, tidak bisa dikembalikan ke password asli) supaya bisa login lewat browser untuk reproduksi bug — user sudah diinfokan untuk ganti ulang lewat `/akun/ubah-password`. Role "Super Admin" (global, `company_id` NULL) ikut ter-assign ke akun ini sebagai bagian dari proses tes yang sama.

## 7. Commit & Deploy

```
02e1c73 (HEAD lama)
b2a4b72 Revisi Absensi (cutoff, kuota izin/cuti, grup Presensi), lanjutan Payroll Fase 4,
        Restrukturisasi Jadwal Tahap 2, dan housekeeping admin — 115 file, +2861/-4201
        (2026-07-19 11:16:50 +0800)
02288cf Perbaiki bug preload checkbox permission di Edit Role (grouping tidak cocok)
        — 1 file, +8/-7 (2026-07-19 11:35:44 +0800)
```

Kedua commit di-push ke `dev` lalu di-merge (fast-forward, tanpa konflik) ke `main` dan di-push juga. Push pertama (`b2a4b72`) dilakukan setelah user eksplisit memilih "deploy semua termasuk gap Shift Tahap 2, terima risikonya" lewat konfirmasi pilihan.

## 8. Yang Masih Perlu Dikerjakan (Belum Selesai per Akhir Sesi)

Urutan bebas, tidak saling bergantung kecuali disebutkan:

1. **Isi `payroll_component_recipients` di production** (§3) — sebelum Generate Payroll periode berikutnya, kalau tidak generate akan diam-diam kosong.
2. **Perbaiki 5 test Payroll Fase 4** (§3) — tambah setup `PayrollComponentRecipient` di masing-masing test.
3. **Isi kategori "Cuti Tahunan"** di `/admin/leave-types` untuk company yang mau limit `cuti_tahunan_max_per_bulan` aktif (§2).
4. **Lanjutkan Restrukturisasi Jadwal Tahap 2** (§4) — minimal edit/delete jadwal + fitur Tukar Shift, supaya gap yang sudah dideploy ini tertutup.
5. **Verifikasi mekanisme deploy CI/CD sungguhan** — tidak ditemukan config pipeline di repo, perlu dicek manual ke platform hosting (README menyebut Bitbucket, remote aktual GitHub — kemungkinan dokumentasi sudah usang).
6. **Ganti ulang password `ahyaghifari288@gmail.com`** (§6) — sempat diganti sementara untuk keperluan tes.
7. Checklist lengkap langkah pasca-migrate ada di `deployment/2026-07-19-post-migrate-revisi-absensi.md` — jalankan semua sebelum benar-benar menganggap revisi Absensi live di production.

## Referensi
- `planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md` — rencana lengkap revisi Absensi.
- `deployment/2026-07-19-post-migrate-revisi-absensi.md` — checklist pasca-migrate.
- `deployment/CHECKLIST.md` — checklist deploy umum project ini.
- `planning/2026-07-18-restrukturisasi-jadwal-schedules.md` — spek Restrukturisasi Jadwal, termasuk §5 (gap yang dikonfirmasi §4 dokumen ini).
- `knowledge/dev/konsep-user-role-bukan-akun-admin-terpisah.md` — prinsip di balik penghapusan `AdminUserResource` (§5).
