# Perubahan: Pilar 1 — Job Position → Role Default (2026-07-04)

Rencana lengkap: [`planning/2026-07-04-draft-role-job-position-mapping.md`](../../planning/2026-07-04-draft-role-job-position-mapping.md).

Implementasi Opsi B: role tetap independen, Job Position cuma jadi saran/pre-fill saat user dipromosikan/dimutasi. Efek ke hak akses aktual (`model_has_roles`) baru terjadi kalau HRD konfirmasi & transisi di-approve — bukan otomatis/terkunci.

## Migration
- `2026_07_04_232859_add_role_id_to_m_job_positions_table.php` — tambah `role_id` (nullable, FK → `roles`, `nullOnDelete`) ke `m_job_positions`. Ini "Role Default" untuk jabatan tsb.
- `2026_07_04_232901_add_role_id_to_user_transitions_table.php` — tambah `role_id` (nullable, FK → `roles`, `nullOnDelete`) ke `user_transitions`. Nyimpen role yang dipilih HRD saat transisi dibuat.

Catatan desain: tidak jadi bikin tabel terpisah `job_position_role_defaults` seperti draft awal di planning doc — karena relasinya 1:1 nullable, cukup 1 kolom di tabel yang sudah ada (YAGNI, hindari tabel tambahan yang tidak perlu).

## Model
- `App\Models\Master\JobPositions` — tambah `role_id` ke `$fillable`, relasi `defaultRole()` (belongsTo `App\Models\Role`).
- `App\Models\User\UserTransition` — tambah `role_id` ke `$fillable`, relasi `role()` (belongsTo `App\Models\Role`).

## Filament UI
- [`JobPositionForm.php`](../../app/Filament/Resources/Master/JobPositions/Schemas/JobPositionForm.php) — field Select "Role Default" baru di step *Job Evaluation*, sejajar Pangkat/Golongan/Kelas, scoped ke company user login.
- [`UserTransitionForm.php`](../../app/Filament/Clusters/UserTransitions/Resources/UserTransitions/Schemas/UserTransitionForm.php):
  - Field Select "Role Baru" baru di section *Perubahan Jabatan* (Promosi/Demosi). Auto ke-pre-fill dari `role_id` milik Job Position yang dipilih di field "Jabatan Baru" (`afterStateUpdated`), tapi tetap bisa diganti/dikosongkan manual.
  - View mode (`editSchema`) nambah `TextEntry` "Role Baru" (placeholder "Tidak diubah" kalau kosong).

## Business Logic
- [`EditUserTransition.php`](../../app/Filament/Clusters/UserTransitions/Resources/UserTransitions/Pages/EditUserTransition.php) → `applyTransitionChanges()` — setelah transisi Promosi/Demosi di-approve dan job position user di-update, kalau `role_id` terisi maka `$user->syncRoles([$record->role])`. Kalau field dikosongkan HRD, role user tidak disentuh sama sekali.

## Status
Migration sudah dijalankan (`php artisan migrate`), relasi diverifikasi lewat tinker. Belum diuji manual lewat browser.
