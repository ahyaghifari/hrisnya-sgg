> **STATUS: SUPERSEDED (2026-07-23).** Rancangan di dokumen ini (2 tabel `kanban_boards`/`kanban_tasks` terpisah dari `task_records`) memang jadi dasar implementasi awal, tapi ~10 hari kemudian modul Kanban ini **dihapus total** dan dilebur balik ke `task_records` (alasan: user tidak mau ada 2 sistem task berbeda). Dokumen pengganti, baca sejarah lengkap & state final di [`manajemen-tugas-dan-project.md`](manajemen-tugas-dan-project.md). Dokumen ini dibiarkan sebagai jejak sejarah kenapa desain awal begini, bukan referensi state saat ini.

# Integrasi Sistem Kanban (Project & Individual) — Analisis & Rancangan Skema (2026-07-13)

Dokumen ini menjawab permintaan: "bisa dibuat task yang sifatnya per-project (kanban board ala Trello/Lark) sekaligus per-individu?" — hasil analisis kode berjalan (`task_records` dan modul sekitarnya) plus rancangan skema baru untuk menjawab kebutuhan tersebut, ditulis **sebelum implementasi** untuk direview dulu.

**Status: semua keputusan desain utama sudah dikonfirmasi** (lihat §7) — skema di bawah ini sudah final untuk memulai migration, tinggal UI yang belum dibahas.

## Ringkasan

1. Sistem `Task` yang sudah ada (`task_records`) **bukan** kanban — itu **task-request-dengan-approval** (formal work order: create → approval wajib → assign → review), bukan board bebas-drag. **Tabel ini tidak dihapus/diganti** — tetap jalan apa adanya untuk kasus yang memang butuh approval berjenjang formal (lihat §5).
2. Modul baru terpisah, **2 tabel inti**: `kanban_boards` dan `kanban_tasks`. `board_id` **nullable** di `kanban_tasks` jadi mekanisme tunggal yang menutup dua kebutuhan sekaligus — board kosong = task individual.
3. **Status dihardcode** lewat enum tetap (`todo` / `in_progress` / `review` / `done`) — bukan tabel kolom kustom.
4. **Multi-assignee didukung** lewat tabel tambahan `kanban_task_members` — 1 task bisa dikerjakan bareng lebih dari 1 orang.
5. **Tidak ada approval terpisah** — siapa yang membuat & assign task (harus role `owner`/`assigner` di board) otomatis jadi "penyetuju"-nya. Tidak ada status `pending` yang perlu ditunggu approve orang lain.
6. **Hak akses bikin board** — reuse persis kondisi yang sudah dipakai fitur MyStaff: `User::canAccessKaryawanMenu()` (punya bawahan, atau Direktur dengan akses global).
7. **Tidak ada perubahan ke tabel yang sudah ada** (`task_records`, `users`, `m_companies`, `m_org_structures`).

## 1. Konteks: Sistem Task yang Sudah Ada

Ringkasan dari investigasi kode langsung (`app/Models/Task/Task.php`, `app/Services/Task/TaskService.php`, `app/Http/Controllers/Api/Task/TaskController.php`, `app/Filament/Resources/TaskRequests/`):

- Tabel `task_records`: `company_id`, `department_id`→`m_org_structures`, `service_id`→`m_task_services` (kategori task dari master data tetap), `assigned_to`, `approved_by`, `sla_reviewer_id`, `requirement`, `status` (`PENDING`/`IN_PROGRESS`/`REJECTED`/`COMPLETE` — 4 status tetap), `due_date`, `risk_level`, `total_cost`/`unit_cost`.
- Alur: siapa saja yang login bisa `POST /api/tasks` (tidak ada role/permission check), tapi task itu **wajib lewat approval flow generik** (`module_type = 'task_request'`, engine yang sama dengan cuti/izin/lembur) sebelum `assigned_to` ditentukan dan status jadi `IN_PROGRESS`.
- Task individual sudah ada versi sederhananya: `POST /api/tasks/self` (`storeSelfTask`) — langsung `assigned_to = diri sendiri`, `status = IN_PROGRESS`, **tanpa approval**.
- Semua akses lewat **API only**, tidak ada Livewire/halaman web. Widget "Task Saya" di dashboard portal masih data statis hardcoded.

## 2. Kenapa Tidak Reuse `task_records`

| Aspek | `task_records` (sekarang) | Kebutuhan Kanban |
|---|---|---|
| Perubahan status | Lewat approval flow formal, 1 approver | Digeser manual kapan saja, tanpa approval |
| Assignee | Ditentukan approver saat approve | Ditentukan pembuat task langsung |
| Pengelompokan | Per `department_id` (struktur organisasi) | Per **project/board** (tim lintas-departemen bisa 1 board) |
| Urutan tampilan | Tidak ada konsep posisi/urutan | Butuh `position` untuk urutan drag dalam kolom |

## 3. Rancangan Skema Final

### 3.1 `kanban_boards` — 1 board = 1 project/tim

```php
Schema::create('kanban_boards', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete(); // pembuat board
    $table->timestamps();
    $table->softDeletes();
});
```

**Siapa boleh bikin board** (dikonfirmasi): reuse `User::canAccessKaryawanMenu()` (`app/Models/User.php:150-153`) — method yang sama persis yang sudah dipakai untuk nampilin menu "MyStaff" (`hasSubordinates()`: punya bawahan lewat struktur jabatan, **atau** `canBrowseAllCompanies()`: Direktur dengan akses global). Jadi kalau seseorang sudah bisa akses MyStaff, otomatis bisa bikin board — tidak perlu permission baru, tinggal reuse.

### 3.2 `kanban_tasks` — inti fitur, `board_id` nullable = mekanisme individual/project

```php
Schema::create('kanban_tasks', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('board_id')->nullable()->constrained('kanban_boards')->cascadeOnDelete(); // null = task pribadi
    $table->string('title');
    $table->text('description')->nullable();
    $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete(); // dialah yang juga "menyetujui" — lihat §3.3
    $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // penanggung jawab utama
    $table->string('status')->default('todo'); // enum app-level: todo/in_progress/review/done — HARDCODE
    $table->date('due_date')->nullable();
    $table->unsignedInteger('position')->default(0); // urutan drag dalam 1 status yang sama
    $table->string('priority')->default('medium'); // enum app-level: low/medium/high/urgent — HARDCODE, sama seperti status
    $table->timestamp('completed_at')->nullable();
    $table->foreignUlid('related_task_id')->nullable()->constrained('task_records')->nullOnDelete(); // opsional, lihat §5
    $table->timestamps();
    $table->softDeletes();
});
```

**Logika kunci**: `board_id` diisi → task tampil di board sebagai kartu kanban, posisinya ditentukan nilai `status`. `board_id` kosong → task individual, tetap pakai `status` yang sama tapi cuma muncul di list pribadi user.

**Status hardcode** (4 nilai tetap):
`todo` → `in_progress` → `review` → `done`

**Priority hardcode juga** (dikonfirmasi — "yang terbaik menurutmu"): `low` / `medium` / `high` / `urgent`, default `medium`. Alasan dihardcode sama seperti `status`: 4 nilai closed-set yang umum dipakai di semua tool sejenis (Trello/Lark/Jira), belum ada requirement butuh label kustom per-board, jadi ikut pola yang sama daripada bikin tabel master terpisah untuk sesuatu yang belum diminta.

**Otorisasi hapus task** (dikonfirmasi): hanya `created_by` (pembuat/pemberi task) atau `owner` board yang boleh hapus (`softDeletes`, bukan hard delete). Selain dua itu — termasuk `assigned_to`/`assigner` lain — **ditolak**.

**Konsistensi `assigned_to` ↔ `kanban_task_members`** (dikonfirmasi wajib): setiap kali `assigned_to` diisi/diganti, aplikasi **wajib** memastikan baris yang sama juga ada di `kanban_task_members` (auto-insert kalau belum ada) — supaya tidak pernah ada PIC yang gak kelist di daftar pengerja. Ini validasi di level service/aplikasi, bukan constraint DB (beda tabel).

### 3.3 Kenapa Tidak Ada Kolom `approval_status` Terpisah

Draf awal sempat merancang `requires_approval`/`approval_status`/`approved_by`/`approved_at` sebagai alur persetujuan terpisah (task dibuat → `pending` → menunggu approve → baru bisa jalan). **Ini dibuang** setelah klarifikasi: *"approval langsung yang bikin tasknya, approver defaultnya adalah yang bikin tasknya."*

Karena otorisasi assign-ke-orang-lain **sudah** digerbang di level board (§3.4 — cuma `owner`/`assigner` yang boleh assign ke orang lain), dan approver default = pembuat task itu sendiri, maka **pembuat task = penyetuju** — tidak pernah ada pihak ketiga yang perlu approve. Alur `pending → menunggu approve` jadi tidak pernah benar-benar terjadi, sehingga kolomnya dibuang: `created_by` di `kanban_tasks` sudah cukup jadi jejak audit "siapa yang menyetujui/membuat task ini berjalan", tidak perlu kolom terpisah.

**Ringkasnya**: keamanan/kontrolnya ada di §3.4 (siapa boleh assign), bukan di step approval tambahan setelah task dibuat.

### 3.4 `kanban_board_members` — Siapa Boleh Assign Task ke Orang Lain

```php
Schema::create('kanban_board_members', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('board_id')->constrained('kanban_boards')->cascadeOnDelete();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('role')->default('member'); // owner | assigner | member
    $table->timestamps();
    $table->unique(['board_id', 'user_id']);
});
```

**3 level role per board:**
- **`owner`** — pembuat board (`kanban_boards.owner_id`), otomatis jadi baris `owner` di sini juga. Kontrol penuh: hapus board, keluarkan member, ubah role member lain, **satu-satunya yang boleh menaikkan orang jadi `assigner`** (dikonfirmasi — `assigner` lain tidak bisa menaikkan `assigner` baru), dan **satu-satunya yang boleh menambahkan member baru ke board** (dikonfirmasi — `assigner` boleh assign task ke anggota yang sudah ada, tapi tidak boleh mengundang orang baru masuk board).
- **`assigner`** — boleh bikin task dan assign ke anggota lain di board itu — role buat atasan/PIC tim yang bukan pemilik board tapi tetap perlu distribusikan kerjaan ke bawahannya.
- **`member`** — biasa, cuma bisa bikin/kerjakan task untuk diri sendiri, tidak bisa assign ke orang lain.

Pengecekan otorisasi: `assigned_to !== auth()->id()` **hanya boleh** kalau pelaku punya role `owner` atau `assigner` di board tersebut — kalau tidak, request ditolak.

**Member dikeluarkan dari board** (dikonfirmasi): task yang sudah terlanjur `assigned_to`/tercatat di `kanban_task_members` **dibiarkan apa adanya** — tidak otomatis di-unassign atau dipindah. Task itu tetap kelihatan riwayatnya, cuma orang yang dikeluarkan itu sudah tidak bisa lagi assign task baru (karena bukan member lagi), sampai owner atau assigner lain assign ulang secara manual.

**Scoping company** (dikonfirmasi): board **hanya untuk 1 company**, ditentukan dari `company_id` milik `owner_id` saat board dibuat (`kanban_boards.company_id`, lihat §3.1). Menambahkan member ke board **wajib divalidasi satu company** dengan board itu — user dari company lain tidak bisa ditambahkan, meski technically FK `user_id` di tabel ini tidak membatasi itu (validasi di level aplikasi, sama seperti pola scoping lain di codebase ini, mis. `m_approval_flows.company_id`).

### 3.5 `kanban_task_members` — Multi-Assignee (Dikonfirmasi)

```php
Schema::create('kanban_task_members', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('task_id')->constrained('kanban_tasks')->cascadeOnDelete();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['task_id', 'user_id']);
});
```

1 task bisa dikerjakan bareng lebih dari 1 orang. `kanban_tasks.assigned_to` tetap ada sebagai penanggung jawab utama/PIC (dipakai buat tampilan ringkas & notifikasi kalau nanti dibutuhkan), `kanban_task_members` sebagai daftar lengkap semua orang yang terlibat (termasuk `assigned_to` itu sendiri, supaya query "task saya" tinggal `whereHas('members', fn($q) => $q->where('user_id', auth()->id()))` tanpa perlu union dua kolom beda).

### 3.6 `kanban_task_comments` — Komentar per Task

```php
Schema::create('kanban_task_comments', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('task_id')->constrained('kanban_tasks')->cascadeOnDelete();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->text('comment');
    $table->timestamps();
});
```

Flat list per task, diurutkan `created_at`, tanpa threading/reply.

### 3.7 `kanban_task_attachments` — Unggah Gambar ke Task

```php
Schema::create('kanban_task_attachments', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('task_id')->constrained('kanban_tasks')->cascadeOnDelete();
    $table->foreignUlid('uploaded_by')->constrained('users')->cascadeOnDelete();
    $table->string('file_path');
    $table->string('file_name')->nullable();
    $table->timestamps();
});
```

Pola sama persis dengan `task_attachments` yang sudah dipakai `task_records` (`app/Models/Task/Task.php` — `file_path` disimpan lewat `Storage::disk('public')`, folder disarankan `kanban/{task_id}/attachments`). Validasi tipe file (jpg/png/webp, ukuran maks) dilakukan di **level aplikasi** (form request), bukan di DB — kolom `file_path` generik string, bukan enum tipe file, konsisten dengan pola `task_attachments`/`task_proofs` yang sudah ada.

Siapa yang boleh unggah: siapa saja yang punya akses ke task itu (member board-nya, atau pemilik task kalau individual) — sama seperti aturan komentar di §3.6.

## 4. Task Individual (Tanpa Board)

Task dengan `board_id = null` otomatis jadi to-do pribadi — hanya muncul di list personal user, tidak nongol di board manapun. Pola yang sama seperti `is_self_assigned` di `task_records`: 1 tabel, cabang logic beda tergantung ada/tidaknya `board_id`.

## 5. Apakah Ada Perubahan ke Tabel yang Sudah Ada?

**Tidak ada — dan `task_records` tidak "pensiun".** Semua tabel di §3 baru, murni tambahan. `task_records` tetap dipakai apa adanya untuk kasus yang memang butuh approval berjenjang formal (mis. lewat `m_approval_flows`, seperti yang sudah diuji untuk cuti/izin/lembur). Dua sistem hidup berdampingan dengan tujuan beda:

| | `task_records` | `kanban_tasks` |
|---|---|---|
| Filosofi | Formal, approval berjenjang wajib | Bebas, cepat, tanpa approval pihak ketiga |
| Kontrol | Approval engine generik (`m_approval_flows`) | Role board (`owner`/`assigner`) di titik pembuatan |
| Kapan dipakai | Task lintas-departemen yang perlu persetujuan resmi | Kerja tim/individu sehari-hari, kanban board |

Satu-satunya "sentuhan" ke tabel lama adalah **foreign key baru** dari `kanban_tasks.related_task_id` → `task_records.id` (opsional) — kalau suatu saat sebuah kanban task perlu "naik level" jadi task formal berapproval, bisa di-link. Tidak wajib dipakai.

## 6. Ringkasan Migration yang Perlu Dibuat

1. `create_kanban_boards_table`
2. `create_kanban_tasks_table`
3. `create_kanban_board_members_table`
4. `create_kanban_task_members_table`
5. `create_kanban_task_comments_table`
6. `create_kanban_task_attachments_table`

Ditambah model Eloquent (`app/Models/Kanban/*.php`).

## 7. Status Keputusan

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Hak akses bikin board | ✅ Reuse `User::canAccessKaryawanMenu()` — sama seperti syarat akses MyStaff |
| 2 | Multi-assignee | ✅ Ya, lewat `kanban_task_members` |
| 3 | Notifikasi saat assign/pindah status | ✅ Tanpa notifikasi dulu (bisa ditambah belakangan) |
| 4 | Approval terpisah (opsi A/B) | ✅ Tidak perlu — pembuat task = approver, dibuang (§3.3) |
| 5 | Approver default | ✅ Pembuat task itu sendiri |
| 6 | Siapa boleh naikkan orang jadi `assigner` | ✅ Cuma `owner` |
| 7 | Unggah gambar ke task | ✅ Ya, lewat `kanban_task_attachments` (§3.7) |
| 8 | Siapa boleh menambahkan member baru ke board | ✅ Cuma `owner` |
| 9 | Task orphan saat member dikeluarkan dari board | ✅ Dibiarkan apa adanya, tidak auto-unassign |
| 10 | Konsistensi `assigned_to` vs `kanban_task_members` | ✅ Wajib — `assigned_to` selalu diinsert juga ke `kanban_task_members` |
| 11 | Siapa boleh hapus task | ✅ Cuma `created_by` atau `owner` board |
| 12 | Nilai `priority` | ✅ Dihardcode juga: `low`/`medium`/`high`/`urgent`, default `medium` |
| 13 | Board lintas-company | ✅ Tidak — 1 board = 1 company, dari `company_id` milik `owner_id` |
| 14 | UI (Livewire board kanban) | 📝 Draf awal di §8 — **masih bisa berubah**, belum final |

## 8. Rancangan UI (Draf Awal — Bisa Direvisi)

Mengikuti konvensi yang sudah mapan di `DESIGN.md`, bukan bikin pattern baru. **Ini draf, belum final** — dicatat di sini supaya ada titik acuan kalau ada revisi nanti.

1. **Identity color baru untuk domain "Kanban"** — sesuai aturan DESIGN.md §"Card identity system" ("jangan pakai warna domain lain untuk fitur baru, tapi jangan juga bikin warna baru buat sesuatu yang sudah cocok ke domain yang ada"). Kanban ini domain baru (project management), belum ada warna identity yang dipakai untuk itu di app ini. Usul: **teal gelap `#0f766e`** sebagai gradient card (pola sama seperti `.welcome-card`/`.absen-card` — gradient solid, white text, `.{prefix}-deco-*`).

2. **List board (halaman awal)** — desktop pakai `.data-table` atau grid card; mobile pakai `.pwa-list`+`.pwa-list-row`+`.pwa-tile-icon`, pola yang sama persis dengan `MyAttendanceRequests`/`MyEmployees` (referensi eksplisit di `DESIGN.md:107` — "every page must have a mobile layout, not just a shrunk desktop one"). Tombol "+ Board Baru" cuma muncul kalau `User::canAccessKaryawanMenu()` — reuse guard yang sama dengan MyStaff (§3.1).

3. **Board detail (kanban view)** — bagian paling beda dari halaman lain karena kolom kanban gak bisa "diperkecil" ke mobile begitu saja:
   - **Desktop**: 4 kolom (`todo`/`in_progress`/`review`/`done`) side-by-side, `display:flex`, tiap kolom scroll vertikal sendiri kalau card-nya banyak.
   - **Mobile**: **bukan** kolom di-scroll horizontal (buruk buat thumb) — pakai pola `.section-nav`+`.section-nav-btn` yang sudah dipakai MyStaff (pill button per status buat switch tab), tampilkan cuma 1 status sekaligus sebagai list `.pwa-list-row`. Konsisten dengan rule "mobile ≠ desktop yang disusutkan" di `DESIGN.md:103-113`.

4. **Task card di dalam kolom** — versi ringkas dari `.pwa-list-row`: judul, avatar-inisial assignee (bisa lebih dari satu kalau `kanban_task_members` terisi banyak), badge due date reuse `.badge-rejected`/`.badge-pending` yang sudah ada (merah kalau overdue, kuning kalau mepet) — **jangan** bikin warna badge baru.

5. **Role badge** (`owner`/`assigner`/`member` dari `kanban_board_members`) — reuse komponen `.badge`, cukup tambah varian warna baru yang konsisten dengan badge yang sudah ada.

6. **Entry point navigasi** — tambah item di `<x-nav>` desktop (gated `canAccessKaryawanMenu()`, sama seperti MyStaff, lihat `nav.blade.php:23-27`) dan di `.pwa-menu-grid` bottom-sheet mobile (`.pwa-menu-grid-item` tile warna teal) — **bukan** masuk 5 slot tetap bottom-nav (sudah penuh & fixed per `DESIGN.md:96`).

7. **Form bikin board/task** — reuse `.form-label`/`.form-input` standar yang sudah ada, jangan reinvent styling form (`DESIGN.md:82`).

## Referensi Terkait
- `app/Models/Task/Task.php`, `app/Services/Task/TaskService.php`, `app/Http/Controllers/Api/Task/TaskController.php` — sistem task-request-dengan-approval yang sudah ada, jadi pembanding.
- `app/Models/User.php:150-153` (`canAccessKaryawanMenu`) — syarat akses yang di-reuse untuk hak bikin board.
- `resources/views/components/nav.blade.php:23-27` — tempat kondisi MyStaff dipakai di nav, pola yang sama untuk kanban nanti.
- `resources/views/livewire/partials/home-cards.blade.php` — widget "Task Saya" di dashboard yang masih placeholder statis.
- `DESIGN.md` — design system portal karyawan, acuan utama §8 (Rancangan UI).
