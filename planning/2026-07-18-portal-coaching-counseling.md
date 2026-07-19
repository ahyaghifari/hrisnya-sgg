# Planning: Portal Coaching & Counseling (2026-07-18)

## 1. Latar Belakang

Coaching & Counseling sudah jadi modul nyata di sistem (bukan stub) — full CRUD Filament (`app/Filament/Resources/Coachings`, `app/Filament/Resources/Counselings`), model, policy, repository, service, plus endpoint API mobile buat karyawan submit issue (Counseling) dan upload bukti pembinaan (Coaching). **Tapi belum ada satu pun halaman di portal web** (`/dashboard` dkk) — akses cuma lewat `/admin` (Filament, Admin/Super Admin only) atau API mobile.

Dua modul ini punya nilai HRIS yang konkret, bukan sekadar catatan administratif:
- **Coaching** = jejak *Performance Improvement Plan*. Kalau suatu saat harus eskalasi ke SP/PHK, riwayat pembinaan yang tercatat jadi bukti perusahaan sudah kasih kesempatan perbaikan secara formal.
- **Counseling** = saluran resmi keluhan karyawan dengan jejak "dilaporkan tanggal X, direspons tanggal Y" — bukti kepatuhan kalau ada eskalasi ke luar (Disnaker, dst).

Dokumen ini merencanakan halaman portalnya. **Yang benar-benar dieksekusi di sesi ini cuma nav item + halaman placeholder minimal** (supaya link tidak mati) — desain halaman penuh di bawah ini buat jadi acuan sesi-sesi berikutnya.

## 2. Fakta Penting dari Kode Existing (jangan diasumsikan ulang)

- **Tidak ada pembeda struktural** antara "coaching dari atasan" vs "coaching dari HR" di skema — cuma 1 tabel `coachings`, kolom `coach_by` (siapa yang tercatat sebagai pembimbing) bisa diisi siapa saja. Perbedaan atasan/HR murni soal **siapa yang membuat record & siapa yang dipilih sebagai `coach_by`**, bukan fitur berbeda.
- **Permission Coaching/Counseling sudah otomatis ada** lewat Shield (karena keduanya Filament Resource biasa): `ViewAny:Coaching`, `Create:Coaching`, `View:Coaching`, `Update:Coaching`, dst (pola sama persis buat `:Counseling`). Ini **beda** dari `CustomPermissionEnum` (`SISTEM_KEUANGAN`, `SISTEM_MASTER`, `SISTEM_ABSENSI`) yang dipakai buat gate section Penggajian/Master/Presensi — permission Shield sudah bisa langsung dipakai sebagai gate portal tanpa bikin enum/seeder baru, dan sudah otomatis muncul di checklist `RoleResource` (`/admin/roles`) buat di-assign per role.
- **`hasSubordinates()`** (`app/Models/User.php:142`) — murni cek struktur organisasi (`activeJobPosition->jobPosition->children()->exists()`), **tidak melibatkan permission apa pun**. Ini dipakai `canAccessKaryawanMenu()` buat gate MyStaff — artinya siapa pun yang punya bawahan otomatis lihat menu MyStaff, terlepas mau atau tidak.
- **`config/portal-pages.php`** mendukung `permission` DAN `guard` sekaligus di 1 entry (dicek AND — lihat `EnsurePortalPageAccess::handle()`, dua `if` independen) — dipakai kalau nanti butuh kombinasi struktur organisasi + permission.
- **My Staff detail page** (`App\Livewire\EmployeeAttendanceStatistics`, route `my-staff.show`) sudah py pola 4 tab (Alpine `x-show`): Profil, Presensi & Absensi (`statistik`), Riwayat Pengajuan (`riwayat`), Dokumen (`dokumen`). Belum ada pola "tombol aksi cepat ke form lain dengan record pre-filled" — ini bakal jadi pola baru kalau mau ditambah tombol "Buat Coaching" di sini.

## 3. Keputusan Desain (disepakati sesi ini)

### 3.1 `created_by` — kolom baru di `coachings`

Field `coach_by` cuma menjawab "siapa pembina yang tercatat", bukan "siapa yang benar-benar input record ini". Karena Coaching baru bisa dibuat lewat `/admin` (Admin/HR-access) atau nanti portal, dua hal ini bisa beda orang: HR bisa buat record dengan `coach_by = Rina` (atasan Budi) padahal yang mengetik itu HR sendiri.

- Tambah kolom `created_by` (nullable FK ke `users`, `nullOnDelete`) — additive, tidak ganggu data lama.
- Diisi otomatis `auth()->id()` saat create (baik dari `/admin` maupun portal nanti), bukan diinput manual.
- Pola sama seperti `payroll_periods.generated_by`/`payrolls.generated_by` (redesign Payroll, lihat `knowledge/markdown/payroll-fase-4-*.md` §4) — dipakai project ini buat kasus serupa "siapa aktor sebenarnya vs siapa yang tercatat sebagai subjek/pihak lain".
- Manfaat konkret: kalau atasan sendiri yang bikin lewat portal nanti, `created_by == coach_by`. Kalau HR yang bikinkan atas nama atasan, keduanya beda — dari situ ketahuan mana yang murni self-service atasan vs dibantu HR.
- **Belum dieksekusi migration-nya di sesi ini** — dicatat sebagai TODO Fase berikutnya (§6).

### 3.2 Dua audiens berbeda, dua entry point berbeda

| Audiens | Yang bisa dilakukan | Gate | Posisi nav |
|---|---|---|---|
| **Atasan** (Coaching) | Lihat riwayat coaching bawahannya, buat coaching baru untuk bawahan | Permission Shield `Create:Coaching` saja (murni role-based, TIDAK ikut `hasSubordinates()`) | Row 2 (role-scoped), **bersebelahan dengan MyStaff** |
| **Semua karyawan** (Counseling) | Submit keluhan/issue sendiri, lihat status & tanggapan HR atas keluhan sendiri | Cukup login (tidak ada gate khusus) | Row 1 (personal), sejajar "Pengajuan Absensi" |
| **Karyawan yang sedang dibina** (Coaching Saya) | Lihat riwayat coaching di mana dia `user_id` (subjek yang dibina), upload bukti perbaikan | Cukup login | Row 1 (personal) — nav + placeholder sudah dieksekusi (lihat §5) |
| **HR** (Counseling — balas & lihat semua) | Lihat semua keluhan company-wide, isi response+solution | Permission `ViewAny:Counseling` (Shield) | Tetap di `/admin` untuk sekarang — versi portal menyusul (§6), bukan prioritas sesi ini |

**Kenapa Coaching murni permission (bukan `hasSubordinates()` seperti MyStaff)**: keputusan final — daripada kombinasi "punya bawahan DAN permission", cukup permission Shield `Create:Coaching` saja. Siapa yang boleh bikin Coaching 100% ditentukan lewat checklist role di `/admin/roles` (assign/cabut permission ke role tertentu), tidak otomatis nempel ke siapa yang kebetulan punya bawahan di struktur organisasi. Ini juga yang menjawab concern awal "atasan punya bawahan tapi tidak mau ada menu Coaching" — solusinya simpel, HR tinggal tidak assign permission itu ke role tersebut, menunya otomatis tidak muncul.

**Kenapa Counseling row 1 (personal), bukan row 2**: beda dari Coaching, Counseling **tidak ada konsep "atasan"** — submit murni milik karyawan sendiri (siapa pun, terlepas dia punya bawahan atau tidak). Jadi levelnya sama seperti Pengajuan Absensi, bukan role-scoped.

### 3.3 Entry point Coaching: halaman mandiri + tombol di My Staff (2 pintu, 1 form)

Sesuai arahan user: **halaman/tombol Coaching yang navigasinya berdampingan dengan MyStaff** (bukan disembunyikan jadi tab di dalam detail My Staff saja). Desainnya:

1. **Halaman "Coaching"** (nav row 2, route baru misal `coaching`) — list riwayat coaching yang pernah dibuat atasan ini untuk bawahannya (scoped ke `OrgHierarchyService::subordinates()`, BUKAN semua company seperti `CoachingsTable` versi admin), plus tombol "Buat Coaching Baru" yang munculkan pilihan bawahan lewat dropdown.
2. **Tombol "Buat Coaching" di detail My Staff** (`my-staff/{id}`) — shortcut kalau atasan lagi buka profil 1 bawahan spesifik & langsung mau bikin coaching buat orang itu (pre-fill `user_id`), redirect ke form yang sama seperti poin 1 dengan karyawan sudah terisi.

Dua pintu masuk, satu form & satu halaman hasil — bukan 2 implementasi terpisah.

## 4. Skema Halaman (detail, buat Fase implementasi nanti)

### 4.1 Halaman "Coaching" (atasan) — route `coaching`

- **List**: card/list riwayat coaching yang `user_id` masuk `OrgHierarchyService::subordinates(auth()->user())`, atau `coach_by = auth()->id()` (union — supaya coaching yang pernah dia buat sebagai coach tetap kelihatan walau org structure berubah setelahnya). Filter status (Semua/Pending/In Progress/Submitted/Completed/Rejected), search nama karyawan.
- **Detail per record**: sama seperti `editSchema()` di `CoachingForm.php` (info karyawan+coach, tujuan, kondisi, solusi, tindakan, foto bukti terkelompok per tanggal) — cukup port ke Blade biasa, bukan Filament Infolist.
- **Create**: form sama seperti `createSchema()` — TAPI dropdown "Karyawan" di-scope ke `OrgHierarchyService::subordinates()` (bukan cuma `org_structure_id` sama seperti versi admin sekarang — perlu diputuskan nanti apakah tetap pakai aturan "departemen sama" dari `CoachingForm.php:43` atau ganti ke "bawahan hierarki", karena dua aturan itu bisa beda hasil tergantung struktur organisasi company).
- **Approve bukti**: aksi ubah status `SUBMITTED → COMPLETED`/`REJECTED` dari halaman ini (sekarang cuma bisa lewat `/admin`).

### 4.2 Halaman "Counseling" (personal) — route `counseling`

- **Submit**: form simpel — textarea `issue` (mirip pola submit Izin/Cuti/Lembur yang sudah ada, custom bukan Filament).
- **Riwayat**: list keluhan yang pernah disubmit sendiri + status (`PENDING`/`DONE`) + tampilkan `response`/`solution` kalau sudah dijawab HR.
- **Tidak ada** fitur "lihat punya orang lain" di halaman ini — itu domain halaman HR terpisah (§4.3, ditunda).

### 4.3 Halaman Counseling — HR (ditunda, belum prioritas)

View-all + balas company-wide, gated `ViewAny:Counseling`/`Update:Counseling`. Untuk sekarang HR tetap pakai `/admin/counselings` yang sudah berfungsi — tidak ada urgensi pindah ke portal dulu, beda dengan Coaching yang memang dari awal didesain buat dipakai atasan (bukan cuma HR).

### 4.4 Coaching Saya (personal, ditunda)

Query: `coachings` di mana `user_id = auth()->id()` — cuma sisi "sedang dibina", bukan gabungan dengan `coach_by`. Row 1 (personal), sejajar Pengajuan Absensi/Counseling — cukup login, tidak digate `Create:Coaching`.

**Nav + placeholder-nya sudah dieksekusi sesi ini** (route `coaching.saya`, `App\Livewire\Coaching\MyCoachingPage`) — isi halamannya (query `user_id`, upload bukti) masih placeholder, lihat §5.

**Gap yang belum terjawab (dicatat, bukan diabaikan)**: orang yang ditunjuk sebagai `coach_by` tapi TIDAK punya permission `Create:Coaching` sendiri (mis. staff biasa ditunjuk HR jadi coach rekannya) saat ini tidak punya tempat lihat coaching yang dia pegang sebagai coach — halaman "Coaching" (§4.1) di-gate `Create:Coaching`, dan "Coaching Saya" ini cuma nampilkan sisi `user_id`. Perlu diputuskan sesi berikutnya: gabung ke halaman ini juga (balik ke desain union), bikin section terpisah, atau dianggap kasus langka yang cukup ditangani manual HR dulu.

## 5. Yang Dieksekusi Sesi Ini (scope kecil, sisanya menyusul)

- Nav item **"Coaching"** — row 2, gated permission Shield `Create:Coaching` saja (murni role-based, bukan `hasSubordinates()`), bersebelahan dengan MyStaff.
- Nav item **"Counseling"** — row 1 (personal), gated login saja.
- Nav item **"Coaching Saya"** — row 1 (personal), gated login saja (BUKAN `Create:Coaching` — lihat §4.4).
- **Ketiga halaman sudah menampilkan data beneran** (bukan lagi placeholder statis) — list pakai pola `.pwa-list`/`.pwa-list-row`/`.badge` yang sudah ada di project:
  - "Coaching Saya" — `Coaching::where('user_id', auth()->id())`.
  - "Coaching" (atasan) — `Coaching` milik `OrgHierarchyService::subordinates()` ATAU `coach_by = auth()->id()`.
  - "Counseling" — `Counseling::where('user_id', auth()->id())`.
- **Belum ada form** — Create Coaching, submit issue Counseling, upload bukti, approve/reject — semua masih read-only list. Juga belum: migration `created_by`, tombol shortcut di My Staff detail, halaman HR Counseling (view-all + balas).

## 6. Belum Dikerjakan / Ditunda (urutan prioritas buat sesi berikutnya)

1. Migration `coachings.created_by` (nullable FK `users`, `nullOnDelete`) + isi otomatis di titik create (admin & portal).
2. Bangun halaman "Coaching" (atasan) penuh — list + create + detail + approve bukti, scoping `OrgHierarchyService::subordinates()`.
3. Tombol "Buat Coaching" di detail My Staff (`my-staff/{id}`), pre-fill karyawan.
4. Putuskan: dropdown "Karyawan" di form Coaching pakai aturan "departemen sama" (existing) atau "bawahan hierarki" (`OrgHierarchyService`) — dua aturan ini bisa beda hasil, perlu dikonfirmasi user sebelum eksekusi form.
5. Bangun halaman "Counseling" (personal) penuh — submit + riwayat + lihat response.
6. Bangun "Coaching Saya" (personal) — riwayat + upload bukti, portal versi dari fitur API mobile yang sudah ada.
7. Bangun halaman HR Counseling (view-all + balas) di portal — kalau memang mau dipindah dari `/admin`, belum tentu perlu.
8. Hapus dead code `dd($data)` di `Coaching::getGroupedProofsAttribute()` (`app/Models/Coaching/Coaching.php:74`) — ditemukan pas eksplorasi, tidak berbahaya (unreachable, setelah `return`) tapi sebaiknya dibersihkan pas menyentuh file ini lagi.

## 7. Referensi

- `app/Filament/Resources/Coachings/*`, `app/Filament/Resources/Counselings/*` — Resource admin existing, jadi basis desain form/table portal.
- `app/Models/Coaching/Coaching.php`, `app/Models/Counseling/Counseling.php` — schema & relasi.
- `app/Services/OrgHierarchyService.php` — resolusi bawahan (`subordinates()`, `subordinateJobPositionIds()`).
- `app/Models/User.php:142-159` — `hasSubordinates()`, `canBrowseAllCompanies()`, `canAccessKaryawanMenu()`.
- `config/portal-pages.php`, `app/Http/Middleware/EnsurePortalPageAccess.php` — registry & middleware gate (permission + guard bisa dikombinasikan AND).
- `app/Livewire/EmployeeAttendanceStatistics.php` + `resources/views/livewire/employee-attendance-statistics.blade.php` — pola tab My Staff detail, tempat nanti nempel tombol "Buat Coaching".
- `knowledge/markdown/payroll-fase-4-koperasi-insentif-manual-line-tracking-slip-gaji.md` §4 — preseden pola `generated_by`/`created_by` di project ini.
