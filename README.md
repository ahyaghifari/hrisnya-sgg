# HRIS — Syifa Global Group

Sistem HRIS (Human Resource Information System) internal untuk **Syifa Global Group**. Mengelola data karyawan, absensi & pengajuan (cuti/izin/lembur), payroll (reguler & dokter), shift, rekrutmen, LMS (learning management), assessment, coaching/counseling, hingga approval flow lintas modul.

## Tech Stack

**Backend**
- Laravel 12 (PHP 8.2+)
- [Filament 4](https://filamentphp.com) — admin panel utama (`/admin`), plus panel terpisah untuk Candidate
- Laravel Sanctum — auth berbasis token untuk API mobile/portal karyawan
- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) — role & permission (ULID-based)
- Livewire — komponen interaktif custom di luar Filament
- Maatwebsite Excel, barryvdh/dompdf, simple-qrcode, guava/calendar

**Frontend**
- Vite 7 + Tailwind CSS 4
- Belum ada SPA framework (Vue/React) — sisi interaktif memakai Livewire

**Database & Infra**
- MySQL, primary key **ULID** di seluruh tabel
- Queue, cache, session: driver `database` (Redis tersedia di config tapi belum dipakai)
- Deploy: Bitbucket Pipelines (SSH ke staging) — belum ada test gate di CI

## Struktur Proyek

Pola arsitektur konsisten: **Controller → Service → Repository → Model**, dikelompokkan per domain.

```
app/
  Filament/         # Resource, Page, Widget untuk admin panel
  Http/Controllers/  # Controller web & API (Api/ untuk mobile/portal)
  Models/            # Dikelompokkan per domain (Attendance, Payroll, Shift, Candidate, LMS, ...)
  Services/          # Business logic per domain
  Repositories/       # Data access layer per domain
  Jobs/              # Proses berat via queue (payroll, import Excel, email)
routes/
  web.php            # Sangat tipis — Filament daftarkan route sendiri di /admin
  api.php            # REST API untuk mobile app / portal karyawan
database/migrations/ # 140+ migration, granular & terus berevolusi
planning/            # Dokumen perencanaan fitur yang belum dikerjakan
```

## Modul Utama

- **User & Profil** — data personal, riwayat jabatan, dokumen, mutasi/transisi
- **Attendance** — record kehadiran, pengajuan cuti/izin/lembur (`attendance_requests` + tabel detail per tipe), saldo cuti
- **Approval Engine** — alur approval generik & reusable, dipakai lintas modul (attendance, task, dll)
- **Payroll** — arsitektur **Registry + Strategy + Config** (redesign 2026-07-17, menggantikan katalog freeform lama): `PayrollComponentEnum` (~18 komponen gaji tertutup — Gaji Pokok, Tunjangan, BPJS, Keterlambatan, Izin, Lembur, dst) di-toggle ON/OFF per company lewat `company_payroll_components`, tiap komponen punya 1 Calculator class sendiri (`app/Services/Payroll/Calculators/`) yang baca angkanya dari tabel config (`pangkat_salaries`, `position_allowances`, `transport_allowances`, `tenure_allowances`, `pangkat_bpjs_bases`, `settings`). `PayrollService::generatePayroll()` jadi orchestrator tipis (earning → deduction → Izin Seharian terakhir, butuh total pendapatan final), bukan lagi 1 method raksasa. Insentif, fee submission (jasa dokter, sudah unifikasi ke siklus payroll biasa) tetap seperti sebelumnya. **Status 2026-07-17: Fase 0+1 (fondasi + Registry rebuild) sudah commit+push ke `dev`. Fase 2 (basis BPJS per pangkat, gabung Izin Terlambat Masuk/Pulang Cepat, Izin Seharian) sudah jalan di lokal tapi belum commit — UI buat isi mapping BPJS per pangkat juga belum dibangun, jadi BPJS belum menghasilkan potongan apa pun sampai itu ada.** BELUM di-deploy ke production sama sekali — lihat `planning/next/2026-07-17-rekap-sesi-dan-lanjutan.md`.
- **Shift** — master shift, template, jadwal, tukar shift
- **Rekrutmen** — job opening publik → candidate → recruitment process
- **LMS** — kursus, kuis, sertifikat, ranking, trainer
- **Assessment, Task tracking**
- **Coaching & Counseling** (redesign penuh 2026-07-26) — dua fitur beda sifat, sengaja dua model akses berbeda:
  - **Coaching** (`/coaching` personal, `/pembinaan/coaching*` sisi coach/atasan) — soal bawahan: atasan bimbing anak buahnya. Akses company-wide kalau punya permission Shield `ViewAny:Coaching`/`Create:Coaching`/`View:Coaching` **atau** custom permission `COACHING_KARYAWAN_LAINNYA` (buat role seperti HC yang bukan atasan siapa pun di struktur organisasi tapi tetap perlu coaching siapa saja); kalau cuma "punya bawahan" (`canAccessKaryawanMenu()`, gate sama MyStaff), scope otomatis ke `OrgHierarchyService::subordinates()` + `coach_by = diri sendiri` saja — bukan company-wide. Alur: `IN_PROGRESS` (dibuat) → karyawan upload bukti pelaksanaan → `SUBMITTED` → coach Approve/Reject → `COMPLETED`/`REJECTED` (bisa upload ulang kalau ditolak, bukti lama tetap tersimpan dengan status historisnya sendiri, tidak hilang). Satu halaman detail (`CoachingDetailPage`) menangani 3 peran sekaligus (coach/atasan-HC/karyawan yang dibina) — bukan 3 halaman terpisah.
  - **Counseling** (`/counseling` personal, `/pembinaan/counseling*` sisi konselor) — konselornya **bisa siapa saja** (karyawan menunjuk sendiri rekan kerja mana pun di company yang sama saat "Konsul"), sama sekali tidak ikut struktur organisasi. Isi keluhan+tanggapan **rahasia ketat**: cuma karyawan yang konsul dan konselor yang ditunjuk yang boleh baca (`Counseling::isVisibleTo()`) — **termasuk Super Admin tidak bisa buka punya orang lain**, bukan cuma gerbang permission Shield biasa yang bisa di-bypass company-wide. Karyawan biasa (bukan atasan) yang ditunjuk jadi konselor akses inbox-nya lewat tombol "Konsul ke Saya" di halaman personalnya sendiri (tidak lihat dropdown row 2, itu murni gerbang "punya bawahan").
  - Notifikasi (`App\Enum\NotificationTypeEnum`): `coaching_assigned`, `coaching_proof_submitted`, `coaching_review_decided`, `counseling_received` — lihat `knowledge/markdown/analisis-coaching-counseling-redesign.md` untuk detail penuh arsitektur & keputusan desainnya.
- **Kanban** (`/kanban`) — board project (multi-member, role owner/assigner/member per board) + task individual tanpa board ("BoardKu"). Checklist per task, komentar, upload lampiran, multi-assignee, kontrol status berlapis (owner/pembuat bebas, member cuma maju 1 langkah, `done` terkunci total kecuali komentar), warna board custom. **"Kanban Staff"** — atasan bisa assign task langsung ke satu bawahan tanpa bikin board (`board_id = null`, muncul di BoardKu staff & section khusus di `/kanban` sisi atasan). `wire:poll.10s` di board detail & list (bukan realtime websocket — project belum pasang broadcasting). Detail lengkap: `knowledge/changes/2026-07-13-modul-kanban-verifikasi-dan-revisi.md`, rencana awal: `planning/2026-07-13-modul-kanban-project-task.md`.

Otorisasi `/admin` memakai **Filament Shield** ([bezhansalleh/filament-shield](https://filamentphp.com/plugins/bezhansalleh-shield)) sejak 2026-07-08 — permission di-generate otomatis dari Resource/Page/Widget yang terdaftar, format `Verb:Subject` (mis. `View:Employee`, bukan `view_karyawan`), 12 verb standar per Resource, Policy ikut di-generate otomatis (layer Policy custom lama sudah dihapus total, digantikan Shield). Permission dikelola lewat `RoleResource` (`/admin/roles`) **dan** portal `/hak-akses` (tree Container > Subject > Permission, `PermissionTreeBuilder` reuse grouping logic yang sama dengan `RoleForm.php` biar dua tempat tidak pernah menyimpang) — role sendiri dikelola lewat portal `/role` (CRUD polos: nama+perusahaan, sengaja tanpa checkbox permission langsung di situ). Akses `/admin` (Filament) dibatasi ke role **Admin/Super Admin** otomatis; role lain butuh permission eksplisit `AKSES_PANEL_ADMIN` (`User::canAccessPanel()`, diperbaiki 2026-08-08 — sebelumnya `return true` tanpa syarat, siapapun yang login bisa buka `/admin`). Role lain (Supervisor, Manager, HC, Karyawan, dst) beraktivitas lewat **portal karyawan** (`/dashboard`, `/my-staff`, `/pengajuan-absensi`, `/data-diri`, dst — lihat bawah). Route portal dijaga registry `config/portal-pages.php` + middleware `EnsurePortalPageAccess` (route yang tidak terdaftar di registry lolos tanpa cek — desain sengaja, bukan whitelist ketat).

**Migrasi gate coarse → granular (2026-08-08)**: pola lama tiap modul portal pakai satu gate flat (`SISTEM_ABSENSI`, `SISTEM_KEPEGAWAIAN`, `SISTEM_LMS`, `SISTEM_PENILAIAN_KARYAWAN`, `SISTEM_KEUANGAN`, dst — `App\Enum\CustomPermissionEnum`) sedang dipecah ke permission Shield per-Resource (`ViewAny/Create/Update/Delete:Subject`) atau pasangan custom `LIHAT_X`/`KELOLA_X` untuk halaman config tanpa Resource sendiri. Sudah selesai: Presensi, Kepegawaian (termasuk Import Akun Karyawan), Rekrutmen, LMS, Penilaian Karyawan, Penggajian/Keuangan. Gate `SISTEM_*` coarse tetap dipakai sebagai toggle dropdown level-atas nav (konsisten di semua modul), tapi tiap link/aksi di dalamnya sudah digerbangi permission granular sendiri. **`SISTEM_APPROVAL` belum dimigrasi** — masih flat. `SISTEM_COACHING_COUNSELING` sudah jadi gerbang beneran (2026-08-08, sebelumnya cuma label dekoratif) di halaman Coaching sisi atasan/HC — **sengaja tanpa auto-grant** ke role existing (instruksi user), harus di-assign manual per role lewat `/hak-akses`.

**Catatan penting**: role "Super Admin" (company holding) sudah dikasih **semua permission secara literal** (2026-07-26, lewat `givePermissionTo(Permission::all())` — bukan bypass otomatis, lihat catatan Shield di atas). Kalau ada baris "Super Admin" lain (mis. di production, histori pernah ada 2 baris: holding + global — lihat catatan lama soal duplikat role nama sama beda `company_id`), cek dan samakan juga. 77 role lain (non-Super-Admin) di 8 company masih punya 0 permission sejak migrasi Shield, belum di-assign ulang. Detail & prioritas lanjutan di [`planning/next/2026-07-11-rekap-sesi-dan-lanjutan.md`](planning/next/2026-07-11-rekap-sesi-dan-lanjutan.md).

Konvensi nama role: **"Super Admin"** cuma dipakai company holding, **"Admin"** untuk semua company lain (permission ceiling sama, beda label saja).

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev      # atau: composer run dev (server + queue + vite sekaligus)
```

## Portal Karyawan (Livewire, bukan API/SPA — satu set halaman responsive, bukan PWA terpisah)

Berbeda dari rencana awal (portal berbasis token Sanctum/SPA — lihat [`planning/web-attendance/PLANNING.md`](planning/web-attendance/PLANNING.md) untuk histori rencana lama), implementasi aktualnya pakai **session (guard `web`) + Livewire penuh**, konsisten dengan sisi admin. Endpoint di `routes/api.php` tetap ada untuk mobile app, tapi portal web TIDAK memakainya — portal manggil Service (`AttendaceReqService`, dll) langsung.

Sempat ada percobaan shell PWA terpisah di `/pwa/*` (app bar + bottom nav sendiri, konten sengaja beda dari `/dashboard`) — itu sudah **dilebur total** ke satu set halaman responsive. Tidak ada lagi route/komponen `/pwa/*` yang duplikat; setiap halaman portal sekarang otomatis menyesuaikan tampilan lewat breakpoint `md:` (768px), diatur di satu `components/layout.blade.php`:
- **Desktop** (`≥768px`): `<x-nav>` — topnav dengan dropdown/link biasa.
- **Mobile** (`<768px`): `<x-pwa-app-bar>` (app bar, tanpa tombol hamburger lagi) + `<x-pwa-bottom-nav>` (bottom nav 5 slot — Beranda/Presensi/Menu (tombol tengah, buka bottom sheet shortcut sesuai role)/Pengajuan/Akun; drawer off-canvas lama sudah dihapus 2026-07-12, lihat [`DESIGN.md`](DESIGN.md) bagian "Mobile navigation").
- Instalabilitas PWA (manifest `public/pwa/manifest.json`, service worker `public/sw.js`, `start_url` ke `/dashboard`) kini berlaku situs-lebar, bukan cuma satu shell terpisah.
- Header tiap halaman (tanggal + jam live yang update tiap detik + judul) pakai satu komponen `<x-page-header title="..." />` (`resources/views/components/page-header.blade.php`) — jangan tulis ulang markup date/title manual per halaman.
- Logo resmi: `public/assets/logo-sgg.png`, dipakai di `<x-nav>`.

Pola "mobile card vs desktop table" dipakai konsisten di semua list/detail: lihat [`DESIGN.md`](DESIGN.md) bagian *Responsive requirement* untuk pola persisnya (kapan pakai `.pwa-list`/`.pwa-list-row` vs Filament Table, cara gabung query Filament Table + query manual mobile lewat satu `baseQuery()`, dan jebakan cascade-layer Tailwind `md:hidden` vs custom CSS unlayered).

Halaman yang sudah berjalan:
- `/dashboard` — ringkasan (saldo cuti + jumlah izin/lembur bulan ini berdampingan presensi, status pengajuan aktif dengan timeline visual — vertikal di mobile, horizontal di desktop, kotak "Butuh Persetujuan Anda" buat atasan, Task Saya/Kursus Saya placeholder).
- `/my-staff` + `/my-staff/{id}/edit` + `/my-staff/{id}` — daftar (Filament Table di desktop, card list di mobile), edit, dan **detail** karyawan (profil lengkap + presensi harian + statistik cuti/izin/lembur + riwayat pengajuan + dokumen karyawan, dinavigasi lewat 4 tombol `.section-nav` warna-warni, bukan tab bergaris bawah), scoped ke bawahan (rekursif via `OrgHierarchyService`) atau semua company (khusus Direktur holding). Widget "Butuh Persetujuan Anda" juga tampil di halaman list-nya, sudah responsive juga.
- `/data-diri` — halaman "detail karyawan" yang sama (`EmployeeAttendanceStatistics`) tapi dipakai untuk **lihat data diri sendiri** (`record` default ke user yang login), bukan cuma untuk atasan melihat bawahan.
- `/pengajuan-absensi` (list riwayat **milik sendiri**, Filament Table di desktop / card list + Aksi Cepat Cuti-Izin-Lembur di mobile) + `/pengajuan-absensi/{cuti,izin,lembur}` (form pengajuan, custom — bukan reuse Filament, header-nya senada warna dengan tombol Aksi Cepat: navy/gold/hijau) + `/pengajuan-absensi/{id}` (detail: timeline status + tombol Approve/Reject/Batalkan buat atasan — manager juga bisa **lihat** detail pengajuan bawahannya walau bukan approver).
- `/akun` + `/akun/ubah-password` — ganti foto profil & ubah password, cuma ada di portal (belum ada padanan di admin panel).
- `/role` (CRUD role: nama+perusahaan) + `/hak-akses` (pilih company+role, tree permission Container > Subject > Permission) — dropdown nav "Manajemen Pengguna", digerbangi `ViewAny/Create/Update/Delete:Role` & `KELOLA_HAK_AKSES`.
- `/kepegawaian/karyawan/import` — upload Excel (nip/nama/email) → preview 3 kategori (Baru/Ada Perubahan/Tidak Ada di File) sebelum ditulis ke DB, password akun baru `!{2 huruf inisial nama}{nip}#`. Digerbangi `IMPORT_KARYAWAN`.
- Approve/Reject/**Batalkan** tersedia di **dua tempat** (admin `/admin/attendance-requests` DAN portal) — reuse service yang sama (`ApprovalFlowService`, `ApprovalPermissionService`), tidak ada logic ganda.
- `/kanban` (daftar board + tombol "Kanban Staff" + section task titipan atasan↔staff) + `/kanban/baru` (buat board, guard `canAccessKaryawanMenu`) + `/kanban/{board}` (papan kanban 4 kolom status) + `/kanban/pribadi` ("BoardKu" — task tanpa board) — lihat detail di § Modul Utama di atas.
- `/coaching` (riwayat + upload bukti pelaksanaan) + `/counseling` (submit "Konsul" pilih konselor sendiri + tombol "Konsul ke Saya" kalau ditunjuk jadi konselor) — personal. `/pembinaan/coaching*` + `/pembinaan/counseling*` — sisi coach/atasan/konselor, lihat detail di § Modul Utama di atas & `knowledge/markdown/analisis-coaching-counseling-redesign.md`.

Detail lengkap tiap bagian ada di `knowledge/changes/` (lihat tanggal terbaru) dan `planning/2026-07-05-portal-pengajuan-absensi.md` / `planning/2026-07-04-draft-role-job-position-mapping.md` / `planning/2026-07-05-statistik-karyawanku.md`. Catatan: `knowledge/changes/2026-07-05-pwa-portal-karyawan.md` mendokumentasikan shell PWA terpisah yang **sudah tidak berlaku** (sudah dilebur ke atas) — baca sebagai histori, bukan state saat ini.

## Design System

Konvensi visual portal karyawan (palet warna, aturan kontras, identitas warna per kartu, komponen shared) didokumentasikan di [`DESIGN.md`](DESIGN.md) — baca sebelum menambah/mengubah tampilan portal, termasuk aturan penting soal inline style vs class utility Tailwind baru (butuh `npm run watch`/`build` sebelum terlihat).

**Dark mode** (2026-07-11) sudah aktif untuk seluruh portal karyawan (tidak untuk `/admin`/`/candidate`) — toggle manual (localStorage, class `dark` di `<html>`) di nav desktop & app bar mobile, warna lewat CSS custom property di `app.css`. **Wajib baca bagian "Dark mode" di [`DESIGN.md`](DESIGN.md) sebelum menambah warna baru** — warna literal (bukan `var(--token)`) di komponen portal gampang jadi nyaris tak kelihatan di dark mode (sudah kejadian sekali, lihat catatan di sana).

## Known Gaps

- Test coverage sudah ada untuk alur portal karyawan & attendance request (`tests/Feature/`, jalan di sqlite in-memory, `RefreshDatabase` + factory) — modul lain (payroll, LMS, shift, dll) masih belum punya test.
- CI (Bitbucket Pipelines) hanya deploy, tidak ada test/lint gate.
- Redis dikonfigurasi tapi tidak dipakai (cache/queue/session masih driver `database`).
- `GET /attendance/template/download` tidak ada middleware `auth`, berbeda dengan endpoint import lain.
- **PENTING buat siapapun yang mengubah `phpunit.xml`**: beberapa test (`tests/Feature/Api/AttendanceApiTest.php`, `Auth/LoginTest.php`, `Portal/DashboardTest.php`, dll) pakai `RefreshDatabase` — kalau `DB_CONNECTION`/`DB_DATABASE` di `phpunit.xml` diarahkan ke database asli (bukan sqlite `:memory:`), `RefreshDatabase` akan **migrate:fresh dan menghapus semua data** di database itu. Sudah pernah kejadian (2026-07-05) — jangan diulang.
- Kalau perubahan CSS/style di portal "tidak muncul" di browser padahal kodenya sudah benar, cek dulu apakah `public/build/assets/app-*.css` sudah lebih baru dari `resources/css/app.css` — kemungkinan besar cuma butuh `npm run watch`/`build` ulang, bukan bug kode.
- `m_companies` belum ada kolom kategori (hospital vs non-hospital) — belum ada gating otomatis buat komponen khusus rumah sakit (Jasa Pelayanan, dst), admin toggle manual per company lewat `company_payroll_components`.
- **Payroll di-redesign total 2026-07-17** (Registry+Strategy+Config, lihat modul Payroll di atas) — tabel `pangkat_salaries`/`position_allowances`/`transport_allowances`/`tenure_allowances`/`company_payroll_components` semuanya **masih kosong** di production (baru diisi kalau finance mulai pakai UI Komponen Gaji baru), dan beberapa komponen (Izin Seharian, Izin Meninggalkan Jam Kerja, rumus Lembur khusus Holding) **belum punya Calculator** — nunggu jawaban aturan bisnis, lihat `knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md` §7 & `planning/next/2026-07-17-rekap-sesi-dan-lanjutan.md`.
- Approval Engine: path reject nyoba nulis `rejected_by`/`rejected_at` ke `attendance_requests` tapi kolom itu tidak ada (silently dropped), dan ejaan status `canceled`/`cancelled` beda antara `approval_activities` vs `approval_snapshots` — detail di `knowledge/markdown/manajemen-persetujuan.md`.
- Beberapa Resource bekas cluster Filament (Assessment, EmployeeDocument, ManPowerPlaning, ShiftPattern, UserTransitions — cluster-nya dihapus 2026-07-11) belum punya `$navigationGroup`, jadi tampil ungrouped di sidebar `/admin` dan di `RoleForm.php`. (`MasterSalaryComponent` sudah beres — direname & masuk grup "Keuangan", lihat modul Payroll di atas.)
- Dokumen karyawan (`UserDocuments`) sekarang dilihat/diunduh lewat portal (tab "Dokumen" di Data Diri/MyStaff), tapi **upload masih cuma lewat Filament** (`/admin/user-documents`, disembunyikan dari nav tapi route-nya aktif) — belum ada form upload dari portal.
- Kanban (`/kanban`) pakai `wire:poll.10s` buat sinkron antar user, **bukan realtime websocket sungguhan** — project belum pasang broadcasting (`BROADCAST_CONNECTION=log` di `.env.example`, tidak ada Pusher/Reverb/Echo). Juga belum ada drag-and-drop asli (masih tombol/dropdown "pindah status").
- **Coaching & Counseling** (redesign 2026-07-26): mobile API (`Api/Coaching`, `Api/Counseling`) ketinggalan dari portal — karyawan bisa pilih konselor & submit konsul lewat mobile, tapi kalau DIA yang jadi konselor, tidak ada endpoint sama sekali buat lihat/balas dari mobile (cuma ada di portal web). `MyCoachingPage` ("Coaching Saya") belum ada filter status/search (beda dari halaman sisi coach yang ada).
- `public/storage` adalah **symlink** (`php artisan storage:link`) — kalau folder project pernah dipindah/direname, symlink lama bisa nunjuk ke path yang sudah tidak ada (gejala: upload berhasil tersimpan tapi gambar/file 404 saat dibuka). Sudah pernah kejadian sekali (2026-07-26) karena sisa path lama sebelum project dipindah — cek `ls -la public/storage` dan jalankan ulang `storage:link` kalau ketemu gejala serupa, bukan langsung curiga bug kode upload.
- Migrasi gate coarse→granular (lihat § Otorisasi) belum menyentuh `SISTEM_APPROVAL` — masih flat, belum dipecah per aksi/subject.
- 77 role non-Super-Admin di 8 company masih 0 permission sejak migrasi Shield (lihat § Otorisasi) — makin relevan sekarang portal `/hak-akses` sudah bisa dipakai admin non-teknis buat assign manual, tapi belum ada yang mengeksekusi assignment massal itu.
