# Planning: Lanjutan Modul Kanban + Pengelolaan Admin via Filament — 2026-07-14

Status: **rencana, belum dieksekusi.** Lanjutan dari implementasi awal (`planning/2026-07-13-modul-kanban-project-task.md`, commit "add kanban" x4) dan revisi sesi 2026-07-13 (`knowledge/changes/2026-07-13-modul-kanban-verifikasi-dan-revisi.md`). Modul portal (`/kanban/*`) sudah jalan dan diuji (47 test di `tests/Feature/KanbanModuleTest.php`, termasuk gating status `review` yang baru ditambah 2026-07-14). Dokumen ini merangkum **sisa pekerjaan portal** yang sudah teridentifikasi + **rancangan baru**: panel admin Filament untuk kanban, yang sampai sekarang belum ada sama sekali (admin tidak punya visibilitas ke board/task siapapun).

## Bagian 1 — Sisa Pekerjaan Portal (dari "Belum Dikerjakan / Ditunda")

Diambil dari `knowledge/changes/2026-07-13-modul-kanban-verifikasi-dan-revisi.md` §"Belum Dikerjakan", urutan disarankan berdasar dampak vs effort:

1. **Checklist & komentar untuk task individual di BoardKu** (`KanbanPersonalTasks`) — saat ini task pribadi/titipan di `/kanban/pribadi` tidak punya modal detail sama sekali, beda dari task di board yang punya checklist+komentar di card. Effort kecil: reuse komponen yang sama persis dari `KanbanBoardDetail` (checklist expand/collapse di card, form komentar), tinggal pindah ke `kanban-personal-tasks.blade.php` + tambah method `addItemToTask`/`toggleItem`/`addCommentToTask` di `KanbanPersonalTasks.php` (bisa copy dari `KanbanBoardDetail.php`, otorisasi `canManageItems()` sudah `board_id`-agnostic).
2. **History perubahan warna board** — kolom `color` sudah bisa diubah owner (§6 sesi 2026-07-13), tapi tidak ada log siapa/kapan ganti. Kalau dibutuhkan: tabel kecil `kanban_board_color_logs` (`board_id`, `changed_by`, `old_color`, `new_color`, `created_at`) diisi di `updateColor()`. **Prioritas rendah** — tidak pernah diminta eksplisit, cuma dicatat sebagai gap.
3. **Drag-and-drop asli** — masih tombol/dropdown "pindah status". Butuh library JS (SortableJS atau Alpine plugin sejenis) — dependency baru, belum ada di project. Kalau dieksekusi: pasang di card kolom kanban (`kanban-board-detail.blade.php`), event drop panggil `moveTaskStatus` yang sama (otorisasi backend sudah benar, tidak perlu diubah — drag cuma ganti cara trigger di frontend).
4. **Broadcasting realtime** — polling `wire:poll.10s` dipakai sekarang. Upgrade ke push instan butuh Laravel Reverb + Echo (project belum pasang, `BROADCAST_CONNECTION=log`). **Paling besar effort-nya**, sengaja ditaruh terakhir — infrastruktur baru (queue worker untuk broadcasting, config `.env`), bukan cuma kode fitur.

Poin 1 disarankan dikerjakan duluan (paling murah, paling terasa bagi user — staff yang dapat task titipan saat ini tidak bisa checklist/komentar progresnya).

## Bagian 2 — Panel Admin Filament untuk Kanban (fitur baru)

### Kenapa

Semua modul lain (Attendance, Payroll, LMS, dst) punya Resource Filament untuk admin pantau/kelola dari `/admin`. Kanban satu-satunya modul portal yang **tidak** punya padanan admin — kalau ada board/task bermasalah (misal butuh dihapus paksa, member salah pasang, atau HR mau lihat progress kerja tanpa buka portal per-user), tidak ada jalan lewat admin panel sama sekali.

### Lingkup — read-mostly monitoring, bukan alat kerja

Kanban itu **alat kerja tim** (drag/pindah status, komentar, checklist) — pola interaksinya cocok di portal (Livewire, real-time-ish), **bukan** di Filament Table admin. Jangan bangun ulang board 4-kolom di admin panel. Yang dibutuhkan admin cuma:
- Lihat semua board + task lintas user (HR/manajemen butuh visibilitas, bukan ikut kerja di board-nya).
- Cari task/board tersangkut (assign salah, stuck di status tertentu lama).
- Hapus board/task bermasalah tanpa harus jadi member/owner-nya (situasi: user resign, owner board tidak aktif lagi).
- Kelola member board kalau owner tidak bisa dihubungi (situasi darurat).

Aksi *mengerjakan* task (pindah status, checklist, komentar) **tetap eksklusif di portal** — tidak direplikasi ke Filament.

### A. `KanbanBoardResource` (`app/Filament/Resources/Kanban/`)

- Model: `KanbanBoard`. Table kolom: nama, `owner.name`, `company.name`, jumlah member, jumlah task, warna (swatch kecil), `created_at`.
- Filter: per company (kalau admin bukan holding — scoping ikut pola `getEloquentQuery()` yang sudah dipakai resource lain, lihat `ApprovalFlowResource::getEloquentQuery()` sebagai contoh).
- Relation manager: `MembersRelationManager` (tabel `kanban_board_members` — tambah/hapus member, ubah role `owner`/`assigner`/`member` langsung dari sini, guna kasus darurat di atas) dan `TasksRelationManager` (read-only list task di board itu, klik untuk lihat detail via `ViewKanbanTask` — **bukan** edit status dari sini).
- Action: hapus board (dengan konfirmasi, cascade ke task/member — sudah ada di model lewat FK `onDelete('cascade')`, tinggal pastikan Filament tidak butuh logic tambahan).
- **Tidak ada create/edit form penuh** — admin tidak bikin board baru dari sini (itu tetap alur portal, `canAccessKaryawanMenu`). Cukup `ViewKanbanBoard` (detail) + hapus + kelola member.

### B. `KanbanTaskResource` (`app/Filament/Resources/Kanban/`) — atau cukup nested di atas?

Pertimbangan: task individual (`board_id = null`, termasuk "Kanban Staff") tidak punya board induk, jadi **butuh Resource terpisah** kalau admin mau lihat/cari task ini juga (bukan cuma yang di dalam board).

- Model: `KanbanTask`. Table kolom: judul, board (nullable, tampilkan "Task Pribadi/Titipan" kalau null), `createdBy.name`, `assignedTo.name`, status (badge warna), prioritas, `due_date`.
- Filter: status, prioritas, company (lewat board atau lewat `createdBy.company_id` untuk task tanpa board).
- Search: judul, nama pembuat, nama assignee.
- Action: hapus task (situasi macet/salah assign). **Tidak ada** aksi pindah status dari admin — itu keputusan sadar, biar satu sumber kebenaran alur kerja tetap portal, admin cuma observasi + cleanup.
- View page: tampilkan checklist + komentar + attachment (read-only), supaya admin bisa lihat histori tanpa perlu login sebagai user itu.

### C. Otorisasi (Filament Shield)

Ikuti konvensi yang sudah ada sejak migrasi Shield 2026-07-08 (`planning/2026-07-08-migrasi-filament-shield.md`) — permission auto-generate dari Resource terdaftar, format `Verb:Subject` (`View:KanbanBoard`, `Delete:KanbanTask`, dst), Policy ikut digenerate otomatis. **Jangan** bikin Policy manual. Setelah Resource didaftarkan, jalankan `php artisan shield:generate` supaya permission baru masuk ke `RoleResource` (`/admin/roles`), lalu assign manual ke role yang relevan (kemungkinan HC/Super Admin dulu — ingat catatan di README: role lain masih 0 permission sejak migrasi Shield, jangan lupa assign kalau mau resource ini kepakai).

Company-scoping: reuse pola `getEloquentQuery()` yang sudah dipakai resource lain untuk membatasi ke company admin yang login (kecuali role holding/Direktur) — **jangan** desain skema otorisasi baru, tinggal contek resource company-scoped yang sudah ada (mis. cek bagaimana `AttendanceRequestResource` atau `EmployeeResource` scoping company-nya).

### D. Navigation

`navigationGroup` baru atau masuk grup existing? Kanban bukan approval/attendance/payroll — kemungkinan besar layak grup sendiri **"Kanban"** atau masuk ke grup umum "Manajemen Organisasi" kalau tidak mau nambah grup baru di sidebar (keputusan kecil, bisa diputuskan saat eksekusi, lihat daftar `navigationGroup` yang sudah ada di resource lain sebelum menentukan).

### Urutan Eksekusi

1. `KanbanBoardResource` (List + View + `MembersRelationManager` + `TasksRelationManager` read-only + hapus).
2. `KanbanTaskResource` (List + View read-only + hapus) — supaya task tanpa board (individual/titipan) juga kelihatan dari admin.
3. `php artisan shield:generate` — daftarkan permission baru.
4. Assign permission ke role yang relevan lewat `/admin/roles` (manual, bukan otomatis — ingat catatan di README soal 77 role masih 0 permission).
5. Verifikasi manual: login sebagai admin, cek board/task lintas user kelihatan, cek scoping company tidak bocor lintas company (kecuali Direktur holding), cek hapus board cascade ke task/member dengan benar.

## Yang SENGAJA Tidak Direncanakan

- **Board 4-kolom drag-and-drop di admin panel** — duplikasi UI portal tanpa manfaat tambahan, admin bukan pekerja board.
- **Admin bikin board/task baru** — tetap eksklusif portal (`canAccessKaryawanMenu`), admin cuma observasi + cleanup darurat.
- **Aksi pindah status/checklist/komentar dari admin** — biar satu sumber kebenaran alur kerja tetap di portal; kalau admin bisa ubah status task tanpa lewat aturan `canMoveStatus()` portal (lihat `app/Services/Kanban/KanbanAuthorizationService.php`), aturan maju-1-langkah/review-lock jadi bisa dibypass diam-diam dari sisi admin — sengaja tidak dibuka.

## Referensi

- `knowledge/changes/2026-07-13-modul-kanban-verifikasi-dan-revisi.md` — histori revisi & daftar "Belum Dikerjakan" sumber Bagian 1.
- `planning/2026-07-13-modul-kanban-project-task.md` — planning awal modul, skema tabel & model.
- `app/Services/Kanban/KanbanAuthorizationService.php` — aturan otorisasi portal yang **tidak** boleh dibypass dari admin.
- `planning/2026-07-08-migrasi-filament-shield.md` — konvensi permission Shield yang harus diikuti Resource baru.
- `app/Filament/Resources/ApprovalFlows/ApprovalFlowResource.php` — contoh Resource dengan company-scoping & navigationGroup untuk dicontek strukturnya.
