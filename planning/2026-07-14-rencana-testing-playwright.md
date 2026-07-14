# Rencana Testing Playwright — Verifikasi Restorasi Fitur Filament (2026-07-14)

## Context

Setelah sesi restorasi besar (lihat [`knowledge/changes/2026-07-14-restorasi-fitur-filament-syifa-dan-buka-akses-panel.md`](../knowledge/changes/2026-07-14-restorasi-fitur-filament-syifa-dan-buka-akses-panel.md)), semua kode sudah jalan di production tapi **belum pernah diklik langsung di browser**. File ini daftar skenario yang akan dijalankan lewat Playwright MCP begitu sesi Claude Code di-restart dan tool-nya aktif.

## Prasyarat sebelum mulai

- [ ] URL base aplikasi (production, `npm run watch` sudah jalan di sisi user).
- [ ] Kredensial login **2 jenis user**: satu **Admin/Super Admin**, satu **karyawan biasa non-admin** (bukan Direktur, bukan holding — supaya semua gate `canBrowseAllCompanies()`/`hasGlobalRole()` teruji di kondisi normal). Kalau belum ada akun karyawan biasa yang permission-nya sudah di-assign, ini mungkin baru ketahuan pas testing (lihat item audit permission di rekap sesi).
- [ ] Konfirmasi ke user: boleh submit data sungguhan (misal bikin 1 pengajuan cuti/lembur test) ke production, atau harus pakai akun/data yang aman dihapus lagi setelahnya?

## Skenario Testing

### A. Login & akses dasar
1. Login sebagai Admin/Super Admin → masuk `/admin`, sidebar tampil.
2. Login sebagai karyawan biasa → **cek apakah sekarang juga bisa masuk `/admin`** (sesuai perubahan `canAccessPanel()`), dan apakah sidebar-nya masuk akal (tidak kosong total, tidak juga bocor ke resource sensitif kalau permission belum di-assign benar).
3. Login karyawan biasa ke portal Livewire (`/login` → `/dashboard`) — pastikan jalur lama ini masih utuh, tidak keganggu perubahan panel.

### B. Sidebar & Cluster (cek hasil restorasi cluster)
4. Sebagai Admin, cek sidebar: grup **"Karyawan"** muncul di posisi pertama.
5. Cek 6 cluster tampil sebagai sub-nav collapsible: Assessment, Employee Document, ManPower Planing, Master Salary Component, Shift Pattern, User Transitions — klik masing-masing, pastikan resource anggotanya kebuka tanpa 500 error.
6. Khusus **Master Salary Component** — ini yang paling banyak disesuaikan (4 resource lama digabung jadi `SalaryComponentResource`, `MasterDoctorServiceResource` di-skip). Pastikan tidak ada link mati/menu kosong di sini.

### C. Jadwal Shift & Tukar Shift (self-service karyawan)
7. Login karyawan biasa → buka `/admin/user-shift-schedules` — pastikan **cuma jadwal milik sendiri** yang muncul (bukan punya orang lain).
8. Cek `ShiftCalendarWidget` di header list tampil dan tidak error.
9. Buka `/admin/user-shift-swaps` → coba **create** pengajuan tukar shift (isi form: target schedule / off day, catatan) → submit → cek redirect ke index, muncul di list.
10. Buka detail salah satu swap request → coba **cancel** (kalau statusnya masih pending & punya sendiri) → cek status berubah.
11. **Uji negatif**: coba akses URL edit swap milik user lain langsung via ID (kalau tahu ID-nya) → harus ketolak (verifikasi fix `getEloquentQuery()` scoping yang ditambahkan sesi ini benar-benar bekerja).

### D. Widget Dashboard
12. Buka `/admin` (dashboard) → cek **Performance Chart** (statistik kinerja bulanan) dan **Notification Widget** (notifikasi izin/cuti/lembur) tampil tanpa error, datanya masuk akal.

### E. LMS Course Planning
13. Buka `/admin/l-m-s/lms-course-plans` → cek list tampil (harus include data lama kalau ada, karena tabelnya tidak pernah ke-drop).
14. Coba **create** rencana pelatihan baru → isi form → cek relation manager "Items" bisa nambah item pelatihan.
15. Buka `/admin/l-m-s/lms-course-plan-items` → cek tab "Pengajuan Saya" vs daftar semua (kalau approver) berfungsi, coba submit 1 pengajuan pelatihan (`saveAndSubmit`), cek approval flow jalan (kalau ada approver test account).

### F. Position-Competency
16. Buka `/admin/master/position-competencies` → cek list jabatan + kolom kompetensi tampil.
17. Klik EditAction (slideover) salah satu baris → assign/ubah kompetensi → save → cek ter-sync.
18. Coba bulk action assign kompetensi ke beberapa jabatan sekaligus.

### G. Struktur Organisasi
19. Buka `/admin/organization-structure` (baru) → cek chart organisasi tampil sama seperti versi portal.
20. Bandingkan dengan portal Livewire (`/struktur-organisasi`) → pastikan datanya konsisten, portal tetap read-only (tidak ada tombol edit inline, cuma redirect ke Filament resource).

### H. Regresi cepat (bukan fokus sesi ini, tapi rawan kesenggol)
21. Buka 2-3 resource yang **tidak disentuh** sesi ini (misal Payroll, Attendance Records) → pastikan masih jalan normal pasca migrasi besar yang baru dijalankan.
22. Cek halaman **AttendanceReport** (`app/Filament/Resources/AttendanceRecords/Pages/AttendanceReport.php`, yang bug role-check-nya diperbaiki sesi ini) — filter laporan sebagai Admin vs non-Admin, pastikan scoping company tetap benar.

## Setelah testing

- Catat temuan (bug/gap) balik ke `knowledge/changes/2026-07-14-restorasi-fitur-filament-syifa-dan-buka-akses-panel.md` sebagai update status, bukan file baru.
- Kalau ada data test yang sempat dibuat di production (pengajuan cuti/lembur dummy, dst), tanya user apakah perlu dihapus.
