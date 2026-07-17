# Planning: Batas Akumulasi Cuti Tahunan per Bulan (2026-07-17)

Status: **dieksekusi penuh (kode + test), migrasi jalan bersih**. Setting `cuti_tahunan_max_per_bulan` sudah di-seed (global default `0` = tidak dibatasi, override Holding `5`). **BELUM diverifikasi manual di browser**, **BELUM di-commit**, **BELUM di-deploy**.

## 1. Latar belakang

Requirement lama yang belum pernah dieksekusi: *"Setiap bulan perusahaan Holding hanya bisa mengajukan cuti selama 5 hari"* (`analisis-payroll-5` §6, ditandai Q11 — 3 pertanyaan terbuka: batas per-pengajuan vs akumulasi, scope per-user, titik enforcement). Sesi ini menjawab semuanya + 1 pertanyaan tambahan (identifikasi "cuti tahunan").

## 2. Keputusan (dikonfirmasi user)

1. **Akumulasi per bulan kalender** — total semua pengajuan Cuti Tahunan user itu dalam 1 bulan (bukan cuma durasi 1 pengajuan).
2. **Cuma Cuti Tahunan** — jenis cuti lain (cuti besar, melahirkan, dst) tidak kena batas ini.
3. **Setting company-scoped, module `attendance`** (bukan `payroll`) — `cuti_tahunan_max_per_bulan`, dicek di `AttendaceReqService::storeLeaveRequest()` sebelum request dibuat (1 titik, berlaku ke semua caller — Livewire `SubmitLeaveRequest` maupun API kalau ada).
4. **Identifikasi "Cuti Tahunan"**: tambah kolom `m_leave_types.category` (nullable string) + `App\Enum\Attendance\LeaveTypeCategoryEnum` (1 case: `TAHUNAN`) — BUKAN cocok nama teks. Ini beda dari `LeaveReqRepository::getLeaveUserBalance()` yang sudah ada, yang MASIH pakai `str_contains(mb_strtolower($leaveType->name), 'tahunan')` (fragile, sudah ada dari sebelumnya) — sengaja **tidak diubah** sekarang (di luar scope), dicatat sebagai inkonsistensi yang mungkin perlu diberesin nanti (idealnya `getLeaveUserBalance()` juga pindah ke `category` biar konsisten 1 sumber kebenaran).

## 3. Perubahan
- Migration `2026_07_17_120000_add_category_to_m_leave_types_table.php` — kolom `category` nullable, data existing TIDAK diklasifikasi otomatis (admin isi manual).
- `App\Enum\Attendance\LeaveTypeCategoryEnum` (baru).
- `LeaveTypes::$fillable` tambah `category`.
- `LeaaveTypeForm`/`LeaaveTypesTable` (Filament, `/admin` — typo "Leaave" di nama folder sudah ada dari sebelumnya, tidak diubah) — field Select "Kategori" + kolom tabel.
- `PayrollSettingsSeeder` — key `cuti_tahunan_max_per_bulan` (module attendance, default `0`) + override Holding (`5`), pola sama `keterlambatan_grace_count`.
- `AttendaceReqService::storeLeaveRequest()` — panggil `assertWithinCutiTahunanMonthlyLimit()` (private) SEBELUM `storeAttendanceRequest()`, supaya gagal cepat tanpa nulis data parsial. Hitung hari existing lewat subquery `attendance_requests` (exclude status `rejected`/`cancelled`/`failed`) + `attendance_req_leave_details.total_days`, dibandingkan ke hari pengajuan baru (`diffInDays + 1`, sama logic `LeaveReqRepository::storeLeaveRequest()`).
- Gagal validasi → `\Exception` dengan pesan jelas (jumlah sudah dipakai/batas/pengajuan ini) — ditangkap `SubmitLeaveRequest::submit()`'s try/catch yang sudah ada, tampil sebagai `Notification::danger()` (root cause notifikasi sudah dibenerin sesi sebelumnya, jadi ini otomatis muncul).

## 4. Test baru
`tests/Feature/CutiTahunanMonthlyLimitTest.php` — 5 test: dalam batas lolos, 1 pengajuan yang sendirian sudah melebihi batas ditolak, akumulasi 2 pengajuan terpisah ditolak di pengajuan ke-2, jenis cuti bukan "tahunan" tidak kena batas sama sekali, company tanpa setting (default 0) = tidak dibatasi. Semua lulus, plus regresi 24 test attendance/leave lain (existing) tetap hijau.

## 5. Yang TIDAK termasuk sesi ini
- `getLeaveUserBalance()` (saldo tahunan) masih pakai cocok-nama, belum disatukan ke `category` — inkonsistensi yang dicatat, bukan bug baru.
- Tidak ada UI untuk isi setting `cuti_tahunan_max_per_bulan` per company selain lewat `settings` table langsung (tidak ada halaman "Pengaturan Absensi") — sama nasibnya dengan `izin_max_per_periode` yang juga belum ada UI.
- Edge case pengajuan yang membentang 2 bulan (mis. 29 Juli–3 Agustus) — dihitung berdasar bulan `start_date` saja, belum ada logic pro-rata per bulan. Disederhanakan sengaja, belum ada laporan kasus nyata yang butuh ini.
- Verifikasi manual di browser, commit, deploy.

## 6. Referensi
- `knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md` §6 (Q11) — sumber requirement.
- `app/Repositories/Attendance/LeaveReqRepository.php:44` — cocok-nama lama yang jadi pembanding kenapa `category` dipilih untuk aturan baru ini.
