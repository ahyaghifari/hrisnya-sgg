# Planning: Statistik Karyawan (per-karyawan, bukan agregat tim) — 2026-07-05

Status: **analisis & rencana selesai (v2, sudah direvisi sesuai jawaban user), belum ada kode dieksekusi.**

## Perubahan dari draft v1

Awalnya direncanakan sebagai 1 halaman agregat tim (`/karyawanku/statistik`) dengan tabel breakdown semua bawahan + filter drill-down ke 1 orang. **Setelah klarifikasi user, bentuknya diganti**: bukan halaman agregat, tapi **halaman detail PER-KARYAWAN**, diakses dari baris di tabel Karyawanku (mirip pola tombol "Edit" yang sudah ada) — jadi "drill down"-nya terjadi lewat **navigasi** (klik 1 karyawan), bukan lewat filter dropdown di halaman agregat.

## Route

**`/karyawanku/{record}`** — pola sama persis dengan `/karyawanku/{record}/edit` (`EditMyEmployee.php`), tanpa kata "statistik" di URL. Nama route: `my-employees.show`.

Guard akses: sama seperti `EditMyEmployee.php` — `$record` harus bawahan (rekursif via `OrgHierarchyService`) dari user yang login, ATAU user itu `canBrowseAllCompanies()` (Direktur holding).

**Entry point**: tambah action baru di tabel `/karyawanku` (`MyEmployees.php`, reuse `EmployeesTable`), disandingkan dengan tombol Edit yang sudah ada — misal ikon "lihat statistik"/"chart", `url()` ke route ini.

## Cakupan (final, sesuai jawaban user)

1. **Filter status**: dropdown ada (Semua/Submitted/Sedang Direview/Disetujui/Ditolak/Dibatalkan), **default: Disetujui (approved)**.
2. **Saldo cuti masuk v1** — tampilkan sisa kuota cuti karyawan tsb.
3. **Filter periode: date range bebas** (dari tanggal — sampai tanggal), bukan dropdown bulan/tahun.
4. **Drill-down** = navigasi per-karyawan (lihat perubahan route di atas), bukan filter di halaman agregat.
5. **Export: Excel dulu** (PDF menyusul kalau dibutuhkan nanti).

## Desain Halaman

Header: nama & jabatan karyawan yang dilihat (ambil dari `$record`, sama pola seperti header di `EditMyEmployee`/`AttendanceRequestDetail`).

**Filter bar**: Tanggal Dari — Tanggal Sampai (2 date picker), Status (default Disetujui), tombol "Export Excel".

**Stat card** (pola `AttendanceStatCards`, angka dihitung utk karyawan ini, sesuai filter aktif):
- Total Cuti
- Total Izin
- Total Lembur

**Saldo Cuti** — bagian terpisah (bukan bagian dari 3 card di atas, karena saldo itu kondisi SEKARANG, tidak tunduk ke filter tanggal/status). Reuse komponen `LeaveBalanceCard` yang sudah ada di Dashboard (nampilin per jenis cuti, misal Tahunan & Sakit) — **tapi komponen itu sekarang hardcode `auth()->user()`/`auth()->id()`**, perlu diadaptasi minimal: tambah `?string $userId = null` (default null → fallback `auth()->id()`, backward compatible buat pemakaian di Dashboard yang sudah ada), supaya bisa dipanggil dengan `:user-id="$record->id"` di halaman ini.

**Tabel riwayat** di bawah stat card: daftar pengajuan karyawan ini sesuai filter (Tanggal Pengajuan, Jenis, Keterangan, Status) — reuse pola kolom yang barusan dibangun di `MyAttendanceRequests` (Jenis badge + Keterangan dari `leaveDetails/permissionDetails/overtimeDetails`), tapi query-nya scoped ke 1 `user_id` (karyawan ini), bukan ke banyak user.

**Export Excel**: ikut pola `TrainingReportExport` (`FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle`) — isinya tabel riwayat di atas (sesuai filter aktif saat tombol diklik), dipicu dari method Livewire yang return `Excel::download(new AttendanceHistoryExport(...), $fileName)`.

## Yang perlu dibangun (ringkasan teknis)

1. Route `/karyawanku/{record}` (`my-employees.show`) + Livewire component baru (misal `EmployeeAttendanceStatistics`).
2. Guard akses — reuse logic `canManage()` yang sudah ada di `EditMyEmployee.php` (copy pola, jangan tulis ulang).
3. Query riwayat pengajuan 1 karyawan dengan filter tanggal (date range) + status — bisa reuse `AttendanceReqRepository::getListAttendanceRequestsByUserId()` yang **sudah single-user** (pas banget, tidak perlu bikin method baru seperti draft v1 — ini jadi lebih sederhana dari rencana awal), tinggal tambah filter tanggal (`whereBetween`) kalau belum ada.
4. Adaptasi `LeaveBalanceCard.php` — tambah param opsional `$userId`.
5. `AttendanceHistoryExport` (baru, `app/Exports/Attendance/`) — ikut pola `TrainingReportExport`.
6. Tambah action "Lihat Statistik" di tabel Karyawanku.

## Status

Rencana final, siap dieksekusi kapan saja — tunggu konfirmasi mulai implementasi.
