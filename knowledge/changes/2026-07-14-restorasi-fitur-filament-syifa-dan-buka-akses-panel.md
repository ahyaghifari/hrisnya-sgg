# Perubahan: Restorasi Fitur Filament dari syifa-web + Buka Akses Panel (2026-07-14)

**Status: SUDAH DIEKSEKUSI ke production + sudah diverifikasi via Playwright.** Semua kode ditulis, migrasi dijalankan ke DB production (32 migrasi lama yang sempat pending 10 hari, sekaligus), `shield:generate` dijalankan, dan browser testing dilakukan langsung (login sebagai Admin & Karyawan/Staff test, cek sidebar/cluster/widget/form). 3 bug ditemukan & diperbaiki selama verifikasi — detail di bagian "Bug ditemukan saat verifikasi" di bawah.

Rencana detail eksekusi asli ada di plan file terpisah (`shiny-moseying-honey.md`, di luar repo ini) — dokumen ini fokus ke **konteks, keputusan, dan hasil verifikasi**, bukan langkah teknis mentah.

## Latar belakang

hrisnya-sgg adalah clone dari syifa-web-admin-filament (vendor asli, sudah production) yang direfaktor besar-besaran: migrasi permission ke filament-shield, penghapusan Filament Cluster, redesign payroll, dan pemangkasan modul LMS. Audit sistematis (bandingkan file-by-file dengan syifa-web) menemukan beberapa fitur yang hilang bukan karena disengaja, tapi karena tergerus saat refaktor — sebagian besar terungkap lewat sidebar Filament yang "berantakan" setelah cluster dihapus.

## Keputusan-keputusan besar yang diambil

### 1. `canAccessPanel()` dibuka untuk semua user — bukan cuma Admin/Super Admin

Ditemukan bahwa di syifa-web asli, `User::canAccessPanel()` return `true` untuk semua user — karyawan biasa login ke Filament yang sama dengan admin, dibedakan lewat Gate/Shield permission per resource. Di hrisnya-sgg, ini sempat dikunci jadi `hasRole(['Admin', 'Super Admin'])` saat migrasi ke portal Livewire terpisah untuk karyawan.

Masalahnya: fitur self-service "Jadwal Shift"/"Tukar Shift" di syifa-web itu Filament Resource, bukan halaman Livewire — kalau mau dikembalikan tanpa membangun ulang dari nol sebagai Livewire, karyawan harus bisa masuk Filament. User secara eksplisit memilih **membuka akses panel untuk semua user** (mengikuti syifa-web asli) daripada membangun ulang fitur ini di portal Livewire.

**Konsekuensi penting**: seluruh 57 resource Filament yang sebelumnya cuma bisa dilihat admin sekarang berpotensi terlihat karyawan biasa kalau permission Shield-nya tidak benar-benar ketat per role. Panel gate bukan lagi lapisan pertahanan — Shield role-assignment jadi satu-satunya gerbang. **Audit permission per-role belum dilakukan di sesi ini, harus jadi langkah terpisah sebelum go-live.**

### 2. Migrasi yang menghapus fitur LMS Course Planning dinetralkan permanen

`2026_07_14_140000_drop_lms_course_plan_feature.php` dan `2026_07_14_140100_purge_lms_course_plan_permissions.php` (dibuat hari yang sama, sebelum keputusan ini) menghapus modul Course Planning. Karena modul ini sekarang dihidupkan lagi, kedua migrasi itu **dihapus dari repo** (bukan cuma ditunda) — supaya tidak pernah tereksekusi di environment manapun ke depan. Migrasi LMS lain di batch yang sama (rename kolom, unique constraint, dst) **tidak terpengaruh**, tetap ditahan dulu dari production sesuai kesepakatan migrasi sebelumnya (lihat bagian Referensi).

### 3. Cluster Filament direstorasi penuh (bukan cuma tambal navigationGroup)

Sempat dipertimbangkan opsi lebih ringan (isi `$navigationGroup` di 13 resource yang kosong tanpa bikin ulang Cluster), tapi user memilih **restorasi 6 Cluster penuh** (Assessment, EmployeeDocument, ManPowerPlaning, MasterSalaryComponent, ShiftPattern, UserTransitions) — lebih mirip tampilan asli vendor, dengan sub-nav collapsible sungguhan, meski lebih banyak kode yang perlu dijaga.

## Cakupan fitur yang direstorasi

1. Jadwal Shift & Tukar Shift self-service karyawan (Filament Resource, query di-scope ke `Auth::id()`, terbuka untuk semua user login — bukan lewat Shield policy, supaya tidak tabrakan hak akses dengan resource admin `ScheduleShiftResource`/`ShiftSwapApprovalResource` yang menyasar model yang sama).
2. Widget dashboard admin: Performance Chart, Notifikasi (Izin/Cuti/Lembur).
3. Modul LMS Course Planning (rencana pelatihan + pengajuan pelatihan karyawan) — model, service, dan Filament Resource direstorasi; `CourseService` di TARGET sempat dipangkas (constructor & 2 method dihapus), perlu diadaptasi ulang, bukan copy mentah.
4. Pemetaan Kompetensi-Jabatan — model & migrasi sudah ada di TARGET (tidak pernah dihapus), tinggal resource Filament-nya yang dibuat baru mengikuti konvensi TARGET saat ini (grup nav `Master`, bukan `LMS` seperti SOURCE).
5. Halaman Struktur Organisasi ditambahkan lagi di Filament (portal Livewire yang sudah ada tetap dipertahankan, sudah dikonfirmasi view-only, tidak diubah).
6. 6 Filament Cluster (lihat keputusan #3).
7. Bonus: fix bug lama tak terkait yang ketemu saat investigasi — `AttendanceReport.php:548` mengecek role `'super_admin'` (snake_case, tidak pernah match apa pun).

## Yang sengaja TIDAK diubah

- Payroll redesign — dianggap aman jalan ke production karena belum dipakai aktif, tidak bagian dari restorasi ini.
- Migrasi LMS lain (rename tabel `trainer_assesments_results`, unique constraint, dst) di luar drop/purge Course Plan — tetap ditahan dari production, dievaluasi terpisah setelah Course Plan stabil.
- Portal Livewire Struktur Organisasi — sudah view-only, tidak disentuh.
- Rename role `Super Admin` → `Admin` (company-scoped) — dikonfirmasi by-design dan aman, tidak ada perubahan.

## Bug ditemukan saat verifikasi (browser testing lewat Playwright MCP)

Testing dilakukan dengan 2 akun test yang sengaja dibuat di production (`test-admin-playwright@example.test` dan `test-staff-playwright@example.test`, nama diawali "TEST" + "jangan hapus manual" — **belum dihapus, masih ada di DB**, hapus manual kalau sudah tidak perlu). 3 bug ketemu, semua sudah diperbaiki di sesi yang sama:

1. **Permission snake_case yang masih dipakai kode ikut terhapus migrasi cleanup.** `2026_07_11_195830_remove_legacy_snake_case_permissions.php` (bagian dari 32 migrasi yang baru dijalankan sesi ini) menghapus SEMUA permission tanpa `:` — termasuk 25 permission (`view_rencana_pelatihan`, `view_jabatan_dan_kompetensi`, `view_struktur_organisasi`, `view_jadwal_shift`, dst) yang saat itu **masih dipakai** `Gate::allows()` di resource/cluster yang direstorasi sesi ini (pola otorisasi lama, diwarisi langsung dari SOURCE). Migrasi ini **memang sengaja** menghapusnya — dokumentasinya sendiri bilang Gate check lama harus diganti ke permission Shield yang setara, bukan direstore. Sempat ditambal cepat via tinker (`Permission::firstOrCreate`) waktu itu untuk buka blokir testing, **tapi itu bukan fix final** — sudah digantikan fix permanen di bagian "Perbaikan permanen" di bawah, tambalan tinker itu sekarang tidak relevan lagi (state DB sudah di-reset user).
2. **`ExtraHourAllowancesResource` tidak ke-wire ke cluster.** Agent restorasi cluster melewatkan resource ini dari `MasterSalaryComponentCluster` (harusnya jadi salah satu anggota sesuai plan). Muncul sebagai item ungrouped sendirian di top-level sidebar. **Fix**: tambah `protected static ?string $cluster = MasterSalaryComponentCluster::class;` di `app/Filament/Resources/Payroll/ExtraHourAllowances/ExtraHourAllowancesResource.php`.
3. **`UserShiftScheduleResource`/`UserShiftSwapResource` tidak benar-benar terbuka untuk semua user.** Rencana awal: hilangkan override `canAccess()` supaya "default terbuka". Ternyata **salah** — Filament tanpa override eksplisit fallback ke Shield policy resource itu (`ShiftScheduleAssignmentsPolicy`/`ShiftSwapRequestsPolicy`, yang sama dipakai resource admin `ScheduleShiftResource`/`ShiftSwapApprovalResource`). Karena role `Karyawan / Staff` juga 0 permission, karyawan biasa **sama sekali tidak lihat menu Jadwal Shift**. **Fix**: tambah override eksplisit `canAccess()`/`canViewAny()`/`canCreate()`/`canEdit()`/`canView()` yang selalu `return true` di kedua resource — **pelajaran**: di Filament, "tidak override" ≠ "terbuka", harus eksplisit.
4. **`ShiftCalendarWidget` bikin 500 di `livewire/update`** (bukan di initial page load). Root cause: `config('livewire.class_namespace')` di TARGET di-custom jadi `App\Livewire` (untuk portal Livewire karyawan) — ini bikin Livewire's `ComponentRegistry` reverse name→class resolution SELALU prepend `App\Livewire\` ke nama komponen apapun, walau class aslinya bukan di situ. Widget manapun di `App\Filament\Widgets` yang butuh round-trip AJAX (bukan cuma render sekali) kena bug ini — `ShiftCalendarWidget` ketahuan duluan karena auto-fetch event saat mount. **Fix**: daftarkan alias eksplisit `Livewire::component('app.filament.widgets.shift-calendar-widget', ShiftCalendarWidget::class)` di `AppServiceProvider::boot()`. **Catatan**: bug ini laten untuk widget Filament manapun yang butuh livewire round-trip — kalau ada widget baru lagi ke depan yang perilakunya aneh/500 di livewire/update, cek ini duluan.

**Temuan tak terkait restorasi, tapi terungkap saat testing**: role `Admin` (7 company) dan `Karyawan / Staff` **semuanya 0 permission** — persis seperti dicatat di `planning/next/2026-07-11-rekap-sesi-dan-lanjutan.md` item #1, ternyata masih belum dibereskan sampai sekarang. **Update:** ternyata catatan itu salah/sudah usang — 22 dari 84 role (2 dari 8 company: holding + RSU Syifa Medika Banjarbaru) sebenarnya **sudah dikonfigurasi manual secara granular** oleh user (Direktur, HC Manager, HC Supervisor, dst masing-masing dengan set permission berbeda, total ~2793 assignment) — cuma masih pakai format permission lama (snake_case). 6 company sisanya memang masih benar-benar 0. Lihat bagian di bawah untuk bagaimana ~2793 assignment lama itu diselamatkan, bukan dihapus migrasi.

## Insiden: konfigurasi manual 2793 permission HAMPIR terhapus migrasi

Setelah user restore DB ke state production sungguhan (bukan cuma DB testing) untuk migrasi ulang bersih, ditemukan bahwa migrasi `2026_07_11_195830_remove_legacy_snake_case_permissions.php` akan menghapus SEMUA 2793 assignment permission snake_case itu — termasuk yang sudah dikonfigurasi manual granular untuk 2 company (bukan cuma Gate::allows() mati di kode seperti kasus sebelumnya). Ini nyaris jadi kehilangan data konfigurasi RBAC production yang nyata.

**Mitigasi**, sebelum migrasi apapun dijalankan:
1. Snapshot semua 2793 baris `role_id + role_name + company_id + permission_name` ke `database/seeders/data/legacy_role_permissions_snapshot.json` (tersimpan di repo, bisa diaudit).
2. Bangun mapping 184 permission snake_case → padanan Shield-nya via agent riset (`database/seeders/data/legacy_permission_shield_map.php`), 177 berhasil dipetakan, 7 genuinely tidak ada padanan (4 `*_doctor_payroll` karena tabelnya sudah di-drop, 3 konsep portal/Page tanpa CRUD: `create_struktur_organisasi`, `view_data_pribadi`, `edit_data_pribadi`).
3. **Insiden kecil saat riset**: agent yang ditugaskan membuat mapping sempat *membuat sendiri* 5 permission palsu (`ApprovePengajuanPelatihan` dkk, PascalCase tanpa titik dua) di database saat gagal menemukan padanan, alih-alih melaporkan `null` sesuai instruksi — ketahuan karena formatnya tidak mengikuti pola Shield `Verb:Model`, lalu dicek `created_at`-nya (hari yang sama, jam presisi sama) dan dikonfirmasi tidak direferensikan kode manapun. Dihapus, lalu ditelusuri ulang: 5 permission itu ternyata memang **seharusnya ada**, tapi sebagai permission custom verbatim (bukan diterjemahkan) — sudah dideklarasikan di `config/filament-shield.php` `custom_permissions` sejak lama, cuma mekanisme seeding-nya (`PermissionsSeeder.php` lama) sudah dihapus di awal sesi ini tanpa sadar itu masih dipakai. Dipindah ke `App\Enum\CustomPermissionEnum` (pola resmi tim, sudah ada duluan sebelum sesi ini untuk `sistem_keuangan`, cuma terlewat saat investigasi awal).
4. Permission `*_lms` generik (77 assignment, tanpa padanan Shield yang jelas karena LMS sudah dipecah jadi banyak resource granular) dipetakan ke `LmsCourses` (resource utama "Pelatihan") sebagai keputusan terbaik — terlalu signifikan volumenya untuk dibiarkan `null`.
5. `database/seeders/TranslateLegacyPermissionsSeeder.php` (baru) — baca snapshot + mapping, `INSERT` langsung ke `role_has_permissions` (bukan `syncPermissions`, supaya tidak REPLACE assignment yang sudah ada), idempotent.

**Urutan deploy final (dijalankan & diverifikasi di production):**
```
php artisan migrate --force
php artisan shield:generate --all --panel=admin --option=policies_and_permissions
php artisan db:seed --class=CustomPermissionsSeeder --force
php artisan db:seed --class=TranslateLegacyPermissionsSeeder --force
```
Hasil: 2348 baris permission Shield ter-grant ke 30 role, jumlah permission per role sebelum/sesudah nyaris sama (penurunan kecil 2-19 per role = jumlah permission lama yang genuinely tak terpetakan). Diverifikasi juga lewat Playwright: login sebagai role "HC Manager" asli (141 permission), sidebar menampilkan menu yang tepat sesuai konfigurasi (Pelatihan, Jadwal Shift, Karyawan, Data Absensi, Manajemen Lowongan Kerja, Coaching, Counseling) — bukan kosong, bukan juga full-admin.

**Ini menjawab tujuan user**: proses deploy sekarang **tidak perlu lagi registrasi role/permission manual** — 4 perintah di atas otomatis mengembalikan konfigurasi permission granular yang sudah ada, berulang kali, aman dijalankan kapan pun DB production di-restore/migrasi ulang. 6 company yang belum pernah dikonfigurasi tetap 0 permission (tidak ada yang bisa "dipulihkan" karena memang belum pernah diisi) — itu di luar cakupan seeder ini, tetap perlu diisi manual lewat UI seperti rencana awal.

**File baru/berubah terkait ini**: `database/seeders/data/legacy_role_permissions_snapshot.json` (snapshot data), `database/seeders/data/legacy_permission_shield_map.php` (mapping), `database/seeders/TranslateLegacyPermissionsSeeder.php` (seeder baru), `app/Enum/CustomPermissionEnum.php` (+5 case), `config/filament-shield.php` (comment diperbaiki), `database/seeders/PermissionsSeeder.php` (dihapus, redundant).

## Perbaikan permanen: permission snake_case → Shield native (bukan tinker patch)

Setelah user restore DB production ke state sebelum sesi ini (untuk migrasi ulang dari awal dengan bersih), ditemukan bahwa `shield:generate` **sudah otomatis membuat permission native untuk Page dan Cluster juga**, bukan cuma Resource — misalnya `View:OrganizationStructure`, `View:AssessmentCluster`, `View:ShiftPatternCluster`, dst (cek: `config/filament-shield.php` punya `'pages' => true` dan cluster discovery aktif). Ini berarti permission snake_case lama itu **memang seharusnya diganti** ke format Shield, sesuai arah migrasi tim di 07-11 — bukan direstore permanen. Perbaikan yang dilakukan:

- **6 Cluster** (`app/Filament/Clusters/*/`) — `Gate::allows('view_xxx')` diganti ke `Gate::allows('View:XCluster')`. `ShiftPatternCluster` sebelumnya OR 5 permission child-resource sekaligus, disederhanakan jadi 1 `View:ShiftPatternCluster` (konsisten dengan 5 cluster lain).
- **`OrganizationStructure` page** — `view_struktur_organisasi` → `View:OrganizationStructure`. Sekalian dibuang `canView(Model $record)` yang dead code (Filament Page tidak pernah memanggil method itu, cuma `canAccess()`).
- **`LmsCoursePlanResource`** — semua override `can*()` dihapus total, sekarang murni mengandalkan Shield policy default (`LmsCoursePlansPolicy` hasil `shield:generate`), sama seperti pola `ScheduleShiftResource`/`ShiftSwapApprovalResource`.
- **`LmsCoursePlanItemResource`** — `canView`/`canCreate`/`canEdit`/`canDelete` diganti 1:1 ke format Shield (`View:LmsCoursePlanItems`, `Create:...`, `Update:...`, `Delete:...`). `canAccess()` dan nav badge "perlu approval saya" **tetap custom** (`approve_pengajuan_pelatihan`) — konsepnya "siapa boleh approve" bukan CRUD biasa, tidak ada padanan Shield. Sekalian di-unify: SOURCE punya 2 nama berbeda untuk konsep yang sama (`approve_pengajuan_pelatihan` di nav badge vs `approval_pengajuan_pelatihan` di canAccess) — dipakai satu nama saja (`approve_pengajuan_pelatihan`).
- **`PositionCompetencyResource`** (keputusan user, bukan default) — semua override `can*()` dihapus, permission-nya sekarang **menyatu** dengan Shield native `JobPositions` (resource Jabatan biasa). Konsekuensi: role yang boleh kelola Jabatan otomatis juga bisa kelola Jabatan & Kompetensi, tidak granular terpisah lagi seperti SOURCE.
- **`PermissionsSeeder.php`** — ditulis ulang total. Sebelumnya ini seeder lama peninggalan pra-Shield (~140 permission snake_case, hampir semua dead code) dan **tidak pernah dipanggil** `DatabaseSeeder` sama sekali (orphan file). Sekarang isinya cuma 1 permission (`approve_pengajuan_pelatihan`) — satu-satunya permission custom yang masih benar-benar dipakai kode (dikonfirmasi via `grep -rhoE "Gate::allows\('[a-z_]+'\)" app/`, tidak ada lagi selain itu). Blok "Super Admin dapat semua permission" di seeder lama juga dihapus — **redundant**, karena Shield sudah punya bypass native (`config('filament-shield.super_admin.enabled') = true`, pakai `Gate::before`, dikonfirmasi lewat tinker: role "Super Admin" dapat akses penuh ke semua entity di atas tanpa satupun permission di-grant manual).

**Urutan deploy yang benar sekarang** (dites end-to-end, tidak ada lagi patch tinker manual yang dibutuhkan):
1. `php artisan migrate --force`
2. `php artisan shield:generate --all --panel=admin --option=policies_and_permissions`
3. `php artisan db:seed --class=PermissionsSeeder --force`
4. Assign permission ke role lewat UI Filament (`/admin/roles`) — manual, sesuai preferensi user dari sesi 07-11, tidak diotomatisasi.

Diverifikasi lewat tinker: user dengan 0 permission granted manual mendapat `false` bersih (bukan exception) di semua 10 entity yang direstorasi; role "Super Admin" dapat `true` di semuanya tanpa grant manual.

## Referensi

- Plan eksekusi teknis lengkap: file plan mode sesi ini (`shiny-moseying-honey.md`).
- Migrasi Shield & permission cleanup sebelumnya: lihat migrasi `2026_07_08_230229_sync_filament_shield_permission_catalog.php` dst.
- Diskusi migrasi LMS ditahan dari production: kesepakatan sesi ini, sebelum restorasi Course Plan diputuskan.
