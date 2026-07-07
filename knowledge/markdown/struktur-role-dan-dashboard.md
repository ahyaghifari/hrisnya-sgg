# Struktur Role & Model Dashboard per Level (per 2026-07-03)

## Ringkasan Role di Seluruh Sistem

15 nama role unik, tapi tiap company punya baris `roles` sendiri-sendiri (bukan role yang dipakai bersama) — total 84 baris di tabel `roles` untuk 8 company.

**11 role dasar** (di-seed sama persis ke semua 8 company via `RoleSeeder.php`):
1. Super Admin *(cuma company holding yang benar-benar bypass semua company — 7 lainnya sudah di-rename jadi "Admin", lihat catatan di bawah)*
2. Admin
3. Direktur
4. HC Supervisor
5. HC Manager
6. Kasubag / Staff Kepegawaian
7. Staff Finance
8. Learning & Development
9. Manager / Kepala Bidang
10. Supervisor / Kepala Ruangan
11. Karyawan / Staff

**4 role tambahan**, cuma ada di company holding (dibuat manual belakangan, bukan dari seeder dasar):
- Accounting Manager
- Finance Manager
- IT Supervisor
- Tax Staff

## Bug yang Ditemukan & Diperbaiki: "Super Admin" Ambigu Lintas Company

`RoleSeeder.php` membuat role **"Super Admin" di SETIAP company** (niatnya jadi admin lokal per company, full akses tapi cuma company sendiri). Masalahnya: Spatie `hasRole()` cuma cek `name` + `guard_name`, TIDAK peduli kolom custom `company_id` di tabel `roles` (fitur `teams` Spatie juga tidak diaktifkan: `config/permission.php: 'teams' => false`). Akibatnya 8 role yang sama-sama bernama "Super Admin" itu ter-treat identik oleh sistem — ini bug PRA-EXISTING (ditemukan juga di `PermissionHelper::hasPermission()`, bukan buatan sesi ini).

**Fix**: migration `2026_07_03_140000_rename_company_scoped_super_admin_roles_to_admin.php` me-rename 7 role "Super Admin" (di company selain holding) jadi **"Admin"**. Sekarang cuma role holding yang bernama "Super Admin" — itu satu-satunya yang bypass company-scope (lewat trait `BelongsToCompany`, lihat `knowledge/dev/`).

## Analisis Kesehatan Struktur Permission

Dicek similarity & subset antar semua role di holding (14 role, paling lengkap):

- **Tidak ada role yang benar-benar duplikat.** Pasangan paling mirip: HC Supervisor vs HC Manager (82,9% overlap) — tetap ada permission unik di masing-masing, bukan identik.
- **Polanya berlapis rapi (bukan acak)**: `Karyawan / Staff` (4 permission) adalah **subset penuh dari SEMUA role lain** di company yang sama — konfirmasi bahwa semua role dibangun dari baseline karyawan + tambahan sesuai levelnya (mis. `Karyawan/Staff` ⊂ `Tax Staff` ⊂ `Finance Manager` ⊂ `Direktur` ⊂ `Super Admin`).
- **Kesimpulan**: 15 role ini SUDAH PAS, tidak perlu dikonsolidasi/dikurangi — mengurangi role berisiko menghilangkan distingsi akses yang memang disengaja beda (misal Tax Staff vs Staff Finance beda beberapa permission spesifik).
- Catatan tambahan: permission count untuk nama role yang SAMA ternyata beda antar company (mis. "Direktur" holding=55 permission vs RSU Banjarbaru=92 permission) — ini **dikonfirmasi disengaja** oleh pemilik project (wewenang Direktur holding vs anak perusahaan memang beda), bukan bug.

## Model Dashboard: Level × Domain (bukan 1 dashboard per role)

15 role tidak berarti butuh 15 dashboard berbeda. Dashboard dimodelkan dari 2 sumbu independen:

**Sumbu 1 — Level** (menentukan CAKUPAN data):
| Level | Cakupan | Dependency teknis |
|---|---|---|
| Karyawan | Diri sendiri | Sudah siap |
| Supervisor | Diri + tim langsung | Fase 3 — `OrgHierarchyService` (belum dibangun) |
| Manager | 1 departemen/fungsi (company-scoped) | Fase 1 company-scoping |
| Direktur/Eksekutif | Seluruh company (holding: lintas company) | Fase 1 + logic khusus holding |

**Sumbu 2 — Domain** (menentukan WIDGET yang muncul): HR/HCGA, Finance/Accounting, L&D, Umum.

Pemetaan role → Level+Domain, dan detail cara bangun (widget library reusable, bukan halaman terpisah per role) ada di `planning/2026-07-03-arsitektur-dashboard-per-role.md`.

**Catatan penting**: Admin & Super Admin TIDAK masuk model Level×Domain ini — mereka tetap pakai Filament `/admin` (kesepakatan: Filament cuma untuk master data & admin, bukan dashboard operasional harian).
