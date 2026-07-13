# Perubahan: Modul Kanban — Verifikasi Implementasi + Rentetan Revisi (2026-07-13)

Lanjutan dari `planning/2026-07-13-modul-kanban-project-task.md` dan `knowledge/markdown/integrasi-kanban-project-management.md`. Modul kanban **sudah diimplementasikan penuh** sebelum sesi ini dimulai (migration, model, service, route, Livewire, nav — commit "add kanban" x4). Sesi ini: verifikasi awal (semua sehat, 26 test pass), lalu serangkaian revisi UX/otorisasi berdasarkan feedback iteratif user, ditutup dengan fitur baru "Kanban Staff" (task langsung dari atasan ke bawahan tanpa board).

## Ringkasan

**Status akhir sesi**: 43 test di `tests/Feature/KanbanModuleTest.php` pass. Fitur baru: checklist item per task, multi-assignee, kontrol status berlapis (owner/creator bebas, lainnya 1-step, `done` terkunci total), warna board custom, avatar foto profil, dan **"Kanban Staff"** — atasan bisa assign task langsung ke satu bawahan tanpa perlu bikin board.

## 1. Member board tidak bisa bikin task baru

Awalnya semua role board (owner/assigner/member) bisa bikin task (member cuma dibatasi assign ke diri sendiri). Direvisi: **cuma owner/assigner** yang boleh bikin task sama sekali. `KanbanAuthorizationService::canCreateTask()` baru (alias `canAssignToOthers()`), digate di 3 lapis: tombol "Task Baru" disembunyikan, `openCreateTask()`, dan `createTask()` — pola `abort_unless` konsisten dengan method lain di service ini.

## 2. Assignee task: multi-select, wajib pilih member lain (bukan diri sendiri)

- Form task berubah dari dropdown single-select (default diri sendiri) jadi **checkbox multi-select**, sumbernya `KanbanBoardDetail::getAssignableMembersProperty()` (= member board dikurangi diri sendiri).
- Validasi server: `Rule::in($assignableIds)` — assignee harus anggota board, dan **tidak boleh termasuk diri sendiri** (list sumbernya sendiri sudah exclude self).
- `kanban_task_members` sekarang benar-benar diisi semua assignee terpilih (dulu cuma 1 baris = `assigned_to`). Kolom `assigned_to` tetap diisi (assignee pertama) buat kompatibilitas avatar di card.

## 3. Checklist item (`kanban_task_items` — tabel baru)

- Migration `2026_07_13_202042_create_kanban_task_items_table.php`: `task_id`, `title`, `is_done` (bool), `position`.
- Model `KanbanTaskItem` + relasi `KanbanTask::items()`.
- **Bukan di modal detail** — tombol "Item" (tambah), toggle centang, dan daftar item semuanya di **card langsung** (kolom kanban), dengan tombol expand/collapse "Checklist (x/y)". List item dibungkus container `background:rgba(0,0,0,0.18)` (lebih gelap dari card), tiap item row `background:var(--surface-alt)` (senada card). Checkbox pakai `accent-color:#0f766e` (teal, identitas kanban) + `border-radius:4px` — native CSS, bukan custom pseudo-checkbox.
- Otorisasi nambah item: `KanbanAuthorizationService::canManageItems()` — **owner board, pembuat task, atau member task itu sendiri** (`kanban_task_members`), BUKAN sembarang member board. Toggle/delete item di-scope ke task-task milik board (bukan `selectedTaskId`, karena sekarang dipanggil dari card langsung, bukan cuma dari modal).
- Modal detail task sekarang **tidak lagi menampilkan checklist** — checklist eksklusif tampil di card.

## 4. Kontrol perpindahan status berlapis

`KanbanAuthorizationService::canChangeStatusFreely()`: **owner board** dan **pembuat task** bebas ubah status ke mana saja. Selain itu (assigner/member yang cuma ditugaskan) **cuma boleh maju 1 langkah** berurutan (`todo→in_progress→review→done`, tidak bisa lompat/mundur). Dropdown status di card ikut dibatasi opsinya sesuai aturan ini (server tetap validasi ulang, dropdown cuma UX).

**Task `done` = terkunci total** untuk siapapun (termasuk owner/creator) — tidak bisa ubah status lagi, checklist (tombol + list) hilang total dari card. **Komentar tetap bisa** kapan saja walau task sudah selesai.

## 5. Komentar pindah ke card (sama seperti checklist)

Section komentar dihapus dari modal detail, dipindah ke card dengan pola sama seperti checklist (expand/collapse "💬 (n)", form tambah "Komentari"). **Siapa saja yang bisa akses board** (owner + semua member) boleh komentar — tidak ada gate tambahan karena akses board itu sendiri sudah jadi gerbangnya.

## 6. Warna board custom (kolom `color` baru)

Migration `2026_07_13_205545_add_color_to_kanban_boards_table.php` — `kanban_boards.color` (hex, default `#042c6c`). Diisi lewat `<input type="color">` native (bukan library color-picker) saat bikin board. Dipakai buat:
- Background workspace kanban di `/kanban/{board}` (solid, bukan gradient lagi — sempat dicoba gradient board→pink, user minta solid saja).
- Border kiri card board di `/kanban` (dulu warna hash-per-id, sekarang warna asli tersimpan).
- Tombol color-picker di sebelah "Task Baru" di board detail, **cuma tampil buat owner** (`updateColor()`, guard `canAddMember` — reuse permission owner-only yang sudah ada).

## 7. Avatar foto profil (`<x-avatar>` — komponen baru)

`resources/views/components/avatar.blade.php` — tampilkan `$user->profile->profile_picture[0]` (lewat `Storage::url()`) kalau ada, fallback ke inisial kalau `null`. Pola yang sudah dipakai di `nav.blade.php`/halaman statistik karyawan, sekarang diekstrak jadi komponen reusable (dipakai 3+ tempat di kanban: header board, card task, list board). Eager load `members.user.profile` ditambahkan di query terkait biar tidak N+1.

## 8. Sorting & styling kecil

- Task per kolom status diurutkan: task yang **user login jadi member-nya** naik ke atas (stable sort, `position`/`created_at` tetap terjaga di dalam grup).
- Zebra stripe: card genap kena `filter:brightness(0.95)` (native CSS, bukan `color-mix`, biar konsisten di light/dark mode).
- Card kolom status dapat `box-shadow:var(--shadow-card)` (token yang sama dipakai `.stat-card`/`.pwa-list`).
- Dekorasi shape sempat dicoba di workspace board (hero-deco lingkaran) — **dibatalkan/dihapus**, balik ke background polos sesuai permintaan user.

## 9. "Kanban Staff" — atasan assign task langsung ke bawahan (tanpa board)

Awalnya dicoba lewat form "Tugas Pribadi" (tambah field assignee di situ) — **direvisi total**, di-revert (`git checkout`), diganti pendekatan terpisah:

- Tombol baru **"Kanban Staff"** (pink `#B9308C`) di `/kanban`, cuma tampil kalau user punya bawahan (`OrgHierarchyService::subordinates()`, reuse persis logic yang sama dengan menu MyStaff — **bukan** guard baru).
- Modal terpisah (bukan reuse form Tugas Pribadi): pilih staff (dropdown dari bawahan langsung, rekursif) + judul/deskripsi/tenggat/prioritas. `createStaffTask()` bikin `KanbanTask` dengan `board_id = null`, `created_by` = atasan, `assigned_to` = staff.
- **BoardKu (Tugas Pribadi) direvisi query-nya**: sekarang cuma nampilin task yang `assigned_to = aku` (dulu `created_by OR assigned_to`). Efeknya: atasan **tidak lihat** task yang dia titipkan ke staff di BoardKu-nya sendiri — task itu cuma muncul di BoardKu staff yang dituju.
- Task titipan yang statusnya `done` otomatis hilang dari BoardKu (baik dari sisi manapun) — task pribadi murni (`created_by == assigned_to`) yang `done` tetap tampil seperti biasa.
- Supaya atasan tetap bisa lihat & aksi progress task yang dia titipkan (tanpa buka BoardKu staff-nya), ditambah **section "Task Titipan" di halaman `/kanban` sendiri** (`KanbanBoardsList::getStaffTasksProperty()`) — list task (board_id null, created_by≠assigned_to, belum done) yang melibatkan user (sebagai atasan ATAU staff), pakai gaya card **sama persis seperti card board** (`.pwa-list`, avatar, dll) tapi `border-radius:0` dan **container list terpisah `flex-column` (bukan ikut grid multi-kolom board)** — sempat salah taruh di grid `auto-fit` yang sama dengan board, bikin desktop jadi per-kolom, diperbaiki jadi container sendiri. Bisa ubah status & hapus (khusus pembuat) langsung dari situ tanpa pindah halaman.
- Istilah "Titipan" di teks UI diganti jadi **"Ditugaskan ke/oleh"** atas permintaan user (istilah "titipan" cuma dipertahankan di komentar kode/nama internal, bukan yang tampil ke user).

## 10. Polling 10 detik (bukan realtime websocket)

`wire:poll.10s` ditambah di root `<div>` `KanbanBoardDetail` dan `KanbanBoardsList`. **Ini bukan realtime sungguhan** — project belum pasang broadcasting (`.env.example` cuma `BROADCAST_CONNECTION=log`, tidak ada Pusher/Reverb/Echo). Polling cukup buat kasus pakai board kerja biasa; kalau butuh push instan ke semua user yang lagi buka board bersamaan, perlu tambah Laravel Reverb + Echo listener — di luar scope sesi ini.

## Test

`tests/Feature/KanbanModuleTest.php` — 43 test, meng-cover semua poin otorisasi di atas (siapa boleh apa), termasuk skenario Kanban Staff (assign ke bawahan, bukan-bawahan ditolak, tidak muncul di BoardKu atasan, muncul di section `/kanban`, aksi status/hapus dari situ).

## Belum Dikerjakan / Ditunda

- Drag-and-drop asli (masih tombol/dropdown "pindah status") — sudah diputuskan sejak planning awal, belum berubah.
- Broadcasting realtime sungguhan (lihat §10) — baru polling 10 detik.
- Edit warna board setelah dibuat sudah bisa (via §6), tapi belum ada history/log siapa yang pernah ganti warna.
- Checklist/komentar untuk task individual di BoardKu (bukan board) — belum ada modal detail sama sekali di `KanbanPersonalTasks`, di luar scope sesi ini.
