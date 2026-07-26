# LMS Lanjutan (Kuis, Baseline, Laporan) + Housekeeping Tabel + Gap Absensi (2026-07-26)

Dokumen ini merangkum satu sesi kerja panjang: melanjutkan migrasi fitur LMS dari admin Filament ke portal karyawan (Kuis, Baseline Pelatihan, Laporan Pelatihan), pembersihan tabel/model LMS yang sudah tidak berguna, dan penambalan 1 gap di pipeline absensi (`meets_target`/`out_of_window` untuk jadwal Flexible). Ditulis sebagai rujukan sebelum push ke `dev` dan `main`.

## 1. Bank Soal — perbaikan kecil

- Checkbox "jawaban benar" diganti jadi `<input type="radio">` asli (di-group per soal via `name`) — sebelumnya `type="checkbox"` + `wire:click` custom rawan desync visual (bug morphdom Livewire: klik checkbox men-set DOM property langsung, bisa tidak sinkron dengan state server). Native radio menjamin cuma 1 opsi ter-check per soal tanpa perlu JS tambahan.
- Kategori dijadikan **wajib** (dulu `nullable`) baik di halaman Tambah maupun modal Edit.
- Guard hapus: soal tidak bisa dihapus kalau sudah dipakai di kuis manapun (`quizzes()->exists()`) atau sudah punya riwayat jawaban peserta (`answers()->exists()`, relasi baru `LmsQuestionBanks::answers()` → `LmsQuizUserAnswers`). Alasan: FK `lms_quiz_user_answers.question_id` → `lms_question_banks` pakai `cascadeOnDelete()`, bukan `restrict` — tanpa guard aplikasi, hapus soal bisa diam-diam menghapus riwayat jawaban kuis peserta.
- File: `app/Livewire/LMS/BankSoalPage.php`, `CreateBankSoal.php`, `app/Services/LMS/QuestionBankService.php`.

## 2. Kuis — portal baru (list, buat, ubah)

Padanan portal dari `App\Filament\Resources\LMS\Quizzes` (yang di admin cuma punya List+Edit — create-nya malah tidak ter-route).

### 2.1 Struktur akhir
- **`QuizzesPage`** (`/lms/kuis`, route `pelatihan.kuis`) — list + search + filter kategori + modal view (read-only, daftar soal) + hapus. **Tidak ada modal edit kecil** — link "Edit" langsung ke halaman `CreateQuiz` dalam mode ubah.
- **`CreateQuiz`** (`/lms/kuis/tambah` = mode buat, `/lms/kuis/{quiz}/ubah` = mode ubah, route `pelatihan.kuis.create` / `pelatihan.kuis.edit`) — **1 komponen Livewire dipakai untuk kedua mode** (dibedakan lewat `mount(?string $quiz = null)`; kalau ada `$quiz`, preload kategori/judul/urutan soal existing dan pakai `Update:LmsQuizzes` permission, kalau tidak pakai `Create:LmsQuizzes`).

### 2.2 Alur halaman Buat/Ubah Kuis
1. Pilih **Kategori** (wajib) → baru bisa pilih soal.
2. Panel kiri: cari & pilih soal dari Bank Soal, difilter kategori yang sama.
3. Panel kanan: **repeater soal terpilih**, bisa diurut naik/turun (tombol panah, bukan drag-drop — sengaja, supaya tidak perlu library JS baru) dan dihapus dari pilihan.
4. Tombol **"+ Buat Soal Baru"** — modal 1 form soal (persis 1 baris repeater `CreateBankSoal`: tipe, pertanyaan Trix, opsi radio-benar), langsung tersimpan ke Bank Soal (`QuestionBankService::createOne()`, kategori ikut kategori kuis) lalu masuk ke daftar terpilih — supaya user tidak perlu pindah halaman kalau soal yang dicari belum ada.
5. Simpan → `QuizService::create()`/`updateQuiz()`.

### 2.3 Kolom urutan soal (`sort_order`)
Migration `2026_07_26_203506_add_sort_order_to_lms_quiz_questions_table.php` menambah `sort_order` ke pivot `lms_quiz_questions` (sebelumnya kolom ini tidak ada sama sekali, padahal `App\Services\LMS\QuizService::getQuizDetail()` — dipakai mobile quiz-taking — sudah lama mencoba `sortBy('sort_order')` yang selama ini no-op karena kolomnya tidak ada). `LmsQuizzes::questions()` ditambah `->withPivot('sort_order')->orderByPivot('sort_order')` — jadi urutan soal saat dikerjakan peserta sekarang benar-benar konsisten dengan urutan yang diatur di halaman ini.

### 2.4 Guard hapus Kuis
`QuizService::deleteQuiz()` menolak hapus kalau `LmsMeetingQuizzes::where('quiz_id', $id)->exists()` (kuis sudah dipasang di pertemuan pelatihan manapun) — pola sama dengan guard Bank Soal, alasan sama (FK `cascadeOnDelete()` berisiko menghapus riwayat hasil kuis peserta secara diam-diam). Admin `EditQuiz.php` **belum** punya guard ini — sengaja tidak diikuti/diperbaiki di sesi ini (di luar scope).

### 2.5 `QuizService` — 1 class lama dipakai ulang, bukan bikin baru
`App\Services\LMS\QuizService` **sudah ada** sebelumnya untuk alur peserta mengerjakan kuis (`getQuizDetail`, `submitQuiz`, `reviewEssayAnswers`, dst — dipakai mobile API). Method authoring (`create`, `updateQuiz`, `deleteQuiz`, `syncQuestions`) ditambahkan ke class yang sama, bukan bikin `QuizService` kedua yang membingungkan.

### 2.6 Bug Livewire yang ditemukan & diperbaiki
`CreateQuiz::mount()` awalnya di-type-hint `?LmsQuizzes $quiz = null` (implicit route-model binding). Ini merusak snapshot Livewire **khusus saat tidak ada parameter `quiz` sama sekali** (rute create) — request `Livewire::test(CreateQuiz::class)` tanpa params berhasil di render awal, tapi request lanjutan (`->set()`/`->call()`) error `"Trying to access array offset on null"` / snapshot jadi `null`. Perbaikan: ganti jadi `mount(?string $quiz = null)` (ULID mentah) + `LmsQuizzes::findOrFail($quiz)` manual di dalam. **Catatan untuk lain kali**: hindari type-hint model Eloquent langsung di `mount()` full-page Livewire component kalau ada rute lain yang memanggil komponen sama TANPA parameter itu.

### 2.7 Pemateri — Tom Select
`meeting-detail-form.blade.php` (form Pertemuan di `LmsCourseDetailPage`) — dropdown Pemateri diganti jadi Tom Select (`data-tom-select`, searchable), dibungkus `<div wire:ignore>` minimal (pola sama seperti semua Tom Select lain di app, wire:ignore wajib supaya Livewire tidak merusak DOM yang sudah di-render TomSelect). `LmsCourseDetailPage::getTrainerOptionsProperty()` difilter `is_active=true`, dengan fallback: kalau pertemuan yang sedang diedit trainernya sudah dinonaktifkan, tetap ditampilkan (label "(nonaktif)") supaya tidak hilang begitu saja dari dropdown.

Juga ditambahkan: field **Jam Mulai/Jam Selesai** di form Pertemuan (kolom `start_time`/`end_time` di `lms_meetings` sudah ada sejak lama, tapi portal belum punya input-nya — cuma admin Filament yang punya).

## 3. Tab "Penilaian dan Gaji Pemateri" (di `LmsCourseDetailPage`)

Tab baru di halaman detail pelatihan portal. Pola picker sama seperti tab Pertemuan: course 1 hari langsung tampil form, course multi-pertemuan pilih dulu pertemuan mana (beda pertemuan bisa beda pemateri & beda hasil hitungan).

Dua jalur tergantung `LmsCategories::training_category`:
- **Scored** (Dokter Spesialis/Eksternal, `TrainingCategoryEnum::usesScoredHonor()`) — pilih 1 kategori per variabel `lms_honorarium_variables`, skor dijumlah, dicocokkan ke `lms_honorarium` (context+range) → `honor_amount`.
- **Fixed** (Umum/Sertifikasi Dokter Umum) — admin input nominal langsung, tanpa skor.

### 3.1 Tabel baru
- `lms_meeting_honorarium_assessments` (model `LmsMeetingHonorariumAssessment`) — 1 baris per pertemuan (unique `meeting_id`), simpan `trainer_id`, `total_score`, `honor_amount`.
- `lms_meeting_honorarium_details` (model `LmsMeetingHonorariumDetail`) — 1 baris per variabel yang dipilih kategorinya (unique `assessment_id`+`variable_id`).

Pola ini **sengaja meniru** cluster lama `TrainerAssessments`/`TrainerAssessmentDetails`, tapi menunjuk ke tabel `lms_honorarium_variables/categories` yang baru (bukan `trainer_score_*` lama) — 2 mesin honorarium ini **independen total**, tidak saling menggantikan datanya, cuma sama-sama menulis ke `trainer_assessment_results` di ujungnya (lihat §7).

### 3.2 Service
`App\Services\LMS\MeetingHonorariumService` — `variablesForContext()`, `saveScoredAssessment()`, `saveFixedAmount()`. Keduanya (scored maupun fixed) meng-upsert `TrainerAssessmentResults` per (`course_id`, `trainer_id`) — tabel yang sama dipakai halaman admin "Gaji Trainer" (`Payroll\TrainerSalaries`, yang sebelumnya form-nya kosong total/tidak pernah dipakai — sekarang mulai terisi data dari jalur baru ini).

## 4. Baseline Pelatihan — portal baru

`LmsCourseBaselinesPage` (`/lms/baseline`, route `pelatihan.baseline`) — padanan portal `Filament\Resources\LMS\LmsCourseBaselines`. CRUD lewat modal (pola sama `LmsCategoriesPage`), bukan halaman terpisah — data flat.

- Field: Perusahaan (cuma muncul untuk user Global Company — `PermissionHelper::hasGlobalRole()` — user company biasa otomatis terkunci ke `company_id` sendiri), Tanggal Mulai/Selesai, Jumlah Kursus Minimum, Jumlah Jam Minimum.
- `is_active` **tidak ada di form** (sama seperti admin) — cuma toggle cepat dari list.
- **Tidak dipaksa single-active-per-company** meski deskripsi admin bilang "hanya boleh 1 aktif" — `TrainingReportPage` (admin) sendiri membiarkan admin **memilih** baseline aktif mana yang dipakai per company (artinya lebih dari 1 baseline aktif per company memang ditoleransi praktiknya). Memaksa exclusivity di sini berisiko mematahkan behavior Training Report yang sudah ada.
- Model `LmsCourseBaselines` pakai trait `BelongsToCompany` (global scope otomatis) — **tidak bisa diuji lewat `php artisan test`** karena trait ini sengaja skip scoping kalau `app()->runningInConsole()` true, dan seluruh proses `php artisan test` berjalan di console context. Bukan bug, cuma keterbatasan environment testing — perilaku scoping-nya tetap jalan normal di request web asli.

## 5. Laporan Pelatihan — portal baru

`TrainingReportPage` (`/lms/laporan`, route `pelatihan.laporan`) — padanan portal `Filament\Resources\LMS\TrainingReport` (halaman filter tunggal, bukan CRUD).

- Filter: pilih periode Baseline aktif (default: baseline milik company sendiri yang paling baru, fallback ke baseline aktif manapun yang paling baru — persis urutan prioritas admin).
- Tabel: NIP, Nama, kolom per pelatihan dalam jendela baseline (centang/silang + tanggal enroll), Total Jam Pelatihan, Jam Belum Terpenuhi, Total Pelatihan — urutan kolom & logika hitung **disalin persis** dari `TrainingReportPage` admin (`getReportData()`) supaya angka yang tampil di admin & portal tidak pernah berbeda.
- Export Excel memakai ulang class export yang sama dengan admin, `App\Exports\LMS\TrainingReportExport` — tidak dibuat export terpisah.

## 6. Reorder dropdown navigasi LMS

Urutan baru (desktop `nav.blade.php` & mobile `pwa-bottom-nav.blade.php`): **Pelatihan → Kategori Pelatihan → Pemateri → Bank Soal → Kuis → Honorarium → Baseline Pelatihan → Laporan**.

Logika pengurutan: entitas inti dulu, lalu master data yang paling dekat dengannya (kategori, pemateri — keduanya langsung dipakai pelatihan/pertemuan), lalu pasangan pembangun konten Bank Soal→Kuis (Kuis dibangun DARI Bank Soal, jadi taruh setelahnya), lalu Honorarium (dipakai tab gaji pemateri), lalu pasangan pelaporan Baseline→Laporan di paling akhir (Laporan dihasilkan DARI Baseline, keduanya tugas periodik/setup, bukan alur harian). Label "Laporan Pelatihan" dipersingkat jadi **"Laporan"** di dropdown (halaman itu sendiri tetap berjudul "Laporan Pelatihan").

## 7. Housekeeping — tabel/model LMS yang dihapus

Permintaan eksplisit: bersihkan tabel yang sudah tidak dipakai, **kecuali** `lms_question_options` (masih dipakai aktif oleh Bank Soal) dan `lms_rankings` (dikecualikan, belum diapa-apakan — ada jalur tulis+baca di `CourseRepository`/`CourseService`/`RankResource` API, ternyata TIDAK orphan, cukup belum ada tampilan portalnya).

### 7.1 Dihapus total (model + Filament Resource + Service/Repository + tabel)
Cluster honorarium **lama** — digantikan konsepnya oleh `lms_honorarium*` + `lms_meeting_honorarium_assessments/details` (§3), tapi keduanya sempat berjalan independen sebelum sesi ini:
- Tabel: `trainer_score_variables`, `trainer_score_categories`, `trainer_score_ranges`, `trainer_assessments`, `trainer_assessment_details`.
- Model: `TrainerScoreVariables`, `TrainerScoreCategories`, `TrainerScoreRanges`, `TrainerAssessments`, `TrainerAssessmentDetails`.
- Filament Resource: `LMS/TrainerScoreVariables/`, `LMS/TrainerScoreRanges/` (folder dihapus total).
- Policy: `TrainerScoreVariablesPolicy`, `TrainerScoreRangesPolicy`.
- Service/Repository: `App\Services\LMS\TrainerAssessmentService`, `App\Repositories\LMS\TrainerAssessmentRepository`.
- Tab admin **"Penilaian Trainer"** di form pelatihan (`LmsCoursesForm::trainerAssessmentSchema()`) dan pemanggilnya di `CreateLmsCourses::afterCreate()` / `EditLmsCourses::afterSave()` — dihapus.

**Yang TETAP ADA**: `trainer_assessment_results` (dan model `TrainerAssessmentResults`) — masih dipakai halaman admin "Gaji Trainer" (`Payroll\TrainerSalaries`) **dan** tab portal baru "Penilaian dan Gaji Pemateri" (§3.2). Ini satu-satunya sisa dari cluster lama yang masih hidup, jadi jangan ikut dihapus di masa depan tanpa cek ulang.

Migration: `2026_07_26_223540_drop_legacy_trainer_honorarium_cluster.php` — urutan drop mengikuti FK (`trainer_assessment_details` dulu → `trainer_assessments` → `trainer_score_categories` → `trainer_score_variables` → `trainer_score_ranges`).

### 7.2 `lms_course_schedules` — dihapus (tabel mati)
Relasi (`LmsCourses::schedules()`) dan delete-cascade-nya (`$model->schedules()->delete()` di boot event) ada, tapi **tidak ada satupun jalur kode yang pernah membuat baris** di tabel ini — selalu kosong. Migration `2026_07_26_223546_drop_lms_course_schedules_table.php`.

### 7.3 `lms_user_histories` — **dikonsolidasi**, bukan sekadar dihapus
Field-nya (`user_id`, `course_id`, `schedule_id`, `score`, `is_passed`, `passed_at`, `certificate_url`, `archived_at`) nyaris seluruhnya duplikat `lms_course_users` (`score`/`is_passed`/`passed_at` sama-sama disimpan di 2 tempat untuk pasangan user+course yang sama — risiko data drift). Field yang benar-benar unik cuma `certificate_url` dan `archived_at`.

Langkah konsolidasi (migration `2026_07_26_223545_consolidate_lms_user_histories_into_lms_course_users.php`, **harus jalan sebelum** drop `lms_course_schedules` karena `lms_user_histories.schedule_id` FK ke situ):
1. Tambah kolom `certificate_url` + `archived_at` ke `lms_course_users`.
2. Pindahkan data dari `lms_user_histories` (match by `user_id`+`course_id`).
3. Drop `lms_user_histories`.

Kode yang diarahkan ulang ke `lms_course_users`: `CourseRepository` (hapus `storeUserHistory`/`getUserHistoryByUserIdAndCourseId`), `CourseUserRepository::getFollowedCoursesByUserId`/`getCertificateHistory`, `CourseDetailsResource` (API — `certificate_url` dibaca langsung dari `$courseUser`), `ParticipantCourses.php` (admin upload sertifikat — `$record->update(['certificate_url' => ...])` langsung ke `LmsCourseUsers`, tidak perlu `updateOrCreate` model terpisah lagi), `LmsProgresListener` (penanda "sudah diproses selesai" sekarang pakai `archived_at` di `lms_course_users` sendiri, bukan ada/tidaknya baris terpisah).

**Penting**: `lms_user_histories` ternyata bukan cuma dipakai admin — dipakai juga di **mobile API** (`CourseUserService::getDownloadableCertificateUrl`, endpoint unduh sertifikat karyawan, dan filter "pelatihan selesai" di riwayat pelatihan). Konsolidasi ini menjaga semua jalur itu tetap jalan, cuma pindah sumber data.

### 7.4 Verifikasi housekeeping
Full test suite setelah housekeeping: hanya kegagalan pre-existing yang tersisa (dikonfirmasi lewat `git log` — file test yang gagal tidak tersentuh sesi ini), 3 test yang sempat gagal akibat perubahan (`LmsApprovalAndReviewTest`, `LmsCourseDueDateTest`, `LmsMandatoryParticipantTest` — semua referensi relasi/model yang dihapus) sudah diperbaiki dan lolos. `composer dump-autoload` & `route:list` mengonfirmasi tidak ada kelas hilang/fatal error di admin panel.

## 8. Gap Absensi — `meets_target` & `out_of_window` (Flexible)

Di luar topik LMS, sempat dibahas ulang pipeline absensi `access_logs → attendance_records` (lihat juga `pengumpulan-data-absensi.md`) untuk menjawab pertanyaan soal jadwal Flexible.

### 8.1 Alur singkat
1. Cron aaPanel → `php artisan schedule:run` tiap menit → `attendance:sync-access-logs` (`everyMinute()`, `withoutOverlapping()`, terdaftar di `bootstrap/app.php`).
2. `SyncAccessLogsAttendance::handle()` baca watermark (`sync_access_logs.last_synced_at`), panggil `AccessLogScheduleReader::readAndGroupIncremental()`.
3. Reader: baca `access_logs` baru sejak watermark → kelompokkan per (nip, tanggal) → **merge** dengan baris `attendance_records` yang sudah ada (`check_in` = `min`, `check_out` = `max` dari semua scan yang pernah terlihat) → resolusi jadwal per user (`ScheduleResolver`) → bucket per tipe (`fixed`/`flexible`/`shift`/`off`).
4. `FixedScheduleCalculator`/`FlexibleScheduleCalculator` menghitung metrik → `AttendanceRecordRepository::saveCalculatedResult()` → `updateOrCreate(['nip','date'], [...])` ke `attendance_records`.
5. Watermark di-update ke `access_datetime` terbaru.

**Konsekuensi desain penting**: karena `updateOrCreate` dikunci ke (`nip`,`date`), **tidak pernah ada baris ganda** berapa pun jumlah scan dalam sehari — scan tambahan cuma meng-update baris yang sama (check-in tidak pernah mundur, check-out selalu ikut scan paling akhir). Tapi ini juga berarti sistem **tidak bisa mendeteksi bolong di tengah hari** (scan pagi + scan sore dianggap kerja penuh, walau ada jeda tidak di kantor di antaranya) — keterbatasan bawaan pendekatan "scan pertama = masuk, scan terakhir = keluar", bukan bug.

**Catatan lain yang ditemukan saat penelusuran**: `buckets['shift']` dan `buckets['off']` dihitung `AccessLogScheduleReader` tapi **tidak diproses sama sekali** oleh `SyncAccessLogsAttendance` — cuma `fixed` dan `flexible` yang di-loop dan disimpan. Karyawan berjadwal shift tidak ikut ter-sync lewat command ini (mungkin disengaja, shift attendance ditangani jalur lain — belum dikonfirmasi, catat sebagai hal yang perlu dicek kalau ada laporan absensi shift tidak muncul).

### 8.2 Gap yang ditambal sesi ini
`FlexibleScheduleCalculator` (dan sebagian `FixedScheduleCalculator`) sudah lama menghitung 2 field tambahan tapi tidak pernah tersimpan ke DB — cuma dipakai print console di command diagnostic manual `attendance:process-access-logs` (`--save`), bukan jalur cron otomatis:

- **`meets_target`** (nullable boolean, khusus Flexible) — apakah total jam kerja (working + overtime) hari itu ≥ `min_work_minutes` jadwalnya. `null` kalau belum check-out. Ini **satu-satunya sinyal kualitas kehadiran yang berarti untuk Flexible** (karena `status` selalu `present`, `late_minutes`/`early_leave_minutes` selalu `0` — konsep telat/pulang-cepat memang tidak berlaku untuk Flexible).
- **`out_of_window`** (boolean, Fixed & Flexible) — scan check-in (Fixed: & check-out) jatuh di luar jendela waktu yang wajar (Fixed: `checkin_buffer_minutes`/`checkout_buffer_minutes` di sekitar jam kerja; Flexible: `flexible_checkin_from`/`until` di level `Schedule`).

Perbaikan: migration `2026_07_26_232652_add_meets_target_and_out_of_window_to_attendance_records_table.php` menambah kedua kolom, `AttendanceRecords` model + `AttendanceRecordRepository::updateOrCreate()`/`saveCalculatedResult()` diperbarui supaya keduanya benar-benar tersimpan lewat jalur cron otomatis. Test: `tests/Feature/FlexibleScheduleSyncTest.php` — simulasi scan di luar jendela + jam kerja di bawah target, jalankan command asli, pastikan kedua kolom persisten dengan nilai yang benar.

**Belum dikerjakan**: menampilkan `meets_target`/`out_of_window` di tabel Filament admin `AttendanceRecords` — datanya sudah tersimpan tapi baru bisa dilihat lewat query manual, belum ada kolom di UI admin.

## 9. Catatan Deployment (aaPanel)

Scheduler Laravel (`bootstrap/app.php` → `withSchedule()`) butuh **1 cron job** di aaPanel yang menjalankan `php artisan schedule:run` tiap menit — Laravel sendiri yang memutuskan task mana yang perlu jalan (`everyMinute`, `monthlyOn`, `yearlyOn`), bukan 1 cron per task.

Yang perlu dipastikan di server produksi:
1. Cron job aaPanel: `cd /path/project && php artisan schedule:run >> /dev/null 2>&1`, jadwal tiap 1 menit, pakai path PHP CLI yang sesuai versi project.
2. `CACHE_STORE`/`CACHE_DRIVER` di `.env` **bukan** `array` — `->withoutOverlapping()` pada `attendance:sync-access-logs` butuh cache driver persisten (file/database/redis) supaya lock-nya benar-benar mencegah 2 eksekusi tumpang tindih.
3. Folder `storage/framework/` writable oleh user yang menjalankan cron (biasanya sama dengan user web server).
4. Task ini **tidak butuh** `queue:work`/Supervisor — sengaja jalan langsung lewat cron, bukan lewat queue (lihat komentar di `SyncAccessLogsAttendance`). Job `ShouldQueue` lain di aplikasi (kalau ada) tetap butuh worker terpisah, di luar cakupan ini.
