# HRIS — Syifa Global Group

Sistem HRIS internal **Syifa Global Group**: data karyawan, absensi & pengajuan (cuti/izin/lembur), payroll, shift, rekrutmen, LMS, assessment, coaching/counseling, tugas, dan approval flow lintas modul.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+), Livewire, Filament 4, Sanctum (token API mobile), [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) + Filament Shield, Maatwebsite Excel, dompdf, simple-qrcode, guava/calendar.
- **Panel Filament**: `/admin` (admin utama), `/rekrutmen` (pipeline pelamar), `/keycloak` (kelola user Keycloak). Panel `/candidate` sudah dihapus.
- **Frontend**: Vite 7 + Tailwind CSS 4. Tanpa SPA framework — interaktif lewat Livewire.
- **Database & infra**: MySQL, primary key **ULID** di semua tabel. Queue/cache/session pakai driver `database` (Redis ada di config tapi belum dipakai). Deploy manual, lihat [`deployment/CHECKLIST.md`](deployment/CHECKLIST.md).

## Struktur Proyek

Pola **Controller → Service → Repository → Model**, dikelompokkan per domain.

```
app/Filament/        Resource/Page/Widget admin panel
app/Livewire/        Halaman & komponen portal karyawan
app/Services/        Business logic per domain (Payroll/Calculators, Approval, Shift, ...)
app/Models/          Per domain (Attendance, Payroll, Shift, Rekrutmen, LMS, Task, ...)
routes/web.php       Orkestrasi require + login/logout; 1 file per modul portal (routes/{modul}.php)
                     di dalam group middleware ['auth','portal.access']
routes/api.php       REST API mobile
database/migrations/ 480+ migration
planning/  knowledge/  Rencana fitur & catatan analisis/perubahan (baca sebelum ubah modul besar)
```

## Modul Utama

- **User & Profil** — data personal, riwayat jabatan, dokumen (upload via portal `/kepegawaian/dokumen` atau Filament), mutasi/transisi.
- **Attendance** — presensi, pengajuan cuti/izin/lembur (`attendance_requests` + tabel detail per tipe), saldo cuti dihitung live dari pengajuan approved. Cuti khusus (haid/melahirkan/keguguran) punya kuota & aturan sendiri di `LeaveTypeCategoryEnum`.
- **Approval Engine** — alur approval generik lintas modul. Snapshot dibekukan sekali saat submit (`steps_snapshot`), perubahan master flow tidak berlaku surut. `ApprovalActivities` = state "sekarang" (1 baris/snapshot), `ApprovalHistories` = jejak append-only. Riwayat di UI pakai `<x-approval-history-list>`. **Gotcha**: efek samping approval (notifikasi eksternal, bikin record turunan) harus dicek dulu bahwa step ini benar-benar final (`snapshot->refresh()->status === 'approved'`), bukan dijalankan di tiap step.
- **Payroll** — **Registry + Strategy + Config**. `PayrollComponentEnum` (17 komponen) di-toggle per company lewat `company_payroll_components`; tiap komponen punya 1 Calculator (`app/Services/Payroll/Calculators/`, dipetakan `PayrollCalculatorRegistry`) yang baca angka dari tabel config (`pangkat_salaries`, `position_allowances`, `transport_allowances`, `tenure_allowances`, `pangkat_bpjs_bases`, `pangkat_lembur_rates`, `extra_shift_rules`, `settings`). `PayrollService::generatePayroll()` = orchestrator tipis (earning → deduction → Izin Seharian terakhir karena butuh pendapatan final). Nominal terenkripsi (`PayrollEncrypted`). Sudah dipakai sungguhan: generate, revisi, slip PDF + email, import Excel manual (`ManualPayrollExcelImporter`, angka final dari Excel RS), pembayaran.
  - Cuti melahirkan/keguguran: 2 teknik lewat setting `cuti_melahirkan_metode` (`per_komponen` | `thp`), semua hari cuti dipotong sesuai instruksi user. **Cek dengan HR/legal** — pasal 84 UU 13/2003 mewajibkan upah penuh (pokok + tunjangan tetap) saat cuti melahirkan.
  - Extra Shift: jumlah `ExtraShiftAssignment` approved kategori `extra_shift` di window absensi × `extra_shift_rules.amount` (per pasangan shift asal→tujuan, diisi di Mapping Komponen Gaji). Tanpa syarat presensi; `double_shift` tidak dibayar; nominal kosong/0 dilewati.
  - Fee Claim & Pajak Jasa Dokter sudah **dihapus total** dari Payroll (dokter/mitra punya sistem sendiri). Slip periode lama tidak disentuh.
- **Shift** — master shift, template, jadwal, roster, extra shift (+ halaman **Rekap Extra Shift** `/presensi/rekap-extra-shift`: filter perusahaan/departemen/divisi/periode, tab terpisah Extra Shift / Double Shift, nominal jasa tidak ditampilkan; menu & halaman butuh **dua** permission: `view_report_data_absensi` + `ViewAny:ExtraShiftAssignment`), tukar shift (modal di Dashboard + halaman `/tukar-shift`, lewat `ShiftSwapRequestService`). "Tanggapan Rekan" opsional, murni informasi, tidak menggerbang approval; aktif lewat `Setting` `ASK_TARGET_RESPONSE_SETTING_KEY` (default OFF, belum ada UI-nya).
- **Rekrutmen** — `ManpowerRequests` (Penambahan Kuota/Karyawan/Penggantian) lewat Approval Engine → disetujui → `JobOpenings` otomatis dibuat (`is_active=false`) → admin publish manual. Kuota Penambahan Karyawan dikunci sesuai kekurangan (`m_job_positions.manpower - terisi`). `manpower` = kuota jabatan yang disetujui (bertambah tiap pengajuan kuota disetujui); **terisi dihitung realtime** lewat `JobPositions::userAssignments()`, bukan kolom. Status jabatan hanya lewat kolom `status` (`draft`/`requested`/`approved`/`rejected`); bagan organisasi dan penugasan jadwal menyaring `status = approved`.
  - **Integrasi ke sistem Rekrutmen eksternal = shared DB langsung**, bukan HTTP API: koneksi Eloquent `recruitment` (`DB_REKRUTMEN_*`), model `App\Models\Rekrutmen\*`, `RecruitmentSync` (upsert perusahaan/lowongan by `hris_code`/`hris_job_opening_id`, **non-fatal** — gagal cuma dicatat log; tombol "Sync ke Rekrutmen" manual menampilkan hasilnya). File privat dibaca dari disk `rekrutmen` (`REKRUTMEN_STORAGE_PATH`).
  - Seluruh pipeline pelamar (daftar, kuis, tes online, wawancara, offering letter, MCU, onboarding, master) ada di panel Filament `/rekrutmen`. `routes/rekrutmen.php` sisa 3 route proxy file. Desain: `planning/2026-09-03-porting-admin-rekrutmen-ke-hris.md`.
- **LMS** — kursus, kuis, sertifikat, ranking, trainer, umpan balik publik.
- **Assessment** — penilaian karyawan: admin panel plus halaman portal Daftar/Jadwal/Template Penilaian. Rencana redesign KPI/Goal/Behavior belum diputuskan (extend vs rewrite).
- **Coaching & Counseling** — dua fitur, dua model akses berbeda. **Coaching**: atasan membimbing bawahan; company-wide kalau punya `ViewAny/Create/View:Coaching` atau `COACHING_KARYAWAN_LAINNYA`, selain itu scope ke bawahan (`OrgHierarchyService::subordinates()`); alur `IN_PROGRESS → SUBMITTED → COMPLETED/REJECTED`. **Counseling**: konselor bisa siapa saja di company yang sama, isi **rahasia ketat** (`Counseling::isVisibleTo()` — Super Admin pun tidak bisa buka milik orang lain). Detail: `knowledge/markdown/analisis-coaching-counseling-redesign.md`.
- **Tugas** (`/tugas`, pengganti modul Kanban) — `/tugas` (`TaskMenuPage`) hanya menu tile; tiap tile buka `TaskListPage` di `/tugas/papan/{scope}` (`todo|saya|individu|tim`; Individu & Tim hanya untuk yang punya bawahan) atau `/tugas/papan/board/{project}`. Board = project multi-member dengan List berwarna (bisa "Selesai"/"Review"), drag-and-drop (SortableJS), arsip, log aktivitas, checklist, komentar, lampiran, reviewer + rating. Buat Board digerbangi `BUAT_BOARD_TUGAS` (migration otomatis memberikannya ke role yang sudah punya `tugas_saya`). Deep-link push (`public/sw.js`) & lonceng (`TaskNotificationUrl`) mengarah ke URL papan.
- **SSO Keycloak** (opsional, berdampingan dengan login lokal) — `socialiteproviders/keycloak`, `routes/keycloak.php`, `KeycloakController`. Match user by `users.nip` ke `preferred_username` (atau klaim `nip`); `keycloak_sub` di-backfill saat login pertama. **Tidak auto-provision** — NIP yang tidak ada ditolak. Logout portal ikut single-logout. Theme login: `another-app/keycloak-theme/syifa/`.
- **Sarana & Prasarana** sudah dilepas dari HRIS (sistem terpisah, kode dihapus dari repo).

## Otorisasi

- **`/admin`** memakai **Filament Shield**: permission `Verb:Subject` (mis. `View:Employee`) di-generate dari Resource/Page/Widget, Policy ikut di-generate. Akses panel otomatis untuk role **Admin/Super Admin**; role lain butuh `AKSES_PANEL_ADMIN` (`User::canAccessPanel()`).
- **Portal karyawan** dijaga registry `config/portal-pages.php` + middleware `EnsurePortalPageAccess` (route yang tidak terdaftar di registry lolos tanpa cek — sengaja, bukan whitelist).
- Permission dikelola lewat `/admin/roles` dan portal `/hak-akses` (tree Container > Subject > Permission; mode "Langsung ke User" bersifat **additive only**, tidak bisa mencabut permission dari role). Role dikelola di portal `/role` (nama + perusahaan saja).
- **Gate coarse → granular**: gate `SISTEM_*` (`CustomPermissionEnum`) tetap jadi toggle dropdown level-atas nav, tiap link/aksi di dalamnya digerbangi permission granular (`ViewAny/Create/Update/Delete:Subject` atau pasangan `LIHAT_X`/`KELOLA_X`). Sudah selesai: Presensi, Kepegawaian, Rekrutmen, LMS, Penilaian, Keuangan.
- `SISTEM_COACHING_COUNSELING` menggerbangi Coaching sisi atasan/HC dan **sengaja tanpa auto-grant** — belum di-assign manual berarti tidak ada role yang bisa membukanya.
- Role **"Super Admin"** (company holding) memegang semua permission **secara literal** (`givePermissionTo(Permission::all())`), bukan bypass otomatis — permission baru **tidak otomatis masuk** — setiap ada permission baru, grant ulang (`Role::findByName('Super Admin')->givePermissionTo(Permission::all())`). Nama role: "Super Admin" hanya company holding, "Admin" untuk company lain. Di DB dev 2026-09-20 hanya 2 dari 85 role yang tanpa permission sama sekali.

## Portal Karyawan

Portal = **session (guard `web`) + Livewire penuh**, bukan SPA/PWA terpisah; `routes/api.php` hanya untuk mobile app. Satu set halaman responsive, diatur di `components/layout.blade.php`: desktop (`≥768px`) `<x-nav>` topnav, mobile `<x-pwa-app-bar>` + `<x-pwa-bottom-nav>`; PWA (manifest, `public/sw.js`) berlaku situs-lebar. Header halaman pakai `<x-page-header title="..." />`. Pola "mobile card vs desktop table": lihat [`DESIGN.md`](DESIGN.md).

Halaman utama: `/dashboard`, `/my-staff` (daftar/detail karyawan, scoped ke bawahan rekursif via `OrgHierarchyService`), `/profil-saya`, `/pengajuan-absensi` (+ form cuti/izin/lembur), `/tukar-shift`, `/tugas`, `/coaching`, `/counseling`, `/pembinaan/*`, `/kepegawaian/*`, `/keuangan/*` (payroll), `/notifikasi`, `/role`, `/hak-akses`, `/akun`. Approve/Reject/Batalkan pengajuan tersedia di admin panel **dan** portal lewat service yang sama (`ApprovalFlowService`). Detail per fitur: `knowledge/changes/` (tanggal terbaru) dan `planning/`.

## Instalasi

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run dev      # atau: composer run dev (server + queue + vite)
```

Permission dari `CustomPermissionEnum` sebagian besar dibuat lewat `php artisan db:seed --class=CustomPermissionsSeeder` (bukan migration) — jalankan setelah menambah case baru.

## Design System

Konvensi visual portal (palet, kontras, komponen shared) ada di [`DESIGN.md`](DESIGN.md) — baca sebelum menambah tampilan. Dark mode aktif untuk portal (bukan `/admin`/`/rekrutmen`): pakai `var(--token)`, jangan warna literal.

## Known Gaps & Gotchas

- **Test**: `tests/Feature/` jalan di sqlite in-memory (`RefreshDatabase`). **Jangan arahkan `phpunit.xml` ke DB asli** — `RefreshDatabase` akan `migrate:fresh` dan menghapus semua data (sudah pernah kejadian). **Suite belum hijau** (diukur 2026-09-20): 1229 test, 336 gagal (227 error + 109 failure), terbanyak di LMS (101), Payroll (99), Presensi (43), Kepegawaian (20), Tugas (20), Portal (12), Sikartap (10). Penyebab yang teridentifikasi: assert langsung ke kolom terenkripsi, kelas yang sudah dihapus, error setup Livewire (`array offset on null` — biasanya permission literal yang dicek `mount()` tidak di-grant test), dan gerbang permission baru (`BUAT_BOARD_TUGAS`). Sebelum guard di migration `seed_dashboard_modul_permissions`, seluruh suite gagal di tahap migrate. Jalankan suite besar dengan `php -d memory_limit=2G vendor/phpunit/phpunit/phpunit <folder>` (`artisan test` tidak meneruskan `-d`).
- **CI**: tidak ada test/lint gate.
- **Payroll**: rollout data config (`pangkat_salaries`, `position_allowances`, ..., `company_payroll_components`) ke semua company belum lengkap — isi lewat UI Komponen Gaji / Mapping Komponen Gaji, bukan kode. `m_companies` belum punya kategori hospital/non-hospital (komponen khusus RS di-toggle manual).
- **Keamanan**: 18 route tanpa middleware `auth` (diaudit 2026-09-20). Sengaja publik/terautentikasi di controller: `api/auth/*`, `api/job-openings*`, `publik/unit-organisasi*`, `auth/keycloak/*`, `resend/webhook`, `api/integrasi/*` (Bearer per aplikasi) dan `api/n8n/*` (Basic), keduanya + throttle. Endpoint `api/sop*` (SOP IT publik tanpa auth) sudah **dihapus** 2026-09-20. Yang masih terbuka: `GET /attendance/template/download` tanpa `auth` (file template kosong, risiko kecil).
- **Kode mati**: `database/seeders/MasterSalaryComponentSeeder.php` merujuk enum/model registry lama yang sudah dihapus dan tidak dipanggil di mana pun (akan error kalau dijalankan).
- **Approval Engine**: path reject menulis `rejected_by/rejected_at` ke kolom yang tidak ada (diam-diam terbuang); ejaan `canceled`/`cancelled` beda antara `approval_activities` dan `approval_snapshots` (`knowledge/markdown/manajemen-persetujuan.md`). Modul `TASK_REQUEST` (dan `APPROVAL_CANDIDATE_APPLICANT`) punya enum + cabang di `ApprovalFlowService`, tapi tidak ada halaman/service yang memanggil `advanceStep()` untuk keduanya (diverifikasi 2026-09-20) — approve-nya belum pernah dibangun.
- **LMS — soal esai dinonaktifkan sementara** (2026-09-20): pembuatan soal baru hanya Pilihan Ganda (`QuestionType::creatable()`, dipakai form Filament, `BankSoalPage`, `CreateBankSoal`, `CreateQuiz`). Alasannya `QuizService::reviewEssayAnswers()` tidak punya UI/route pemanggil, jadi attempt berisi esai macet di `pending_review` (badge "Belum Dinilai" tanpa aksi). Soal esai lama tetap ada di DB dan masih menghasilkan `pending_review` kalau ada di kuis; untuk mengaktifkan lagi, bangun dulu halaman penilaiannya lalu kembalikan `creatable()` ke semua case.
- **Rekrutmen**: mode `free` sudah dipensiunkan (2026-09-23) — semua rekrutmen wajib dikelola dari HRIS, lowongan di project `rekrutmen` read-only. Arah data kandidat balik ke HRIS belum diputuskan.
- **Notifikasi**: deep-link tujuan baru ada untuk notifikasi Tugas; jenis lain belum. Real-time memakai `wire:poll` (Tugas 10 dtk), belum ada broadcasting (`BROADCAST_CONNECTION=log`).
- **Coaching/Counseling**: mobile API ketinggalan — konselor tidak bisa lihat/balas dari mobile.
- **Nama permission salah ketik = menu hilang tanpa error** (permission yang tidak ada di DB membuat `can()` selalu false, termasuk Super Admin). Kalau menu tidak muncul untuk siapa pun, cek nama permission-nya cocok persis.
- **Dropdown nav multi-kolom** (`.dropdown-menu-columns`): kolom yang seluruh isinya digerbangi permission harus dibungkus `@if` di level `<div class="dropdown-col">`, bukan tiap `<a>`.
- **`public/storage` adalah symlink** — kalau upload berhasil tapi file 404 setelah folder project dipindah, jalankan ulang `php artisan storage:link`.
- **CSS tidak berubah di browser** padahal kode benar: cek `public/build/assets/app-*.css` lebih baru dari `resources/css/app.css`; kemungkinan butuh `npm run build`.
- **Cache Filament/Laravel di production**: `php artisan optimize` juga menjalankan `filament:optimize` (cache daftar Resource/Page). Cache ini tidak refresh sendiri: setelah menambah/rename Resource/Page jalankan `php artisan filament:optimize-clear` sebelum `optimize` ulang, kalau tidak bisa muncul `ComponentNotFoundException`. Kalau masih muncul, curigai OPcache PHP-FPM yang belum di-reset (belum terbukti di project ini).
