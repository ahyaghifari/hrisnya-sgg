# Rekap Sesi 2026-07-03 & Rencana Lanjutan

## Context

Dokumen ini adalah rekap/handoff untuk menutup sesi kerja tanggal 2026-07-03 (panjang, banyak topik). Tujuannya supaya sesi berikutnya — baik dilanjutkan olehku atau orang lain — bisa langsung paham apa yang sudah selesai, apa yang sengaja ditunda, dan apa langkah berikutnya yang paling masuk akal, tanpa harus menggali ulang seluruh riwayat percakapan.

**Status akhir sesi**: Login ✅, Dashboard Karyawan ✅, Struktur Organisasi ✅ (halaman baru, di luar Filament). **Attendance (Tahap 1c) BELUM dikerjakan sama sekali** — itu pekerjaan besar berikutnya.

Detail lengkap tiap tahap ada di planning file masing-masing (jangan diduplikasi di sini, cukup dirujuk):
- `planning/2026-07-03-perbaikan-backend-scoping-akses.md` — perbaikan backend (company-scoping, IDOR, bug role)
- `planning/2026-07-03-rencana-implementasi-portal-karyawan.md` — Login, Dashboard, keputusan tech stack
- `planning/2026-07-03-arsitektur-dashboard-per-role.md` — model Level×Domain dashboard
- `planning/2026-07-03-akun-tes-login-admin.md` — akun test (semua password `password`)

## Ringkasan Apa yang Sudah Dikerjakan (urut waktu)

### 1. Analisis awal & audit
- Analisis keseluruhan project (stack, model, flow), update `README.md`.
- Deep-dive approval flow lintas hierarki organisasi (verifikasi lewat query DB langsung, bukan cuma baca kode) — hasil didokumentasikan di `knowledge/markdown/approval-flow-role-access.md`.
- Audit company-scoping di seluruh Resource Filament (banyak yang bocor company_id) — `knowledge/dev/filament-scoping-analysis.md`.

### 2. Perbaikan Backend (Fase 1 dari `perbaikan-backend-scoping-akses.md`)
- Trait `app/Models/Concerns/BelongsToCompany.php` + `BelongsToCompanyOrShared.php` (untuk tabel shared/nullable) — dipasang ke: `ShiftScheduleAssignments`, `ShiftSwapRequests`, `PayrollPeriod`, `AssessmentTemplates`, `ProgramIncentive`, `LmsCourseBaselines`, `ExtraHourAllowanceRules`, `MasterDoctorService`, `MasterSalaryComponents`, plus scope custom di `AssessmentUsers`.
- Fix IDOR: `UserResource` (edit profil sendiri saja), `UserShiftSwapResource` (lewat company-scope di model, bukan ownership check, karena role manajerial memang harus lihat bawahan).
- Fix bug besar: role **"Super Admin" duplikat nama** di 7 company non-holding bikin `hasRole('Super Admin')` salah match lintas company → di-rename ke "Admin" lewat **migration** (`2026_07_03_140000_rename_company_scoped_super_admin_roles_to_admin.php`), BUKAN seed ulang (sesuai permintaan eksplisit user: tidak mau seed ulang lagi).
- **Fase 2 (data hygiene approval flow) dan Fase 3 (hierarki dinamis) BELUM dikerjakan** — lihat bagian "Belum Dikerjakan" di bawah.
- Kategori tabel yang sengaja ditahan (kemungkinan shared/global taxonomy, bukan bug): `lms_categories`, `m_competencies`, `assessment_types`.

### 3. Tahap 1a — Login ✅
- `app/Http/Controllers/Auth/LoginController.php`, `resources/views/auth/login.blade.php` (Tailwind + `@tailwindcss/forms`, bukan DaisyUI — keputusan eksplisit user).
- Session-based (guard `web`), rate-limit `throttle:5,1`, `canAccessPanel()` dibatasi ke role Admin/Super Admin.
- Test: `tests/Feature/Auth/LoginTest.php` (12 test).

### 4. Tahap 1b — Dashboard Karyawan ✅ (dengan beberapa koreksi arah di tengah jalan)
- **Koreksi tech stack penting** (jangan diulang kesalahannya): dashboard BUKAN bagian PWA. Cuma fitur Attendance (submit cuti/izin/lembur, Tahap 1c) yang PWA dan pantas pakai `fetch()` ke JSON API. Semua halaman lain **Blade + Livewire biasa, server-rendered, tanpa Alpine.js**.
- Livewire full-page routing pakai atribut `#[Layout('components.layout')]` + `wire:navigate` (bukan Controller + view).
- Istilah **"Portal" dihapus total** dari kode — flat namespace `App\Livewire\*` (bukan `App\Livewire\Portal\*`), `resources/views/components/*` (bukan `components/portal/*`), CSS `body.site` (bukan `body.portal`).
- Komponen: `App\Livewire\Dashboard`, `LeaveBalanceCard` (dipakai 2x: Cuti Tahunan & Cuti Sakit), `RecentRequestsCard`, `AttendanceStatCards`.
- Struktur dashboard disamakan persis dengan demo HTML asli (`planning/web-attendance/demo/dashboard.html`) — sempat ada elemen hilang saat refactor (quick action, stat card, icon box) lalu dikembalikan semua.
- Card "Absensi Hari Ini" → **"Presensi Hari Ini"**, nav "Pengajuan" → **"Absensi"**. Warna card Presensi tetap hijau (`#047C54`) meski belum terintegrasi ke clock-in nyata (memang belum ada endpoint clock-in/out sama sekali — ini keputusan sadar, bukan bug).
- Test: total 29 (Login + Dashboard).

### 5. Struktur Organisasi — dipindah dari Filament ke halaman sendiri ✅ (dikerjakan hari ini, BELUM ADA TEST)
- **Sebelumnya**: `App\Filament\Pages\OrganizationStructure` (di dalam panel `/admin`). **Sekarang**: dihapus dari Filament, jadi halaman Livewire biasa di `/struktur-organisasi` (`App\Livewire\OrganizationStructure`, layout sama seperti Dashboard, guard `web`, dicek `Gate::allows('view_struktur_organisasi')` di `mount()`).
- Widget chart-nya sendiri (`App\Livewire\OrganizationChartWidget` + `resources/views/livewire/organization-chart-widget.blade.php`) **tidak ditulis ulang**, cuma dipindah konteksnya dari dalam panel Filament ke halaman biasa — makanya masih pakai komponen visual `x-filament::section`/`x-filament::button`/heroicon (itu masih jalan di luar panel karena `filament/support` registrasi Blade component-nya global, bukan panel-scoped) plus `@filamentStyles`/`@filamentScripts` di-push lewat `@stack('head')`/`@stack('scripts')` yang baru ditambahkan ke `resources/views/components/layout.blade.php`.
- Tombol akses ditambahkan di quick-action dashboard karyawan (welcome card), muncul cuma kalau user punya permission `view_struktur_organisasi`.
- **Perbaikan sekalian saat modal detail jabatan dibuka**:
  - Section baru "Pemegang Jabatan" — nampilin user yang benar-benar menempati jabatan itu (query `userAssignments.user`), sebelumnya cuma nampilin sub-jabatan tanpa info siapa orangnya.
  - Tombol "Edit Jabatan" sebelumnya tampil ke SEMUA orang yang bisa buka struktur organisasi (`view_struktur_organisasi`), padahal edit jabatan beneran butuh permission berbeda (`edit_master_data_jabatan`) — sekarang digating `@can('edit_master_data_jabatan')` supaya tidak menyesatkan (klik tapi ditolak Filament).
- **Zoom & pan**: chart sekarang di dalam viewport 70vh dengan toolbar zoom in/out/reset + scroll-wheel zoom + drag-to-pan (vanilla JS, event delegation di `document` supaya tetap jalan walau widget-nya di-remount Livewire saat ganti company). Garis penghubung org-chart (`drawOrgChartConnections`) di-re-run tiap kali zoom/pan berubah supaya tetap sejajar.
- **Modal detail jabatan diganti dari `x-filament::modal` ke Preline (`HSOverlay`)** — alasan: `x-filament::modal` butuh JS panel bootstrap Filament yang tidak lengkap di luar konteks panel `/admin`, jadi modal-nya tidak kebuka dengan benar. Preline dipasang sebagai dependency baru (`npm install preline`, `import 'preline'` di `resources/js/app.js`, `@source`+`@import variants.css` di `resources/css/app.css` sesuai cara resmi Tailwind v4). Tombol trigger pakai kombinasi `wire:click` (load data server-side) + `data-hs-overlay="#job-position-detail-modal"` (buka panel client-side) — dua listener independen jalan bareng di klik yang sama. Setelah Livewire render ulang (ganti company), dipanggil `window.HSStaticMethods.autoInit('overlay')` supaya trigger baru ikut ke-bind.
- `npm run watch` (`vite build --watch`) ditambahkan di `package.json` supaya user bisa lihat perubahan CSS/Blade sendiri tanpa aku bolak-balik `npm run build`.

**PENTING — gap yang harus ditutup**: fitur Struktur Organisasi di atas **belum ada satu pun test** (feature test render halaman, permission gate, dsb). Ini menyalahi kesepakatan "1 paket 1 paket harus ada test" yang sudah disepakati sejak Tahap 1a. Kalau lanjut sesi berikutnya dan belum keburu ditambah manual oleh user, ini yang harus dikerjakan duluan sebelum lanjut ke Attendance.

## Belum Dikerjakan / Ditunda (urutan prioritas yang disarankan)

1. **Test untuk halaman Struktur Organisasi** (gap di atas) — `tests/Feature/OrganizationStructureTest.php`: render halaman untuk user yang punya izin, 403/redirect untuk yang tidak, data perusahaan sendiri vs semua company (Super Admin/global role) muncul benar.
2. **Tahap 1c — Attendance (cuti/izin/lembur)** — pekerjaan utama berikutnya, lihat rencana di `planning/2026-07-03-rencana-implementasi-portal-karyawan.md` bagian "Tahap 1c":
   - Form submit cuti/izin/lembur → **HARUS pakai `fetch()` ke JSON API** (`resources/js/api.js` / `window.apiFetch`, endpoint `POST /attendance/leave|permission|overtime` yang sudah ada & sudah ada test-nya di `tests/Feature/Api/AttendanceApiTest.php`), **BUKAN Livewire** — ini satu-satunya bagian yang memang didesain PWA-ready, jangan diubah ke Livewire biar konsisten dengan alasan awal (supaya gampang ditambah offline-cache nanti).
   - List pengajuan & detail pengajuan → `GET /attendance/requests` dan `GET /attendance/requests/{id}`.
   - Approval flow di baliknya sudah jalan (lihat `knowledge/markdown/approval-flow-role-access.md`), halaman ini cuma nampilin status/histori, jangan bikin ulang logic approval.
   - Ingat kesepakatan test: paket ini juga wajib ada unit/feature test sebelum dianggap selesai.
   - Nav dropdown "Absensi" (`resources/views/components/nav.blade.php`) saat ini link Cuti/Izin/Lembur masih `href="#"` — sambungkan ke route Tahap 1c begitu halamannya ada.
3. **Fase 2 backend** (`perbaikan-backend-scoping-akses.md`): fix `parent_id` job position "Finance Analyst Staff" yang salah company, fix bug filter `scope_type = same_department` di `ApprovalPermissionService`/`NotificationService`, tambah flow default `attendance_request` untuk company holding & 6 company lain yang belum punya.
4. **Fase 3 backend**: `OrgHierarchyService::subordinateUserIds()` — dasar untuk dashboard level Supervisor (lihat data tim, bukan seluruh company) sesuai model Level×Domain di `planning/2026-07-03-arsitektur-dashboard-per-role.md`. Baru relevan setelah dashboard non-Karyawan (Supervisor/Manager/Direktur) mulai digarap.
5. Kategori tabel master data yang ditahan (`lms_categories`, `m_competencies`, `assessment_types`) — perlu keputusan bisnis dulu, apakah memang shared/global atau perlu di-scope per company.
6. Dashboard untuk level selain Karyawan (Supervisor/Manager/Direktur/HC/Finance/L&D) — bergantung Fase 1 selesai total (untuk Manager/Direktur, tidak semua Resource sudah tersentuh trait `BelongsToCompany`) dan Fase 3 (untuk Supervisor).

## Cara Cepat Lanjut Sesi Berikutnya

- Test suite: `vendor/bin/phpunit` — harus tetap 29/29 lolos (atau lebih kalau sudah nambah test Struktur Organisasi/Attendance).
- **Jangan jalankan `npm run build`** — user selalu punya `npm run watch` jalan sendiri untuk lihat perubahan visual, biarkan dia yang cek tampilan di browser.
- Akun test: lihat `planning/2026-07-03-akun-tes-login-admin.md` (semua password `password`).
- Rute utama saat ini: `/login`, `/dashboard`, `/struktur-organisasi` — semua di bawah middleware `auth`, guard `web`, full-page Livewire (`#[Layout('components.layout')]` + `wire:navigate`).
- Kalau ragu soal keputusan tech stack (Livewire vs fetch API, kapan pakai Alpine/Preline, dsb), cek dulu `planning/2026-07-03-rencana-implementasi-portal-karyawan.md` — sudah ada beberapa koreksi arah di sana, jangan ulangi asumsi yang sudah dikoreksi.
