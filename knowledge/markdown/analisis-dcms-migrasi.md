# Analisis DCMS (Meeting/Notulensi/SOP) untuk Migrasi ke HRIS — 2026-08-12

Dokumen ini merangkum analisis awal project **DCMS** (`another-app/dcms/` di repo yang sama, git repo terpisah) — sistem existing buat setting meeting, notulensi rapat, dan SOP milik Syifa Global Group, yang rencananya mau digabung/dimigrasi ke HRIS ini. Sampai titik ini BARU ANALISIS, belum ada eksekusi migrasi/kode.

**Keputusan yang sudah diambil user:**
- Struktur organisasi DCMS (`Department`/`Unit`) di-map ke `App\Models\Master\OrgStructure` HRIS (tree `m_org_structures`, `parent_id`+`level`, enum `OrgStructurLevel`: `LEVEL_DEPARTMENT`(1)/`LEVEL_DIVISION`(2)/`LEVEL_SUBDIVISION`(3)) — DCMS Department → level 1, DCMS Unit → level 2 (anak Department), level 3 nganggur/opsional.
- Skema `meeting_minutes` (notulensi terstruktur, action_items/decisions) masih mau dieksplorasi lebih lanjut sebelum didesain ulang — JANGAN ikut copy skema DCMS yang ada (dead table, gak dipake).

## 1. Stack & Arsitektur

Laravel 12 + **Filament v4 murni** (bukan Livewire custom kayak HRIS) — full admin panel Filament, cuma 1 controller custom (`NotulenController` buat serve file PDF notulen). Pakai `spatie/laravel-permission` + `bezhansalleh/filament-shield`, pola sama persis HRIS. Tambahan: `barryvdh/laravel-dompdf`, `guava/calendar` (kalender meeting), `tapp/filament-auditing` + `owen-it/laravel-auditing` (audit trail), `spatie/laravel-activitylog`.

Skala kecil-menengah: 7 model inti, ~70 file Filament resource.

## 2. Domain Model

| Model | Field kunci | Relasi |
|---|---|---|
| `Company` (singular — **beda nama class dari `Companies` di HRIS**, konflik penamaan pertama saat merge) | name, code, is_active | hasMany departments/categories/documents/users |
| `Department` | name, code, company_id | belongsTo Company; hasMany units |
| `Unit` | name, prefix (fillable tapi **kolomnya gak ada di migration awal** — kemungkinan bug), department_id | belongsTo Department |
| `User` | name, email, **username** (unique, ditambah belakangan), company_id/department_id/unit_id | HasRoles (Spatie); Auditable |
| `Meeting` | title, doc_number, agenda, content (HTML notulen), file_path (upload notulen), attachments(json), date_time, location, status, company_id/department_id/unit_id, created_by, notulis_id | belongsToMany participants (pivot `meeting_participants`, kolom `attendance`); scope `access()` custom ACL |
| `Document` | title, code_number, description, file_path, content, document_type(file/form/hybrid), status(draft/pending_review/approved/rejected/archived), approved_by/approved_at, rejected_by/rejected_at, company/department/unit/category_id | SoftDeletes; LogsActivity+Auditable; scope `access()` |
| `DocumentCategory` | name, prefix, company_id, requires_approval(migration ada, model gak pakai) | hasMany documents — **SOP BUKAN model sendiri**, cuma kategori dokumen dengan prefix `SOP` |
| `DocumentNumbering` | category_id, company_id, department_id, year, last_number | generate nomor dokumen otomatis |

**Tabel dead/gak kepake** (JANGAN ikut di-porting apa adanya):
- `meeting_minutes` (migration ada: content, action_items json, decisions json, mentioned_users json) — **0 baris data, gak ada Model, cuma ada Policy yatim**. Notulensi aktual disimpan di kolom `Meeting.content`/`Meeting.file_path` langsung.
- `document_templates` — migration ada, model gak ada, 0 baris data.

## 3. Alur Notulen Meeting (detail)

Form (`MeetingForm.php`): mode "Template" pakai **1 RichEditor HTML bebas** (bukan repeater/struktur field per agenda-item/decision/action-item) — cuma di-inject template HTML tabel (kolom NO/PEMBAHASAN/ACTION PLAN/PIC) sekali di awal, abis itu user edit bebas WYSIWYG tanpa validasi struktur. Mode "Upload" cuma file upload PDF/Word, tanpa metadata tambahan.

`NotulenController::view()` cuma `response()->file()` file mentah dengan **`Content-Type: application/pdf` di-hardcode** — bug kalau file-nya Word (.docx).

**Gak ada approval/publish workflow buat notulen sendiri** — status Meeting cuma soal jadwal (scheduled/completed/cancelled, enum form vs table sempat gak konsisten), bukan approval isi notulennya.

## 4. Alur Document/SOP (approval lemah)

- `document_type`: form cuma bisa pilih `file` atau `form` (`hybrid` ada di DB/kode tapi gak reachable dari form create/edit — cuma data lama).
- **Auto code numbering itu placeholder mati** — field `Code Number` di form default hardcode string `'AUTO-GENERATED'`, kemungkinan besar dead code (nama field gak match kolom DB `code_number`).
- Approval **single-level flat**: kolom `approved_by`/`rejected_by` doang (bukan berjenjang/multi-step), 1 tombol Filament Action, approver hardcode `hasRole('super_admin')`. **Gak ada mekanisme submit-for-review dari form** — status `pending_review` ada di filter & widget count tapi gak pernah di-trigger kode manapun (dead status).
- `DocumentPolicy` punya method custom `viewOwnCompany()`/`viewAllCompanies()` yang **gak dipanggil** — permission-nya dipanggil langsung via string (`$user->can('view_own_company_data')`) di `scopeAccess()`, bukan lewat policy method.

## 5. Permission custom (non-CRUD Shield)

Cuma 3, dipakai manual di `scopeAccess()` **duplikat di 2 model** (Meeting & Document, bukan trait reusable):
- `view_own_company_data`
- `view_all_companies_data`
- `view_any_company_participants` (filter peserta lintas company/department/unit di form Meeting)

## 6. Widget/Dashboard — ada bug & dummy data

- **`MyCalendarWidget`** (Guava Calendar): drag-reschedule event **gak ada authorize check** di `onEventDrop` — siapapun yang bisa liat kalender bisa geser tanggal rapat orang lain tanpa cek permission `update`. **Celah keamanan nyata**, harus dibenerin kalau fitur kalender ini diporting.
- **`MyWidget`** (Document stats): 2 dari 4 stat card ("Disetujui"/"Menunggu") pakai deskripsi **hardcode dummy** "Peningkatan 8%"/"Penurunan 2%" — bukan hasil hitung real.
- `MeetingInvitedWidget` gak pakai scope `access()` (beda pendekatan dari widget Meeting lain) — potensi inkonsisten company-scoping.

## 7. Approval engine — gak bisa direuse tanpa refactor

HRIS punya `ApprovalFlowService`/`ApprovalPermissionService`/`ApprovalOverrideService` — engine generic multi-step berbasis `steps_snapshot` + `current_step_order` (`MasterApprovalFlows`/`MasterApprovalFlowSteps`, `ApprovalModuleEnum`), dipakai banyak modul (Pengajuan Absensi, Training Request, dll). Document DCMS single-approver flat (`approved_by` doang, gak ada konsep step/order/flow definition). Kalau mau reuse pas migrasi, Document approval harus di-treat sebagai flow 1-step (`steps_snapshot` isi 1 entry) — **refactor total dari kolom flat ke snapshot+history, bukan sekadar adapter tipis**.

## 8. Data Real (`sql_dcms_syifaglobalgroup_com`, dicek 2026-08-12)

| Tabel | Jumlah | Catatan |
|---|---|---|
| companies | 6 | |
| departments | 15 | |
| units | 71 | |
| users | 282 | 281 aktif, 100% punya `username` |
| meetings | 134 | rentang 2026-04-16 s/d 2026-08-11 (~4 bulan pemakaian aktif) |
| meeting_participants | 863 | rata-rata ~6.4 peserta/meeting |
| documents | **3** | fitur SOP/Document nyaris gak dipakai secara real |
| meeting_minutes | 0 | konfirmasi dead table |
| document_templates | 0 | konfirmasi dead table |
| roles | 4 | super_admin(4 user), direktur(**0 user**), user(270), Sekretaris(7) |
| permissions | 108 | |

Detail:
- Meeting status: completed 126, cancelled 7, scheduled 1.
- Isi notulen: dari 134 meeting — 80 pakai `content`, 81 pakai `file_path`, **31 kosong dua-duanya** (gak ada notulen sama sekali).
- Document: 3 baris — 1 draft/1 approved/1 rejected, 2 file/1 form. Role "direktur" 0 user berarti fitur khusus direktur di form (opsi status `published`) gak pernah kepake real.
- Kategori dokumen cuma 2: "Standar Operasional Prosedur" (prefix SOP), "Notulensi Acara" (prefix Notulen).

### Mapping company: TIDAK 1:1 by name

Dicek silang company DCMS (6) vs `m_companies` HRIS (8):

| DCMS | HRIS | Catatan |
|---|---|---|
| PT. Syifa Global Group | Syifa Global Group | cocok (beda "PT." prefix doang) |
| PT. Syifa Global Med | Syifa Global Medica | cocok (beda nama dikit) |
| Lerina Hotel Banjarbaru | Lerina Inn | cocok (beda nama) |
| PT. Syifa Global Lestari (1 entitas) | Syifa Global Lestari **Banjarbaru** + **Surabaya** (2 entitas terpisah) | **HRIS udah pecah jadi 2 cabang, DCMS masih 1** |
| RSU Syifa Medika Banjarbaru | RSU Syifa Medika Banjarbaru | sama persis |
| RSU Syifa Medika Barabai | RSU Syifa Medika Barabai | sama persis |
| — | **Syifa Agro Natura** | **gak ada padanan di DCMS sama sekali** — company baru yang belum pernah pakai DCMS |

**Konsekuensi**: migrasi company gak bisa auto-match by name, wajib tabel mapping manual + keputusan eksplisit soal data "PT. Syifa Global Lestari" DCMS itu masuk ke company mana (Banjarbaru atau Surabaya, atau perlu dipecah historis).

## 9. Tabel/Model DCMS vs Padanan HRIS — apa yang beneran perlu diporting

Dari semua tabel DCMS, cuma sebagian kecil yang beneran butuh tabel baru di HRIS — sisanya numpang struktur yang udah ada.

**Utuh ada padanannya di HRIS (jangan diporting mentah, integrasi ke tabel existing):**

| DCMS | Padanan HRIS | Catatan |
|---|---|---|
| `users` | `users` (`User` model) | data DCMS harus **di-match/merge** ke user existing, bukan tabel/insert baru |
| `companies` | `m_companies` (`Companies` model) | 8 company ada, tapi nama gak 1:1 (lihat §8) |
| `departments` + `units` | `m_org_structures` (`OrgStructure`) | keputusan yang udah diambil |
| `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `model_has_permissions` | Tabel Spatie Permission HRIS (`2025_09_11_113956_create_permission_tables.php`) | paket sama (`spatie/laravel-permission`) — cuma perlu permission BARU buat fitur Meeting/Document, data role/permission DCMS gak diimport mentah (nama role "user" generik, rawan bentrok) |
| `imports`, `exports`, `failed_import_rows` | Tabel bawaan Filament (`2025_10_30_001237_create_imports_table.php` dkk) | fitur Filament core, sudah ada, identik |
| `sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`, `migrations`, `password_reset_tokens` | Infra Laravel standar | HRIS udah punya sendiri, data DCMS di tabel ini transient/tidak relevan |

**Konsepnya ada, mekanismenya beda (bukan tabel yang sama persis):**

`activity_log` (Spatie ActivityLog) & `audits` (owen-it/laravel-auditing) — HRIS **gak pakai 2 paket ini**. HRIS punya mekanisme sendiri: tabel `audit_logs` (`2026_07_23_122710_create_audit_logs_table.php`, via trait `LogsAuditChanges`), tapi skemanya cuma catat perubahan config generik (`module`/`key`/`old_value`/`new_value`), bukan full model-audit trail selengkap punya DCMS. Kalau mau porting histori perubahan Meeting/Document, harus adaptasi ke pola `audit_logs` HRIS, bukan bawa 2 tabel DCMS itu apa adanya.

**Beneran baru, gak ada padanan sama sekali di HRIS:**

- `meetings`, `meeting_participants` — domain baru total.
- `documents`, `document_categories`, `document_numberings` — domain baru. **Hati-hati beda konsep**: HRIS udah punya `UserDocuments` (dokumen pribadi karyawan — KTP/KK/ijazah), itu BUKAN padanan `documents` DCMS (manajemen dokumen/SOP perusahaan) meski nama mirip — jangan disamain.
- `meeting_minutes`, `document_templates` — dead di DCMS (0 baris data, gak dipakai kode manapun), mau didesain ulang bukan diporting apa adanya.

## 10. Rekomendasi Urutan Kerja (belum dieksekusi)

1. ~~Struktur organisasi~~ — **diputuskan**: map ke `OrgStructure`.
2. Desain ulang skema notulensi terstruktur (action_items/decisions beneran) — **masih dieksplorasi**, jangan ikut skema dead DCMS.
3. Putuskan SOP tetap pola "Document + kategori" atau dibikin model sendiri di HRIS.
4. Refactor `scopeAccess()` jadi trait/gate reusable pas porting (jangan duplikat pola DCMS).
5. Document approval numpang `ApprovalFlowService` HRIS sebagai flow 1-step.
6. Bikin tabel mapping company manual (lihat §8) sebelum import data user/meeting/document real — JANGAN auto-match by name.
7. Benerin celah keamanan calendar drag-reschedule (§6) kalau fitur kalender diporting.
8. Department/Unit → OrgStructure: belum dicek detail isi 15 department + 71 unit real (nama, hierarki) — perlu digali sebelum mapping final ke `m_org_structures`.
9. Histori perubahan Meeting/Document (kalau dibutuhkan) — adaptasi ke pola `audit_logs` HRIS, bukan bawa paket `owen-it/laravel-auditing`/`spatie/laravel-activitylog` baru.
