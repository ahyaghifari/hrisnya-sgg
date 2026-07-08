# Lanjutan: Migrasi Filament Shield & Bersih-Bersih Admin — Catatan Sesi 2026-07-08/09

Status: **sesi ditutup di tengah investigasi bug, belum semua beres.** Dokumen ini rangkuman semua yang sudah dikerjakan + apa yang masih nge-gantung, supaya sesi besok bisa lanjut tanpa baca ulang seluruh riwayat chat.

Commit terkait di git: `9495603`, `cbdbf3f` ("bersih bersih admin dan role") — semua perubahan di bawah **sudah ter-commit**, git status bersih per akhir sesi ini.

## 1. Ringkasan Perubahan yang Sudah Selesai

### 1.1 Dashboard admin
- Widget custom (`PerformanceChart`, `NotificationWidget`) dihapus dari `/admin` — dashboard sekarang tampilan default Filament, cuma `AccountWidget`.
- Widget lain yang juga dihapus (tidak dipakai lagi di mana pun): `ShiftCalendarWidget`, `AttendancePoliciesWidget` (+ view blade-nya).

### 1.2 Halaman "Data Diri" (admin) dihapus
- `App\Filament\Resources\Users\UserResource` (folder `app/Filament/Resources/Users/`) dihapus total — dulu halaman standalone (`navigationGroup=null`, sort=-1) buat user edit profil sendiri (alamat, NIK, bank, dokumen) di admin. Alasan: sudah tidak dibutuhkan, semua fitur karyawan diarahkan ke portal.
- Gate `view_data_pribadi`/`edit_data_pribadi` jadi orphan (tidak ada Gate check runtime lagi), tapi permission-nya tetap ada di DB (cuma nongkrong di seeder lama).

### 1.3 Navigasi `/admin` dirombak (`AdminPanelProvider.php`)
Urutan grup navigasi final:
```
Kepegawaian, Manajemen Organisasi, Keuangan, LMS, Manajemen Pengguna, Master
```
- **Grup "Karyawan" dihapus total** — dulu isinya `Jadwal Shift`/`Tukar Shift`, keduanya **dihapus** (bukan cuma dipindah) karena data diri sendiri:
  - `UserShiftSwapResource`: query eksplisit `where('requester_id', Auth::id())`.
  - `UserShiftScheduleResource`: judul "Jadwal Shift Saya" tapi kodenya **bug** — tidak filter apa-apa (nampilin semua karyawan). Tetap dihapus karena niatnya jelas self-data.
- **"Master Data" → "Master"**, dipindah jadi grup terakhir (di bawah "Manajemen Pengguna"). 15 file Resource + mapping grup checkbox permission di `RoleForm.php` (`$navGroupMap`, `$containerOrder`) ikut disesuaikan.
- **"Kepegawaian" dipindah jadi grup pertama.**
- Tombol **theme toggle** (`darkMode(false)` dihapus) — sekarang muncul lagi di profile.

### 1.4 Fitur "data diri sendiri" lain di admin — dihapus
Ternyata ada 2 tempat lagi yang bikin nav item manual (`getNavigationItems()`) nyelipin akses "punya saya", terpisah dari `navigationGroup` biasa jadi kelewat waktu grep pertama:
- **`AttendanceRecordResource`**: nav item grup "Karyawan" ke `?user=Auth::id()` — **dihapus**. Halaman `records`-nya sendiri (`ListAttendanceRecords`) TETAP ADA — itu halaman generik drill-down absensi karyawan mana pun (diklik dari laporan "Rekapan Absensi"), bukan cuma self.
- **`LmsCoursePlanItemResource`**: fitur "Ajukan Pelatihan" (`type=request`, query `where('requested_by', Auth::id())`) **dihapus total** — beda dari kasus attendance, ini murni self-service tanpa makna lain:
  - `CreateLmsCoursePlanItem.php` dihapus filenya.
  - `ListLmsCoursePlanItems.php` disederhanakan — tidak ada lagi tab/type, cuma 1 tabel approval (`request_status != draft`).
  - `EditLmsCoursePlanItem.php` — aksi `Submit`/`Save as Draft` (edit draft sendiri) dihapus, tinggal `Approve`/`Reject`/`Delete` (soal approve punya **orang lain**, dicek `ApprovalPermissionService`).

**Prinsip yang dipakai konsisten sepanjang sesi ini**: `/admin` TIDAK BOLEH ada fitur/nav yang datanya scoped ke `Auth::id()` (punya sendiri) — itu domainnya portal karyawan, bukan admin panel.

### 1.5 Portal — permission check dicabut sementara
5 titik `Gate::allows()`/`@can`/`->can()` di portal Livewire (bukan admin) sengaja **dicabut**, dianggap terbuka semua dulu sampai ada keputusan otorisasi portal yang baru:
- `EditMyEmployee.php` (`edit_karyawan`)
- `MyEmployees.php` (`edit_karyawan`, tombol edit)
- `OrganizationStructure.php` (`view_struktur_organisasi`)
- `organization-chart-widget.blade.php` (`create_struktur_organisasi`)
- `organization-structure.blade.php` (`edit_master_data_jabatan`)
- `config/portal-pages.php` entry `organization-structure` → `permission: null`

### 1.6 Migrasi ke Filament Shield — Fase 0-5 selesai
Plan lengkap & histori keputusan ada di **[`planning/2026-07-08-migrasi-filament-shield.md`](2026-07-08-migrasi-filament-shield.md)** (baca §9-§11 buat versi final, §1-8 historis/sempat dibatalkan). Ringkasan hasil akhir:

- Package `bezhansalleh/filament-shield ^4.2` terinstall.
- **Keputusan final (pivot)**: pakai penamaan **default Shield sendiri** (PascalCase, `Verb:Subject`, mis. `View:Employee`) — BUKAN mempertahankan nama lama (`view_karyawan`). Sempat dibikin custom mapping (`PermissionSubjectMap` + closure) buat preservasi nama lama, tapi **dibongkar lagi** atas permintaan eksplisit ("buang mapping, pakai default Shield").
- **862 total permission** di tabel `permissions` sekarang: ~197 lama (snake_case, orphan — tidak ada Gate check yang baca lagi) + ~660 baru (format `Verb:Subject`, format standar Shield 12 verb: `viewAny/view/create/update/delete/deleteAny/restore/forceDelete/forceDeleteAny/restoreAny/replicate/reorder`).
- **Bug ditemukan & diperbaiki**: sempat trim verb ke 4 (`view/create/update/delete`) untuk YAGNI, ternyata bikin **fail-open** — `canAccess()` Filament sebenarnya cek ability `viewAny` (bukan `view`), bulk-delete cek `deleteAny`; kalau Policy tidak punya method itu, Filament default-nya **allow semua orang** (bukan deny). Sudah diperbaiki: `config/filament-shield.php` → `policies.methods` balik ke default penuh 12 verb.
- **Fase 5 selesai**: `canAccess()`/`canView()`/`canCreate()`/`canEdit()`/`canDelete()` manual dihapus dari 49 file Resource (via script custom, sudah diverifikasi tidak ada syntax error & tidak ada import nyasar) — Filament sekarang fallback ke Policy Shield (`app/Policies/**`, 55 file, auto-discovered Laravel tanpa registrasi manual, sudah diverifikasi lewat `Gate::getPolicyFor()`).
- **Migration**: `database/migrations/2026_07_08_230229_sync_filament_shield_permission_catalog.php` — idempotent, isinya: generate katalog permission (`Artisan::call('shield:generate', ...)`), safety-net rename role "Super Admin" nyasar di company lain → "Admin", bikin role **"Super Admin" global** (`company_id` NULL, id=85, beda baris dari yang lama id=1 company holding), sync SEMUA permission ke kedua baris "Super Admin".
- **2 seeder lama dihapus** (`GrantPermissionManagementToSuperAdminSeeder`, `RenameCompanySuperAdminToAdminSeeder`) — logic-nya sudah digabung ke migration di atas.
- **Konsekuensi yang SUDAH DIKONFIRMASI & DITERIMA**: begitu Fase 5 aktif, **semua role selain 2 "Super Admin" kehilangan akses ke seluruh `/admin`** — karena mereka masih pegang permission lama (`view_karyawan`), sedangkan Policy baru cek nama baru (`View:Employee`). Diverifikasi lewat tes fungsional: `EmployeeResource::canAccess()` untuk role "Admin" biasa = `false`, Super Admin = `true`.

## 2. YANG BELUM SELESAI — Prioritas Besok

### 2.1 🔴 PALING PENTING: Assign ulang permission ke semua role di 8 company
Ini konsekuensi terbesar dari migrasi Shield — **belum dikerjakan sama sekali**. Semua role (Admin, HC Manager, Staff Finance, dst di 8 company) sekarang efektif tidak bisa akses apa-apa di `/admin` kecuali dikasih permission baru secara manual lewat `/admin/roles`. Ini pekerjaan besar terpisah, belum ada rencana konkret gimana cara assign-nya (satu-satu manual? bikin script mapping baru mirip `RoleDefaultPermissionsSeeder` tapi versi permission baru?).

### 2.2 🔴 Bug nyata di `RoleForm.php` — BELUM DIPERBAIKI
File: `app/Filament/Resources/Roles/Schemas/RoleForm.php`, sekitar baris 66-185.

**Masalah**: logic parsing nama permission masih asumsi format lama `verb_subject` (`explode('_', $permission->name)`). Permission baru dari Shield formatnya `Verb:Subject` (PascalCase, colon, **tidak ada underscore**). Akibatnya:
- `explode('_', 'View:Employee')` cuma hasilkan 1 elemen → grouping logic nganggep semua masuk grup `'general'`.
- **660 permission baru numpuk jadi 1 CheckboxList raksasa** di form Role, bukan tersebar rapi per Resource.
- Actionlabel mapping (`$actionLabels`) juga cuma kenal verb lama (`view/create/edit/delete` lowercase), belum ada mapping buat verb baru (`View/Create/Update/Delete/ViewAny/DeleteAny/Restore/dst`, PascalCase).

**Rencana perbaikan** (sudah didiskusikan, belum dieksekusi — sempat dimulai lalu keburu dipotong investigasi bug Livewire):
```php
// Deteksi format: kalau ada ':', split jadi [verb, subject] gaya Shield.
// Kalau tidak, pakai logic lama (explode '_').
$parseParts = function (string $name): array {
    if (str_contains($name, ':')) {
        return explode(':', $name, 2); // ['View', 'Employee']
    }
    $parts = explode('_', $name);
    $action = $parts[0] ?? '';
    $subject = count($parts) > 1 ? implode('_', array_slice($parts, 1)) : 'general';
    return [$action, $subject];
};
```
- Subject dari permission baru (PascalCase, mis. `Employee`, `Religions`) TIDAK akan match `$navGroupMap` (isinya key snake_case Indonesia) — otomatis jatuh ke `$ungroupedSections` (section berdiri sendiri per Resource, bukan numpuk 1 grup) — ini **behavior yang diinginkan**, bukan bug baru.
- `$actionLabels`/`$actionOrder` perlu diperluas: tambah key lowercase versi verb baru (`'update' => 'Edit Data'`, `'viewany' => 'Akses Menu'`, dst) — normalisasi `$action` ke `strtolower()` dulu sebelum lookup, supaya "View" dan "view" dianggap sama.

### 2.3 🟡 Investigasi belum tuntas: `php artisan optimize` bikin Livewire error
**Gejala**: `Livewire\Exceptions\ComponentNotFoundException: Unable to find component: [...]` muncul saat interaksi Select di form manapun (`CreateRole`, `CreateDivisions`, dst) — awalnya dikira masalah production, ternyata **reproducible di lokal juga**.

**Sudah ketemu & fixed sebagian**: root cause utama adalah **View cache basi** (`php artisan view:clear` / `optimize:clear` menyelesaikan) — compiled Blade/Livewire view dari sebelum banyak Resource diubah/dihapus hari ini jadi tidak cocok lagi.

**Yang BELUM tuntas**: user lapor setelah `optimize:clear` (kerja normal), begitu jalankan **`php artisan optimize`** (= `config:cache` + `route:cache` + `view:cache` sekaligus), error muncul LAGI. Sudah dicoba jalankan `config:cache`, `route:cache`, `view:cache` **satu-satu** dari CLI — ketiganya sukses tanpa error output. Artinya masalahnya di **perilaku runtime setelah cache aktif**, bukan di proses caching itu sendiri — butuh reproduksi manual lewat browser (klik Select beneran) buat isolasi persis yang mana penyebabnya, karena tidak bisa disimulasikan dari CLI.

**Dugaan kuat (belum terverifikasi)**: `route:cache` — Filament resource route didaftarkan dinamis lewat `discoverResources()`/`discoverPages()`/`discoverClusters()` (scan filesystem tiap boot), berpotensi tidak cocok kalau di-cache jadi snapshot statis. Filament secara umum punya reputasi "kurang bersahabat" dengan `route:cache` karena sifat dinamisnya.

**Langkah lanjutan yang perlu dicoba besok** (sudah diarahkan ke user, belum ada hasil laporan baliknya):
1. `php artisan config:cache` saja → tes di browser (klik Select di form manapun).
2. Kalau masih OK, tambah `php artisan route:cache` → tes lagi.
3. Kalau masih OK, tambah `php artisan view:cache` → tes lagi.
4. Begitu ketemu langkah yang bikin error, itu penyebabnya — laporkan balik biar bisa diperbaiki spesifik (misal: exclude route caching buat panel Filament, atau cari solusi lain).

**Status cache lokal saat sesi ditutup**: semua bersih (`config:clear`, `route:clear`, `view:clear` sudah dijalankan, `php artisan about --only=cache` konfirmasi semua "NOT CACHED"). Aman dipakai buat lanjut kerja besok, tidak perlu clear ulang kecuali sengaja di-cache lagi.

## 3. Catatan Tambahan / Hal yang Perlu Diingat

- **`role_has_permissions` yang lama (~197 permission snake_case) TIDAK dihapus** — masih ada di DB, masih ter-assign ke role di 8 company, cuma sudah tidak ada Gate/Policy yang baca lagi. Kalau nanti mau bersih-bersih total, itu keputusan terpisah (belum diminta).
- **Urutan kerja dengan plan lain**: ada `planning/2026-07-08-redesign-struktur-payroll.md` (redesign payroll, hapus skema `doctor_*`) yang beririsan Resource dengan migrasi Shield (`DoctorFeeSubmissions`, `DoctorPayrollPeriods`). Kalau lanjut salah satu duluan, cek dulu overlapnya.
- **Database `hris_db` yang dipakai selama sesi ini dikonfirmasi user sebagai DB lokal/dev**, bukan database company yang benar-benar live — semua eksperimen (generate permission, migration, dst) sudah dieksekusi langsung ke situ atas konfirmasi eksplisit.
- Kalau mulai sesi baru dan lupa konteks: baca urutan **`planning/2026-07-08-migrasi-filament-shield.md`** (§9 dulu buat versi final, baru §10-11 buat bug fail-open + Fase 5) → dokumen ini (`2026-07-09-lanjutan-shield-dan-admin-cleanup.md`) buat pending items.

## 4. Referensi File Kunci
- `app/Providers/Filament/AdminPanelProvider.php` — navigasi, widget, theme.
- `app/Filament/Resources/Roles/Schemas/RoleForm.php` — **butuh fix parsing permission** (§2.2).
- `config/filament-shield.php` — konfigurasi Shield final (12 verb, custom_permissions, exclude cluster).
- `app/Providers/AppServiceProvider.php` — kosong lagi (closure custom penamaan sudah dibongkar).
- `database/migrations/2026_07_08_230229_sync_filament_shield_permission_catalog.php` — migration sinkronisasi permission + Super Admin global.
- `planning/2026-07-08-migrasi-filament-shield.md` — histori keputusan lengkap migrasi Shield.
