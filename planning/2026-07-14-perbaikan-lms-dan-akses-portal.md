# Planning: Roadmap Perbaikan LMS + Akses Portal via `sistem_lms` — 2026-07-14

Status: **rencana, belum dieksekusi.** Lanjutan dari `knowledge/markdown/analisis-lms.md` (§11 gap fungsional, §12 gap struktur/kode — addendum 2026-07-14). Dokumen ini punya 2 bagian: **(A)** urutan eksekusi perbaikan gap dari yang kritikal ke yang santai, dan **(B)** rencana membuka modul LMS di portal karyawan lewat permission `sistem_lms`, meniru pola `sistem_keuangan` yang sudah terbukti jalan.

## Bagian A — Roadmap Perbaikan (Kritikal → Santai)

Semua nomor gap merujuk `knowledge/markdown/analisis-lms.md` §11 dan §12.

### Tahap 1 — Kritikal (mempengaruhi validitas data & keputusan bisnis, tapi butuh keputusan user dulu sebelum dikerjakan — bukan sekadar rapikan kode)

| Gap | Yang harus diputuskan user sebelum eksekusi |
|---|---|
| #1 Esai selalu 0 poin, tidak ada review manual | **Keputusan (2026-07-14): dipakai, buat fitur review manual.** Halaman Filament baru "Review Jawaban Esai" (list jawaban `pending_review` per quiz, admin input skor manual) + ubah `submitQuiz()` supaya status simpan beneran, bukan dibuang. |
| #2 Sertifikat bisa terbit tanpa cek `is_passed` | **Keputusan (2026-07-14): upload tetap boleh tanpa gate, tapi DOWNLOAD dikunci.** Admin tetap bisa unggah sertifikat kapan saja course `COMPLETED` (tidak diubah). Endpoint download (`CourseUserController::downloadCertificate`) ditambah cek `is_passed === true` — peserta yang belum lulus tidak bisa mengunduh sertifikatnya sendiri walau filenya sudah ada di server. |
| #3 Status `REQUESTED` tidak pernah jadi `APPROVED` | **Keputusan (2026-07-14): dibuat fungsional.** Tambah aksi admin approve/reject pendaftaran course, dan peserta baru bisa ikut meeting/quiz setelah `APPROVED`. |

**Eksekusi**: ketiganya sudah dikonfirmasi user 2026-07-14, siap dikerjakan berurutan (esai → sertifikat → approval gate), masing-masing dengan test sesuai §Testing Tahap 1 di bawah.

**Status: SELESAI (2026-07-14).** Ringkasan implementasi:
- **Esai**: migration tambah `status`/`reviewed_by`/`reviewed_at` di `lms_quiz_user_results`. `QuizService::submitQuiz()` sekarang benar-benar menyimpan status `pending_review`. Method baru `QuizService::reviewEssayAnswers()` — admin input skor per jawaban esai (clamped ke `max_score` soal), total skor disummasi ulang dari semua jawaban, memicu ulang `updateMeetingUserData()`. Halaman baru **Filament → LMS → "Review Jawaban Esai"** (`app/Filament/Pages/Lms/ReviewEssayAnswers.php`).
- **Sertifikat**: upload tetap tanpa gate (sesuai keputusan), tapi endpoint `GET /lms/courses/{id}/certificate` sekarang menolak (403) kalau `is_passed !== true`.
- **Approval**: `ParticipantCourses` (halaman peserta admin) dapat aksi baru **Setujui**/**Tolak**. `MeetingAttendanceService::clockIn()` dan `QuizService::submitQuiz()` sekarang menolak peserta yang belum `APPROVED`. **Perubahan tambahan**: admin yang assign peserta langsung dari form Course (`LmsCoursesForm.php`) sekarang otomatis `APPROVED` (bukan `REQUESTED`) — karena tindakan admin memilih peserta itu sendiri sudah setara persetujuan; hanya self-enroll lewat API (`/lms/courses/{id}/follow`) yang tetap masuk `REQUESTED` dan butuh direview.

**2 bug laten yang kesingkap & ikut diperbaiki** (di luar 3 gap awal, ditemukan saat implementasi):
1. `QuizService::updateMeetingUserData()` memakai `auth('sanctum')->user()` buat event `LmsProgresUpdated`, padahal seharusnya user targetnya — kebetulan selalu sama selama cuma dipanggil dari API peserta sendiri. Baru kepampang begitu dipanggil dari Filament admin (guard beda, jadi `null`) lewat fitur review esai. Diperbaiki: sekarang pakai user dari `meetingUser` yang sedang diproses.
2. `LmsQuizUserAnswers::question()` salah target relasi (nunjuk ke pivot `LmsQuizQuestions`, padahal `question_id` di tabel itu sebenarnya nyimpan ID dari `QuestionBanks`). Diperbaiki karena tanpa ini, skor esai hasil review selalu ke-clamp jadi 0.

**⚠️ Catatan rollout penting**: semua `lms_course_users` yang sudah ada di database **saat ini semua bertatus `REQUESTED`** (baik dari self-enroll maupun assign admin lama, karena sebelum perbaikan ini admin-assign juga hardcode `REQUESTED`). Begitu perbaikan approval-gate ini di-deploy, **seluruh peserta yang sedang mengikuti course aktif akan langsung terblokir** dari check-in/quiz sampai di-approve ulang satu-satu lewat halaman "Peserta" tiap course. **Perlu tindakan migrasi data** sebelum/saat deploy: jalankan 1 query sekali jalan untuk set `status = APPROVED` pada semua `lms_course_users` existing yang `status = REQUESTED` DAN `is_mandatory = true` (assign admin lama), supaya tidak memblokir siapapun yang sudah aktif ikut course — sisakan cuma yang benar-benar self-enroll murni (`is_mandatory = false`) untuk direview manual. Belum dieksekusi di sesi ini, perlu dikonfirmasi & dijalankan terpisah sebelum fitur ini benar-benar live.

**Test**: `tests/Feature/LmsApprovalAndReviewTest.php` (4 test, semua pass) — cakup approval-gate checkin/quiz, review esai + clamp skor, gate download sertifikat. Satu test UI-level (approve/reject lewat Filament Livewire Page) dilepas karena butuh setup testing-harness Filament yang belum ada presedennya di project ini (infrastruktur, bukan bug fitur) — perilakunya tetap tercakup lewat test service-level yang sudah ada. Full suite dijalankan, 6 test gagal (`LoginTest`, `PortalPageAccessTest`, `DashboardTest` x3, `SalaryComponentCrudTest`) — dikonfirmasi lewat `git stash` semuanya **sudah gagal sebelum perubahan ini**, bukan regresi.

**Testing Tahap 1** (wajib sebelum tahap ini ditutup, per gap yang jadi dikerjakan):
- #1: test submit quiz esai → status tersimpan benar (bukan dibuang), admin bisa input skor manual lewat halaman review, skor akhir peserta ikut terupdate setelah direview.
- #2: test upload sertifikat ditolak/diberi peringatan untuk peserta `is_passed = false`, tetap bisa untuk `is_passed = true`.
- #3: test transisi status `REQUESTED → APPROVED` benar-benar tersimpan lewat aksi yang disepakati (atau, kalau diputuskan dihapus dari alur, test yang membuktikan status itu tidak lagi dipakai di mana pun).
- Jalankan seluruh `tests/Feature/` yang menyentuh LMS (kalau sudah ada dari tahap ini) untuk pastikan tidak regresi ke fitur lain.

### Tahap 2 — Aman & Cepat (tidak ubah perilaku existing, boleh langsung dieksekusi)

| Gap | Perbaikan |
|---|---|
| #9 `lms_user_histories` tanpa unique `(user_id, course_id)` | Migration tambah unique index — **cek dulu tidak ada data duplikat existing** sebelum apply. |
| #17 `lms_meeting_attendances` tanpa unique constraint anti double check-in | Sama, migration tambah unique `(meeting_id, user_id)` setelah cek data. |
| #13 `CourseRepositories.php` rename jadi `CourseRepository.php` | Rename file + class + semua `use` yang mereferensikannya. |
| #14 Komentar penjelas kenapa `TrainerScoreRanges`/`TrainerScoreVariables` dikelola dari Filament folder `Payroll/` bukan `LMS/` | Cukup docblock singkat di kedua Resource, tidak perlu pindah file (pindah file berisiko lebih besar dari manfaatnya). |
| #16 Rename folder Filament `LmsBaselines` → `LmsCourseBaselines` biar match nama model | Rename folder + namespace + referensi di `getPages()`/nav. |

Tahap ini bisa dikerjakan langsung tanpa perlu konfirmasi user lebih lanjut — tidak mengubah cara kerja fitur, cuma kerapian & proteksi data.

**Testing Tahap 2** (wajib sebelum tahap ini ditutup):
- #9/#17: sebelum migration jalan, query dulu data existing cari duplikat `(user_id, course_id)` dan `(meeting_id, user_id)` — kalau ada, bersihkan/putuskan cara merge dulu, baru apply unique index. Test: insert baris duplikat lewat kode → harus gagal kena constraint.
- #13/#16: jalankan full test suite (`php artisan test`) setelah rename — rename yang lupa update 1 referensi `use`/`namespace` langsung ketahuan dari error fatal, bukan silent bug.
- Pastikan `/admin/lms/*` masih bisa diakses & tabel/form yang di-rename foldernya tidak 404.

**Status: SELESAI (2026-07-14).**
- #9/#17: dicek dulu — 0 duplikat existing di `lms_user_histories` dan `lms_meeting_attendances`. Migration `2026_07_14_130000_...` tambah unique constraint di keduanya, sudah jalan.
- #13: `CourseRepositories.php` → `CourseRepository.php` (class `CourseRepositories` → `CourseRepository`), 6 file pemakai (`QuizService`, `MeetingAttendanceService`, `CourseUserService`, `CourseService`, `LmsProgresListener`, `CreateMeetingUsersJob`) diupdate. Nama variabel `$courseRepositories` sengaja dibiarkan (nama variabel, bukan nama class).
- #14: awalnya docblock penjelas, tapi user minta dipindah beneran — jadi **`TrainerScoreRangesResource`/`TrainerScoreVariableResource` dipindah dari `Resources/Payroll/` ke `Resources/LMS/`** (folder + namespace 12 file + `navigationGroup = 'LMS'` eksplisit, sort 8-9). Permission Shield tidak orphan (berbasis nama model, dicek langsung ke tabel `permissions` — 20 baris tetap utuh). URL admin ikut pindah dari `/admin/payroll/trainer-score-*` ke `/admin/l-m-s/trainer-score-*` — dicek dulu tidak ada referensi hardcoded ke URL lama di manapun.
- #16: folder `LmsBaselines/` → `LmsCourseBaselines/` (6 file: Resource, 3 Pages, Schema, Table), semua namespace/class ikut diupdate. **Efek samping wajar**: URL admin berubah dari `/admin/l-m-s/lms-baselines` → `/admin/l-m-s/lms-course-baselines` (Filament auto-derive slug dari nama Resource) — dicek dulu tidak ada referensi/bookmark hardcoded ke URL lama di manapun di kode, jadi aman.
- Shield permission (`View:LmsCourseBaselines`, dst) **tidak terpengaruh** rename folder — permission Shield berbasis nama **model** (`LmsCourseBaselines`, sudah begitu dari awal), bukan nama folder/class Resource. Dicek langsung ke tabel `permissions`, tidak ada baris yang jadi orphan.
- `composer dump-autoload` + `php artisan migrate` dijalankan, full test suite: 119 pass, 6 gagal — sama persis dengan baseline pre-existing sebelum sesi ini (dikonfirmasi `git stash` di Tahap 1), bukan regresi baru dari Tahap 2.

### Tahap 3 — Menengah (fitur nyata hilang, tapi bukan bug yang aktif merusak data hari ini)

| Gap | Catatan |
|---|---|
| #4 Course `scheduler` tidak punya job consumer | Butuh scheduled command baru (`php artisan lms:generate-scheduled-courses` + entri di `routes/console.php`) — baru dikerjakan **kalau memang ada course yang butuh mode berulang** (cek dulu ke user: ada course `type=scheduler` yang sudah dibuat tapi mandek?). |
| #5 `is_mandatory` tanpa target rule otomatis | Butuh logic baru di `CourseService` yang mengonversi `target_job_position_id`/`target_org_structure_id`/`competency_id` di `lms_course_plan_items` jadi assign otomatis ke `lms_course_users` saat plan direalisasikan — scope lumayan besar, cek dulu apakah alur "course plan" ini memang dipakai aktif sekarang. |

**Testing Tahap 3**:
- #4: test command scheduled-course-generator dengan course `type=scheduler` yang `next_run_date`-nya sudah lewat → instance baru benar-benar dibuat, `next_run_date` bergeser sesuai `repeat_cycle`/`repeat_interval`. Test juga course `type=seasonal` **tidak** ikut ke-generate (pastikan tidak ada efek samping ke tipe lain).
- #5: test realisasi 1 `lms_course_plan_item` dengan target job position tertentu → semua user di jabatan itu otomatis punya baris `lms_course_users`, dan yang bukan di jabatan itu tidak ikut ter-assign.

**Status: KEPUTUSAN BERUBAH (2026-07-14).**
- **#4 diganti total** — dicek dulu ke data: dari 14 course cuma **1** yang `type=scheduler`, dan `next_run_date`-nya kosong (tidak pernah kepakai). Scheduler otomatis dibatalkan, diganti **fitur "Duplikat Course"** (`CourseService::duplicateCourse()` + tombol "Duplikat" di `LmsCoursesTable.php`) — admin duplikat course lama sebagai template kapan saja perlu, lalu sesuaikan tanggal meeting-nya manual. Yang ikut disalin: meeting, materi, quiz yang terpasang (quiz bank tetap dipakai bersama, tidak digandakan), kompetensi. Yang **tidak** ikut disalin: peserta, kehadiran, hasil quiz, riwayat — course baru mulai kosong dari sisi peserta. Test: `tests/Feature/LmsCourseDuplicationTest.php` (1 test, pass, 12 assertion).
- **#5 sebagian dikerjakan (2026-07-14, revisi)** — auto-assign dari course plan (target jabatan/unit/kompetensi) **tetap ditahan**, lalu jadi tidak relevan lagi karena course plan sendiri dihapus total (lihat §Rencana Pelatihan di bawah). Tapi `is_mandatory` sendiri sekarang **punya konsumen nyata**, bukan lagi kolom mati:
  - Kolom + badge "Wajib"/"Opsional" dan filter berdasarkan itu di halaman **Peserta** admin (`ParticipantCourses.php`).
  - Diekspos ke API mobile (`is_mandatory` di `CoursesResource`/`CourseDetailsResource`) — peserta sekarang bisa lihat status wajib/opsional course-nya sendiri.
  - Cara assign tidak berubah (admin manual tetap `true`, self-enroll tetap `false` dari default kolom) — cuma nilainya sekarang benar-benar kelihatan efeknya.
  - Test: `tests/Feature/LmsMandatoryParticipantTest.php` (2 test, pass, 4 assertion).

### Penghapusan Total "Rencana Pelatihan" (2026-07-14, di luar Tahap 1-4, keputusan user)

Diskusi lanjutan dari Tahap 3 (di atas) menemukan bahwa **"Rencana Pelatihan" (Course Plan) dan "Pelatihan" (Course) sudah lama terputus di praktiknya** — kolom `realized_course_id` yang seharusnya menautkan rencana yang disetujui ke Course asli **tidak pernah diisi kode manapun**. Sementara itu Course sendiri sudah punya alur draft/publish sendiri (`CreateLmsCourses.php`, tombol "Save as Draft"/"Save & Publish") yang cukup untuk kebutuhan "belum siap". User memutuskan: **hapus total**, termasuk data.

**Yang dihapus** (konfirmasi dulu ke data: 2 rencana, 3 item usulan, 1 master approval flow, 2 approval snapshot — semua dihapus permanen, keputusan sadar):
- Filament Resource: `LmsCoursePlans/`, `LmsCoursePlanItems/` (12 file) + Policy (`LmsCoursePlanItemsPolicy`, `LmsCoursePlansPolicy`).
- Model: `LmsCoursePlans`, `LmsCoursePlanItems`, `LmsCoursePlanSchedules`.
- `CourseService::requestCourse()`/`approveCourseRequest()` + dependency constructor yang cuma dipakai 2 method itu (`ApprovalCreationService`, `ApprovalSnapshotRepository`, `ApprovalFlowService`).
- `CourseRepository::updateCourseRequest()`.
- Entry `lms_course_request` di dropdown modul `ApprovalFlowForm`/`ApprovalFlowsTable`.
- Migration `2026_07_14_140000_drop_lms_course_plan_feature.php` — drop 3 tabel (`lms_course_plan_schedules` → `lms_course_plan_items` → `lms_course_plans`, urutan aman FK) + purge `approval_snapshots`/`m_approval_flows` untuk `module_type`/`module = 'lms_course_request'` (activities/histories ikut cascade lewat FK).
- Migration susulan `2026_07_14_140100_purge_lms_course_plan_permissions.php` — 20 baris permission Shield hantu (`*:LmsCoursePlans`, `*:LmsCoursePlanItems`) dibersihkan (ketahuan setelah cek manual ke tabel `permissions`, bukan otomatis kehapus oleh migration drop tabel).

**Diverifikasi aman**: `RoleForm.php` grouping permission dinamis (tidak ada referensi hardcoded ke Resource ini), `route:list` sudah tidak ada rute `course-plan`, `CourseService` masih bisa di-resolve normal via container setelah constructor disederhanakan.

**Tidak ada test baru** untuk penghapusan ini — tidak ada perilaku baru untuk diverifikasi, cuma memastikan yang lain tidak ikut rusak (dicek manual: route list, container resolve, permission table).

### Tahap 4 — Santai (kosmetik / nice-to-have, effort lebih besar dari manfaat langsung — kerjakan kalau ada waktu luang, bukan prioritas)

- #6 Sertifikat generate PDF otomatis (dompdf) — sekarang upload manual sudah *cukup* jalan, cuma menambah kerja admin.
- #7 Deadline/due date course & quiz — belum pernah diminta eksplisit di requirement manapun.
- #8 Tracking level materi (resume video, "sudah dibaca") — butuh skema baru + investasi UI player, besar buat manfaat yang belum tentu dibutuhkan sekarang.
- #10 Rename tabel `trainer_assesments_results` (typo) — migration rename tabel di production ada risiko downtime/urutan deploy, manfaatnya cuma kosmetik.
- #11 `trainers.company` jadi FK ke `m_companies` — perlu keputusan data cleanup dulu (banyak baris `trainers.company` kemungkinan tidak match nama company manapun persis).
- #15 Rename `QuestionBanks`/`QuestionOptions` → `LmsQuestionBanks`/`LmsQuestionOptions` — cosmetic rename class, banyak file terdampak (repository, service, Filament) untuk manfaat predictability saja.

**Testing Tahap 4** (kalau salah satu poin di atas dikerjakan): minimal jalankan full `php artisan test` setelah tiap perubahan (kebanyakan poin di tahap ini rename/tambah field, bukan logic baru — resiko utamanya referensi yang lupa diupdate, bukan logic salah). Untuk #6 (generate PDF): test hasil PDF-nya bisa dibuka & kontennya benar (nama peserta, nama course, tanggal), bukan cuma "tidak error saat generate".

### Cross-cutting — bukan 1 tahap, harus dicek di SETIAP tahap di atas sebelum ditutup

**#12 Tidak ada test coverage.** Tiap tahap di atas sekarang sudah punya bagian **"Testing Tahap N"** sendiri-sendiri — itu bukan opsional, itu **syarat tahap tersebut dianggap selesai**. Pola test yang dipakai reuse dari `tests/Feature/KanbanModuleTest.php`/attendance test (sudah ada di project, jangan bikin pola testing baru). Jangan tunda test sebagai "kerjaan terpisah nanti" — lampirkan di PR yang sama dengan perbaikannya, dan **jalankan** (`php artisan test`), bukan cuma ditulis lalu dilewati.

## Bagian B — Modul LMS ke Portal Karyawan (`sistem_lms`)

### Kenapa

Sama seperti kasus `sistem_keuangan` (`planning/2026-07-12-portal-menu-keuangan-payroll.md`): staf yang perlu kelola LMS (misal koordinator training/HC) sekarang **wajib** jadi role Admin dan masuk `/admin` penuh — akses kebanyakan buat orang yang cuma perlu urus kursus. Solusinya: buka sebagian halaman LMS di portal, digerbangi 1 permission baru `sistem_lms`, pola identik `CustomPermissionEnum::SISTEM_KEUANGAN`.

### Riset — kesiapan reuse Table Filament LMS (dicek langsung, bukan asumsi)

Pola `sistem_keuangan` bisa cepat karena `SalaryComponentsTable`/`IncentivesTable` **full modal CRUD** (tidak ada halaman Create/Edit terpisah) — tinggal dibungkus Livewire portal, reuse 100%. **LMS beda-beda per Resource**, sudah dicek satu-satu ke `getPages()`:

| Resource | Mode | Portal-ready langsung? |
|---|---|---|
| `LmsCategoriesResource` | Modal (cuma `index`, create/edit dikomentari) | **Ya** — persis pola Keuangan |
| `QuestionBanksResource` | Halaman terpisah (`create`/`edit` route) | Tidak — `EditAction`/`CreateAction` akan redirect ke `/admin/...` |
| `QuizResource` | Halaman terpisah (`edit` route) | Tidak — sama |
| `LmsBaselinesResource` | Halaman terpisah (`create`/`edit` route) | Tidak — sama |
| `LmsCoursesResource` | Halaman terpisah + aksi tambahan `participants`/`detail-participant` (`LmsCoursesTable.php:71`, `->url(fn ($record) => LmsCoursesResource::getUrl('participants', ...))`) | Tidak — paling kompleks, form course besar (500+ baris `LmsCoursesForm.php`) + drill-down peserta |

**Kesimpulan riset**: cuma `LmsCategoriesResource` yang bisa portal-ready secepat Keuangan. Sisanya (Course, Question Bank, Quiz, Baseline) kalau di-reuse apa adanya, tombol "Edit"/"Buat" di portal akan **melempar user balik ke `/admin`** — bertentangan sama tujuan awal (staf yang bukan Admin tidak punya akses `/admin` sama sekali, jadi malah 403 di tengah alur). Ini **bukan halangan**, tapi berarti scope-nya lebih besar dari sekadar "bungkus Table yang sudah ada" — perlu salah satu:
1. Bikin halaman portal baru yang page-based juga (mirror struktur `/admin/lms/...` tapi di bawah `/lms/...`, form direuse dari `LmsCoursesForm`/`QuestionBanksForm`/dst kalau formnya reusable lepas dari Resource-nya), atau
2. Ubah Resource-nya jadi modal-based (mengubah juga perilaku di `/admin`, bukan portal-only — perlu hati-hati, form Course itu besar & bertab, belum tentu cocok jadi modal).

**Ini keputusan produk, bukan keputusan teknis semata — perlu dikonfirmasi user sebelum eksekusi Fase 2.**

### Fase 1 — Fondasi permission + Kategori LMS (aman, ikuti pola Keuangan persis)

1. **Permission `sistem_lms`**: tambah `case SISTEM_LMS = 'sistem_lms';` di `App\Enum\CustomPermissionEnum` (+ `label()` "Sistem LMS"), daftarkan di `database/seeders/CustomPermissionsSeeder.php::$permissions`, jalankan ulang seeder. Tidak ada default role assignment — user assign manual lewat `/admin/roles`, sama seperti `sistem_keuangan`.
2. **Route baru** (`routes/web.php`, grup `auth + portal.access`):
   ```php
   Route::get('/lms/kategori', LmsCategoriesPage::class)->name('lms.kategori');
   ```
   + entry di `config/portal-pages.php`: `'permission' => \App\Enum\CustomPermissionEnum::SISTEM_LMS->value`.
3. **Livewire component** `app/Livewire/Lms/LmsCategoriesPage.php` — pola persis `SalaryComponentsPage.php`: `implements HasTable, HasSchemas, HasActions`, `table()` panggil `LmsCategoriesTable::configure($table)` apa adanya, `mount()` cek `abort_unless($user->can('sistem_lms'), 403)`.
4. **Nav** — `nav.blade.php` (desktop) + `pwa-sidebar.blade.php`/`pwa-bottom-nav.blade.php` (mobile), digate `@if (auth()->user()->can('sistem_lms'))`, dicontek langsung dari blok "Master Gaji" yang sudah ada.
5. **Testing Fase 1** — tambah test baru di `tests/Feature/` (pola `KanbanModuleTest.php`), sebelum fase ini ditutup:
   - User tanpa permission `sistem_lms` → akses route `/lms/kategori` langsung dapat 403.
   - User dengan permission → bisa lihat, buat, edit, hapus kategori dari portal (`Livewire::test(LmsCategoriesPage::class)`).
   - Nav item "Kategori LMS" tidak muncul di render untuk user tanpa permission, muncul untuk yang punya.
   - Regresi: `/admin/lms/categories` masih berfungsi normal seperti sebelum perubahan (reuse Table yang sama, jadi kalau ini rusak berarti ada sesuatu yang ke-couple tidak sengaja).
   - Jalankan `php artisan test` penuh, bukan cuma test baru — pastikan tidak ada modul lain yang kebentur perubahan `CustomPermissionEnum`/seeder.

### Fase 2 — Sisa Resource LMS (Course, Question Bank, Quiz, Baseline) — MENUNGGU KEPUTUSAN

Tidak dieksekusi sebelum user pilih salah satu dari 2 opsi di atas (halaman portal page-based baru, atau ubah Resource jadi modal). Kalau dipilih opsi 1 (halaman page-based baru), estimasi scope-nya mendekati membangun ulang bagian admin tapi di route portal — cukup besar, sebaiknya dipecah lagi jadi planning terpisah per Resource setelah keputusan ini diambil (`LmsCourses` sendiri kemungkinan butuh planning-nya sendiri, mengingat kompleksitas form + tab peserta).

**Testing Fase 2** (berlaku untuk planning turunan mana pun yang lahir dari fase ini): test akses per permission (sama pola Fase 1), plus test khusus alur page-based (create → redirect ke halaman portal yang benar, bukan ke `/admin`; edit → begitu juga) supaya kekhawatiran "kelempar balik ke admin" di §Riset di atas benar-benar terbukti sudah tidak terjadi, bukan cuma asumsi.

## Yang SENGAJA Tidak Direncanakan di Sini

- **"Kursus Saya" employee self-service** (`/dashboard` placeholder, lihat `analisis-lms.md` §9) — **beda scope** dari permintaan ini. `sistem_lms` itu gerbang buat staf **pengelola** LMS (setara admin terbatas), bukan buat karyawan biasa lihat kursus yang mereka ikuti. Kalau nanti mau dikerjakan juga, itu planning terpisah (tidak butuh permission `sistem_lms` — karyawan pengin lihat course dia sendiri, bukan kelola semua course).
- **Trainer/TrainingReport/CoursePlan Resource** ke portal — belum masuk lingkup Fase 1, nunggu Fase 1 terbukti jalan dulu baru diperluas kalau memang dibutuhkan.

## Referensi

- `knowledge/markdown/analisis-lms.md` §11-§12 — sumber semua nomor gap di Bagian A.
- `planning/2026-07-12-portal-menu-keuangan-payroll.md` — pola persis yang dicontek untuk Bagian B (permission custom, route, Livewire component, nav).
- `app/Enum/CustomPermissionEnum.php` — tempat nambah case `SISTEM_LMS`.
- `database/seeders/CustomPermissionsSeeder.php` — wajib diupdate barengan enum baru.
- `app/Livewire/Keuangan/SalaryComponentsPage.php` — contoh component portal yang paling mirip buat `LmsCategoriesPage.php` (Fase 1).
- `app/Filament/Resources/LMS/LmsCourses/Tables/LmsCoursesTable.php:71` — bukti konkret kenapa Course tidak bisa langsung di-reuse seperti Kategori.
