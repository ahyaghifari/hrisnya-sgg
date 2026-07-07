# Manajemen Persetujuan (Approval Management) — Referensi Teknis (2026-07-05)

Dokumen ini murni referensi teknis: **tabel apa saja yang terlibat, bagaimana relasinya, dan bagaimana alurnya** — dari pengajuan dibuat sampai selesai (disetujui/ditolak/dibatalkan). Ditulis dari pembacaan kode langsung (migration, model, service), bukan asumsi. Untuk analisis gap bisnis/data riil (siapa yang belum ke-assign flow, dsb), lihat dokumen terpisah [`approval-flow-role-access.md`](approval-flow-role-access.md) — dokumen ini fokus ke strukturnya saja.

## Ringkasan

Sistem approval di project ini adalah **engine generik yang dipakai lintas modul** — bukan cuma buat cuti/izin/lembur. `module_type` di `approval_snapshots` bisa berupa `attendance_request`, `task_request`, `swap_shift_request`, `jobposition_request`, `lms_course_request`, `approval_candidate_applicant`, `user_transition`, `additional_employee_request`, `employee_replacement_request` — tapi baru **3 modul** (`attendance_request`, `task_request`, `swap_shift_request`) yang punya efek samping nyata di kode (`ApprovalFlowService::advanceStep()`), sisanya baru terdaftar sebagai opsi `module` di form admin.

Ada 2 sisi tabel yang perlu dibedakan:
- **Sisi konfigurasi** (dibuat admin lewat Filament, jarang berubah) — "alur approval-nya seperti apa".
- **Sisi runtime** (dibuat otomatis tiap ada pengajuan baru) — "progress approval pengajuan ini sudah sampai mana".

## 1. Tabel & Skema

### 1.1 Sisi Konfigurasi

**`m_approval_flows`** — 1 baris = 1 alur approval, terikat ke 1 company + 1 modul.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `company_id` | ulid, FK → `m_companies.id` | cascade delete |
| `name` | string | nama alur, bebas (mis. "Alur Perizinan IT") |
| `module` | string | modul target (`attendance_request`, dst) |
| `flow_type` | enum(`default`, `specific`) | `default` = 1 per company+module (fallback), `specific` = di-assign ke job_position tertentu |
| `description` | text, nullable | |
| `is_active` | boolean | |
| `updated_by` | ulid, FK → `users.id` | nullable, null on delete |
| `deleted_at` | timestamp | soft delete |

**`m_approval_flow_steps`** — 1 baris = 1 langkah approval dalam sebuah flow.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `flow_id` | ulid, FK → `m_approval_flows.id` | cascade delete |
| `step_order` | unsigned int | urutan step (1, 2, 3, ...) |
| `approver_type` | enum(`role`, `user`, `job_position`) | siapa yang approve step ini |
| `approver_ref_id` | string | ID target — **bukan FK sungguhan**, isinya ID role/user/job_position tergantung `approver_type` (polymorphic ala-kadarnya, tanpa constraint DB) |
| `approval_mode` | enum(`any`, `all`) | kalau approver_type=job_position otomatis `all`, selain itu `any` (auto-derive, tidak bisa diatur manual dari form admin) |
| `scope_type` | enum(`same_department`, `specific_department`, `global`) | pembatas tambahan (lihat catatan di §5 — `same_department` **tidak benar-benar difilter** di kode) |
| `scope_ref_id` | ulid, FK → `m_org_structures.id`, nullable | dipakai kalau `scope_type = specific_department` |
| `is_final` | boolean | step terakhir? |

**`m_approval_flow_assignments`** — cuma dipakai kalau `flow_type = specific`, menghubungkan flow ke job_position yang memakainya.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `approval_flow_id` | ulid, FK → `m_approval_flows.id` | cascade delete |
| `assignable_id` / `assignable_type` | ulid morph | saat ini SELALU `App\Models\Master\JobPositions` |

Unique constraint `(approval_flow_id, assignable_id, assignable_type)` — 1 flow tidak bisa di-assign dobel ke job_position yang sama.

### 1.2 Sisi Runtime

**`approval_snapshots`** — 1 baris = 1 pengajuan yang lagi/sudah diproses. Dibuat sekali saat submit, isinya **beku** (copy dari flow saat itu, bukan live-reference).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `module_type` | string | penentu jenis pengajuan (`attendance_request`, dst) — bukan FK, cuma diskriminator |
| `module_id` | ulid | ID baris di tabel modul (mis. `attendance_requests.id`) — juga bukan FK sungguhan |
| `flow_id` | ulid, FK → `m_approval_flows.id` | flow yang dipakai |
| `flow_name` | string | copy nama flow saat itu (denormalized) |
| `steps_snapshot` | json (cast `array`) | **copy beku** seluruh steps dari `m_approval_flow_steps` saat pengajuan dibuat — perubahan di master flow SETELAHNYA tidak otomatis ikut ke pengajuan yang sudah berjalan |
| `status` | enum(`in_review`, `approved`, `rejected`, `cancelled`) | ejaan **2 L** `cancelled` |
| `current_step_order` | integer, nullable | step yang sedang aktif |
| `company_id` | ulid, nullable | |
| `created_by` | ulid, nullable | |

**`approval_activities`** — 1 baris = "step yang sedang/terakhir diproses" untuk 1 snapshot. **Bukan histori** — barisnya di-update terus di tempat yang sama, tidak nambah baris baru tiap step naik (relasi ke snapshot adalah `hasOne`, bukan `hasMany`).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `snapshot_id` | ulid, FK → `approval_snapshots.id` | cascade delete |
| `current_step_order` | integer, nullable | |
| `approver_type` | enum(`role`, `user`, `job_position`), nullable | |
| `approver_ref_id` | string, nullable | |
| `scope_type` | string, nullable | (plain string di tabel ini, bukan enum — beda dari `m_approval_flow_steps.scope_type` yang enum) |
| `scope_ref_id` | string, nullable | |
| `status` | enum(`pending`, `created`, `approved`, `rejected`, `skipped`, `canceled`) | ejaan **1 L** `canceled`. `created`/`skipped` terdaftar di enum tapi **tidak pernah ditulis** di kode manapun |
| `note` | text, nullable | |
| `acted_by` | ulid, FK → `users.id`, nullable | |
| `acted_at` | timestamp, nullable | |

**`approval_histories`** — log lengkap, append-only (satu-satunya tabel di sistem ini yang benar-benar jadi audit trail penuh).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `snapshot_id` | ulid, FK → `approval_snapshots.id` | cascade delete |
| `activity_id` | ulid, FK → `approval_activities.id`, nullable | null on delete |
| `action` | string bebas | `requested`, `approved`, `rejected`, `cancelled`, `completed`, `flow_updated`, `activity_restored` |
| `actor_id` | ulid, FK → `users.id`, nullable | null on delete |
| `actor_name` | string, nullable | denormalized (tetap kebaca walau user dihapus) |
| `note` | text, nullable | |
| `metadata` | json (cast `array`), nullable | |
| `requestable_id` / `requestable_type` | ulid morph | **morphTo sungguhan** (beda dari `module_id`/`module_type` di snapshot yang cuma string biasa) — diisi sama persis dengan `module_id`/`module_type` snapshot-nya |

### 1.3 Tabel Modul (contoh: `attendance_requests`)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `user_id` | ulid, FK → `users.id` | cascade delete |
| `type` | enum(`leave`, `permission`, `overtime`) | |
| `reason` | text, nullable | |
| `status` | enum(`submitted`, `in_review`, `approved`, `rejected`, `cancelled`, `failed`) | |
| `approved_by` / `approved_at` | ulid / timestamp, nullable | |
| `cancelled_by` / `cancelled_at` | ulid / timestamp, nullable | |
| `is_flow_changed` | boolean, default false | **tidak pernah di-set `true` di kode manapun** — kolom menganggur |

⚠️ **Tidak ada kolom `rejected_by`/`rejected_at`** di tabel ini — lihat temuan penting di §5.

## 2. Relasi Antar Tabel

```
m_approval_flows  (config, per company+module)
   │
   ├─ 1:N ──> m_approval_flow_steps        (step_order, approver_type, scope_type, is_final)
   └─ 1:N ──> m_approval_flow_assignments  (assignable → job_position, hanya flow_type=specific)

──────────── saat user submit pengajuan, sistem resolve flow di atas lalu bikin: ────────────

approval_snapshots  (1 pengajuan = 1 snapshot, steps beku dari flow saat itu)
   │
   ├─ 1:1 ──> approval_activities   (SATU baris "step aktif saat ini", terus di-update)
   └─ 1:N ──> approval_histories    (log lengkap, append-only: requested/approved/rejected/...)

approval_snapshots.module_id  ──(module_type = 'attendance_request')──>  attendance_requests.id   (string biasa, bukan FK)
approval_histories.requestable_id/type  ──(morphTo)──>  attendance_requests   (relasi polymorphic sungguhan)
```

Relasi lewat Eloquent (`app/Models/ApprovalManagement/*`, semua pakai `HasUlids`):

| Model | Relasi |
|---|---|
| `MasterApprovalFlows` | `company()` belongsTo, `steps()` hasMany (`MasterApprovalFlowSteps`, urut `step_order`), `assignments()` hasMany (`ApprovalFlowAssignments`) |
| `MasterApprovalFlowSteps` | `flows()` belongsTo `MasterApprovalFlows` |
| `ApprovalFlowAssignments` | `approvalFlow()` belongsTo `MasterApprovalFlows` — **tidak ada** relasi `assignable()` morphTo, dibaca manual per query |
| `ApprovalSnapshots` | `activity()` hasOne `ApprovalActivities` — **tidak ada** relasi `module()`/histories di sini, diakses dari sisi model modul |
| `ApprovalActivities` | `snapshot()` belongsTo `ApprovalSnapshots` |
| `ApprovalHistories` | `requestable()` morphTo |
| `AttendanceRequests` | `approvalSnapshot()` hasOne `ApprovalSnapshots` (key `module_id`, filter `module_type='attendance_request'`), `approvalHistories()` hasMany `ApprovalHistories` (key `requestable_id`, **tidak** ikut filter `requestable_type` — celah kecil, lihat §5) |

## 3. Alur (Flow)

### 3.1 Submit pengajuan

File: `AttendaceReqService.php` (`storeLeaveRequest`/`storePermissionRequest`/`storeOvertimeRequest` — strukturnya identik untuk ketiganya).

1. **Resolve flow** — `ApprovalSnapshotRepository::resolveApprovalFlowForUser('attendance_request', $companyId, $userId)`:
   - Ambil `job_position_id` user dari `user_profiles`.
   - Cari flow `flow_type=specific` yang aktif, modul cocok, company cocok, dan di-assign ke job_position tsb lewat `m_approval_flow_assignments`.
   - Kalau tidak ada → fallback ke flow `flow_type=default` company tsb.
   - Kalau dua-duanya tidak ada → `throw new \Exception(...)`, pengajuan GAGAL total (tidak ada fallback diam-diam).
2. Insert baris `attendance_requests` (`status = in_review`) + baris detail (leave/permission/overtime).
3. **`ApprovalCreationService::createSnapshot()`** (1 `DB::transaction()`):
   - Copy `$flow->steps` jadi `steps_snapshot` (beku).
   - Insert `approval_snapshots` (`status = in_review`, `current_step_order` = step pertama).
   - Kalau ada step pertama → insert **1 baris** `approval_activities` (`status = pending`, `acted_by` = user pengaju — cuma buat timestamp, bukan berarti dia approver).
   - Insert `approval_histories` (`action = 'requested'`, note "User membuat pengajuan").
4. `NotificationService::getFirstApproverFromSnapshot()` cari siapa yang perlu dikabari, kirim notifikasi.

### 3.2 Approve

Dipicu dari `AttendanceRequestDetail::confirmAction()` → `ApprovalFlowService::advanceStep($snapshot, $actorId, $note, 'approved')`.

Semua dalam 1 `DB::transaction()`, urutan tulis selalu sama:

1. **Selalu duluan**: insert `approval_histories` (`action = 'approved'`) mencatat aksi yang baru terjadi, mereferensi activity yang (sebentar lagi) akan di-timpa.
2. **Kalau masih ada step berikutnya** (`getNextApprover()` nemu step dengan `order = current_step_order + 1`):
   - **Update** (bukan insert) baris `approval_activities` yang sama → isi approver step berikutnya, `status` tetap `pending`.
   - Update `approval_snapshots.current_step_order` naik satu, `status` tetap `in_review`.
3. **Kalau ini step terakhir (final)**:
   - `approval_activities.status = approved`.
   - `approval_snapshots.status = approved`.
   - Update tabel modul: `attendance_requests.status = approved`, `approved_by`, `approved_at`.
   - Efek samping khusus modul lain: `task_request` → assign+set jatuh tempo+`in_progress`; `swap_shift_request` → set `approved` lalu **benar-benar tukar** `shift_id`/`user_id` antar 2 baris `shift_schedule_assignments` (dengan re-check konflik, `throw RuntimeException` kalau ada jadwal bentrok yang muncul setelah pengajuan dibuat).
   - Insert 1 baris `approval_histories` lagi (`action = 'completed'`).

### 3.3 Reject

Sama seperti approve, tapi:
- `approval_activities.status = rejected`.
- `approval_snapshots.status = rejected`.
- Update tabel modul — **di sinilah ditemukan bug**: kode coba set `attendance_requests.rejected_by`/`rejected_at`, tapi **kolom itu tidak ada** di tabel & tidak ada di `$fillable` model. Laravel diam-diam buang 2 field itu (tidak error) — cuma `status` yang benar-benar tersimpan. Jejak siapa/kapan reject cuma ada di `approval_activities.acted_by/acted_at` dan `approval_histories`, **tidak** di baris `attendance_requests` itu sendiri.
- `task_request` → `TaskStatus::REJECTED`; `swap_shift_request` → `status = rejected`.
- Insert `approval_histories` (`action = 'completed'`).

### 3.4 Cancel

- `approval_activities.status = canceled` (**1 L**).
- `approval_snapshots.status = cancelled` (**2 L** — beda ejaan, lihat §5).
- `attendance_requests.status = cancelled`, `cancelled_by`, `cancelled_at` — kolom ini **memang ada**, jadi cancel tersimpan lengkap (beda dari reject yang bermasalah di atas).
- Insert `approval_histories` (`action = 'completed'`).

### 3.5 Siapa yang boleh approve (`ApprovalPermissionService`)

- `canApprove()` — activity harus `status=pending` dulu, baru cek `checkUserPermission()`.
- `checkUserPermission()` dispatch berdasar `approver_type`:
  - `user` → cocok persis `approver_ref_id === $user->id`.
  - `role` → `$user->hasRole(...)`; kalau `scope_type=specific_department` → tambahan syarat departemen user harus cocok `scope_ref_id`. **`same_department` tidak benar-benar mengecek "departemen yang sama dengan pengaju"** — efeknya sama seperti `global` di kode ini.
  - `job_position` → `$user->profile?->job_position_id === $activity->approver_ref_id`.
- `pendingApprovalsFor($user, $moduleType)` — query semua activity `pending` yang cocok ke user (langsung/role/job_position), lalu disaring ulang lewat `checkUserPermission()` (menghindari duplikasi logic scope) → ini yang dipakai widget "Butuh Persetujuan Anda".

### 3.6 Timeline visual (`ApprovalTimelineService::build()`)

- Load `approvalSnapshot.activity`, `approvalHistories`, `user` dari record modul.
- Entry pertama selalu "Diajukan" (state `done`).
- Tiap step: kalau ada histori yang cocok (`approved`/`rejected`/`cancelled` di `metadata['step_order']`) → pakai itu; kalau belum, tandai `current` (kalau ini step aktif snapshot & status masih `in_review`) atau `pending`.

### 3.7 Sinkronisasi manual (`checkStepsSync`/`syncStepsFromMaster`)

Bukan bagian dari alur approve/reject/cancel — ini alat **perbaikan manual** kalau admin ubah flow master padahal sudah ada pengajuan berjalan di flow lama:
- `checkStepsSync()` — bandingkan `steps_snapshot` vs master flow saat ini, return `false` kalau beda.
- `syncStepsFromMaster()` — timpa `steps_snapshot` dari master terbaru, reset `current_step_order` & `status`, log `action = 'flow_updated'`/`'activity_restored'`.

**Tidak ada cron/observer yang manggil ini otomatis** — konsisten dengan `is_flow_changed` yang juga menganggur.

## 4. Konfigurasi Admin (Filament)

`app/Filament/Resources/ApprovalFlows/` — form di `Schemas/ApprovalFlowForm.php`.

**Level flow:**
- `company_id` (hidden, ambil dari user login), `name`, `module` (dropdown modul yang didukung), `flow_type` (`default`/`specific`).
- Validasi custom: maksimal 1 flow `default` per company+module; tidak boleh ada flow `specific` dobel buat job_position yang sama.
- `assignment_job_positions` (multi-select, cuma muncul kalau `flow_type=specific`) → ditulis ke `m_approval_flow_assignments`.

**Level step** (`inputStepSchema()`):
- `approver_type` (role/job_position/user), `approver_ref_id`.
- `source_company` (`same_company`/`holding_company`) — **hanya filter UI**, tidak pernah disimpan ke DB. Cuma mengubah opsi Role/User/JobPosition yang ditampilkan saat memilih approver (ID holding company di-hardcode langsung di kode form).
- `scope_type` — form cuma menyediakan `same_department`/`specific_department` (opsi `global` ada di enum DB tapi tidak ditawarkan form).
- `scope_ref_id` (muncul kalau `specific_department`).
- `is_final` (toggle).
- `approval_mode` — hidden, auto: `all` kalau `approver_type=job_position`, selain itu `any`.

Saat disimpan (`EditApprovalFlow::afterSave()`): **semua step lama dihapus dan ditulis ulang dari form** — step tidak punya identitas stabil lintas edit selain `step_order`.

## 5. Temuan Penting / Catatan (Known Gaps)

1. **Bug nyata — reject tidak tersimpan lengkap**: `attendance_requests` tidak punya kolom `rejected_by`/`rejected_at`, tapi `ApprovalFlowService::advanceStep()` mencoba menulis ke situ. Laravel diam-diam membuang field yang bukan `$fillable` (tidak error) — cuma `status` yang benar-benar berubah. (`app/Services/Approval/ApprovalFlowService.php`, `app/Repositories/Attendance/AttendanceReqRepository.php::updateAttendanceRequest()`)
2. **Ejaan enum beda antar tabel** — `approval_activities.status` pakai `canceled` (1 L), `approval_snapshots.status` pakai `cancelled` (2 L). Sudah dikomentari eksplisit di kode (`ApprovalFlowService.php` baris ~121) — bukan typo yang lolos tanpa sadar, tapi tetap berisiko kalau ada yang query manual pakai ejaan yang salah.
3. **`scope_type = same_department` tidak difilter sungguhan** di `checkUserPermission()`/`checkRolePermission()` — cuma `specific_department` yang benar dibatasi. Kalau step pakai `role` + scope "Satu Departemen", perilakunya sama persis seperti "Global".
4. **Inkonsistensi kecil di `getDeniedReason()`** vs `checkRolePermission()` — yang satu cek `$user->profile?->department?->id`, satunya cek `$user->profile?->orgStructure?->id`, buat scope yang sama (`specific_department`). Juga `getDeniedReason()` baca `$user->job_position_id` langsung (kemungkinan selalu null, karena job position ada di `$user->profile`, bukan di model `User`).
5. **`flow_version` ditulis tapi tidak pernah tersimpan** — `ApprovalCreationService::createSnapshot()` menyisipkan key `flow_version` ke array create, tapi kolom itu tidak ada di migration `approval_snapshots` maupun `$fillable` — dibuang diam-diam.
6. **`approval_activities` bukan histori** — cuma 1 baris per snapshot yang terus di-update. Kalau butuh lihat "siapa approve step 1, siapa approve step 2", itu ada di `approval_histories`, bukan di `approval_activities`.
7. **`AttendanceRequests::approvalHistories()` tidak filter `requestable_type`** — cuma filter `requestable_id`. Secara teori (bukan praktik, ULID nyaris mustahil bentrok) longgar dibanding morph relation yang benar.
8. **`is_flow_changed`, `checkStepsSync()`, `syncStepsFromMaster()`** semuanya alat manual yang sudah dibuat tapi belum pernah dipanggil otomatis di mana pun — kalau admin edit flow yang sudah punya pengajuan berjalan, snapshot lama TIDAK otomatis ter-update sampai ada yang manggil alat ini secara manual.

## 6. Referensi File Kode

- `app/Models/ApprovalManagement/` — `MasterApprovalFlows`, `MasterApprovalFlowSteps`, `ApprovalFlowAssignments`, `ApprovalSnapshots`, `ApprovalActivities`, `ApprovalHistories`
- `app/Models/Attendance/AttendanceRequests.php` — relasi `approvalSnapshot()`/`approvalHistories()`
- `app/Repositories/Approval/ApprovalSnapshotRepository.php` — `resolveApprovalFlowForUser()`
- `app/Services/Approval/ApprovalCreationService.php` — `createSnapshot()`
- `app/Services/Approval/ApprovalFlowService.php` — `getNextApprover()`, `advanceStep()`, `checkStepsSync()`, `syncStepsFromMaster()`
- `app/Services/Approval/ApprovalPermissionService.php` — `checkUserPermission()`, `canApprove()`, `hasPermission()`, `pendingApprovalsFor()`, `getDeniedReason()`
- `app/Services/Approval/ApprovalTimelineService.php` — `build()`
- `app/Services/Attendance/AttendaceReqService.php` — pemanggil resolve flow untuk cuti/izin/lembur
- `app/Filament/Resources/ApprovalFlows/` — konfigurasi flow lewat admin panel
- Migration: `2025_10_23_204835_create_m_approval_flows_table.php`, `2025_10_23_204844_create_m_approval_flow_steps_table.php`, `2026_02_20_132321_add_column_default_m_approval_table.php`, `2025_10_26_000441_create_approval_snapshots_table.php`, `2025_10_26_000530_create_approval_activities_table.php`, `2025_10_26_000537_create_approval_histories_table.php`, `2025_11_03_103111_add_requestable_morph_approval_histories_table.php`
