# Modul LMS (Learning Management System) — Analisis Teknis (2026-07-13)

Dokumen ini menjawab pertanyaan "bagaimana modul LMS (kursus, kuis, sertifikat, ranking, trainer) bekerja di sistem ini?" — ditulis dari pembacaan kode langsung (model, migration, service, repository, controller API, Filament resource, route), bukan asumsi. Rujukan gaya & kedalaman: `knowledge/markdown/pengumpulan-data-absensi.md`.

**Addendum 2026-07-14 (§12)**: struktur kode & penamaan dari review lanjutan — gap #13-17.

**Addendum 2026-07-14 (eksekusi perbaikan)**: gap esai (#1), validasi download sertifikat (#3), status approval (#5), `is_mandatory` (#6), dan course scheduler (#4, diganti fitur Duplikat Course) **sudah dieksekusi** — lihat status update di §11 dan detail lengkap di `planning/2026-07-14-perbaikan-lms-dan-akses-portal.md`. Fitur "Rencana Pelatihan" (§1.4 lama) **dihapus total** atas keputusan user, cluster tabelnya sudah tidak ada.

## Temuan Utama (kondisi 2026-07-13, sebagian sudah diperbaiki — lihat addendum di atas)

LMS di codebase ini adalah modul **API-first** (dikonsumsi lewat `routes/api.php` prefix `lms`, kemungkinan besar aplikasi mobile terpisah) yang **dikelola sepenuhnya lewat Filament admin panel** — bukan trainer self-service, bukan (belum) portal karyawan. Tiga gap besar ditemukan saat riset awal:

1. **Sertifikat bukan digenerate PDF otomatis** — melainkan **file upload manual oleh admin/HC**, meski `barryvdh/laravel-dompdf` sudah terpasang & dipakai modul lain (Payroll). *(Masih berlaku — sengaja tidak dikerjakan, lihat §11 gap #2.)*
2. ~~Soal essay tidak pernah bisa dinilai~~ — **sudah diperbaiki 2026-07-14**, lihat §11 gap #1.
3. **Portal karyawan (`/dashboard`) "Kursus Saya" masih 100% hardcoded HTML placeholder** — masih berlaku, di luar cakupan sesi perbaikan 2026-07-14 (lihat §9).

## 1. Model & Tabel

### 1.1 Cluster inti — Course/Meeting/Material

| Model | Tabel | Peran |
|---|---|---|
| `LmsCategories` | `lms_categories` | Kategori kursus, juga dipakai kategori soal & quiz |
| `LmsCourses` | `lms_courses` | Master kursus |
| `LmsMeetings` | `lms_meetings` | Sesi/pertemuan dalam 1 kursus |
| `LmsMaterials` | `lms_materials` | Materi per meeting (dokumen/teks/file) |
| `LmsCourseSchedules` | `lms_course_schedules` | "Batch" penyelenggaraan kursus (mis. "Batch Oktober 2025") |
| `LmsCourseCompetencies` | `lms_course_competencies` | Pivot kursus ↔ `m_competencies` |
| `LmsCourseFeedbacks` | `lms_course_feedbacks` | Rating + feedback teks per peserta |
| `LmsCourseBaselines` | `lms_course_baselines` | Target minimal jumlah kursus/jam per company per periode |

`lms_courses` (migration `2025_09_12_230628_create_lms_courses_table.php` + `2025_09_15_114809_update_lms_tables.php` + `2025_10_21_212309_create_lms_course_feedbacks_table.php`): `category_id`→`lms_categories` (nullOnDelete), `company_id`→`m_companies` (nullOnDelete, ditambah belakangan), `code` (unique), `type_nakes` enum `nakes/non-nakes/all`, `source_type` enum `internal/external`, `type` enum `seasonal/scheduler`, kolom penjadwalan berulang (`repeat_cycle`, `repeat_interval`, `first_start_date`, `last_end_date`, `next_run_date` — "Hanya untuk scheduler"), `minimum_score` (default 0, jadi passing score), `created_by`→`users` (nullOnDelete), `softDeletes`.

**Catatan penting**: `start_date`/`end_date` di-drop di migration `update_lms_tables` lalu **diisi ulang otomatis** oleh `LmsCourses::syncDatesFromMeetings()` (`app/Models/LMS/LmsCourses.php:86-96`, dipanggil di event `saving`) — bukan input manual, melainkan hasil agregasi `MIN(meetings.start_date)`/`MAX(meetings.end_date)`.

`lms_meetings`: `course_id`→`lms_courses` (**cascadeOnDelete**), `type_trainer` (`internal`/`external`, string bukan enum), `trainer_id`→`trainers` (nullOnDelete), `trainer_employee_id`→`users` (nullOnDelete, dipakai kalau trainer internal adalah karyawan sendiri), `attendance_type` (`online`/`offline`/`hybrid`, string bebas bukan enum), `sort_order`.

`lms_materials`: `meeting_id`→`lms_meetings` (**cascadeOnDelete**), `content` (text, materi berbasis teks), `file_path`/`file_type`/`file_name` (materi berbasis file — **tidak ada validasi/whitelist tipe file di migration**, `file_type` cuma string bebas, jadi PDF/video/gambar semua ditampung field yang sama tanpa constraint DB).

### 1.2 Cluster Quiz

| Model | Tabel |
|---|---|
| `QuestionBanks` | `lms_question_banks` |
| `QuestionOptions` | `lms_question_options` |
| `LmsQuizzes` | `lms_quizzes` |
| `LmsQuizQuestions` | `lms_quiz_questions` |
| `LmsMeetingQuizzes` | `lms_meeting_quizzes` |
| `LmsQuizUserAnswers` | `lms_quiz_user_answers` |
| `LmsQuizUserResults` | `lms_quiz_user_results` |

`lms_question_banks`: `type` enum **hanya** `multiple_choice`/`essay`, `max_score`/`min_score` per soal, `category_id`→`lms_categories` (nullOnDelete). `lms_question_options`: `question_id`→`lms_question_banks` (**cascadeOnDelete**), `is_correct` boolean — kebenaran jawaban pilihan-ganda ditandai per-opsi, bukan field jawaban terpisah di soal.

`lms_quizzes`: cuma wadah (`title`, `total_score`, `category_id`). `lms_quiz_questions`: pivot `quiz_id`↔`question_bank_id` (keduanya **cascadeOnDelete**) — 1 bank soal bisa dipakai ulang di banyak quiz.

`lms_meeting_quizzes`: pivot `meeting_id`↔`quiz_id` **unique** (1 quiz cuma bisa dipasang sekali per meeting), plus `duration` (menit) dan `sort_order` — ini yang jadi acuan API (`/quiz/{meetingQuizId}`), bukan `quiz_id` langsung.

`lms_quiz_user_answers`: FK ke `lms_meeting_quizzes`, `lms_quizzes`, `lms_question_banks`, `users` (**semua cascadeOnDelete**), `user_answer` (string — dipakai baik untuk `option_id` pilihan ganda maupun teks essay), `is_correct`, `earned_points`. **Unique** `(meeting_quiz_id, question_id, user_id)` — 1 jawaban per soal per user per attempt quiz-meeting.

`lms_quiz_user_results`: **unique** `(meeting_quiz_id, user_id)` — 1 hasil per user per quiz-meeting, `total_score`/`max_score`/`correct_count`/`wrong_count`/`is_completed`/`completed_at`.

### 1.3 Cluster Progress & Enrollment

`lms_course_users`: `user_id`, `course_id` (keduanya nullable, `nullOnDelete` — **tidak wajib**, beda dari kebanyakan tabel LMS lain yang `cascadeOnDelete`), `is_mandatory`, `status` (string bebas — dikontrol app-level oleh `App\Enum\CourseUserStatus`: `requested/approved/in_progress/completed/canceled`), `progress` (int, persentase), `score`, `ranking`, `enrolled_at`, `completed_at`, `is_passed`+`passed_at` (ditambah belakangan).

`lms_course_meeting_users`: granularitas **per meeting per user** — `is_attended`, `is_present` (beda maknanya, lihat §7), `quiz_completed`, `quiz_score`, `is_completed`, `completed_at`.

`lms_meeting_attendances`: log kehadiran mentah per meeting — `status` (default `present`, di-set jadi `late` dari service), `checked_in_at`. FK `meeting_id`/`user_id` **cascadeOnDelete**, **tidak ada unique constraint** di level DB untuk cegah double-checkin (dicegah di service level, lihat §3).

`lms_user_histories`: arsip hasil akhir per user per course — `schedule_id`→`lms_course_schedules` (nullable), `score` (decimal 5,2), `certificate_url` (text), `is_passed`+`passed_at`, `archived_at`. **Tidak ada unique constraint** `(user_id, course_id)` di migration meski dipakai sebagai kunci logis di kode (`updateOrCreate` di `ParticipantCourses.php:139-147`) — celah race-condition kalau 2 proses `LmsProgresListener` jalan bersamaan untuk course sama (queue job, lihat §7).

`lms_rankings`: `user_id`+`course_id` **unique**, `total_score`, `rank`, `calculated_at`. Index `(course_id, total_score)` dan `user_id` — didesain untuk query leaderboard per-course cepat.

### 1.4 ~~Cluster Course Plan (usulan/planning kursus)~~ — DIHAPUS TOTAL 2026-07-14

Fitur "Rencana Pelatihan" (`lms_course_plans`, `lms_course_plan_items`, `lms_course_plan_schedules`) **sudah dihapus total** atas keputusan user — tabel, model, Filament Resource, Policy, dan integrasi Approval Engine-nya semua dibuang (migration `2026_07_14_140000_drop_lms_course_plan_feature.php` + `2026_07_14_140100_purge_lms_course_plan_permissions.php`). Data yang ada saat penghapusan (2 rencana, 3 item usulan, 1 master approval flow, 2 approval snapshot) **hilang permanen** — keputusan sadar, dikonfirmasi user sebelum eksekusi.

**Alasan penghapusan**: `realized_course_id` (kolom yang seharusnya menautkan rencana yang disetujui ke Course asli yang dibuat dari rencana itu) **tidak pernah diisi kode manapun** — jadi di praktiknya rencana dan pelaksanaan course sudah lama terputus total, admin tetap harus bikin Course dari nol manual tanpa jejak sama sekali ke rencana asalnya. Sementara itu, Course (`lms_courses`) sendiri sudah punya alur draft/publish sendiri (`CreateLmsCourses.php` — tombol "Save as Draft"/"Save & Publish", pakai `App\Enum\LsmCourseStatus`) yang cukup untuk kebutuhan "belum siap dipublish" — jadi fitur Rencana Pelatihan dianggap tidak lagi punya nilai unik yang benar-benar tertegakkan di kode.

Lihat `planning/2026-07-14-perbaikan-lms-dan-akses-portal.md` untuk detail eksekusi lengkap.

### 1.5 Cluster Trainer

`trainers`: `name`, `expertise`, `email`, `phone`, `company` (**string bebas**, bukan FK ke `m_companies`), `address`, `type` enum `internal`/`external` (default `external`), `user_id`→`users` (nullable, nullOnDelete — link ke akun karyawan **hanya kalau** trainer internal).

`trainer_score_variables`/`trainer_score_categories`/`trainer_score_ranges`: konfigurasi rubrik penilaian trainer — 2 tipe variable (`honor`, `validity`), tiap variable punya kategori dengan skor (`trainer_score_categories.score`), lalu range skor total dipetakan ke `honor_amount` (nominal Rp) atau `validity_months` (`trainer_score_ranges`).

`trainer_assessments`: **per meeting** — `meeting_id`→`lms_meetings` (nullOnDelete), `trainer_id`→`trainers` (nullOnDelete), `score_honor`, `score_validity` (angka final, hasil agregasi dari `trainer_assessment_details`).

`trainer_assessment_details`: baris per variable+kategori yang dipilih penilai, `type` (`honor`/`validity`), `score`.

`trainer_assesments_results` (**nama tabel typo** "assesments" tanpa 's' kedua): hasil final **per course** (bukan per meeting) — `honor_amount` (decimal), `validity_months`, `valid_until` — hasil agregasi lintas-meeting oleh `TrainerAssessmentService`.

## 2. Alur Pembuatan Course

Course **hanya bisa dibuat lewat Filament admin panel** (`app/Filament/Resources/LMS/LmsCourses/LmsCoursesResource.php` + `Schemas/LmsCoursesForm.php`), dilindungi permission Filament Shield generik (`Create:LmsCourses`, dicek lewat `App\Policies\LMS\LmsCoursesPolicy::create()`) — **bukan role "trainer"**. Trainer (`trainers` table) sama sekali **tidak punya panel/login/self-service** untuk membuat atau mengelola course sendiri — trainer cuma jadi *data yang dipilih* di form meeting (`Select::make('trainer_id')->relationship('trainer', 'name')`, `LmsCoursesForm.php:294-299`) dan objek yang dinilai (§6).

Struktur konten: **Course → Meeting (sesi/pertemuan) → Material** (linear, 3 level, tidak ada konsep "module"/"section" terpisah — meeting itu sendiri berfungsi sebagai unit modul). Tipe konten materi (`lms_materials.file_type`) **bebas string, tidak divalidasi enum di DB maupun whitelist eksplisit di service** — PDF/video/gambar/dokumen lain semua ditampung kolom generik `file_path`/`file_type`/`file_name`, plus alternatif `content` (text) untuk materi teks murni.

Course punya 2 mode (`lms_courses.type`): **`seasonal`** (sekali jalan, tanggal manual) dan **`scheduler`** (berulang — `repeat_cycle`: daily/weekly/monthly/yearly, `repeat_interval`, `next_run_date`).

> **Gap**: kolom `next_run_date`/`repeat_cycle`/`repeat_interval` ada di migration dan fillable model, tapi **tidak ditemukan job/command apa pun** (grep `app/Console/Commands`, `app/Jobs`, `routes/console.php`, `bootstrap/app.php`) yang mengonsumsi `next_run_date` untuk benar-benar men-generate instance course baru secara otomatis — pola gap yang sama seperti `shift_template_days` di `pengumpulan-data-absensi.md` (data ada, consumer tidak ada). `LmsCourses::booted()` (`LmsCourses.php:118-131`) cuma membersihkan field scheduler kalau `type` diubah ke `seasonal`, tidak ada logic sebaliknya (generate run).

## 3. Alur Quiz/Assessment

Soal disimpan di **bank soal terpusat** (`lms_question_banks`) yang bisa dipakai lintas quiz lewat pivot `lms_quiz_questions`. Tipe soal cuma **2**: `multiple_choice` (opsi di `lms_question_options`, kebenaran per-opsi via `is_correct`) dan `essay` (jawaban teks bebas).

Penilaian (`app/Services/LMS/QuizService.php:65-153`, `submitQuiz()`):

- Skor total dipatok ke basis **100** untuk soal pilihan ganda (`$pointsPerMultipleChoice = 100 / $totalMultipleChoiceQuestions`, baris 86) — bobot antar soal PG selalu rata, **tidak** memakai `max_score`/`min_score` per soal yang sudah didefinisikan di `lms_question_banks` (kolom itu **hanya dipakai untuk soal essay**, `$maxAutoScore += $question->max_score`, baris 107).
- **Soal essay selalu diberi `earnedPoints = 0`** saat submit (baris 101-107, tidak ada logic scoring essay sama sekali) — `is_correct` di-set `false` secara hardcode. `$status = $hasEssay ? 'pending_review' : 'completed'` (baris 131) dihitung tapi **hanya dikembalikan di response API, tidak pernah disimpan** ke kolom manapun (`is_completed` yang disimpan **selalu `true`**, baris 141) — dan **tidak ada Filament resource/halaman untuk menilai ulang jawaban essay** (grep `LmsQuizUserAnswers` ke `app/Filament` nihil).

  **Kesimpulan: soal essay secara efektif tidak pernah bisa mendapat nilai** — otomatis 0 poin permanen, tanpa jalur manual review yang berfungsi.

- **Tidak ada retry/percobaan ulang**: `submitQuiz()` (baris 67-76) cek `findUserResultByMeetingQuizId()` dulu — kalau sudah ada hasil, langsung return hasil lama tanpa proses ulang. Constraint DB `unique(meeting_quiz_id, user_id)` menegakkan ini di level tabel juga. Jadi **1 quiz per meeting = 1 kali kesempatan submit seumur akun**, tidak ada mekanisme reset/retry di kode manapun.
- **Passing score** ada di level **course**, bukan quiz: `lms_courses.minimum_score` (default 0) — dicek di `LmsProgresListener::checkUserIsPassed()` (`app/Listeners/LmsProgresListener.php:158-162`), fallback `?? 70` kalau null. Skor akhir yang dibandingkan bukan skor quiz murni, tapi **skor gabungan** (lihat §7 — 50% rata-rata quiz + 50% persentase kehadiran).

Attendance meeting (`MeetingAttendanceService::clockIn`, `app/Services/LMS/MeetingAttendanceService.php`): double-checkin dicegah di **service level** (cek `findByMeetingAndUser` dulu, bukan constraint DB — rawan race condition kalau 2 request submit bersamaan). Status `present` vs `late` ditentukan murni dari perbandingan jam sekarang terhadap `meeting.start_date/start_time` s.d. `end_date/end_time` — **tidak ada geofencing/foto**, mirip pola di modul absensi kepegawaian.

## 4. Alur Sertifikat

**Tidak ada generator PDF otomatis untuk sertifikat** — meski `barryvdh/laravel-dompdf` sudah terpasang di project (dipakai modul lain seperti `PayrollService`), **tidak dipakai sama sekali** di LMS (grep `dompdf`/`Pdf::` ke `app/**/LMS/**` nihil).

Alur nyata (`app/Filament/Resources/LMS/LmsCourses/Pages/ParticipantCourses.php:122-153`): admin/HC membuka halaman "Peserta" course, action **"Unggah Sertifikat"** hanya muncul kalau `status === COMPLETED` (baris 128) dan otomatis ter-disable kalau sudah ada `certificate_url` terisi (baris 126) — jadi ini **upload file manual** (PDF/JPEG/PNG, maks 10MB, `FileUpload::make('certificate_file')`, disk `public`, folder `certificates/{course_id}/{user_id}`), bukan sertifikat yang digenerate dari template. `LmsUserHistories::updateOrCreate()` (baris 139-147) menyimpan path file ke `certificate_url`.

Syarat sertifikat bisa diunggah: `LmsCourseUsers.status === CourseUserStatus::COMPLETED` — dicapai kalau **semua meeting** di course itu `is_completed = true` per user (`LmsProgresListener::checkCourseCompletion()`, baris 97-156), bukan sekadar lulus quiz. `is_completed` per meeting sendiri butuh **kombinasi hadir (`is_attended`) + quiz selesai (`quiz_completed`)** (`QuizService::updateMeetingUserData()`, baris 175-183).

> **Gap**: Lulus (`is_passed`) tidak otomatis mensyaratkan sertifikat bisa diunggah — cek `is_passed` dan availability upload sertifikat itu independen (halaman upload cuma cek `COMPLETED`, tidak cek `is_passed`), jadi peserta yang selesai semua meeting tapi skornya di bawah `minimum_score` (gagal) tetap **bisa** diberi sertifikat kalau admin mau — tidak ada validasi silang.

Download sertifikat oleh peserta lewat API `GET /lms/courses/{courseUserId}/certificate` (`CourseUserController::downloadCertificate`, `app/Http/Controllers/Api/LMS/CourseUserController.php:56-77`) — baca file dari `storage/app/public/{certificate_url}` dan `response()->download()`.

## 5. Ranking/Leaderboard

Dihitung **per course**, bukan global (tabel `lms_rankings` unique `(user_id, course_id)`). Trigger: `CourseService::updateRanking()` (`app/Services/LMS/CourseService.php:52-77`) dipanggil dari `LmsProgresListener::checkCourseCompletion()` **hanya sekali** — saat `existingHistory` belum ada, artinya **ranking cuma dihitung ulang saat pertama kali user menyelesaikan course itu** (guard `if (!$existingHistory)`, `LmsProgresListener.php:117`), bukan tiap kali skor berubah setelahnya.

Perhitungan: `upsertCourseRanking()` simpan `total_score` user, lalu `recalculateCourseRanking()` (baris 68-77) **query ulang SEMUA ranking course itu, order by `total_score` DESC, lalu update `rank` 1..N secara sequential untuk setiap baris** — ini bukan batch/scheduled job, melainkan **inline recompute per-event** (setiap kali 1 user complete, seluruh leaderboard course itu di-`UPDATE` ulang N kali dalam 1 request/job). Tidak ada indikasi caching/materialized view — cukup untuk skala kecil, berpotensi jadi bottleneck kalau peserta per course banyak.

Endpoint baca ranking: `GET /lms/ranks` (`CourseController::getRanksByUserId` → `CourseRepositories::getRanksByUserId`).

## 6. Trainer

**Bukan role user** — `trainers` adalah tabel data master terpisah, bukan bagian dari sistem role/permission (`spatie/laravel-permission` via Filament Shield yang dipakai modul lain). Trainer bisa **internal** (`type=internal`, opsional link ke `user_id`, karyawan sendiri yang jadi pengajar) atau **external** (`type=external`, kontak manual: `name`/`email`/`phone`/`company` bebas teks, tanpa akun sistem sama sekali).

Trainer **tidak punya akses/panel apa pun** — semua penilaian trainer (`trainer_assessments`) dan hasil (`trainer_assesments_results`, honor & masa berlaku sertifikasi) **diinput dan dilihat oleh admin lewat Filament**, bukan self-service trainer. Rubrik penilaian dua sumbu — `honor` (nominal bayaran) dan `validity` (masa berlaku sertifikasi trainer, dalam bulan) — dikonfigurasi lewat variable→kategori→skor dan dipetakan ke hasil akhir lewat range skor. `TrainerAssessmentService::generateTrainerAssessmentResult()` (`app/Services/LMS/TrainerAssessmentService.php:25-88`) mengagregasi **rata-rata skor per meeting** dalam 1 course jadi 1 hasil akhir per trainer per course (`trainer_assesments_results`), termasuk `valid_until = now()->addMonths($validityMonths)`.

Hasil penilaian trainer (`honor_amount`) terhubung ke modul Payroll untuk pencairan honor lewat `TrainerSalaries` (`app/Filament/Resources/Payroll/TrainerSalaries/`). **Update 2026-07-14**: `TrainerScoreRangesResource`/`TrainerScoreVariableResource` yang sebelumnya ada di `Filament/Resources/Payroll/` (meski model-nya domain LMS) sudah **dipindah ke `Filament/Resources/LMS/`** dengan `navigationGroup = 'LMS'` — lihat §12 gap #14. `TrainerSalaries` sendiri tetap di Payroll (itu memang murni data pencairan gaji, bukan rubrik penilaian).

## 7. Progress Tracking Peserta

Granularitas terkecil yang dilacak sistem adalah **per meeting**, bukan per material. `lms_course_meeting_users` menyimpan 2 boolean terpisah: `is_attended` (pernah check-in, termasuk yang telat) vs `is_present` (check-in **tepat waktu**, di dalam rentang `start`–`end` meeting) — dibedakan eksplisit di `MeetingAttendanceService::clockIn()` (baris 40-89, cabang telat set `is_present=false` tapi `is_attended=true`).

**Tidak ada tracking level material** — tidak ada kolom "sudah dibaca/ditonton" per `lms_materials`, apalagi **resume position untuk video** (`lms_materials` cuma punya `file_path`/`file_type`/`file_name` generik, tidak ada kolom durasi/posisi tonton di migration manapun). Progress course (`LmsCourseUsers.progress`, persen) dihitung murni dari **rasio 2 komponen per meeting** (`is_attended` + `quiz_completed`), bukan dari materi individual (`LmsProgresListener::handle()`, baris 25-58: `$totalComponents = $totalMeetings * 2`).

Skor akhir (`LmsCourseUsers.score`) dihitung `countTotalScore()` (`LmsProgresListener.php:61-95`): **rata-rata skor quiz (bobot 50%) + persentase kehadiran (bobot 50%)** — rumus tetap (hardcoded `* 0.5` di kedua sisi), tidak configurable per course.

Event pemicu recompute: `LmsProgresUpdated` event (`app/Events/LmsProgresUpdated.php`), listener `LmsProgresListener implements ShouldQueue` (**async lewat queue**, bukan sync) — dipicu dari 3 titik: `MeetingAttendanceService::clockIn()` (setelah presensi), `QuizService::updateMeetingUserData()` (setelah submit quiz), dan (dikomentari-mati) `CourseService::getCourseById()` baris 43 (`// event(new LmsProgresUpdated(...))` — sengaja dimatikan, kemungkinan karena bakal terlalu sering fire di endpoint GET biasa).

## 8. Enrollment

**Dua jalur berbeda dengan default `is_mandatory` yang beda**:

1. **Manual assign oleh admin** (Filament, `LmsCoursesForm.php:462-539`, tab "Peserta") — admin pilih user dari `CheckboxList`, tiap user baru dibuatkan baris `lms_course_users` dengan **`is_mandatory => true` hardcoded** (baris 526) dan `status = REQUESTED`.
2. **Self-enroll oleh karyawan** lewat API `POST /lms/courses/{id}/follow` (`CourseUserController::assignUser` → `CourseUserService::assignUserToCourse` → `CourseUserRepository::assignUserToCourse`, `app/Repositories/LMS/CourseUserRepository.php:12-24`) — baris baru dibuat **tanpa** field `is_mandatory` di-set eksplisit (jadi jatuh ke default migration: `false`), `status = REQUESTED` juga. Ada guard cegah double-enroll (`isUserEnrolledInCourse` dicek dulu, `CourseUserService.php:23-25`).

**Tidak ada mekanisme "wajib per role/jabatan"** — `is_mandatory` cuma flag boolean tanpa target rule (tidak ada logic yang otomatis meng-assign semua orang dengan jabatan/kompetensi tertentu ke course, meski `lms_course_plan_items` punya kolom `target_job_position_id`/`target_org_structure_id`/`competency_id` yang **secara struktural cocok untuk itu** — tapi kolom-kolom ini cuma dipakai di tahap *usulan/planning* (§1.4), **tidak ada kode yang mengonversi target itu jadi assign otomatis** ke `lms_course_users` saat course direalisasikan).

**Tidak ada deadline/due date sama sekali** — grep `due`/`deadline` ke seluruh model & service LMS nihil. Yang ada cuma tanggal mulai/selesai meeting (dipakai buat status telat/hadir, bukan tenggat penyelesaian course).

## 9. Portal Karyawan — Status UI

**Belum ada** — dikonfirmasi eksplisit di `README.md:85`: "*Task Saya/Kursus Saya placeholder*" di `/dashboard`. Verifikasi kode: `resources/views/livewire/partials/home-cards.blade.php:123-147` — section "Kursus Saya" berisi **HTML statis hardcoded**, 2 kartu contoh ("Etika Pelayanan Publik" 60% progress, "K3 Perkantoran Dasar" nilai 88 lulus) yang **sama sekali tidak diambil dari database** — tidak ada `@foreach`, tidak ada binding ke `LmsCourseUsers`/`LmsCourses` apa pun di file ini. Tidak ditemukan satu pun Livewire component (`app/Livewire/**Course**`, `**Lms**`) untuk portal karyawan.

Kesimpulan: LMS 100% berjalan lewat 2 kanal saja — **Filament admin panel** (pengelolaan penuh: course/quiz/trainer/plan) dan **REST API** (`routes/api.php` prefix `lms`, presumably dikonsumsi aplikasi mobile terpisah — bukan portal Livewire ini). Portal karyawan berbasis Livewire **belum punya jalur LMS fungsional apa pun**.

## 10. Approval Engine

**Sebagian pakai, sebagian tidak** — hanya **`lms_course_plan_items`** (usulan/planning kursus) yang terhubung ke approval engine generik, lewat `module_type = 'lms_course_request'`:

~~`CourseService::requestCourse()`/`approveCourseRequest()` — terhubung ke Approval Engine lewat module_type `lms_course_request`~~ — **DIHAPUS TOTAL 2026-07-14** bareng seluruh fitur Rencana Pelatihan (§1.4). `module_type = 'lms_course_request'` juga sudah dibuang dari dropdown pilihan modul di `ApprovalFlowForm`/`ApprovalFlowsTable`.

**Course itu sendiri (`lms_courses`), enrollment (`lms_course_users`), dan quiz sama sekali tidak melalui approval engine** — status `REQUESTED`→`APPROVED` di `CourseUserStatus` **sekarang sudah fungsional** (beda dari temuan awal riset ini), lihat §11 gap #5 di bawah untuk update statusnya.

## 11. Gap/TODO Terkumpul

1. ~~Essay tidak bisa dinilai~~ — **BERES 2026-07-14**. Halaman Filament baru "Review Jawaban Esai" (`app/Filament/Pages/Lms/ReviewEssayAnswers.php`), admin input skor manual per jawaban esai (clamped ke `max_score` soal), `QuizService::submitQuiz()` sekarang beneran menyimpan status `pending_review` (dulu dibuang). Lihat `planning/2026-07-14-perbaikan-lms-dan-akses-portal.md` Tahap 1.
2. **Sertifikat manual upload, bukan generate PDF** (§4) — masih berlaku, sengaja tidak dikerjakan (effort besar, manfaat kosmetik — lihat Tahap 4 di planning).
3. ~~Sertifikat bisa diunggah tanpa cek `is_passed`~~ — **BERES SEBAGIAN 2026-07-14**. Upload tetap tanpa gate (keputusan sadar), tapi **download** sertifikat sekarang ditolak (403) untuk peserta `is_passed !== true` — lihat `CourseUserService::getDownloadableCertificateUrl()`.
4. ~~Course scheduler (`repeat_cycle`/`next_run_date`) tidak punya consumer~~ — **DIGANTI TOTAL 2026-07-14**. Dicek dulu ke data: cuma 1 dari 14 course yang pernah pakai `type=scheduler`, `next_run_date` tidak pernah terisi — scheduler otomatis dibatalkan, diganti fitur **"Duplikat Course"** (`CourseService::duplicateCourse()`, tombol di `LmsCoursesTable.php`).
5. ~~`CourseUserStatus::APPROVED` tidak pernah benar-benar di-set~~ — **BERES 2026-07-14**. Halaman Peserta admin (`ParticipantCourses.php`) dapat aksi Setujui/Tolak. `MeetingAttendanceService::clockIn()` dan `QuizService::submitQuiz()` sekarang menolak peserta yang belum `APPROVED`. Admin-assign langsung dari form Course otomatis `APPROVED` (bukan lagi `REQUESTED`), karena tindakan admin memilih peserta itu sendiri sudah setara persetujuan.
6. ~~`is_mandatory` tanpa target rule~~ — **auto-assign dari course plan TIDAK dikerjakan** (fiturnya sendiri sudah dihapus, lihat §1.4). Tapi `is_mandatory` sendiri **sekarang punya konsumen nyata**: badge "Wajib"/"Opsional" + filter di halaman Peserta admin, dan diekspos ke API mobile (`CoursesResource`/`CourseDetailsResource`) — dulu cuma ditulis, tidak pernah dibaca di manapun.
7. ~~Tidak ada deadline/due date untuk course maupun quiz~~ — **BERES 2026-07-14** (level Course, bukan quiz — sesuai keputusan user). Kolom `due_date` opsional di `lms_courses`, lewat deadline cuma badge "Terlambat" (tidak ada yang diblokir). Lihat `planning/2026-07-14-perbaikan-lms-dan-akses-portal.md` Tahap 4.
8. **Tidak ada tracking level material** (video resume position, "sudah dibaca") — **DILEWATI 2026-07-14**, di luar jangkauan backend ini sendirian (materi dikonsumsi lewat app mobile terpisah, butuh app itu lapor balik posisi tonton — tidak bisa diselesaikan dari sisi Laravel saja).
9. ~~`lms_user_histories` tanpa unique constraint `(user_id, course_id)`~~ — **BERES di Tahap 2, 2026-07-14** (sempat kelewat ditandai di sini). Dicek dulu 0 duplikat data existing, lalu migration tambah unique constraint. Lihat `planning/2026-07-14-perbaikan-lms-dan-akses-portal.md` Tahap 2.
10. ~~Nama tabel typo `trainer_assesments_results`~~ — **BERES 2026-07-14**: tabel di-rename jadi `trainer_assessment_results`, model `TrainerAssesmentsResults` → `TrainerAssessmentResults` (+ Policy, referensi Repository/Filament), permission Shield di-rename di tempat (20 role assignment tetap utuh). Dikonfirmasi eksplisit dulu sebelum eksekusi karena tabel production (5 baris data, tidak ada FK dari tabel lain — aman).
11. ~~`trainers.company` kolom string bebas, bukan FK ke `m_companies`~~ — **DIKOREKSI 2026-07-14, BUKAN GAP.** Dicek langsung ke data: 3 trainer yang punya `company` terisi, 0 yang cocok ke `m_companies` manapun ("PT Maju Jaya IT", "PT RSU Banjarbaeu", "Global Training Corporation" — nama perusahaan TEMPAT trainer eksternal itu bekerja, bukan perusahaan internal Syifa Global Group). Kolom string bebas ini justru sudah benar desainnya — memaksa jadi FK ke `m_companies` internal akan salah secara bisnis. Penilaian awal keliru karena cuma pattern-matching skema tanpa cek makna data sebenarnya.
12. **Tidak ada test coverage** untuk LMS — dikonfirmasi `README.md:102`: modul LMS termasuk yang "masih belum punya test" (beda dari portal karyawan & attendance request yang sudah ada `tests/Feature/`).

## 12. Struktur Kode & Penamaan (ditambahkan 2026-07-14, dari review struktur/database/naming)

Temuan tambahan dari pembacaan langsung struktur folder `app/Models/LMS`, `app/Repositories/LMS`, `app/Filament/Resources/LMS` — bukan bug fungsional, tapi menambah biaya kognitif developer yang bekerja di modul ini:

13. ~~`CourseRepositories.php` plural, sendirian~~ — **BERES 2026-07-14**: di-rename jadi `CourseRepository.php` (class `CourseRepository`), konsisten dengan repository LMS lain. Lihat `planning/2026-07-14-perbaikan-lms-dan-akses-portal.md` Tahap 2.
14. ~~Model trainer-scoring (domain LMS) dikelola dari Filament folder Payroll, tanpa penjelasan~~ — **BERES 2026-07-14**: dipindah beneran ke `app/Filament/Resources/LMS/TrainerScoreRanges/` dan `.../LMS/TrainerScoreVariables/`, `navigationGroup` di-set eksplisit `'LMS'`. URL admin ikut pindah dari `/admin/payroll/trainer-score-*` ke `/admin/l-m-s/trainer-score-*`. Permission Shield tidak orphan (berbasis nama model, bukan folder Resource).
15. ~~`QuestionBanks`/`QuestionOptions` tidak ikut prefix `Lms*`~~ — **BERES 2026-07-14**: di-rename jadi `LmsQuestionBanks`/`LmsQuestionOptions` (+ Policy, 12 file referensi diupdate: repository, service, Filament, test), permission Shield di-rename di tempat (20 role assignment tetap utuh).
16. ~~Folder Filament `LmsBaselines` tidak match nama model `LmsCourseBaselines`~~ — **BERES 2026-07-14**: folder di-rename jadi `LmsCourseBaselines/`, namespace & class ikut diupdate. URL admin ikut pindah dari `/admin/l-m-s/lms-baselines` ke `/admin/l-m-s/lms-course-baselines`.
17. ~~`lms_meeting_attendances` tanpa unique constraint anti double check-in~~ — **BERES 2026-07-14**: migration tambah unique `(meeting_id, user_id)`, dicek dulu 0 duplikat data existing sebelum apply.

**Status 2026-07-14**: #13, #14, #15, #16, #17 semua sudah selesai. Tahap 4 gap struktur/kode tuntas semua.

**Bonus temuan saat eksekusi Tahap 1** (`planning/2026-07-14-perbaikan-lms-dan-akses-portal.md`) — 2 bug laten baru, di luar daftar gap ini:
18. `QuizService::updateMeetingUserData()` memakai `auth('sanctum')->user()` untuk event `LmsProgresUpdated`, seharusnya user targetnya — kebetulan selalu sama selama cuma dipanggil dari API peserta sendiri, baru kepampang begitu dipanggil dari admin panel (guard beda). **Sudah diperbaiki.**
19. `LmsQuizUserAnswers::question()` salah target relasi (nunjuk ke pivot `LmsQuizQuestions`, padahal `question_id` sebenarnya nyimpan ID dari `QuestionBanks`). **Sudah diperbaiki** — sebelumnya `max_score` soal esai selalu ke-null lewat relasi ini.

## Referensi Terkait

- `knowledge/markdown/manajemen-persetujuan.md` — approval engine generik, termasuk status `lms_course_request` sebagai module terdaftar tapi belum punya efek samping generik.
- `knowledge/markdown/pengumpulan-data-absensi.md` — pola serupa (gap company-type, fallback tanpa validasi) yang juga muncul di LMS.
- `README.md:51,85,102` — LMS disebut sebagai modul HRIS, portal "Kursus Saya" ditandai placeholder, dan tidak ada test coverage untuk modul ini.
