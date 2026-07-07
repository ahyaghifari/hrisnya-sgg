# Planning: Portal Pengajuan Absensi (Cuti/Izin/Lembur) — 2026-07-05

Status: **analisis selesai, belum ada kode dieksekusi.** Melanjutkan rencana lama di [`planning/web-attendance/PLANNING.md`](web-attendance/PLANNING.md), tapi arsitekturnya diganti — bukan API token (Sanctum/SPA), melainkan **reuse pola Livewire + Filament schema** yang sudah terbukti jalan di fitur Karyawanku (lihat [`planning/2026-07-04-draft-role-job-position-mapping.md`](2026-07-04-draft-role-job-position-mapping.md) Pilar 3).

## Konfirmasi dari Diskusi

1. **Semua karyawan bisa mengajukan absensi, kecuali Direktur holding** (memang tidak ada atasan approval untuk dia). Reuse `User::canBrowseAllCompanies()` yang sudah ada (role "Direktur" + company holding) — tinggal dibalik jadi exclusion (`! $user->canBrowseAllCompanies()`), bukan bikin check baru.
2. **Navigasi**: dropdown "Absensi" → ganti label jadi **"Pengajuan Absensi"**. Isinya tetap Cuti/Izin/Lembur/Riwayat, tapi link `href="#"` diganti ke route beneran begitu halamannya jadi.
3. **Adopsi tampilan/isi form dari Filament** — sama seperti `EmployeeForm`/`EmployeesTable` yang direuse untuk Karyawanku. Layout portal (nav, `components.layout`, CSS) sudah ada, tinggal pasang form-nya.

## Temuan dari Analisis Kode

### A. Lampiran — TIDAK perlu tabel baru
Tabel `attendance_req_attachments` (model `AttendanceRequestAttachments`) sudah ada, strukturnya:
| Kolom | Isi |
|---|---|
| `request_id` | FK → `attendance_requests` |
| `file_path` | 1 file per baris |

"Multiple files" sudah didesain sebagai **banyak baris**, bukan kolom array/JSON. Backend (`OvertimeReqRepository::storeOvertimeRequest()`) sudah loop `$data['attachments_files']`, simpan tiap file ke `Storage::disk('public')->putFile("overtime/{request_id}", $file)` lalu insert 1 row attachment per file. Tinggal reuse pola ini.

Pola form-nya juga sudah ada presedennya di `UserForm.php` (`attachmentsSchema()` untuk `user_documents`): `Repeater::make(...)->relationship(...)->schema([FileUpload::make('file_path')...])`.

### B. Form mana yang punya lampiran?
Dicek dari `app/Http/Requests/Attendance/{Leave,Permission,Overtime}Request.php` — **cuma Lembur (Overtime)** yang punya field `attachments` (nullable array, mimes pdf/jpg/jpeg/png, max 10MB/file). Cuti & Izin tidak ada lampiran sama sekali.

### C. Gap: Form Cuti & Lembur belum ada di Filament
`AttendanceRequestForm::createSchema()` cuma include `permissionFormSchema()`. Belum ada `leaveFormSchema()` / `overtimeFormSchema()` — beda dari kasus Employee kemarin yang tinggal reuse langsung, di sini **2 dari 3 form harus dibangun dulu**.

**Rekomendasi**: bangun `leaveFormSchema()` dan `overtimeFormSchema()` langsung di `AttendanceRequestForm.php` yang sudah ada (bukan di file terpisah khusus portal) — sekali kerja, manfaatnya dobel: nutup gap admin panel (`/admin/attendance-requests/create?type=leave` yang sekarang kosong) SEKALIGUS jadi bahan reuse buat portal, persis pola `EmployeeForm`.

### D. Service/Repository existing pakai guard `sanctum`, bukan `web`
`AttendaceReqService::storeOvertimeRequest()` dkk hardcode `auth('sanctum')->user()` — didesain untuk API token (mobile/SPA), bukan portal session-based (guard `web`) yang kita pakai. Logic di dalamnya (resolve approval flow, create approval snapshot, kirim notifikasi ke approver pertama) itu **non-trivial, jangan ditulis ulang**.

**Rekomendasi**: adaptasi minimal — ganti `auth('sanctum')->user()` jadi terima `User $user` sebagai parameter (dioper dari Livewire component yang pakai `Auth::user()` biasa/guard `web`). Reuse service, bukan bikin service baru.

## Rencana Halaman (mengikuti pola Karyawanku)

| Halaman | Route | Reuse dari Filament |
|---|---|---|
| Form Cuti | `/pengajuan/cuti` | `AttendanceRequestForm::leaveFormSchema()` *(baru dibangun)* |
| Form Izin | `/pengajuan/izin` | `AttendanceRequestForm::permissionFormSchema()` *(sudah ada)* |
| Form Lembur | `/pengajuan/lembur` | `AttendanceRequestForm::overtimeFormSchema()` *(baru, + Repeater lampiran)* |
| List Pengajuan (Riwayat) | `/pengajuan` | `AttendanceRequestsTable` *(cek dulu isinya, belum dianalisis detail)* |
| Detail Pengajuan | `/pengajuan/{id}` | `AttendanceRequestForm::editSchema()` (read-only + timeline approval, sudah ada & lengkap) |

Guard tiap halaman: `abort_unless(! Auth::user()->canBrowseAllCompanies(), 403)` (Direktur holding dikecualikan).

## Yang Belum Dianalisis (perlu sesi berikutnya sebelum implementasi)

- `AttendanceRequestsTable.php` (buat halaman List/Riwayat) — belum dibaca.
- `LeaveRequest.php`/`PermissionRequest.php` validasi detail (baru cek Overtime).
- `LeaveReqRepository`/`PermissionReqRepository` — pola simpannya, apakah sama seperti Overtime (guard sanctum hardcode).
- Endpoint pembatalan pengajuan (`Batalkan`, disebut di planning lama tapi belum dicek kode aslinya).
- Approval flow existing untuk `attendance_request` — perlu dipastikan sudah ada master flow aktif per company (kalau belum ada, submit akan gagal — sudah ada exception handling untuk itu di `storeOvertimeRequest`).

## E. `AttendanceRequestsTable.php` — sudah lengkap, siap direuse
Kolom: Tanggal Pengajuan, Nama Karyawan (+ company sebagai deskripsi), Status (badge + info reviewer saat ini via `getReviewerInfo()`). Filter company cuma muncul untuk global role. Pola ini bisa direuse persis seperti `EmployeesTable` untuk halaman List/Riwayat portal — cuma perlu override `->query()` biar scope ke pengajuan milik user sendiri (bukan filter company kayak di admin).

## F. Validasi Cuti & Izin — dikonfirmasi ulang, sama persis planning lama
`LeaveRequest.php` & `PermissionRequest.php` isinya sama persis dengan yang sudah didokumentasikan di [`planning/web-attendance/PLANNING.md`](web-attendance/PLANNING.md) §5. Tidak ada perubahan, tidak ada lampiran di keduanya.

## G. Fitur "Batalkan Pengajuan" — BELUM ADA sama sekali di kode (bukan cuma belum ke-expose di UI)

Dicek: `cancelled_by`/`cancelled_at` (kolom di `attendance_requests`) dan `AttendanceReqStatus::CANCELLED` (enum) itu cuma **scaffolding** — tidak ada satupun controller/service/repository yang benar-benar mengisi kolom itu atau mengubah status jadi `cancelled`. Ini gap asli yang perlu dibangun, bukan sekadar nyambungin yang sudah ada.

**Klarifikasi dari user**: yang bisa membatalkan adalah **atasan (approver)**, bukan karyawan yang mengajukan sendiri, dan bisa dilakukan **di step manapun selama masih pending, sampai sebelum approval final**.

**Temuan penting**: ini PERSIS pola yang sudah ada & terbukti jalan untuk aksi Approve/Reject (lihat `EditUserTransition.php` yang sudah kita bangun sebelumnya):
- `ApprovalPermissionService::canApprove($snapshotId, $user)` — sudah otomatis true HANYA untuk approver yang sedang bertugas di step yang `pending`. Ini persis syarat "atasan yang sedang menangani step itu, sampai final" — reuse langsung, tidak perlu logic baru.
- `ApprovalFlowService::advanceStep($snapshot, $actorId, $note, $action, $additionalData)` — `$action` itu string bebas (bukan enum terbatas approve/reject), tinggal panggil dengan `action: 'cancelled'`.

**Rekomendasi implementasi** (nanti): tambah 1 tombol Action "Batalkan" persis di sebelah tombol Approve/Reject yang sudah ada di halaman Edit AttendanceRequest — visible-nya pakai `canApprove()` yang sama, action-nya `advanceStep(..., action: 'cancelled')` lalu `$record->update(['status' => AttendanceReqStatus::CANCELLED->value, 'cancelled_by' => auth()->id(), 'cancelled_at' => now()])`. Tidak perlu service/tabel baru.

## Status per 2026-07-05 (update)

✅ **Sudah diimplementasi & dites** (bagian A–G di atas semua sudah jalan, bukan cuma rencana lagi):
- Form Cuti/Izin/Lembur di portal (`/pengajuan/cuti`, `/izin`, `/lembur`) — Livewire biasa, UI diambil dari mockup `planning/web-attendance/demo/form-*.html`, bukan reuse Filament schema (diputuskan belakangan supaya kontrol tampilan penuh).
- List Pengajuan (`/pengajuan`, reuse `AttendanceRequestsTable`) & Detail Pengajuan (`/pengajuan/{id}`, reuse `AttendanceRequestForm::editSchema()`).
- Fitur Batalkan — sudah jalan di admin (`EditAttendanceRequest.php`), reuse `ApprovalFlowService::advanceStep(..., 'cancelled')`.
- `AttendaceReqService` sudah menerima `?User $actingUser` (guard `web` kompatibel).
- Semua dites otomatis (`tests/Feature/AttendanceRequestPortalTest.php`, `AttendanceRequestCancelTest.php`), lolos di sqlite in-memory.

⚠️ **Belum diverifikasi manual di browser** — logic & data sudah teruji, tapi tampilan/interaksi asli belum pernah dilihat langsung (lihat catatan di percakapan 2026-07-05).

---

## H. Permintaan Baru: Komponen Status Timeline + Kotak Approval (2026-07-05)

Referensi visual dari user: timeline horizontal ala mockup `planning/web-attendance/previews/dashboard-mockup.html` — tiap node berisi nama orang + tanggal (kalau sudah selesai) atau kosong (kalau belum), garis penghubung hijau solid untuk yang sudah lewat, garis abu-abu untuk yang belum. Dua kebutuhan:

1. **Timeline status untuk PENGAJU** — karyawan lihat progress pengajuannya sendiri: Diajukan → Approve Supervisor → Approve HC → Selesai. Step yang sudah kelar tidak perlu ditampilkan sebagai "menunggu" lagi (sesuai contoh user: begitu Supervisor approve, tidak muncul lagi di step itu — current step pindah ke step berikutnya).
2. **Kotak konfirmasi untuk ATASAN** — begitu ada bawahan yang punya pengajuan menunggu approval si atasan, muncul kotak/daftar di tempat atasan itu login (kemungkinan Dashboard) supaya dia bisa langsung approve/reject/batalkan.

### 🔴 Temuan kritis — ini bukan cuma "nice to have", tapi nutup gap akses yang beneran ada

Approve/Reject/Batalkan **HANYA ada di Filament admin** (`EditAttendanceRequest.php`, path `/admin/attendance-requests/{id}/edit`). Tapi `/admin` cuma bisa diakses role **Admin/Super Admin** (`User::canAccessPanel()`). Artinya: **Supervisor/HC/Manager biasa (bukan Admin) sampai sekarang TIDAK BISA approve pengajuan sama sekali**, kecuali kebetulan mereka juga punya role Admin. Kotak approval yang diminta user ini justru **wajib ada** supaya approval flow yang sudah dibangun (approve/reject/cancel) bisa benar-benar dipakai atasan yang sebenarnya.

### Data yang sudah tersedia (tidak perlu tabel baru)

- `attendance_requests` → `approvalSnapshot` (1:1) → `activity` (step yang sedang aktif) + `steps_snapshot` (json, rencana semua step) + `current_step_order` + `status`.
- `approvalHistories` (log semua aksi: requested/approved/rejected/cancelled/completed, per actor + waktu) — ini yang isi "nama + tanggal" di tiap node timeline yang SUDAH selesai.
- `ApprovalPermissionService::checkUserPermission()` — logic "apakah user ini approver dari activity ini" (per user/role/job_position + scope department) **sudah ada, reuse untuk kedua komponen**.

### Yang BELUM ada — perlu dibangun

**1. Query "semua pending approval milik SAYA"** — `ApprovalPermissionService` sekarang cuma bisa cek 1 record spesifik (`canApprove($snapshotId, $user)`), belum ada query buat LIST semua yang nunggu approval user tertentu. Perlu method baru, misal `ApprovalPermissionService::pendingApprovalsFor(User $user)` yang query `approval_activities` `where(status, pending)` lalu filter cocok ke salah satu dari:
   - `approver_type='user' AND approver_ref_id = $user->id`
   - `approver_type='role' AND $user punya role tsb` (+ scope_type='specific_department' → cek department user cocok)
   - `approver_type='job_position' AND $user->profile->job_position_id = approver_ref_id`

   Ini query gabungan 3 kondisi OR — perlu hati-hati soal performa kalau nanti dipakai lintas banyak module (bukan cuma attendance_request), tapi untuk sekarang scope-nya cuma `module_type = 'attendance_request'` dulu.

**2. Komponen Timeline (untuk pengaju)** — visual baru, ganti/lengkapi tabel `Riwayat Approval` yang sekarang ada di `AttendanceRequestForm::editSchema()` (masih bentuk tabel, bukan timeline). Dibangun dari:
   - `steps_snapshot` (rencana lengkap semua step + label "final" atau bukan)
   - `current_step_order` + `status` snapshot (step mana yang aktif sekarang)
   - `approvalHistories` (isi nama actor + tanggal untuk step yang sudah lewat)
   
   Ditaruh di halaman **Detail Pengajuan** (`/pengajuan/{id}`) yang sudah ada, gantikan bagian repeater tabel approval historinya dengan komponen timeline visual.

**3. Kotak "Butuh Persetujuan Anda" (untuk atasan)** — komponen/widget baru, kemungkinan ditaruh di Dashboard (`dashboard.blade.php`, sejajar `recent-requests-card`). Isinya: daftar pengajuan yang nunggu approval user yang login (pakai query poin 1), tiap item bisa diklik ke Detail Pengajuan.

**4. Detail Pengajuan (`/pengajuan/{id}`) perlu diperluas** — sekarang cuma bisa diakses SI PENGAJU (`abort_unless($record->user_id === Auth::id())`). Approver (atasan) juga harus bisa buka halaman yang sama untuk lihat detail + approve/reject/batalkan. Guard perlu jadi: `$record->user_id === Auth::id() OR pernah/sedang jadi approver di snapshot ini`. Approve/Reject/Batalkan Action juga perlu ditambahkan di halaman portal ini (reuse `ApprovalFlowService`+`ApprovalPermissionService`, sama persis pola yang sudah dipakai di `EditAttendanceRequest.php` admin — cuma dipindah/ditambahkan ke portal).

### Keputusan (2026-07-05)

1. **Scope kotak approval: `attendance_request` dulu saja.** Query `pendingApprovalsFor()` tidak perlu generik lintas module dulu — cukup filter `module_type = 'attendance_request'`. Modul lain (task_request, user_transition, swap_shift_request) menyusul kalau memang dibutuhkan nanti, tidak sekarang.
2. **Approve/Reject/Batalkan tetap ada di DUA tempat** — admin (`/admin`, buat Admin/SuperAdmin) dan portal (buat atasan asli). Tidak saling menggantikan, dua-duanya reuse service yang sama (`ApprovalFlowService`, `ApprovalPermissionService`), jadi tidak ada logic ganda — cuma UI/entry point-nya beda.

### Status: ✅ SELESAI DIIMPLEMENTASI (2026-07-05)

- `ApprovalPermissionService::pendingApprovalsFor()` — query gabungan (user/role/job_position + scope), reuse `checkUserPermission()` yang sudah ada.
- `PendingApprovalsCard` (Livewire) — kotak "Butuh Persetujuan Anda" di Dashboard, tampil hanya kalau ada pending (kalau kosong komponen tetap render div kosong, bukan hilang total — supaya tidak melanggar aturan Livewire yang wajib ada root tag).
- `AttendanceRequestDetail` — guard diperluas (submitter ATAU approver saat ini/sebelumnya), timeline visual (`getTimelineProperty()`), dan aksi Approve/Reject/Batalkan langsung dari portal (reuse `ApprovalFlowService`/`ApprovalPermissionService`, sama seperti di admin — tidak ada logic ganda).
- 7 test baru (`AttendanceApprovalPortalTest.php`), semua lolos + 42 test total di seluruh project tetap lolos. Dijalankan aman di sqlite in-memory, database dev tidak tersentuh.

⚠️ Belum diverifikasi manual di browser (tampilan timeline, kotak approval di Dashboard, tombol aksi) — sama seperti bagian portal lain, cek langsung disarankan sebelum dianggap benar-benar final.
