# Planning: Modul Kanban (Project & Individual Task Management) — 2026-07-13

Status: **rencana, belum dieksekusi.** Lanjutan dari riset & rancangan skema di [`knowledge/markdown/integrasi-kanban-project-management.md`](../knowledge/markdown/integrasi-kanban-project-management.md) (+ versi HTML-nya) — semua keputusan desain sudah dikonfirmasi user di sana (14 poin di §7 dokumen itu). Dokumen ini menerjemahkan hasil riset itu jadi **urutan eksekusi konkret**: migration, model, service, route, Livewire component.

## Kenapa

User minta fitur task management ala Trello/Lark — board per-project **dan** task individual tanpa board, dengan multi-assignee, komentar, upload gambar, dan kontrol siapa boleh assign ke siapa. Sistem `Task` yang sudah ada (`task_records`) tidak cocok dipakai ulang karena filosofinya beda (approval-gated formal, bukan board bebas-drag) — lihat `knowledge/markdown/integrasi-kanban-project-management.md` §2 untuk analisis lengkapnya. Modul ini **baru dan terpisah**, tidak mengubah `task_records` sama sekali.

## Keputusan Desain (sudah dikonfirmasi user — ringkasan)

Rujukan detail: `knowledge/markdown/integrasi-kanban-project-management.md` §7 (14 baris keputusan). Ringkasan yang paling menentukan struktur kode:

1. 2 tabel inti: `kanban_boards` + `kanban_tasks` (`board_id` nullable = task individual kalau kosong).
2. Status **hardcode** 4 nilai: `todo` → `in_progress` → `review` → `done`. Priority hardcode juga: `low`/`medium`/`high`/`urgent` (default `medium`).
3. **Tidak ada approval terpisah** — pembuat task = otomatis "penyetuju", kontrol ada di level role board, bukan step approval tambahan.
4. Role per board (`kanban_board_members.role`): `owner` (pembuat board, kontrol penuh, satu-satunya yang bisa nambah member baru & naikkan orang jadi `assigner`) / `assigner` (boleh assign task ke anggota lain) / `member` (cuma assign ke diri sendiri).
5. Hak bikin board: reuse `User::canAccessKaryawanMenu()` (`app/Models/User.php:150-153`) — sama seperti syarat akses MyStaff.
6. Multi-assignee via `kanban_task_members`, wajib konsisten dengan `kanban_tasks.assigned_to` (assigned_to selalu ikut ter-insert ke situ).
7. Hapus task: cuma `created_by` atau `owner` board.
8. Komentar (`kanban_task_comments`) dan upload gambar (`kanban_task_attachments`) — flat, tanpa threading, pola sama seperti `task_attachments`/`task_proofs` yang sudah ada di `task_records`.
9. 1 board = 1 company (`kanban_boards.company_id`, dari company milik `owner_id` saat board dibuat). Tidak lintas-company.
10. Tanpa notifikasi dulu.
11. UI masih draf (§8 di knowledge doc) — dipakai sebagai acuan awal, bisa direvisi saat implementasi.

## Riset Infrastruktur Portal yang Sudah Ada (wajib dipakai, jangan bikin pola baru)

### Routing & guard portal — sudah ada, tinggal daftar
Semua route portal masuk grup `Route::middleware(['auth', 'portal.access'])` di `routes/web.php`. Middleware `EnsurePortalPageAccess` (`app/Http/Middleware/EnsurePortalPageAccess.php`) baca `config/portal-pages.php` per nama route — kalau ada `'guard' => 'namaMethod'`, dicek `$user->{namaMethod}()`. **`canAccessKaryawanMenu` sudah ada** di `User` model — tinggal pakai untuk route "bikin board baru", **tidak perlu bikin guard baru**.

Route yang butuh cek **per-record** (misal "apakah user ini member board X") **tidak bisa** pakai guard route-level di registry — itu untuk cek yang sama ke SEMUA request ke route itu. Polanya harus kayak `my-staff.show` (lihat `config/portal-pages.php` komentar di entry itu): guard `null` di registry, cek manual di `mount()` komponen Livewire.

### Pola Livewire + Filament Table di luar panel — TIDAK dipakai di sini
`MyEmployees.php`/`MyAttendanceRequests.php` pakai `InteractsWithTable` buat reuse Filament Table di portal. **Board kanban tidak cocok pola ini** — Filament Table itu daftar baris/kolom, bukan board drag-drop multi-kolom. Board detail akan jadi Livewire component custom (Blade + Alpine), bukan `{{ $this->table }}`.

### File upload — pola sudah ada, reuse persis
`SubmitOvertimeRequest`/`AttendaceReqService` (attachment cuti/lembur) sudah punya pola upload (`Storage::disk('public')`, validasi `pdf,jpg,jpeg,png`, maks 10MB) — pola yang sama dipakai untuk `kanban_task_attachments`, cukup ganti folder jadi `kanban/{task_id}/attachments`.

### Otorisasi per-record custom — reuse pola `ApprovalPermissionService`, bukan Filament Policy
Policy di `app/Policies/**` semua format Shield (`Verb:Subject`) buat admin panel — tidak cocok untuk kontrol "apakah user ini `owner`/`assigner` di board X". Pola yang cocok: service class kecil mirip `ApprovalPermissionService::canApprove()` — 1 method per pertanyaan otorisasi, dipanggil dari Livewire component. Nama: `app/Services/Kanban/KanbanAuthorizationService.php`.

## Rancangan Teknis

### A. Migrations (urutan sesuai dependency FK)

1. `create_kanban_boards_table`
2. `create_kanban_board_members_table` (FK ke `kanban_boards`)
3. `create_kanban_tasks_table` (FK ke `kanban_boards`, `task_records`)
4. `create_kanban_task_members_table` (FK ke `kanban_tasks`)
5. `create_kanban_task_comments_table` (FK ke `kanban_tasks`)
6. `create_kanban_task_attachments_table` (FK ke `kanban_tasks`)

Skema kolom persis seperti di `knowledge/markdown/integrasi-kanban-project-management.md` §3.1–§3.7 — jangan didesain ulang saat eksekusi, tinggal disalin ke file migration.

### B. Models — `app/Models/Kanban/`

- `KanbanBoard.php` — `belongsTo(Company)`, `belongsTo(User, 'owner_id')`, `hasMany(KanbanBoardMember)`, `hasMany(KanbanTask)`.
- `KanbanBoardMember.php` — `belongsTo(KanbanBoard)`, `belongsTo(User)`. Konstanta role: `ROLE_OWNER = 'owner'`, `ROLE_ASSIGNER = 'assigner'`, `ROLE_MEMBER = 'member'`.
- `KanbanTask.php` — `belongsTo(KanbanBoard)` (nullable), `belongsTo(User, 'created_by')`, `belongsTo(User, 'assigned_to')`, `belongsTo(Task::class, 'related_task_id')` (nullable, ke `task_records`), `hasMany(KanbanTaskMember)`, `hasMany(KanbanTaskComment)`, `hasMany(KanbanTaskAttachment)`. Konstanta status: `STATUS_TODO`/`STATUS_IN_PROGRESS`/`STATUS_REVIEW`/`STATUS_DONE`. Konstanta priority: `PRIORITY_LOW`/`MEDIUM`/`HIGH`/`URGENT`.
- `KanbanTaskMember.php`, `KanbanTaskComment.php`, `KanbanTaskAttachment.php` — model tipis, relasi `belongsTo`.

### C. `app/Services/Kanban/KanbanAuthorizationService.php`

Method (semua terima `User $user`, return `bool`):
- `canCreateBoard(User $user): bool` — `$user->canAccessKaryawanMenu()`.
- `canAddMember(KanbanBoard $board, User $user): bool` — role `owner` di board itu.
- `canPromoteToAssigner(KanbanBoard $board, User $user): bool` — sama, cuma `owner`.
- `canAssignToOthers(KanbanBoard $board, User $user): bool` — role `owner` atau `assigner`.
- `canDeleteTask(KanbanTask $task, User $user): bool` — `$task->created_by === $user->id` atau `owner` board (kalau `board_id` ada; task individual cuma `created_by`).
- `canAccessBoard(KanbanBoard $board, User $user): bool` — ada baris di `kanban_board_members`.

Dipanggil dari Livewire component (`mount()`, action method) — **bukan** dari Blade langsung, biar 1 sumber kebenaran.

### D. Routes (`routes/web.php`, grup `auth + portal.access` yang sudah ada) + `config/portal-pages.php`

```php
Route::get('/kanban', KanbanBoardsList::class)->name('kanban');
Route::get('/kanban/baru', CreateKanbanBoard::class)->name('kanban.create');
Route::get('/kanban/pribadi', KanbanPersonalTasks::class)->name('kanban.personal');
Route::get('/kanban/{board}', KanbanBoardDetail::class)->name('kanban.show');
```

`config/portal-pages.php` — cuma `kanban.create` yang butuh guard route-level:
```php
'kanban' => ['label' => 'Kanban', 'permission' => null, 'guard' => null],
'kanban.create' => ['label' => 'Buat Board', 'permission' => null, 'guard' => 'canAccessKaryawanMenu'],
'kanban.personal' => ['label' => 'Tugas Pribadi', 'permission' => null, 'guard' => null],
'kanban.show' => ['label' => 'Detail Board', 'permission' => null, 'guard' => null], // per-record check di mount()
```

### E. Livewire Components

1. **`KanbanBoardsList.php`** (`/kanban`) — daftar board yang user jadi member-nya (query `KanbanBoard::whereHas('members', fn ($q) => $q->where('user_id', auth()->id()))`). Tombol "+ Board Baru" cuma tampil kalau `KanbanAuthorizationService::canCreateBoard()`. Desktop: `.data-table`/grid card. Mobile: `.pwa-list`.
2. **`CreateKanbanBoard.php`** (`/kanban/baru`) — form nama+deskripsi board. Guard route-level sudah cukup (§D), tapi tetap `abort_unless` di `mount()` (pola `EditMyEmployee`/`MyEmployees`, defense-in-depth). Submit: buat `KanbanBoard` (`company_id` dari `auth()->user()->company_id`), lalu insert baris `kanban_board_members` role `owner` untuk pembuatnya.
3. **`KanbanBoardDetail.php`** (`/kanban/{board}`) — halaman utama, kanban view. `mount()` cek `canAccessBoard()`, `abort_unless(..., 403)`. State: daftar task per status (4 query atau 1 query di-`groupBy('status')`). Aksi: pindah status (dropdown/tombol per card — **lihat catatan drag-drop di bawah**), buka modal detail task (comment + attachment + edit), buka modal "Task Baru" (guard `canAssignToOthers()` kalau mau isi `assigned_to` ≠ diri sendiri; kalau bukan `owner`/`assigner`, field assignee dikunci ke diri sendiri).
4. **`KanbanPersonalTasks.php`** (`/kanban/pribadi`) — task individual (`board_id = null`, `created_by = auth()->id()` atau member di `kanban_task_members`). List flat, bukan kanban 4-kolom (opsional: tetap pakai 4 status sebagai grouping, keputusan UI final saat eksekusi).

### F. Nav (2 file, konsisten pola yang sudah ada — lihat `planning/2026-07-12-portal-menu-keuangan-payroll.md` §E sebagai contoh persis)

`resources/views/components/nav.blade.php` (desktop) dan mobile equivalent (`.pwa-menu-grid-item` di bottom-sheet, **bukan** 5 slot tetap bottom-nav — lihat `DESIGN.md:96-98`). Link "Kanban" selalu tampil (siapa saja bisa lihat board yang dia jadi member), tapi tombol "+ Board Baru" di dalam halaman list yang digate, bukan item nav-nya.

### G. UI Visual

Ikuti draf `knowledge/markdown/integrasi-kanban-project-management.md` §8 — identity color teal `#0f766e`, pola `.section-nav`+`.section-nav-btn` untuk switch status di mobile, reuse `.badge`/`.pwa-list-row`/`.form-input` yang sudah ada. **Ini draf, boleh disesuaikan saat implementasi** kalau ternyata ada kendala teknis.

## Yang SENGAJA Belum Dikerjakan di Rencana Ini

- **Drag-and-drop asli** (mouse drag antar kolom) — versi pertama pakai **tombol/dropdown "Pindah ke [status]"** per card, bukan drag beneran (butuh library JS tambahan seperti SortableJS yang belum ada di project — nambah dependency untuk sesuatu yang belum diminta eksplisit). Upgrade ke drag-and-drop bisa menyusul kalau dirasa perlu.
- **Notifikasi** — sudah diputuskan skip dulu (§7 poin 3 di knowledge doc).
- **Board lintas-company** — sudah diputuskan tidak perlu (§7 poin 13).
- **Label/tag per task** — tidak pernah masuk requirement, tidak dirancang skemanya.
- **Reorder task dalam 1 status (`position`)** — kolomnya sudah ada di migration, tapi logic drag-reorder-nya ikut poin drag-and-drop di atas (default: urut `created_at` dulu, `position` disiapkan tapi belum dipakai aktif di v1).

## Urutan Eksekusi (kalau sudah dikonfirmasi user)

1. 6 migration (§A) — jalankan `php artisan migrate` setelah semua dibuat, verifikasi FK tidak error.
2. Model + relasi (§B).
3. `KanbanAuthorizationService` (§C).
4. Routes + `config/portal-pages.php` (§D) — halaman masih kosong/placeholder dulu, supaya routing bisa dites duluan.
5. `CreateKanbanBoard` — board paling dasar harus bisa dibuat dulu sebelum fitur lain ada gunanya untuk dites.
6. `KanbanBoardsList` — supaya board yang dibuat kelihatan.
7. `KanbanBoardDetail` — bikin task, pindah status, assign ke orang lain (tes otorisasi `owner`/`assigner`/`member`), komentar, upload attachment.
8. `KanbanPersonalTasks`.
9. Nav (2 file) (§F).
10. Styling sesuai §G (bisa paralel dengan langkah 5-8, tidak blocking).
11. **Verifikasi manual** (disarankan pakai Playwright MCP, pola yang sama seperti sesi testing cuti/izin/lembur sebelumnya): login sebagai 3 user beda role dalam 1 board (owner/assigner/member) → tes assign task ke orang lain ditolak untuk `member`, diterima untuk `assigner`/`owner`; tes hapus task ditolak untuk selain `created_by`/`owner`; tes task individual tidak muncul di board manapun.

## Referensi

- `knowledge/markdown/integrasi-kanban-project-management.md` (+ versi HTML) — sumber kebenaran skema & keputusan desain, baca dulu sebelum eksekusi kalau ada detail yang lupa.
- `app/Models/User.php:150-153` (`canAccessKaryawanMenu`) — guard yang di-reuse untuk hak bikin board.
- `app/Services/Approval/ApprovalPermissionService.php` — pola otorisasi per-record yang dicontek untuk `KanbanAuthorizationService`.
- `app/Http/Middleware/EnsurePortalPageAccess.php` + `config/portal-pages.php` — infrastruktur guard route yang direuse.
- `resources/views/components/nav.blade.php`, `resources/views/components/pwa-bottom-nav.blade.php` — nav yang perlu diedit.
- `DESIGN.md` — design system portal, acuan §G.
- `planning/2026-07-12-portal-menu-keuangan-payroll.md` — contoh planning doc dengan struktur serupa (fitur portal baru + nav + guard), dicontek formatnya untuk dokumen ini.
