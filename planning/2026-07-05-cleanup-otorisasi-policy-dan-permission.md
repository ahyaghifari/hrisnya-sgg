# Planning: Perapian Otorisasi — Hapus Policy Mati, Rapikan Pola Permission — 2026-07-05

Status: **analisis selesai (sudah dikonfirmasi lewat scan menyeluruh), belum ada kode dieksekusi.**

Konteks lengkap ada di percakapan sesi ini (bukan dokumen terpisah) — ringkasannya di bawah, supaya rencana ini bisa dieksekusi tanpa perlu baca ulang seluruh chat.

## Latar belakang

Project ini sudah punya sistem permission (spatie/laravel-permission, ~190 permission string, UI assign permission per role di `/admin/roles`). Tapi ada beberapa hal yang bikin kodenya terlihat berantakan / tidak konsisten:

1. **`app/Policies/` (44 file) hampir semuanya dead code.** Sudah dikonfirmasi lewat scan menyeluruh (`->can(`, `@can(`, `$this->authorize(`, `Gate::authorize(` di seluruh `app/`, `resources/views/`, `routes/`): cuma **1 pemanggilan Policy** di seluruh project — `@can('create_struktur_organisasi', App\Policies\OrganizationStructurePolicy::class)` di `resources/views/livewire/organization-chart-widget.blade.php:56`. Isi method-nya sendiri cuma bungkus tipis:
   ```php
   public function create(User $user): bool
   {
       return $user->can('create_struktur_organisasi');
   }
   ```
   — persis pengecekan permission-string yang sudah dipakai di 189 tempat lain, tanpa logic tambahan apa pun. 43 Policy lainnya (di `Attendance/`, `Finance/`, `Kepegawaian/`, `LMS/`, `MasterData/`, `Shift/`, dan beberapa di root) tidak dipanggil sama sekali di mana pun.
2. **Tidak ada `AuthServiceProvider`/registrasi Policy manual** — jadi Policy-Policy ini juga tidak "otomatis kepakai" lewat konvensi Laravel; benar-benar tidak pernah masuk jalur eksekusi.
3. **Pola otorisasi yang SUDAH konsisten & terbukti jalan** (tidak perlu diubah, ini yang mau dipertahankan): dua lapis —
   - **Feature-level** ("boleh akses fitur X secara umum?") → `Gate::allows('permission_name')` / `Auth::user()->can('permission_name')` / `@can('permission_name')`, sumbernya permission string dari `role_has_permissions` (Spatie).
   - **Record-level** ("boleh untuk data SPESIFIK ini?") → method biasa di Model/Service, bukan Policy — contoh yang sudah ada: `User::canManage()`, `User::hasSubordinates()`, `User::canBrowseAllCompanies()`, `OrgHierarchyService::subordinates()`, `ApprovalPermissionService::canApprove()`.

Keputusan: **tidak perlu Policy sama sekali** untuk kedua lapis di atas — Policy Laravel didesain buat otorisasi per-model-instance, tapi project ini sudah menyelesaikan itu lewat method biasa (lebih simpel, konsisten dengan yang sudah dipakai di 99% tempat).

## Tujuan

Bikin kode otorisasi jujur soal pola yang benar-benar dipakai — hapus lapisan yang tidak pernah jalan, supaya tidak ada developer baru yang bingung "kenapa ada Policy tapi kok tidak pernah dipanggil?" atau salah kira Policy itu bagian aktif dari alur otorisasi.

## Langkah eksekusi

### 1. Fix satu titik pemanggilan Policy (WAJIB sebelum langkah 2)

File: `resources/views/livewire/organization-chart-widget.blade.php:56`

```blade
{{-- SEBELUM --}}
@can('create_struktur_organisasi', App\Policies\OrganizationStructurePolicy::class)

{{-- SESUDAH --}}
@can('create_struktur_organisasi')
```

Perilaku identik (sudah diverifikasi — isi method Policy-nya cuma manggil permission-string yang sama). Kalau langkah ini dilewat dan langsung ke langkah 2, halaman ini akan **fatal error** ("Class not found") karena reference class eksplisit ke Policy yang sudah dihapus.

### 2. Hapus `app/Policies/` seluruhnya

Semua 44 file, termasuk subfolder (`Attendance/`, `Finance/`, `Kepegawaian/`, `LMS/`, `MasterData/`, `Shift/`, dan file-file langsung di root `app/Policies/` seperti `EmployeePolicy.php`, `RolePolicy.php`, `TaskPolicy.php`, dst). Tidak ada file lain yang perlu diubah setelah langkah 1 — sudah dikonfirmasi tidak ada `$this->authorize()`/`Gate::authorize()` di mana pun, dan tidak ada Policy lain yang dipanggil.

### 3. (Opsional, kalau mau — bukan bagian wajib "beresin Policy") Dokumentasikan pola yang benar

Tambah 1 subsection singkat di `DESIGN.md` atau bikin catatan baru di `knowledge/dev/` yang menegaskan 2 pola otorisasi yang dipakai (feature-level via permission string, record-level via method model/service) — supaya developer selanjutnya tidak coba-coba bikin Policy baru lagi.

## Yang SENGAJA tidak termasuk di rencana ini (dibahas tapi belum diputuskan lanjut atau tidak)

Beberapa ide lain sempat dibahas tapi ini task terpisah, jangan dicampur ke pembersihan Policy di atas:

- **Registry `portal-pages` + middleware** — supaya route baru di portal (Livewire) otomatis wajib didaftarkan permission-nya (fail-closed), bukan cuma `->middleware('auth')` polos seperti sekarang. Menutup celah "halaman baru lupa dikasih guard" (sudah kejadian di route Dashboard).
- **Seed permission default untuk role selain Super Admin** — saat ini `RolePermissionSeeder` cuma kasih semua permission ke role "Super Admin" per company; role lain (Manager, HC, Supervisor, Karyawan) mulai dari nol, harus di-assign manual lewat `/admin/roles`.
- **`PermissionResource` di Filament** — biar permission baru bisa dibuat lewat UI (create/list/delete nama permission), bukan cuma lewat edit `PermissionsSeeder.php` + jalankan seeder manual.

Ketiga hal di atas nilainya beda dari sekadar "beresin Policy" — itu soal menutup celah proses ke depan (portal-pages, seed default), bukan soal kode yang sudah mati. Bisa jadi planning terpisah kalau mau dilanjutkan.

## Risiko & catatan

- Ini murni penghapusan kode yang sudah dikonfirmasi tidak dipakai — risiko regresi sangat rendah. Tetap disarankan jalankan `php artisan test` setelah eksekusi (kalau diminta user; jangan jalan otomatis tanpa diminta, sudah jadi catatan proses di sesi ini).
- Kalau nanti ternyata ada kebutuhan otorisasi per-record yang genuinely butuh Policy (bukan cuma dua lapis yang sudah ada), boleh dipertimbangkan lagi saat itu — bukan berarti "Policy dilarang selamanya", cuma "tidak dipakai sekarang karena tidak ada kebutuhannya".
