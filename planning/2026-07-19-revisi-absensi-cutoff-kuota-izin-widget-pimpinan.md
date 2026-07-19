# Planning: Revisi Absensi — Cutoff Periode, Kuota Izin/Cuti, Widget Pimpinan (2026-07-19)

Status: **rencana, belum dieksekusi.** Sumber requirement: screenshot revisi fitur Absensi dari user + diskusi klarifikasi sesi ini. Beberapa poin di screenshot ternyata sudah ada di kode (dicatat di §9), fokus dokumen ini di bagian yang belum ada / perlu direfactor.

## 1. Latar Belakang

Screenshot revisi berisi 10 poin campuran: aturan bisnis baru (cutoff 21-20, gabung limit izin), perbaikan bug lama (saldo cuti harus berkurang saat approve, bukan saat submit — bug reversal sudah diketahui sebelumnya), dan fitur UI baru (widget kuota untuk pimpinan). Setelah dicek satu-satu ke kode, beberapa infrastruktur sudah ada tapi belum tersambung (`izin_max_per_periode` sudah di-seed tapi tidak pernah dibaca; `cuti_tahunan_max_per_bulan` sudah jalan tapi belum di-commit) — lihat `planning/2026-07-17-batas-cuti-tahunan-per-bulan.md`.

## 2. Ringkasan Keputusan (per poin screenshot)

| Poin screenshot | Keputusan |
|---|---|
| Cutoff tanggal 21, periode 21-20 | Setting baru company-scoped `attendance_period_cutoff_day` (§3) |
| Izin seluruh jenis maksimal 2 hari | Pakai `izin_max_per_periode` yang sudah di-seed, module `attendance` sudah benar, tinggal disambungkan enforcement-nya (§4) |
| Meninggalkan jam kerja/pulang cepat & datang terlambat 2 hari | **Digabung** ke kuota `izin_max_per_periode` yang sama (bukan kuota terpisah) — dikonfirmasi user |
| Izin sakit tidak masuk pengajuan | Dihapus total dari sistem (bukan cuma disembunyikan) — dikonfirmasi user (§7) |
| Cuti maksimal 5 hari | Sudah ada (`cuti_tahunan_max_per_bulan`), diformalisasi jadi enum (§5), periode ikut cutoff baru |
| Setiap di-approve otomatis berkurang jatahnya | Refactor dari counter `used` yang di-mutasi manual → dihitung otomatis dari request `approved` (§6) — sekaligus menutup bug reversal lama |
| Libur cuti bersama ditetapkan di awal tahun | Tidak ada perubahan — mekanisme `Holidays`/`is_collective_leave` yang ada sudah cukup, di luar scope dokumen ini |
| Kuota sisa cuti = jumlah pengurangan | Terjawab implisit lewat refactor §6 (breakdown quota/cuti bersama/used/sisa) |
| Widget kuota karyawan untuk pimpinan | **Bukan widget dashboard** — info kuota pemohon ditampilkan di halaman detail approval (portal & admin), §8 |
| Format hover breakdown di home cards | **Sudah ada**, tidak perlu dikerjakan (§10) |
| Halaman Pengaturan Absensi, grup nav "Presensi & Absensi" | Baru, ikut grup+permission `SISTEM_ABSENSI` yang sudah dirintis workstream Jadwal (§9.1) |
| Halaman kelola Hari Libur/Cuti Bersama di grup Presensi | **Sudah ada** (grup lain) — direlokasi ke grup Presensi, bukan dibangun ulang (§9.2) |

## 3. Setting Baru: Cutoff Periode Presensi

Setting `attendance_period_cutoff_day` (module `attendance`, company-scoped lewat `Setting::resolve()`, integer, default `1`).

- Default `1` = periode ikut kalender biasa (tanggal 1 s/d akhir bulan) — perilaku existing tidak berubah untuk company yang tidak di-override.
- Kalau di-override, misal `21`: periode berjalan tanggal 21 bulan berjalan s/d tanggal 20 bulan berikutnya.
- **1 angka ini dipakai bareng oleh semua limit "per bulan"** (`cuti_tahunan_max_per_bulan`, `izin_max_per_periode`) — tidak perlu setting terpisah untuk "periode awal"/"periode akhir", cukup 1 helper resolver:

```php
// pseudocode, nama final ditentukan saat implementasi
function resolveAttendancePeriod(Carbon $anchorDate, int $cutoffDay): array
{
    if ($cutoffDay <= 1) {
        return [$anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()];
    }
    $periodStart = $anchorDate->day >= $cutoffDay
        ? $anchorDate->copy()->day($cutoffDay)
        : $anchorDate->copy()->subMonthNoOverflow()->day($cutoffDay);
    $periodEnd = $periodStart->copy()->addMonthNoOverflow()->subDay();
    return [$periodStart, $periodEnd];
}
```

Dipakai untuk ganti `startOfMonth()/endOfMonth()` di `AttendaceReqService::assertWithinCutiTahunanMonthlyLimit()` (baris 215) dan enforcement baru `izin_max_per_periode` (§4).

**Pertanyaan terbuka (perlu dicek saat implementasi, bukan blocker desain)**: pengajuan yang membentang lintas 2 periode (mis. cutoff 21, pengajuan 19-23) — sama seperti catatan di `planning/2026-07-17-batas-cuti-tahunan-per-bulan.md` §5, dihitung berdasar bulan `start_date` saja dulu (sederhana), pro-rata belum digarap kecuali ada laporan kasus nyata.

## 4. Kuota Izin Gabungan (`izin_max_per_periode`)

Setting sudah ada di `PayrollSettingsSeeder.php:37` (key `izin_max_per_periode`, module `attendance`, default `2`) tapi **tidak pernah dibaca di kode manapun** — perlu dibangun fungsi enforcement setara `assertWithinCutiTahunanMonthlyLimit()`, dipanggil dari titik yang sama (`AttendaceReqService`) tapi untuk `storePermissionRequest()` (izin), bukan `storeLeaveRequest()` (cuti).

- **Kategori yang ikut dihitung dalam 1 kuota gabungan** (dikonfirmasi user): **semua 4 case** `PermissionTypeCategoryEnum` — `IZIN_TERLAMBAT_MASUK`, `IZIN_PULANG_CEPAT`, `IZIN_SEHARIAN`, `IZIN_MENINGGALKAN_JAM_KERJA` — masuk 1 kuota gabungan yang sama (2 hari/periode), bukan dipecah per kategori.
- Periode pakai cutoff dari §3, bukan kalender tetap.
- Pola pesan error & titik enforcement (fail-fast sebelum `storeAttendanceRequest()`) sama persis seperti Cuti Tahunan.

## 5. Formalisasi Setting jadi Enum

Ikuti pola `App\Enum\Payroll\GlobalPayrollSettingEnum` (key sebagai enum case + `label()` + `type()`), bikin versi attendance:

```php
enum AttendanceSettingEnum: string
{
    case CUTI_TAHUNAN_MAX_PER_BULAN = 'cuti_tahunan_max_per_bulan';
    case IZIN_MAX_PER_PERIODE = 'izin_max_per_periode';
    case PERIOD_CUTOFF_DAY = 'attendance_period_cutoff_day';

    public function label(): string { ... }
    public function type(): SettingTypeEnum { ... }
}
```

Semua pemanggilan `Setting::resolve('cuti_tahunan_max_per_bulan', ...)` (string mentah) diganti `Setting::resolve(AttendanceSettingEnum::CUTI_TAHUNAN_MAX_PER_BULAN->value, ...)` — konsisten dengan pola payroll, dan seeder-nya idealnya pindah dari `PayrollSettingsSeeder` (nama menyesatkan untuk data attendance) ke `AttendanceSettingsSeeder` sendiri. Kosmetik tapi rapi, dikerjakan bareng saat menyentuh area ini.

## 6. Refactor "Sisa Jatah Cuti" — dari Counter Manual ke Hitung Otomatis

**Masalah sekarang** (`LeaveReqRepository.php`): `LeaveUserBalances.used` ditambah manual saat request **dibuat** (`storeLeaveRequest()` → `updateCreateLeaveUserBalance()`, status masih `in_review`), bukan saat **disetujui**. Tidak ada kode yang mengurangi lagi kalau request ditolak/dibatalkan — bug lama yang sudah diketahui, sekarang jadi prioritas untuk ditutup karena bertentangan langsung dengan requirement screenshot ("setiap di-approve otomatis berkurang").

**Desain baru**: hitung "used"/"sisa" secara live dari data `approved`, bukan kolom termutasi — pola yang sudah dipakai `assertWithinCutiTahunanMonthlyLimit()` (subquery ke `attendance_requests`, filter status). Tidak ada apa pun yang perlu "dikembalikan" saat reject/cancel karena tidak ada yang pernah ditulis untuk request yang belum `approved`.

```
sisa = quota(dari LeaveTypes/setting) - SUM(total_days WHERE status='approved', dalam periode) - cuti_bersama
```

`LeaveReqRepository::getLeaveUserBalance()` diganti query langsung (analog `assertWithinCutiTahunanMonthlyLimit`), bukan baca `LeaveUserBalances.used`.

**Yang perlu dicek sebelum eksekusi** (ditemukan lewat grep, belum dibaca detail):
- `app/Filament/Resources/Employees/Schemas/EmployeeForm.php` — pakai `LeaveUserBalances`, kemungkinan form set kuota awal karyawan baru. Perlu dipastikan alurnya tetap jalan (kuota tetap dari `LeaveTypes.quota_days`, cuma "used"-nya yang berubah cara hitung).
- `app/Console/Commands/ResetLeaveUserBalance.php` — command reset saldo tahunan, kemungkinan besar jadi tidak relevan lagi (tidak ada `used` yang perlu di-reset kalau dihitung live) atau perlu disesuaikan.
- Apakah kolom `LeaveUserBalances.used`/`quota` dihapus total atau dibiarkan sebagai cache tidak terpakai — keputusan diambil saat implementasi setelah 2 poin di atas diverifikasi.

## 7. Izin Sakit Dihapus dari Sistem

Dikonfirmasi user: bukan cuma disembunyikan, memang tidak ada lagi.
- `database/seeders/AttendanceRequestTypeSeeder.php:19` — hapus `'Cuti Sakit'` dari array `$leaveTypes`.
- Cek `app/Filament/Resources/Master/LeaaveTypes/*` — pastikan tidak ada hardcode lain yang mengasumsikan "Sakit" ada.
- Data lama (`m_leave_types` baris "Cuti Sakit" yang sudah ada di production/lokal, plus riwayat pengajuan yang sudah pakai jenis ini) **tidak dihapus paksa** — cukup dicegah dipilih lagi untuk pengajuan baru (soft-remove dari pilihan, bukan drop data historis). Detail mekanismenya (soft delete row `m_leave_types` vs flag nonaktif) ditentukan saat implementasi.

## 8. Info Kuota Pemohon di Halaman Approval (bukan widget dashboard)

**Koreksi kedua (dikonfirmasi user)**: bukan widget ringkasan di `/dashboard` — **kuota pemohon ditampilkan langsung di halaman detail pengajuan/approval**, supaya approver bisa lihat sisa jatah cuti/izin si pemohon **saat itu juga**, pas mau memutuskan Approve/Reject. Jadi ini info kontekstual per-pengajuan, bukan daftar ringkasan terpisah.

Juga koreksi dari draf sebelumnya soal siapa "pimpinan": tetap **siapapun yang berperan sebagai approver** (bukan posisi struktural org-hierarchy — lihat `ApprovalPermissionService::checkUserPermission()`, approver ditentukan oleh konfigurasi `approval_flows`: `user`/`role`/`job_position`), tapi karena info kuota sekarang tampil per-halaman-detail (bukan daftar semua "bawahan"), **gap query "siapa saja semua orang yang diampu user X sebagai approver" dari draf sebelumnya jadi tidak relevan lagi** — tidak perlu dibangun, karena setiap kali approver buka 1 pengajuan, pemohonnya sudah diketahui dari record itu sendiri (`request->user`), tinggal panggil `getLeaveUserBalance()`/enforcement izin (hasil refactor §6/§4) untuk user itu.

- **Portal**: `app/Livewire/AttendanceRequestDetail.php` + view detail pengajuan (`/pengajuan-absensi/{id}`, README §"Halaman yang sudah berjalan") — tambah blok kuota pemohon (pakai kembali data/format yang sama seperti popover Detail di `leave-balance-card.blade.php`, §10) di dekat tombol Approve/Reject, khusus untuk tipe pengajuan Cuti (`cuti_tahunan_max_per_bulan`) dan Izin (`izin_max_per_periode`) — tidak relevan untuk Lembur.
- **Admin Filament**: halaman Approve/Reject di `AttendanceRequestResource` (Filament, tetap di grup Manajemen Organisasi — lihat §9 catatan) — perlu infoblock/section serupa kalau approve juga bisa dilakukan dari `/admin`, supaya konsisten antara 2 tempat approve yang sudah ada (README: "Approve/Reject/Batalkan tersedia di dua tempat, admin & portal, reuse service yang sama").
- Isi blok: sisa kuota + breakdown (Kuota/Cuti Bersama/Digunakan/Sisa untuk Cuti; Kuota/Digunakan/Sisa untuk Izin gabungan §4) — dan idealnya highlight kalau pengajuan yang sedang di-approve ini bakal membuat pemohon **melebihi** kuotanya (meski sudah dicegah saat submit oleh §4/§6, approver tetap perlu tahu konteksnya, terutama untuk kasus data lama yang submit sebelum enforcement ini ada).

## 9. Navigasi Baru: Grup "Presensi & Absensi" + 2 Halaman

Ditambahkan belakangan oleh user (di luar screenshot awal). Grup nav **"Presensi & Absensi" ternyata sudah mulai dibentuk** oleh workstream lain yang sedang berjalan (restrukturisasi Jadwal, lihat `planning/2026-07-18-restrukturisasi-jadwal-schedules.md`) — sudah ada namespace `App\Livewire\Presensi\*` (`SchedulesPage.php`, `CreateSchedulePage.php`), permission `CustomPermissionEnum::SISTEM_ABSENSI`, dan route prefix `presensi.*` (`presensi.jadwal`, `presensi.jadwal.tambah` di `config/portal-pages.php`). **2 halaman baru di bawah ini harus ikut pola yang sudah ada ini, bukan bikin grup/permission baru** — supaya "Presensi & Absensi" jadi 1 grup nav yang konsisten isinya (Jadwal + Pengaturan + Hari Libur), bukan tersebar.

### 9.1 Halaman "Pengaturan Absensi" — baru total

Belum ada UI sama sekali untuk mengisi setting attendance (`AttendanceSettingEnum` dari §5: cutoff, `cuti_tahunan_max_per_bulan`, `izin_max_per_periode`) selain langsung ke tabel `settings` — dicatat juga sebagai gap di `planning/2026-07-17-batas-cuti-tahunan-per-bulan.md` §5.

- Namespace `App\Livewire\Presensi\AttendanceSettingsPage` (atau nama serupa), route `presensi.pengaturan`, permission `SISTEM_ABSENSI` (pola sama persis `SchedulesPage::mount()`).
- **Reuse pola layout `SalaryComponentsPage`** (`app/Livewire/Keuangan/SalaryComponentsPage.php`) — kiri daftar company, kanan form setting company-scoped, karena semua `AttendanceSettingEnum` di sini company-scoped (tidak ada yang "selalu global" seperti `GlobalPayrollSettingEnum` di payroll). Tidak perlu bikin pola baru, cukup contek strukturnya.
- Field: cutoff hari (angka 1-28), Batas Cuti Tahunan per Bulan (angka), Batas Izin per Bulan (angka) — 1 tombol simpan per company, sama seperti pola Komponen Gaji.

### 9.2 Halaman "Hari Libur & Cuti Bersama" — SUDAH ADA, perlu DIPINDAH bukan dibuat ulang

Fitur ini **sudah dibangun penuh** dari sesi sebelumnya (`planning/2026-07-16-master-hari-libur-cuti-bersama.md`):
- Filament admin: `app/Filament/Resources/Master/Holidays/HolidayResource.php` — `navigationGroup = 'Master'`.
- Portal: `app/Livewire/Master/HolidaysPage.php`, route `master.hari-libur` (`config/portal-pages.php:159`), permission `SISTEM_MASTER`.

Karena user minta ditaruh di grup Presensi, ini **relokasi**, bukan build baru — semua dikonfirmasi user:
- Portal: pindah namespace `App\Livewire\Master\HolidaysPage` → `App\Livewire\Presensi\HolidaysPage`, route `master.hari-libur` → `presensi.hari-libur`, permission **diganti** `SISTEM_MASTER` → `SISTEM_ABSENSI` (konsisten dengan grup barunya — dikonfirmasi, hak akses memang berubah, bukan cuma pindah tampilan).
- Filament admin: `HolidayResource::$navigationGroup` diganti dari `'Master'` ke `'Presensi & Absensi'`.
- Table/Form class (`HolidayForm`, `HolidaysTable`) **tidak perlu diubah** — sudah reusable, cuma titik registrasinya yang pindah.

**Sudah dikonfirmasi**: `AttendanceRequestResource`/`AttendanceRecordResource` **TIDAK ikut dipindah** — tetap di grup `'Manajemen Organisasi'` seperti sekarang. Grup "Presensi & Absensi" isinya cuma Jadwal (§9, existing) + Pengaturan Absensi (§9.1) + Hari Libur (§9.2, relokasi).

### 9.3 Halaman "Hari Libur & Cuti Bersama" versi lihat-saja untuk karyawan biasa — baru total

Ditambahkan user belakangan: halaman §9.2 (`master.hari-libur`/relokasinya) adalah **halaman kelola** (CRUD Table Filament, permission `SISTEM_MASTER`/`SISTEM_ABSENSI` — cuma HR/admin). Karyawan biasa **tidak punya akses ke situ**, padahal butuh tahu tanggal cuti bersama tahun berjalan (relevan langsung ke saldo cuti mereka, lihat breakdown "Cuti Bersama" di hover §10). Jadi ini **halaman terpisah, baru dibangun, read-only**, bukan variasi dari §9.2:

- Route baru, mis. `presensi.kalender-libur` — didaftarkan di `config/portal-pages.php` dengan `'permission' => null, 'guard' => null` (pola sama halaman self-service lain seperti `my-attendance-requests`, siapa saja yang login boleh akses, tidak perlu permission Shield khusus).
- Livewire baru `App\Livewire\Presensi\HolidayCalendarPage` (read-only) — **bukan reuse `HolidaysTable`/`HolidayForm`** Filament (itu untuk CRUD admin) — cukup query `Holidays` model langsung (pola scoping sama seperti dipakai `LeaveReqRepository::getLeaveUserBalance()`: `whereNull('company_id')->orWhere('company_id', $companyUser)`), tampil sebagai list/card per bulan (pola `.pwa-list`/`.pwa-list-row` yang sudah dipakai konsisten di portal, lihat `DESIGN.md`), tandai baris yang `is_collective_leave=true` beda warna/badge dari libur nasional biasa.
- **Belum ditaruh link masuknya di nav manapun untuk karyawan** (dikonfirmasi user) — halaman ini dibangun & bisa diakses lewat URL langsung dulu, belum ada entry point di `/dashboard` atau menu bottom-sheet. Penempatan link menyusul, di luar scope sesi ini.

## 10. Hover Breakdown — SUDAH ADA, Tidak Perlu Dikerjakan

`resources/views/livewire/leave-balance-card.blade.php:56-87` — popover "Detail" (hover/klik kartu) sudah menampilkan persis format yang diminta: Kuota, Cuti Bersama (−), Digunakan (−), Sisa. Dipasang di home cards (`partials/home-cards.blade.php:57-81`) lewat `<livewire:leave-balance-card>`. Satu-satunya yang perlu dijaga: setelah refactor §6, pastikan `$balance['used']` yang dikirim ke view ini tetap terisi benar (nilai live, bukan lagi dari kolom `used` lama) — komponennya sendiri tidak perlu diubah.

## 11. Urutan Eksekusi yang Disarankan

1. Setting `attendance_period_cutoff_day` + `AttendanceSettingEnum` (§3, §5) — fondasi, dipakai 2 limit di bawah.
2. Refactor `getLeaveUserBalance()` jadi hitung live (§6) — paling berisiko (data cuti), kerjakan sendirian dulu + test regresi sebelum ditumpuk perubahan lain.
3. Enforcement `izin_max_per_periode` (§4) — pola sudah terbukti dari Cuti Tahunan, tinggal replikasi.
4. Hapus Izin Sakit (§7) — perubahan kecil, independen dari 3 poin di atas.
5. Halaman "Pengaturan Absensi" (§9.1) — baru bisa dibangun setelah §1/§3/§5 (enum settingnya) beres, karena halaman ini cuma UI di atas setting yang sudah ada.
6. Relokasi halaman Hari Libur ke grup Presensi (§9.2) — independen, bisa duluan atau belakangan, tunggu konfirmasi soal perubahan permission dulu.
7. Info kuota pemohon di halaman approval (§8) — baru bisa akurat setelah §6 (hitung live) dan §4 (enforcement izin) selesai, karena isinya hasil dari 2 poin itu.

## 12. Referensi
- `planning/2026-07-17-batas-cuti-tahunan-per-bulan.md` — implementasi `cuti_tahunan_max_per_bulan` yang sudah ada, jadi rujukan pola untuk §3/§4.
- `planning/2026-07-18-restrukturisasi-jadwal-schedules.md` — asal grup nav "Presensi & Absensi", namespace `App\Livewire\Presensi\*`, permission `SISTEM_ABSENSI` yang dipakai di §9.
- `planning/2026-07-16-master-hari-libur-cuti-bersama.md` — spek awal halaman Hari Libur yang direlokasi di §9.2.
- `knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md` §6 — asal-usul requirement "Holding max 5 hari cuti/bulan".
- `app/Repositories/Attendance/LeaveReqRepository.php`, `app/Services/Attendance/AttendaceReqService.php` — kode yang kena dampak langsung §4/§6.
- `app/Enum/Payroll/GlobalPayrollSettingEnum.php`, `app/Livewire/Keuangan/SalaryComponentsPage.php` — pola enum setting & layout halaman yang dicontek di §5/§9.1.
- `resources/views/livewire/leave-balance-card.blade.php` — hover yang sudah ada (§10), referensi format breakdown untuk blok kuota di halaman approval §8.
- `app/Livewire/AttendanceRequestDetail.php` — halaman detail pengajuan portal, titik penyisipan blok kuota §8.
