# Manajemen Tugas & Project — Sejarah, State Saat Ini, dan Rencana ke Arah KPI (2026-07-23)

Dokumen ini merangkum satu rangkaian kerja dalam sesi yang sama: menghapus modul Kanban yang baru dibuat, meleburnya kembali ke sistem Task Request yang formal, menambahkan konsep Project di atasnya, dan alasan kenapa jalur ini dipilih ketimbang alternatif — supaya bisa dilanjutkan di sesi lain tanpa perlu menggali ulang percakapan sebelumnya. Untuk detail approval engine generik yang dipakai Task formal, baca [`manajemen-persetujuan.md`](manajemen-persetujuan.md) — dokumen ini fokus ke Task/Project saja.

## Ringkasan Keputusan (kalau cuma sempat baca 1 bagian)

1. **Kanban dihapus total** (`app/Models/Kanban/*`, `app/Livewire/Kanban/*`, 7 tabel `kanban_*`) — modul ini cuma berumur ~10 hari, belum ada data produksi berarti, jadi aman di-drop tanpa migrasi data.
2. **`task_records` (model `Task`) jadi basis tunggal** — bukan Kanban yang di-generalisasi. Alasan: Task formal sudah superset (approval, SLA reviewer, biaya, review/rating) dibanding Kanban yang cuma status+checklist+komentar.
3. **Approval jadi opsional per-task**, bukan wajib seperti Task lama. Default-nya diatur per company lewat `Setting` (mekanisme generik yang sudah ada, bukan tabel config baru), bisa di-override manual di form saat bikin task.
4. **Project adalah entitas ringan**, bukan reinkarnasi board Kanban yang penuh role owner/assigner/member. Cuma 2 tingkat: owner (kelola anggota) dan member (bikin & kerjakan task).
5. **Portal Tugas** (`/tugas`) sekarang punya sidebar ala Lark: scope pribadi (Tugas Saya/Saya Buat/Tim Saya) + daftar Project, dengan toggle tampilan List/Board.
6. **Belum terhubung ke KPI** — semua kerja ini fondasi data & UI-nya saja. Lihat §7 untuk rencana penyambungan.

## 1. Kenapa Kanban Dihapus (bukan sekadar diganti nama)

Kronologi: modul Kanban awalnya dibangun terpisah dari Task (lihat dokumen lama [`integrasi-kanban-project-management.md`](integrasi-kanban-project-management.md), sekarang ditandai superseded) sebagai solusi "task board ala Trello", karena waktu itu dianggap Task formal (`task_records`) terlalu berat (wajib approval) untuk kerja harian.

User lalu minta ditarik ke satu sistem saja — pertimbangannya: dua sistem task yang berbeda konsep (approval formal vs board bebas) membingungkan dan duplikatif. Setelah dibandingkan, `task_records` dipilih sebagai basis karena sudah py approval, SLA, biaya, risk level, review/rating — semua itu justru bahan mentah paling berharga untuk KPI nanti, sementara Kanban cuma prosedural (status + checklist).

**Trade-off yang disadari sejak awal**: mengangkat fitur formal (approval, SLA, biaya) ke atas board berarti field-field itu harus jadi *opsional*, bukan wajib seperti sebelumnya — itu topik §2.

## 2. Approval Opsional — Cara Kerjanya

Dua jalur pembuatan task di `App\Services\Task\TaskService`, dipilih berdasarkan toggle "Butuh Approval" di form:

| | `createCasualTask()` | `createFormalTask()` |
|---|---|---|
| Approval | Tidak ada | Wajib, lewat `ApprovalFlowService` (module `task_request`, sama seperti cuti/izin/lembur) |
| Status awal | `TODO` (langsung masuk board) | `PENDING` (nunggu approve) |
| `service_id`/`department_id` | Tidak wajib (nullable di DB, lihat migration `2026_07_23_063500_...`) | Wajib — kategori layanan dari master data `m_task_services` |
| Assignee | Diri sendiri, bawahan, atau sesama anggota project | Ditentukan approver saat approve (pola lama, tidak berubah) |
| Dipakai dari | Portal (`/tugas`) | Portal (`/tugas`, toggle approval ON) |

**Default toggle per company** (dulu `TaskSettingEnum::REQUIRE_APPROVAL_DEFAULT` via `Setting::resolve()`) — **dihapus 2026-09-21**: halaman `/tugas/pengaturan`, `TaskSettingEnum`, `TaskService::requiresApprovalByDefault()` dan permission `edit_pengaturan_tugas` sudah tidak ada (form Tugas baru tidak memakai default ini).

**Saat approve** (`ApprovalFlowService::advanceStep()`, cabang `module_type === 'task_request'`): task pindah dari `PENDING` langsung ke `TODO` (dulu ke `IN_PROGRESS`) — supaya task yang lolos approval juga masuk board di kolom pertama, konsisten dengan alur casual.

**Mobile API (`/api/tasks`, `/api/tasks/self`) tidak disentuh** — `createTask()`/`createSelfTask()` lama di `TaskService` tetap ada apa adanya, dipakai endpoint API. `createFormalTask()`/`createCasualTask()` adalah versi baru khusus portal (pakai `Auth::user()` / guard web, bukan `auth('sanctum')`).

## 3. Status Board

`App\Enum\Task\TaskStatus` diperluas dari 4 case lama (`PENDING`/`IN_PROGRESS`/`REJECTED`/`COMPLETE`) jadi 6: tambah `TODO` dan `REVIEW`.

Alur: `PENDING` (cuma task formal, nunggu approve) → `TODO` → `IN_PROGRESS` → `REVIEW` → `COMPLETE`. `REJECTED` terminal.

`TaskStatus::BOARD_ORDER` = `[TODO, IN_PROGRESS, REVIEW, COMPLETE]` — subset yang bisa digeser manual di board. `PENDING` sengaja di luar array ini, ditampilkan sebagai kolom terpisah read-only di UI.

**Aturan pindah status** (`TaskService::moveStatus()`):
- Cuma `assigned_to` atau `created_by` yang boleh
- Cuma maju 1 langkah berurutan (`BOARD_ORDER`)
- Boleh mundur 1 langkah khusus dari `REVIEW` → `IN_PROGRESS`
- `COMPLETE` terkunci total, `PENDING` tidak movable sama sekali (nunggu approve)

Ini jauh lebih sederhana dari `KanbanAuthorizationService::canMoveStatus()` yang lama (yang punya konsep role board owner/assigner/member) — karena sekarang syaratnya cuma dua peran (assigned_to/created_by), tidak ada tingkat role tambahan.

**Catatan desain penting**: `status` di model `Task` **sengaja tidak di-cast ke enum** (`protected $casts` di `app/Models/Task/Task.php`) — banyak tempat lain (Filament infolist `TaskRequestForm`, `ApprovalFlowService`, query builder) masih baca/tulis sebagai string mentah. Kalau nanti mau cast, harus audit semua pemakaian `TaskStatus::tryFrom($state)` dulu (state jadi object enum, bukan string, bakal `TypeError`) — ini sempat kejadian pas nge-cast `priority` (lihat §6).

## 4. Project — Desain Final (Bukan Kembali ke Kanban Board)

Keputusan eksplisit dari user: project **cuma wadah pengelompokan + daftar anggota**, bukan sistem role berlapis kayak `kanban_boards` lama (owner/assigner/member + banyak tabel pendukung).

**Skema** (`database/migrations/2026_07_23_0901xx_*`):
- `task_projects` — id, company_id, name, description, color, owner_id, timestamps, soft delete
- `task_project_members` — project_id, user_id, role (`owner`|`member` saja, lihat `TaskProjectMember::ROLE_*`), unique(project_id, user_id)
- `task_records.project_id` — nullable, FK ke `task_projects`, nullOnDelete

**Aturan** (`TaskService`):
- `createProject()` — pembuat otomatis jadi owner (row `task_project_members` role owner), member awal (opsional) langsung masuk role member
- `canAccessProject()` — sekadar cek baris membership ada
- `canManageProjectMembers()` — owner-only, dicek sebelum `addProjectMember()`/`removeProjectMember()`
- Owner project **tidak bisa dikeluarkan** dari project-nya sendiri (`removeProjectMember` abort kalau targetnya `owner_id`)
- Saat bikin task dengan `project_id` diisi: assign ke orang lain kini boleh ke **siapa saja anggota project itu**, bukan cuma bawahan langsung (`createCasualTask()`, cabang `$project ? canAccessProject(...) : subordinates(...)`)

**Yang sengaja tidak dibuat** (kalau nanti dibutuhkan, ini titik mulainya):
- Tidak ada role "assigner" terpisah dari owner/member
- Tidak ada rename/ganti warna project setelah dibuat, atau hapus project
- Tidak ada multi-assignee per task (tetap 1 `assigned_to`)
- Tidak ada project untuk task formal (`createFormalTask()` belum terima `project_id`) — cuma task casual yang bisa masuk project buat sekarang

## 5. Portal UI (`/tugas`)

Satu Livewire component `App\Livewire\Task\TaskListPage` (bukan dipecah per scope/project) karena kontennya (toggle List/Board, modal create/detail task, checklist, komentar) sama persis dipakai baik mode scope maupun mode project — hindari duplikasi.

**Sidebar** (`.doc-type-nav`/`.doc-type-btn`, class CSS yang **sudah ada** dipakai ulang, bukan bikin baru — sama yang dipakai Komponen Gaji, varian warna `-sky`):
- Tugas Saya (`assigned_to = user`) / Saya Buat (`created_by = user`) / Tim Saya (`assigned_to` di antara bawahan, muncul cuma kalau `OrgHierarchyService::subordinates()` tidak kosong)
- Daftar Project yang diikuti (`getMyProjects()`)
- Tombol "Project Baru"

State `$activeProjectId` pakai atribut `#[Url]` Livewire — link ke project tertentu bisa di-bookmark/di-share tanpa perlu route baru per project.

**Board vs List**: toggle murni tampilan di atas data yang sama (`$this->tasks`), bukan dua sumber data berbeda. Board pakai `<select>` dropdown per kartu buat pindah status (bukan native HTML5 drag-and-drop) — sengaja, karena drag-and-drop native kurang reliable di layar sentuh/PWA mobile yang jadi target utama aplikasi ini, dan tidak ada library sortable yang sudah ter-install (ponytail: kalau nanti perlu drag beneran, baru tambah `Sortable.js`).

**Komponen UI yang di-reuse, bukan dibikin baru** (penting biar konsisten sama halaman lain):
- Modal: `.modal-overlay`/`.modal-box` (z-index 9999, di atas nav — sebelumnya sempat pakai inline style `z-index:50` yang ketutupan nav, sudah diperbaiki)
- Checkbox: `.custom-checkbox` (dipakai juga di Komponen Gaji)
- Select dengan banyak opsi: TomSelect (`data-tom-select` + `wire:ignore`, auto-init lewat `resources/js/app.js`, support `multiple` buat "Anggota Awal" project)

## 6. Jebakan yang Sudah Kejadian & Cara Hindarinya

Dicatat karena sempat bikin error runtime pas development, biar tidak terulang:

1. **Cast enum di model vs kode lama yang baca string mentah** — nge-cast `priority` ke `TaskPriority::class` bikin `TaskPriority::tryFrom($task->priority)` di Blade error (`tryFrom()` butuh string, bukan object enum). Fix: baca langsung `$task->priority?->getLabel()` di tempat yang tahu itu sudah enum, JANGAN panggil `tryFrom()` lagi di atasnya.
2. **`due_date` sengaja tidak di-cast ke `date`** — sempat mau ditambah supaya `->translatedFormat()` di Blade jalan, tapi dibatalkan karena `TaskDetailResource`/`TaskListResource` (mobile API) nge-return kolom ini apa adanya; kalau di-cast, format JSON ke mobile app berubah diam-diam jadi ISO datetime. Solusinya `Carbon::parse($task->due_date)` manual di level Blade (helper `$fmtDate` di `task-list-page.blade.php`), bukan sentuh model.
3. **`status` juga sengaja tidak di-cast** — alasan sama seperti due_date: banyak konsumen lain (Filament, ApprovalFlowService) baca sebagai string.

**Pelajaran umum**: kalau mau nambah `$casts` di `Task` model, cari dulu semua pemakaian kolom itu lintas Filament admin + mobile API Resources sebelum nge-cast — model ini dipakai banyak konsumen dengan asumsi tipe data berbeda-beda.

## 7. Rencana ke Arah KPI (belum dikerjakan, ini titik lanjutannya)

Konteks awal permintaan user: analisis kesiapan KPI aplikasi ini dinilai **4/10** — fondasi (org struktur, approval engine, modul Assessment yang sudah ada tapi dormant, Task tracking) cukup kuat, tapi belum ada alur KPI utuh (goal-setting → tracking otomatis → skor → approval → linkage ke insentif). Detail analisis itu ada di riwayat percakapan, belum dituangkan ke file terpisah — kalau mau, bisa diminta dibuatkan dokumen `analisis-kpi.md` tersendiri.

Yang sudah relevan buat KPI dari kerja sesi ini:
- Task sekarang punya `priority`, `due_date`, `completed_date`, `is_overdue` — bisa jadi metrik "ketepatan waktu" & volume kerja
- `TaskReview` (rating + komentar) tetap ada di task formal — sumber sinyal kualitas
- Project bisa jadi unit pengelompokan buat skor per-tim/per-inisiatif

Yang **belum** ada dan jadi PR kalau lanjut ke KPI:
- Tidak ada tabel goal/target periodik (mis. "target bulan ini: selesaikan 10 task prioritas tinggi")
- Modul `Assessment` (`app/Models/Assessment/*`, sudah ada dari awal Juli, dormant, cuma di Filament admin) belum terhubung ke data Task — skor assessment masih harus diisi manual, bukan ditarik otomatis dari `completed_date`/`is_overdue`/`review.rating`
- Belum ada dashboard tren KPI (per periode/departemen/project)
- Belum ada linkage skor KPI → modul Insentif (`keuangan.insentif`) yang sudah ada

**Kalau mau lanjut**: langkah termurah adalah hidupkan lagi modul `Assessment` yang sudah ada (bukan bikin baru) dan sambungkan `AssessmentTemplateItems`-nya ke query agregat dari `task_records` (COUNT completed, AVG overdue days, AVG review rating) sebagai salah satu item skor otomatis — bukan mengganti Task jadi sistem KPI itu sendiri.

## 8. Peta File (referensi cepat)

| Area | File |
|---|---|
| Migration | `database/migrations/2026_07_23_0635xx_*` s/d `090300_*` |
| Enum | `app/Enum/Task/TaskStatus.php`, `TaskPriority.php`|
| Model | `app/Models/Task/Task.php`, `TaskProject.php`, `TaskProjectMember.php`, `TaskChecklistItem.php`, `TaskComment.php` |
| Service | `app/Services/Task/TaskService.php` |
| Livewire portal | `app/Livewire/Task/TaskListPage.php` |
| View | `resources/views/livewire/task/task-list-page.blade.php` |
| Test | `tests/Feature/Task/TaskModuleTest.php` (11 test: status transition, casual/formal creation, project membership & assignment) |
| Approval (disentuh minimal) | `app/Services/Approval/ApprovalFlowService.php` — 1 baris, status approve task jadi `TODO` |
