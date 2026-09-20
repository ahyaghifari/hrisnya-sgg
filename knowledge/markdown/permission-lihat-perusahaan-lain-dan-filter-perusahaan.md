# Permission `lihat_perusahaan_lain` — Gerbang Filter Perusahaan di Portal (2026-07-23)

Dokumen ini merangkum satu rangkaian kerja dalam sesi yang sama: bikin permission baru buat gerbang "boleh lihat/filter data company lain", audit seluruh halaman portal yang punya filter perusahaan, seragamkan gating-nya, plus beberapa bug pra-existing yang ketemu di jalan. Ditulis supaya bisa dilanjutkan di sesi lain tanpa perlu menggali ulang percakapan sebelumnya.

## Ringkasan Keputusan (kalau cuma sempat baca 1 bagian)

1. **Permission baru**: `App\Enum\CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN` (`lihat_perusahaan_lain`) — permission "manual" (bukan Shield Verb:Subject), pola sama seperti `sistem_keuangan` dkk.
2. **Satu method sumber kebenaran**: `User::canFilterByCompany()` — dipakai di semua tempat yang butuh cek "boleh pilih company lain apa nggak", gantiin 3 pola lama yang sebelumnya beda-beda per halaman (`PermissionHelper::hasGlobalRole()`, `User::canBrowseAllCompanies()`, cek `hasRole('Super Admin')` inline).
3. **19 halaman portal diseragamkan** ke method ini — kalau user tidak punya permission, filter perusahaan disembunyikan total dan `companyId` dikunci ke company user sendiri (server-side, bukan cuma UI).
4. **1 halaman sengaja DIKECUALIKAN**: Struktur Organisasi (`/struktur-organisasi`) — atas instruksi eksplisit user, tetap terbuka buat siapa saja tanpa gating sama sekali.
5. **3 bug pra-existing ketemu & diperbaiki** di jalan (bukan disengaja dicari, ketemu pas audit): filter Insentif/Koperasi/Periode Gaji/Jadwal ketutup nav (z-index salah), form Tambah Karyawan companyId-nya selalu ke-disable (variabel undefined), dan bug regresi dari perubahan sesi ini sendiri (kolom kiri kosong nganggur pas filter disembunyikan).
6. **Belum di-assign ke role manapun** — permission sudah ada di DB, tapi belum dicentang ke Super Admin/Direktur. Lihat §5 buat langkah lanjutannya.

## 1. Kenapa Permission Ini Dibuat

User mau bikin 1 permission permanen buat kontrol siapa yang boleh lihat data lintas company di portal — rencananya nanti diberikan ke Super Admin dan/atau Direktur. Dibuat dulu sebagai permission murni (`App\Enum\CustomPermissionEnum`), belum langsung diikat ke fitur apapun.

Investigasi lanjutan (diminta user: "lihat di portal halaman apa saja yang ada filter perusahaannya") nemuin bahwa codebase ini **sudah lama** butuh permission kayak gini — ada 3 pola beda buat "siapa boleh lintas company" yang tumbuh sendiri-sendiri per fitur (lihat catatan lama `planning/2026-07-19-kewenangan-lintas-perusahaan-super-admin-vs-admin.md`, status BELUM DIKERJAKAN sebelum sesi ini):

1. `PermissionHelper::hasGlobalRole($user)` — cuma cek `company_id === Global Company` (TIDAK cek role, siapapun user di company Holding kena `true`).
2. `PermissionHelper::hasPermission()` — ada inline check `hasRole('Super Admin')` di dalamnya.
3. `User::canBrowseAllCompanies()` — `hasRole('Direktur') && hasGlobalRole($this)`, dipakai khusus `/my-staff`.

Permission `lihat_perusahaan_lain` + method `User::canFilterByCompany()` jadi jawaban paling sederhana buat pola #1 dan #3 (khusus buat kasus filter perusahaan) — bukan grand unification total (`hasPermission()` yang punya inline Super Admin check tidak disentuh, itu concern lain).

## 2. Method Baru: `User::canFilterByCompany()`

```php
// app/Models/User.php
public function canFilterByCompany(): bool
{
    return $this->can(\App\Enum\CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value);
}
```

Ditaruh dekat `canBrowseAllCompanies()` yang sudah ada — **tidak menggantikan** method itu. `canBrowseAllCompanies()` masih dipakai ~40 file lain untuk keperluan di luar "filter perusahaan" (macam-macam otorisasi lintas company), sengaja dibiarkan apa adanya, di luar scope kerja ini.

## 3. Survey Awal vs Kenyataan — Jangan Percaya Laporan Subagent Mentah-Mentah

Survey pertama pakai Explore subagent sempat salah klasifikasi beberapa halaman ("belum ada gating" padahal sebenarnya sudah, cuma subagent tidak baca sampai ke `@if` di blade). Setelah diverifikasi manual (grep + baca file asli satu-satu), baru ketemu klasifikasi yang benar. **Pelajaran**: laporan subagent untuk audit lintas file yang berdampak security/permission harus diverifikasi ulang ke source langsung sebelum dieksekusi, jangan dieksekusi mentah dari ringkasan.

## 4. Cakupan — 19 Halaman yang Diseragamkan

**Kelompok A — sebelumnya sudah ada gating, tapi pakai mekanisme lama:**

| Halaman | File |
|---|---|
| MyStaff | `app/Livewire/MyEmployees.php` |
| Karyawan | `app/Livewire/Kepegawaian/KaryawanPage.php` |
| Tambah Karyawan | `app/Livewire/Kepegawaian/CreateKaryawan.php` |
| Dokumen Kepegawaian | `app/Livewire/Kepegawaian/DocumentsPage.php` |
| Rekap Cuti | `app/Livewire/Kepegawaian/CutiRecapPage.php` |
| Rekap Lembur | `app/Livewire/Kepegawaian/LemburRecapPage.php` |
| Rekap Izin | `app/Livewire/Kepegawaian/IzinRecapPage.php` |
| Perubahan Status Karyawan | `app/Livewire/Kepegawaian/UserTransitionsPage.php` |
| Ajukan Perubahan Status | `app/Livewire/Kepegawaian/CreateUserTransitionPage.php` |
| Pelatihan (LMS) | `app/Livewire/LMS/LmsCoursesPage.php` (tidak ada UI filter, cuma logic query internal) |

**Kelompok B — sebelumnya SAMA SEKALI tidak ada gating** (siapapun login bisa lihat/pilih company lain lewat dropdown/tombol — celah data lintas company nyata sebelum sesi ini):

| Halaman | File |
|---|---|
| Periode Gaji | `app/Livewire/Keuangan/PayrollPeriodsPage.php` |
| Payroll | `app/Livewire/Keuangan/PayrollsPage.php` |
| Koperasi | `app/Livewire/Keuangan/KoperasiPage.php` |
| Insentif | `app/Livewire/Keuangan/IncentivesPage.php` |
| Mapping Komponen Gaji | `app/Livewire/Keuangan/MappingKomponenGajiPage.php` |
| Penerima Komponen Gaji | `app/Livewire/Keuangan/PayrollComponentRecipientsPage.php` |
| Komponen Gaji | `app/Livewire/Keuangan/SalaryComponentsPage.php` |
| Jadwal | `app/Livewire/Presensi/SchedulesPage.php` |
| Pengaturan Absensi | `app/Livewire/Presensi/AttendanceSettingsPage.php` |

**Sengaja DI LUAR scope**: `Presensi/CreateSchedulePage` (field `company_id` cuma buat bikin 1 jadwal, bukan filter list). Method mutasi (`KaryawanPage::canManage()`, `DocumentsPage::canManage()` — guard edit/hapus lintas company) juga **tidak disentuh**, itu wewenang beda dari "lihat filter", di luar apa yang didiskusikan.

### Pola implementasi (diulang di semua 19 file, sama persis)

**`mount()`** — kunci `companyId` ke company user kalau tidak diizinkan:
```php
if (! Auth::user()->canFilterByCompany()) {
    $this->companyId = Auth::user()->company_id;
}
```

**Guard sisi server pas filter diganti** (BARU — sebelumnya tidak ada satupun file yang menjaga ini, cuma UI yang kadang disembunyikan; request Livewire langsung tetap bisa set `companyId` sembarang company sebelum sesi ini):
- Pola `wire:model.live="companyId"` (select) → di `updatedCompanyId()`, reset balik ke company sendiri kalau tidak diizinkan.
- Pola `wire:click="selectCompany('{id}')"` (tombol `.doc-type-nav`) → `abort_unless(Auth::user()->canFilterByCompany(), 403);` di awal method.

**Blade** — 2 titik ungkit:
- `resources/views/components/company-filter.blade.php` (komponen bersama, dipakai 5 halaman Kelompok B) — dibungkus `@if (auth()->user()->canFilterByCompany())` di root komponen, otomatis nutup 5 halaman sekaligus lewat 1 file.
- 5 halaman lain pakai markup `.doc-type-nav` custom (tidak lewat komponen) — `@if` membungkus **seluruh `<div class="doc-type-nav">`**, bukan cuma `@foreach` di dalamnya (lihat §6.1 soal kenapa ini penting).

## 5. Halaman yang Sengaja DIKECUALIKAN: Struktur Organisasi

`app/Livewire/OrganizationStructure.php` sempat ikut diseragamkan, tapi **dibatalkan** atas instruksi eksplisit user ("untuk struktur organisasi keknya semua bisa") — dikembalikan ke perilaku semula: filter company selalu tampil, `companyId` tidak dikunci, semua user bisa pilih company manapun. Tidak ada permission check di halaman ini sama sekali.

## 6. Bug yang Ketemu & Diperbaiki di Jalan

### 6.1 Regresi dari perubahan sesi ini sendiri — kolom kiri kosong nganggur

`.doc-type-nav` (CSS: lebar tetap 210px di desktop) awalnya cuma dibungkus `@if` di `@foreach`-nya saja, bukan div-nya:
```blade
{{-- SALAH — div tetap render, cuma isinya kosong --}}
<div class="doc-type-nav">
    @if (auth()->user()->canFilterByCompany())
        @foreach (...) ... @endforeach
    @endif
</div>
```
User tanpa permission jadi lihat kolom kiri kosong nganggur (bukan hilang total). Diperbaiki dengan mindahin `@if` ke luar, membungkus seluruh div:
```blade
@if (auth()->user()->canFilterByCompany())
<div class="doc-type-nav">
    @foreach (...) ... @endforeach
</div>
@endif
```
Kena di 5 file: `salary-components-page.blade.php`, `mapping-komponen-gaji-page.blade.php`, `payroll-component-recipients-page.blade.php`, `task-settings-page.blade.php`, `attendance-settings-page.blade.php`.

### 6.2 Bug pra-existing — Tambah Karyawan, dropdown company selalu disabled

`resources/views/livewire/kepegawaian/create-karyawan.blade.php`:
```blade
<select wire:model="company_id" class="form-input" @disabled(! $isGlobal)>
```
`$isGlobal` **tidak pernah didefinisikan** di `CreateKaryawan.php` manapun (bukan public property, bukan computed property `getIsGlobalProperty()`, bukan dilewatkan dari `render()`). PHP 8 nge-treat undefined variable sebagai warning + `null`, jadi `@disabled(! null)` = `@disabled(true)` — **selalu disabled**, buat siapapun, sejak fitur ini dibuat. Bug ini ketemu pas audit halaman "Tambah Karyawan" atas permintaan user, sama sekali tidak berkaitan langsung dengan permission baru selain kebetulan lokasinya sama (dropdown company). Diperbaiki jadi `@disabled(! auth()->user()->canFilterByCompany())`, sekaligus `mount()`/`updatedCompanyId()`-nya diseragamkan ke permission baru juga.

### 6.3 Bug pra-existing — 5 modal ketutup nav (z-index salah)

Ketemu lewat grep `position:fixed;inset:0` di seluruh `resources/views` — 5 modal masih pakai inline style custom, bukan class bersama `.modal-overlay`/`.modal-box` (di `app.css`, z-index 9999), padahal `.topnav` z-index 200:

| File | z-index lama |
|---|---|
| `incentives-page.blade.php` (modal Tambah/Ubah Insentif) | 100 |
| `koperasi-page.blade.php` (2 modal: Tambah Anggota, Ubah Amount) | 100 |
| `payroll-periods-page.blade.php` (modal Tambah/Ubah Periode) | 50 |
| `presensi/partials/schedule-day-form.blade.php` (modal Pengaturan Lanjutan, Fixed/Flexible) | 100 |
| `presensi/partials/schedule-shift-form.blade.php` (modal Pengaturan Lanjutan, Shift) | 100 |

Semua diganti ke `class="modal-overlay"` / `class="modal-box"` (2 modal jadwal pakai `style="padding:0;max-width:...px;"` tambahan karena layout header/body custom-nya beda dari modal standar). User yang laporin (ditemukan lewat instruksi eksplisit "modal insentif belum menggunakan class modal yang lain, terhalang nav") — Insentif jadi contoh konkret, tapi pola yang sama ketemu di 4 file lain lewat grep, sekalian diperbaiki semua.

## 7. Yang Belum Dikerjakan / Langkah Lanjutan

- **Permission belum di-assign ke role manapun.** Baris `lihat_perusahaan_lain` sudah ada di tabel `permissions` (dibuat lewat `php artisan db:seed --class=CustomPermissionsSeeder`), tapi belum dicentang ke role apapun.
  - **Super Admin**: otomatis dapat SEMUA permission (termasuk yang baru) kalau `php artisan db:seed --class=RolePermissionSeeder` dijalankan ulang — belum dieksekusi, sengaja ditahan sampai user konfirmasi (dampaknya ke semua company sekaligus).
  - **Direktur**: harus di-assign manual lewat `/admin/roles` (Filament Shield) — user sudah bilang akan lakuin ini sendiri.
- **`canBrowseAllCompanies()` dan `PermissionHelper::hasGlobalRole()`/`hasPermission()`** masih dipakai apa adanya di luar scope filter perusahaan (~40 file). Unifikasi total ke satu sistem otorisasi lintas company (kalau nanti dianggap perlu) masih topik terbuka, lihat `planning/2026-07-19-kewenangan-lintas-perusahaan-super-admin-vs-admin.md`.
- Test otomatis belum dibuat untuk perubahan ini — verifikasi sejauh ini manual (`php -l`, `Blade::compileString`, cek DB lewat tinker). Kalau nanti mau nambah test regresi, titik paling berharga: pastikan `updatedCompanyId()`/`selectCompany()` benar-benar menolak/reset `companyId` yang di-set user tanpa permission (defense-in-depth server-side, bukan cuma UI hidden).

## 8. Peta File (referensi cepat)

| Area | File |
|---|---|
| Enum permission | `app/Enum/CustomPermissionEnum.php` (`LIHAT_PERUSAHAAN_LAIN`) |
| Seeder permission | `database/seeders/CustomPermissionsSeeder.php` |
| Shield custom permission registry | `config/filament-shield.php` (`custom_permissions`) |
| Method sumber kebenaran | `app/Models/User.php` (`canFilterByCompany()`) |
| Komponen filter bersama | `resources/views/components/company-filter.blade.php` |
| 19 halaman diseragamkan | lihat tabel §4 |
| Dikecualikan | `app/Livewire/OrganizationStructure.php` + `resources/views/livewire/organization-structure.blade.php` |
| Bug modal z-index | `incentives-page.blade.php`, `koperasi-page.blade.php`, `payroll-periods-page.blade.php`, `presensi/partials/schedule-day-form.blade.php`, `presensi/partials/schedule-shift-form.blade.php` |
| Bug `$isGlobal` undefined | `app/Livewire/Kepegawaian/CreateKaryawan.php` + blade-nya |
