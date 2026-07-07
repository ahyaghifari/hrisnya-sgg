# Perubahan: Halaman Detail Karyawanku, Akses Manager ke Detail Pengajuan, Design System (2026-07-05)

Lanjutan dari [`2026-07-05-portal-karyawan-dan-pengajuan-absensi.md`](2026-07-05-portal-karyawan-dan-pengajuan-absensi.md) di hari yang sama. Rencana awal: [`planning/2026-07-05-statistik-karyawanku.md`](../../planning/2026-07-05-statistik-karyawanku.md).

## Ringkasan

Karyawanku sekarang punya halaman detail per-karyawan (`/karyawanku/{id}`, **bukan** `/karyawanku/{id}/statistik` — sengaja tanpa kata "statistik" di URL, mengikuti pola `/karyawanku/{id}/edit`) yang menggabungkan profil lengkap, presensi harian, statistik pengajuan absensi, dan riwayat pengajuan — bukan cuma halaman statistik seperti rencana awal. Sekalian ada beberapa perbaikan lintas-halaman (akses manager ke detail pengajuan bawahan, widget approval di Karyawanku) dan satu putaran penuh perapian design system (kontras warna, identitas warna per kartu, `DESIGN.md`).

## 1. Halaman Detail Karyawanku (`EmployeeAttendanceStatistics`)

Route: `GET /karyawanku/{record}` → nama `my-employees.show`. Guard sama seperti `EditMyEmployee` (`canManage()`: bawahan rekursif via `OrgHierarchyService`, atau `canBrowseAllCompanies()`). Judul halaman dinamis: `"Karyawanku - {nama}"` (pakai `->layout()` di `render()`, bukan atribut `#[Layout]` statis, karena butuh nama record).

3 tab (plain Alpine `x-data`/`x-show`, tanpa Filament Wizard):

- **Profil** — kartu identitas (avatar inisial, nama, badge aktif/nonaktif, jabatan · departemen, meta NIP/email/perusahaan/role) di atas, lalu kartu-kartu terpisah per topik (Informasi Pribadi + Kontak berdampingan, lalu Alamat dan Informasi Pekerjaan full-width) — pola *field-row* (label kiri, value kanan, garis pemisah tipis) dipakai buat daftar field, bukan grid label-di-atas-value. Field kosong ditampilkan sebagai "Belum diisi" (italic, abu-abu terbaca) bukan tanda hubung telanjang.
- **Presensi & Absensi** — filter periode (dari/sampai tanggal + status pengajuan) + tombol export di atas, lalu:
  - **Presensi**: kartu "Presensi Hari Ini" (reuse gaya `.absen-card` dari Dashboard, tapi diisi data asli jam masuk/keluar/status dari `AttendanceRecords`, bukan placeholder statis) berdampingan dengan kartu "Indeks Presensi Periode Ini" (chip Hadir/Terlambat/Tidak Hadir/dst), lalu tabel "Data Presensi" (daftar harian).
  - **Absensi**: 3 stat card (Cuti/Izin/Lembur — jumlah pengajuan sesuai filter) + Saldo Cuti (reuse `<livewire:leave-balance-card>`, kini terima `userId` opsional).
- **Riwayat Pengajuan** — tabel semua pengajuan karyawan ini pada periode/status terpilih, dengan tombol **Detail** per baris (lihat §2 soal guard aksesnya).

File baru/berubah:
- `app/Livewire/EmployeeAttendanceStatistics.php` (baru)
- `resources/views/livewire/employee-attendance-statistics.blade.php` (baru)
- `app/Exports/Attendance/AttendanceHistoryExport.php` (baru) — export Excel riwayat, dipicu dari tombol Export di tab Presensi & Absensi.
- `app/Repositories/Attendance/AttendanceReqRepository.php` — `getListAttendanceRequestsByUserId()` ditambah filter `date_from`/`date_to`.
- `app/Livewire/LeaveBalanceCard.php` — terima `?string $userId` opsional (default `Auth::id()`), supaya bisa dipakai buat lihat saldo cuti orang lain.
- `app/Livewire/MyEmployees.php` — tombol aksi tabel "Lihat Statistik" diganti jadi **"Lihat"** saja, mengarah ke route baru ini.

## 2. Manager bisa lihat detail pengajuan bawahan (bukan cuma approver)

`AttendanceRequestDetail::canAccess()` diperluas — dulu cuma izinkan submitter ATAU (pernah/sedang) approver. Sekarang tambah kondisi: manager (lewat `OrgHierarchyService::subordinates()`, rekursif) atau `canBrowseAllCompanies()` juga boleh buka detail pengajuan bawahannya, **walau dia bukan bagian dari approval flow**. Ini yang membuat tombol Detail di tab Riwayat Pengajuan (Karyawanku) bisa dipakai HC Manager/Supervisor tanpa 403.

## 3. `/pengajuan-absensi` dikembalikan ke self-only

Sempat ditambah scoping rekursif ke bawahan (lihat entri sebelumnya) — **dibalik lagi** ke "riwayat milik sendiri saja" (tanpa kolom "Nama Karyawan"), karena sekarang lihat riwayat bawahan sudah difasilitasi lewat tab Riwayat Pengajuan di Karyawanku (§1). Menghindari 2 tempat berbeda menampilkan hal yang sama dengan scoping yang beda-beda.

## 4. Widget "Butuh Persetujuan Anda" juga muncul di Karyawanku

`<livewire:pending-approvals-card />` (sebelumnya cuma di Dashboard) sekarang juga tampil di atas tabel `/karyawanku`, supaya manager yang buka Karyawanku langsung lihat approval yang perlu ditindak tanpa harus balik ke Dashboard.

## 5. Design system — kontras warna & identitas kartu

Audit menyeluruh (dipicu laporan user: teks abu-abu menyatu dengan background, terutama tanggal di bawah judul halaman & jabatan di bawah nama). **Aturan baru**: `#94a3b8`/`text-slate-400` (kontras ~2.8:1, gagal WCAG AA) tidak boleh dipakai buat teks yang harus dibaca — minimal `#64748b`/`text-slate-500`. Diterapkan di ~15 file blade + `app.css` (termasuk `.data-table thead th`, yang otomatis memperbaiki header SEMUA tabel di aplikasi).

Sistem "identitas kartu" ditetapkan — tiap domain fitur pakai satu warna kartu yang konsisten dan dikenali di mana pun muncul (didokumentasikan di `DESIGN.md`):
- `.welcome-card` (navy) — sudah ada sebelumnya.
- `.absen-card` (hijau) — sudah ada sebelumnya.
- `.approval-card` (**oranye** — `#9a3412 → #ea580c → #fb923c`) — kartu "Butuh Persetujuan Anda".
- `.timeline-card` (**kuning solid, tanpa gradasi** — `#FCF0D6`, teks gelap `#422006`) — komponen `<x-approval-timeline>`, dipakai di Dashboard & Detail Pengajuan. Sempat beberapa iterasi (gradasi krem lembut → gradasi gelap ala welcome-card → oranye gelap → kuning gelap bergradasi → **akhirnya kuning solid tanpa gradasi + shape dekorasi**, sesuai preferensi eksplisit user).
- Node/langkah di timeline pakai pola "soft-UI ringan": permukaan putih polos + cincin border berwarna + ikon berwarna (bukan dot solid berwarna) — TANPA box-shadow (dihapus sesuai permintaan user, murni mengandalkan warna buat kontras).

Bug ikutan yang ditemukan & diperbaiki: tombol **Keluar** di nav (avatar dropdown & mobile menu) ikut ke-override jadi navy oleh rule CSS global `button[type="submit"] { background: #042c6c !important; }` (aslinya buat form Filament) — di-scope ulang supaya tombol logout tetap netral abu-abu.

## 6. `DESIGN.md` (baru)

Dokumen referensi desain di root project — palet warna, aturan kontras, katalog kartu-identitas, komponen (badge/tombol/tabel/form), konvensi layout, dan **aturan penting**: pakai inline `style="..."` ketimbang class utility Tailwind baru untuk apa pun di luar sekumpulan kecil class yang sudah terbukti aman (`flex`, `items-center`, dst) — karena class Tailwind baru (termasuk arbitrary value `text-[19px]`, `gap-x-*`, dst) butuh rebuild (`npm run watch`/`build`, dijalankan user sendiri) sebelum muncul, dan ini beberapa kali bikin halaman terlihat "berantakan" padahal kodenya benar.

## 7. Catatan proses

- User menegaskan: **jangan jalankan/usulkan `php artisan test` kecuali diminta eksplisit** — terutama saat iterasi visual/CSS cepat. Sudah disimpan ke memory lintas-sesi (`feedback_no_unsolicited_tests.md`).
- Beberapa kali styling terlihat tidak berubah di browser padahal kode sudah benar — root cause selalu sama: `public/build/assets/app-*.css` belum di-compile ulang. Cek timestamp file sebelum menyimpulkan ada bug kode.

## Status

Fitur & fix di atas sudah diimplementasikan. Test suite (44+ test, sqlite in-memory) terakhir hijau sebelum putaran perapian visual murni CSS di akhir sesi ini (yang sengaja tidak diverifikasi ulang lewat test sesuai instruksi user di §7) — disarankan jalankan `php artisan test` sekali lagi kalau mau extra yakin sebelum menganggap semuanya final, dan jangan lupa `npm run watch`/`build` supaya perubahan CSS termuat.
