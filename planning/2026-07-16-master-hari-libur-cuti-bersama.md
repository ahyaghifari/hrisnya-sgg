# Planning: Master "Hari Libur & Cuti Bersama" — Resource Filament + Halaman Portal — 2026-07-16

Status: **rencana, siap dieksekusi.**

## Latar Belakang

Diskusi awal: kartu "Sisa Cuti Tahunan" di `home-cards` (dashboard portal) cuma menghitung `quota - used` ([`LeaveReqRepository::getLeaveUserBalance()`](../app/Repositories/Attendance/LeaveReqRepository.php)) — belum memperhitungkan **cuti bersama** (tanggal yang ditetapkan pemerintah, mengurangi jatah cuti tahunan karyawan). Belum ada tabel/model apa pun untuk kalender hari libur di codebase ini.

Di Indonesia, "Hari Libur Nasional" dan "Cuti Bersama" diterbitkan sebagai **satu kalender yang sama** (SKB 3 Menteri) — beda tanggal cuma beda efeknya:
- **Libur nasional**: tidak masuk kerja, tidak ada presensi wajib, **tidak** mengurangi cuti tahunan.
- **Cuti bersama**: tidak masuk kerja, tidak ada presensi wajib, **mengurangi** cuti tahunan.

Karena satu kalender dengan 1 flag pembeda, dipakai **1 tabel** (bukan `LiburDanCutiBersama` split 2 tabel — bakal butuh join tiap kali cek "apakah tanggal ini hari libur", YAGNI untuk kasus ini). Tabel ini juga jadi fondasi untuk fitur **presensi otomatis** yang direncanakan belakangan (tanggal-tanggal di tabel ini dipakai buat skip requirement presensi) — tidak perlu tabel terpisah lagi nanti.

## Penamaan

Ikuti konvensi master data yang sudah ada di domain attendance (`m_leave_types`/`LeaveTypes`, prefix `m_` + nama Inggris plural — sama pola dengan `m_employee_types`/`EmployeeTypes`):

| | Nama |
|---|---|
| Tabel | `m_holidays` |
| Model | `App\Models\Master\Attendance\Holidays` (folder sama `LeaveTypes`, domain sama) |
| Resource Filament | `App\Filament\Resources\Master\Holidays\HolidayResource` |

## Skema Tabel

```
m_holidays
  id           ulid, PK
  company_id   ulid, FK -> m_companies, NULLABLE (null = berlaku semua company, pola sama
               m_salary_components/m_doctor_services lama — mayoritas hari libur nasional
               berlaku semua company, tapi dibuka opsi override per company kalau perlu)
  date         date, wajib
  name         string, wajib (mis. "Hari Raya Idul Fitri", "Cuti Bersama Idul Fitri")
  is_collective_leave  boolean, default false (true = cuti bersama, mengurangi cuti tahunan)
  timestamps
```

Tidak ada `is_active` — tidak ada FK lain yang mereferensikan baris ini (beda dari `salary_components` yang butuh soft-disable karena dipakai `assignments`), jadi kalau ada baris salah, admin tinggal hapus langsung. Index `(company_id, date)` buat query kalender per periode.

## Perubahan Kode

### 1. Migration
`database/migrations/2026_07_16_xxxxxx_create_m_holidays_table.php` — `Schema::create('m_holidays', ...)` sesuai skema di atas, FK `company_id` ke `m_companies` (`nullOnDelete`).

### 2. Model
`app/Models/Master/Attendance/Holidays.php` — `HasUlids`, `$table = 'm_holidays'`, cast `date` → `date`, `is_collective_leave` → `boolean`, relasi `company(): BelongsTo`.

### 3. Resource Filament (admin, `/admin/holidays`)
Folder `app/Filament/Resources/Master/Holidays/` — ikuti pola **terbaru** yang dipakai `SalaryComponentResource` (modal CRUD polos, `EditAction`/`DeleteAction`/`CreateAction` default tanpa sub-action konfirmasi manual — lebih ringkas dari pola lama `LeaaveTypeResource`):
- `HolidayResource.php` — `$navigationGroup = 'Master'` (sekelompok dengan `LeaaveTypeResource`, `EmployeeTypeResource`, dll — ini murni master data referensi, bukan transaksi), `$navigationSort = 15` (lanjut dari `TaskServiceResource` yang sort 14), icon `Heroicon::OutlinedFlag`.
- `Schemas/HolidayForm.php` — `date` (DatePicker), `name` (TextInput), `company_id` (Select, optional, helper text "Kosongkan = berlaku untuk semua company"), `is_collective_leave` (Toggle, label "Cuti Bersama (mengurangi cuti tahunan)").
- `Tables/HolidaysTable.php` — kolom `date` (sortable, default urut terbaru), `name`, `company.name` (placeholder "Semua Perusahaan"), `is_collective_leave` (badge/icon boolean). Filter `company_id` (pola sama `SalaryComponentsTable`).
- `Pages/ListHolidays.php` — `CreateAction::make()` polos.

### 4. Perhitungan Sisa Cuti Tahunan
`app/Repositories/Attendance/LeaveReqRepository.php::getLeaveUserBalance()` — tambah pengurang cuti bersama, **cuma untuk leave type yang namanya mengandung "tahunan"** (konsisten dengan cara `LeaveBalanceCard` sudah mencocokkan tipe lewat `typeKeyword`, bukan kolom terpisah — tidak menambah kolom baru di `m_leave_types` untuk 1 flag ini):

```php
$collectiveLeaveDays = 0;
if ($leaveType && str_contains(mb_strtolower($leaveType->name), 'tahunan')) {
    $periodStart = $balanceRecord?->period_start ?? now()->startOfYear();
    $periodEnd = $balanceRecord?->period_end ?? now()->endOfYear();

    $collectiveLeaveDays = Holidays::where('is_collective_leave', true)
        ->whereBetween('date', [$periodStart, $periodEnd])
        ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $leaveType->company_id))
        ->count();
}

$available = $quota - $used - $collectiveLeaveDays;
```

Hasil akhir: "Sisa Cuti Tahunan" = `quota_days (12) - hari cuti bersama tahun ini - cuti yang sudah dipakai/diajukan` — persis rumus yang diminta.

### 5. Portal Karyawan — dropdown nav "Master"

Ini dropdown nav portal **baru** (beda dari dropdown "Master Gaji" yang sudah ada di grup Keuangan) — isinya baru 1 halaman sekarang (Hari Libur & Cuti Bersama), tapi user minta bentuknya dropdown (bukan link langsung), jadi disiapkan sebagai wadah kalau nanti nambah item master lain.

- **Permission baru**: `CustomPermissionEnum::SISTEM_MASTER = 'sistem_master'` (pola sama persis `SISTEM_KEUANGAN` — gerbang tunggal, assign manual ke role lewat `/admin/roles`, dijaga `CustomPermissionsSeeder`).
- **Route** (`routes/web.php`, grup `auth+portal.access` yang sudah ada): `GET /master/hari-libur` → `App\Livewire\Master\HolidaysPage`, name `master.hari-libur`.
- **Livewire component** `app/Livewire/Master/HolidaysPage.php` — reuse `HolidayResource`'s `HolidaysTable`/`HolidayForm` (pola sama persis `Keuangan\SalaryComponentsPage`: `implements HasActions, HasSchemas, HasTable`, `mount()` cek `abort_unless(Auth::user()->can(SISTEM_MASTER), 403)`, `getDefaultActionSchemaResolver()` suntik `HolidayForm` buat Create/Edit).
- **View** `resources/views/livewire/master/holidays-page.blade.php` — `<x-page-header title="Hari Libur & Cuti Bersama" />` + `{{ $this->table }}` (sama pola `salary-components-page.blade.php`).
- **`config/portal-pages.php`** — entry baru `'master.hari-libur' => ['label' => 'Hari Libur & Cuti Bersama', 'permission' => SISTEM_MASTER, 'guard' => null]`.
- **Nav desktop** (`resources/views/components/nav.blade.php`) — dropdown baru gated `$canAccessMaster`:
  ```blade
  @if ($canAccessMaster)
      <div class="dropdown">
          <a href="#" class="nav-item {{ request()->routeIs('master.*') ? 'active' : '' }}">
              <i class="fa-solid fa-database text-xs"></i> Master <i class="fa-solid fa-chevron-down text-[10px]"></i>
          </a>
          <div class="dropdown-menu">
              <a href="{{ route('master.hari-libur') }}" wire:navigate class="dropdown-item">Hari Libur & Cuti Bersama</a>
          </div>
      </div>
  @endif
  ```
- **Nav mobile** (`resources/views/components/pwa-bottom-nav.blade.php`, sheet "Menu") — 1 item grid baru, gated sama, ditaruh dekat item Keuangan yang sudah ada.

### 6. Seeder & permission generation (dijalankan manual, bukan auto — DB koneksi saat ini production, lihat Known Gaps README)
Setelah migration jalan:
- `php artisan migrate` (tabel `m_holidays`).
- `php artisan db:seed --class=CustomPermissionsSeeder` (baris permission `sistem_master`).
- `php artisan shield:generate --panel=admin --all --option=permissions` (biar `HolidayResource` baru dapat entri permission Shield, muncul di `/admin/roles`).

Semua idempotent, aman dijalankan ulang — tapi **konfirmasi ke user dulu sebelum dieksekusi ke DB production**, sesuai catatan `planning/next/2026-07-14-rekap-sesi-dan-lanjutan.md`.

## Yang SENGAJA tidak dikerjakan sekarang
- **Presensi otomatis** (skip requirement presensi di tanggal `m_holidays`) — disebut user sebagai rencana lanjutan, tapi presensi clock-in/out sendiri belum terintegrasi ke sistem ini (lihat komentar `#absen-section` di `home-cards.blade.php`: "Belum terintegrasi otomatis"). Tabel `m_holidays` sudah didesain siap dipakai nanti (butuh tanggal + flag), tapi wiring ke presensi menunggu presensi otomatis itu sendiri ada.
- **Halaman View/detail terpisah** — resource ini modal-only (Create/Edit/Delete di tempat), konsisten pola `SalaryComponentResource`.
- **Import bulk kalender tahunan** (mis. upload API/CSV SKB 3 Menteri) — bisa ditambah belakangan kalau input manual per tanggal dirasa lambat; untuk sekarang cukup CRUD manual.

## Verifikasi
- `php artisan migrate` jalan bersih (dev dulu, baru production setelah dikonfirmasi).
- `/admin/holidays` — CRUD jalan, filter company, cuma company match/global yang tampil.
- Isi 1 baris `is_collective_leave=true` di tanggal dalam tahun berjalan → cek `LeaveBalanceCard` (`Sisa Cuti Tahunan` di dashboard) berkurang sesuai jumlah hari cuti bersama, tapi tipe cuti lain (izin, dll — kalau ada leave type lain) **tidak** ikut berkurang.
- Login user tanpa permission `sistem_master` → dropdown "Master" tidak muncul di nav, akses langsung `/master/hari-libur` → 403.
- Assign `sistem_master` ke 1 role test → dropdown muncul, halaman portal bisa CRUD sama seperti `/admin/holidays`.
- Cek `/admin/roles` → `HolidayResource` (atau nama Shield-nya, mis. `Holidays`) muncul di `RoleForm.php` setelah `shield:generate`.

## Referensi
- Diskusi sebelumnya (naming & rumus perhitungan) — lihat riwayat percakapan sesi ini, belum ada dokumen `knowledge/` terpisah untuk topik ini.
- `app/Filament/Resources/SalaryComponents/` — pola Resource modal-CRUD terbaru yang dicontek.
- `app/Filament/Resources/Master/LeaaveTypes/` — pola master data attendance yang jadi acuan penamaan & nav group.
- `app/Livewire/Keuangan/SalaryComponentsPage.php` + `planning/2026-07-12-portal-menu-keuangan-payroll.md` — pola reuse Table/Form Filament di portal, dicontek persis untuk dropdown "Master".
- `app/Repositories/Attendance/LeaveReqRepository.php`, `app/Livewire/LeaveBalanceCard.php` — titik perhitungan saldo cuti yang diubah.
