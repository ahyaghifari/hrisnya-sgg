# Planning: Kuis & Umpan Balik Publik via QR Code (2026-07-28)

Status: **rencana final, siap dieksekusi — semua keputusan terbuka sudah dijawab.** Lanjutan dari sesi LMS hari ini (housekeeping `lms_rankings`, Pertanyaan Evaluasi/master feedback, JP, tombol Publish pelatihan, Mode Absensi). Sumber requirement: diskusi langsung dengan user.

## 1. Latar Belakang

Sejauh ini kuis dan umpan balik pelatihan cuma bisa diisi peserta yang **terdaftar & login** (`lms_course_users`, terikat `user_id`). User ingin kuis dan umpan balik bisa juga diisi **siapa saja tanpa login** — buka lewat link/QR publik, isi identitas manual (Nama, NIP, Perusahaan, Divisi/Jabatan/Unit), lalu kerjakan kuis atau isi umpan balik.

**Keputusan kunci** (dikonfirmasi user): submission publik **selalu jadi catatan terpisah** dari data peserta terdaftar — walau NIP yang diisi kebetulan cocok dengan salah satu peserta di `lms_course_users`, jawabannya **tidak** ditulis/digabung ke situ. Pencocokan NIP cuma boleh dipakai untuk *lookup/join* di sisi laporan (mis. "responden ini kemungkinan karyawan X"), bukan menyatukan skor resmi. Alasan: link publik tidak ada verifikasi identitas sama sekali, jadi tidak aman untuk mengklaim langsung ke data resmi.

## 2. Ringkasan Keputusan

| Aspek | Keputusan |
|---|---|
| Model data responden | Tabel baru `lms_respondents` — **satu konsep buat siapa saja yang mengerjakan kuis/mengisi umpan balik**, karyawan login maupun publik (§3 REVISI KE-2) |
| `user_id` di tabel jawaban | **Dihilangkan total** — jawaban/hasil kuis & umpan balik cuma mengenal `respondent_id`, tidak ada lagi kolom `user_id` di tabel-tabel itu |
| `user_id` di `lms_respondents` | **Tetap ada** (nullable), tapi cara pengisiannya lewat **pencocokan NIP saat submit**, bukan cuma dari sesi login — lihat §3.1 |
| Kaitan ke karyawan asli | Kalau NIP yang disubmit (baik dari sesi login maupun isian manual publik) cocok dengan `users.nip`, `user_id` langsung terisi otomatis; kalau tidak cocok/tidak diisi, dilewati (`user_id` tetap `null`) |
| Nama tabel jawaban kuis | `lms_quiz_user_answers` → **`lms_quiz_answers`**, `lms_quiz_user_results` → **`lms_quiz_results`** (rename + drop `user_id` + tambah `respondent_id` wajib) |
| Nama tabel umpan balik | `lms_course_feedbacks` **tetap nama sama** (tidak diminta rename), cuma `user_id` → `respondent_id` |
| Dampak ke jalur karyawan yang sudah jalan | **BUKAN LAGI "tanpa perubahan"** — `QuizService`, `QuizRepository`, `QuizAttempt.php` (portal), `Api\LMS\QuizController` (mobile), `CourseService::storeFeedback()` semua perlu disentuh (daftar lengkap §8) |
| Syarat publish kuis/umpan balik publik | Course harus **Published** dulu (tombol Publish yang sudah jalan) |
| Kapan token dibuat | **Otomatis** begitu course di-Publish (tidak ada tombol "Publish Kuis"/"Publish Umpan Balik" terpisah) — §4 |
| Token | Random panjang (40 char, `Str::random`), unique per baris, **bukan** ID yang bisa ditebak |
| Scope token kuis | Per `lms_meeting_quizzes`, **bukan** `lms_quizzes` — quiz bank dipakai ulang lintas pertemuan/pelatihan |
| Scope token umpan balik | Per `lms_courses` |
| QR Code | Reuse `App\Services\LMS\QrCodeService` + `simplesoftwareio/simple-qrcode` — bukan dependency baru |
| Rate limiting | `throttle` di route publik, pola sama seperti `/login` (`throttle:5,1`) |
| Auth | Route publik **di luar** middleware `auth` |

## 3. Skema Data — REVISI KE-2: Satu Konsep "Respondent" untuk Semua

Rencana pertama (tabel mirror `lms_public_*`) sudah dibatalkan. Rencana kedua (dual nullable `user_id`/`respondent_id` di tabel existing) **juga dibatalkan** — diganti pendekatan lebih bersih: **`user_id` dihapus total** dari tabel jawaban, semua yang mengerjakan kuis/mengisi umpan balik (karyawan login ATAU publik) direpresentasikan lewat satu tabel `lms_respondents`. Konsekuensinya, constraint `unique()` di database bisa dipakai lagi secara normal (tidak ada lagi masalah NULL-vs-NULL dari revisi sebelumnya), karena `respondent_id` sekarang **wajib diisi**, bukan opsional.

### 3.1 `lms_respondents` (rename dari rencana `lms_public_respondents`, sekarang generic)
```
id (ulid, pk)
course_id (FK lms_courses, cascade)
user_id (FK users, nullable, nullOnDelete — lihat aturan pengisian di bawah)
name (string) — selalu keisi: dari users.name (karyawan login) atau isian manual (publik)
nip (string, nullable) — dari users.nip (karyawan login) atau isian manual (publik, boleh kosong utk tamu eksternal)
company_id (FK m_companies, nullable) — dari users.company_id (karyawan login) atau pilihan manual (publik)
unit (string, nullable) — Divisi/Jabatan/Unit, manual (karyawan bisa dikosongkan, sudah ada di data kepegawaian)
created_at, updated_at
```

**Aturan pengisian `user_id` — dicek tiap kali submit, 2 jalur:**
1. **Karyawan login** (portal/mobile, `Auth::id()` ada) → `user_id` langsung diisi dari sesi login, tidak perlu cari-cari. `name`/`nip`/`company_id` di-snapshot dari `$user`.
2. **Publik (tidak login)** → sistem cek: NIP yang diisi manual di form cocok dengan baris `users.nip` yang ada? Kalau **cocok**, `user_id` ikut terisi (link otomatis, sekadar informasi — **tidak** menyentuh `lms_course_users` atau data resmi apa pun, sesuai keputusan §1). Kalau **tidak cocok atau NIP dikosongkan**, `user_id` dilewati/tetap `null`. `name`/`nip`/`company_id`/`unit` tetap dari isian manual publik itu sendiri (bukan ditimpa data karyawan yang match, supaya konsisten dengan apa yang benar-benar dia isi).

**Dedup**: `firstOrCreate` pakai `(course_id, user_id)` untuk jalur karyawan login (1 karyawan = 1 baris respondent per course, dipakai ulang lintas kuis/umpan balik course yang sama). Jalur publik **tidak** di-dedup — tiap submit form publik selalu jadi baris baru, walau `user_id` kebetulan ke-link ke karyawan yang sama (mencerminkan bahwa itu tetap submission terpisah, cuma "diketahui" identitasnya).

### 3.2 `lms_quiz_answers` (rename dari `lms_quiz_user_answers`)
```diff
  foreignUlid('meeting_quiz_id')->constrained('lms_meeting_quizzes')->cascadeOnDelete();
  foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
  foreignUlid('question_id')->constrained('lms_question_banks')->cascadeOnDelete();
- foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
+ foreignUlid('respondent_id')->constrained('lms_respondents')->cascadeOnDelete();
  string('user_answer')->nullable();
  boolean('is_correct')->default(false);
  integer('earned_points')->default(0);
- unique(['meeting_quiz_id', 'question_id', 'user_id']);
+ unique(['meeting_quiz_id', 'question_id', 'respondent_id']);
```

### 3.3 `lms_quiz_results` (rename dari `lms_quiz_user_results`)
```diff
  foreignUlid('meeting_quiz_id')->constrained('lms_meeting_quizzes')->cascadeOnDelete();
  foreignUlid('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
- foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
+ foreignUlid('respondent_id')->constrained('lms_respondents')->cascadeOnDelete();
  integer('total_score')->default(0);
  integer('max_score')->default(0);
  integer('correct_count')->default(0);
  integer('wrong_count')->default(0);
  boolean('is_completed')->default(false);
  timestamp('completed_at')->nullable();
  string('status')->default('completed');
  foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
  timestamp('reviewed_at')->nullable();
- unique(['meeting_quiz_id', 'user_id']);
+ unique(['meeting_quiz_id', 'respondent_id']);
```
Kolom `status`/`reviewed_by`/`reviewed_at` (alur review esai admin) tetap ada apa adanya — otomatis berlaku juga untuk hasil kuis publik. Lihat §7 poin 4 soal apakah esai perlu ditampilkan di kuis publik sama sekali.

### 3.4 `lms_course_feedbacks` (nama tetap sama, cuma kolom yang berubah)
```diff
  foreignUlid('course_id')->constrained('lms_courses')->onDelete('cascade');
- foreignUlid('user_id')->constrained('users')->onDelete('cascade');
+ foreignUlid('respondent_id')->constrained('lms_respondents')->onDelete('cascade');
  integer('rating')->nullable();
  text('feedback')->nullable();
```
`lms_course_feedback_answers` **tidak berubah** — tetap menunjuk ke `course_feedback_id`, tidak pernah langsung menyentuh user/respondent.

### 3.5 Token publik
```
lms_meeting_quizzes + public_token (string, nullable, unique)
lms_courses + feedback_public_token (string, nullable, unique)
```
`nullable` = belum di-publish. Digenerate **otomatis** (bukan tombol terpisah) — lihat §4.

### 3.6 Migrasi Data (WAJIB, karena tabel produksi sudah ada isinya)
Urutan migration harus:
1. Buat `lms_respondents`.
2. Backfill: untuk tiap kombinasi unik `(course_id, user_id)` yang ditemukan di `lms_quiz_user_results`/`lms_quiz_user_answers`/`lms_course_feedbacks` yang ada sekarang, buat 1 baris `lms_respondents` (snapshot dari data `users` saat migrasi jalan).
3. Buat tabel baru `lms_quiz_answers`/`lms_quiz_results` (nama baru, bukan rename in-place — lebih aman bisa diverifikasi datanya pindah lengkap sebelum tabel lama dihapus), isi dari data lama + `respondent_id` hasil lookup dari langkah 2.
4. Tambah `respondent_id` ke `lms_course_feedbacks` langsung (in-place, drop kolom `user_id` setelah backfill — tabel ini tidak perlu rename jadi bisa ALTER langsung).
5. Verifikasi jumlah baris cocok (`count(old) === count(new)` per tabel) sebelum drop `lms_quiz_user_answers`/`lms_quiz_user_results` lama.
Migration ini **harus dites di data non-kosong** (copy data production/staging), bukan cuma tabel fresh — beda dari migration LMS lain sesi ini yang semuanya additive/aman.

## 4. Alur Publish (portal, Detail Pelatihan) — Token Otomatis, Tanpa Tombol Terpisah

Tidak ada tombol "Publish Kuis"/"Publish Umpan Balik" tersendiri. Begitu tombol **Publish** pelatihan (yang sudah jalan, `LmsCourseDetailPage::publishCourse()`) ditekan dan berhasil:
1. Tiap `lms_meeting_quizzes` milik course itu yang belum punya `public_token` → digenerate sekalian (loop simpel di dalam `publishCourse()` atau lewat model event `LmsCourses::saved()` saat `status` berubah jadi `published`).
2. Kalau `requires_feedback = true` dan `lms_courses.feedback_public_token` masih kosong → digenerate juga.

Tab **Pertemuan** & header course tetap dapat tombol "Lihat QR" (bukan "Publish") untuk tiap kuis/umpan balik yang sudah punya token — buka modal QR + link mentah buat disalin, reuse komponen modal QR absensi yang sudah ada. Tombol ini muncul begitu course berstatus Published, tidak butuh aksi tambahan.

Kalau course di-*unpublish* lagi nantinya (belum ada fiturnya sekarang) — token **tidak** dihapus, cuma link publiknya perlu digerbangi cek `course->status === Published` juga di halaman publik (bukan cuma cek token ada), supaya training yang ditarik kembali ke Draft otomatis menutup akses publiknya tanpa perlu invalidasi token manual.

## 5. Halaman Publik (tanpa login)

Route baru di luar grup `auth`, prefix `/publik` (biar jelas beda dari portal):
```
GET  /publik/kuis/{token}          -> PublicQuizPage (Livewire)
POST /publik/kuis/{token}/submit   -> handled di dalam komponen Livewire, tapi tetap di-throttle
GET  /publik/umpan-balik/{token}   -> PublicFeedbackPage (Livewire)
```
Kedua route pakai middleware `throttle:20,1` (generous karena legitimate traffic bisa banyak orang buka bareng dari 1 QR di 1 ruangan/NAT/wifi yang sama — beda dari `/login` yang sengaja ketat 5/menit). Submit action (create respondent + jawaban) di-throttle lebih ketat per **sesi browser** (bukan cuma per IP) — detail di §7.

**Alur di dalam halaman:**
1. Form identitas: Nama*, NIP (opsional), Perusahaan* (select dari `m_companies`, reuse pola dropdown yang sudah ada di halaman lain), Unit/Divisi/Jabatan* (text manual).
2. Setelah submit identitas → `LmsRespondent` dibuat (`user_id` kosong karena publik), id-nya disimpan di Livewire component state (bukan session — reload halaman = mulai ulang, disengaja supaya tidak ada "lanjutkan kuis nanti" yang rawan disalahgunakan).
3. Render soal kuis (reuse struktur `QuizService::getQuizDetail()` — opsi diacak, tipe essay/multiple_choice) atau daftar `LmsFeedbackQuestion` aktif (skala 1-5).
4. Submit jawaban → panggil `QuizService::submitQuiz()`/`CourseService::storeFeedback()` yang sudah di-refactor (§8) dengan `respondentId` hasil langkah 2, tampilkan halaman "Terima kasih" (tidak ada leaderboard/skor ditampilkan ke publik — hindari insentif buat asal jawab demi lihat skor).

## 6. Skoring Kuis Publik

Karena `submitQuiz()`/`getQuizDetail()` sudah harus direfactor untuk resolve ke `respondent_id` (§8), skoring publik & karyawan **bisa pakai method yang sama** — bedanya cuma di titik pemanggil:
- Jalur karyawan (portal `QuizAttempt.php`, mobile `QuizController`): resolve `respondent_id` dari `Auth::id()` dulu (`firstOrCreate` di §3.1), lalu validasi enrollment `CourseUserStatus::APPROVED` tetap jalan seperti sekarang.
- Jalur publik (halaman baru): resolve `respondent_id` dari form identitas yang baru diisi, **tanpa** validasi enrollment (tidak ada `courseUser` sama sekali untuk publik), dan soal esai difilter keluar sebelum ditampilkan (§7 poin 4 — kuis publik cuma pilihan ganda).

Jadi logic inti skoring (100 poin dibagi rata soal pilihan ganda) **tidak diduplikasi** — cukup 1 method `submitQuiz(meetingQuizId, answers, respondentId)`, validasi enrollment jadi parameter opsional/dilewati kalau caller-nya jalur publik. Percabangan esai (`$hasEssay`, `max_score` nambah) tetap jalan apa adanya untuk peserta terdaftar; jalur publik otomatis tidak pernah mengirim jawaban esai karena soalnya sudah difilter di `getQuizDetail()` versi publik, jadi `$hasEssay` selalu `false` untuk submission publik tanpa perlu percabangan tambahan.

## 7. Rate Limiting & Keamanan

1. **Throttle submit**: per-IP saja rawan false-positive (banyak orang 1 ruangan bisa share IP kantor/wifi event), tapi tanpa itu 1 orang bisa spam submit berkali-kali dari device sama. Usul: kombinasi `throttle` per-IP di level route (generous, cegah bot) **+** cek aplikasi `exists()` sebelum insert (1 respondent cuma boleh submit 1x per `meeting_quiz_id`/`course_id`, kasih pesan "sudah pernah mengisi" alih-alih biarkan gagal di constraint `unique()` database §3.2/3.3).
2. **Token unguessable**: `Str::random(40)` dari Laravel sudah cryptographically random, aman dari brute-force praktis. Tidak perlu tambahan expiry kecuali diminta (token tetap valid selamanya sampai di-reset manual).
3. **Spam essay/feedback text**: tidak ada validasi konten (tidak ada moderasi kata kasar dsb.) di iterasi pertama — kalau jadi masalah nyata, bisa ditambah honeypot field / minimal delay JS sebelum submit-button aktif (anti-bot sederhana tanpa captcha pihak ketiga).
4. **Esai di kuis publik — DIPUTUSKAN: di-skip.** Form kuis publik cuma menampilkan soal pilihan ganda (`getQuizDetail()` publik filter `type !== 'essay'` sebelum dikirim ke frontend); soal esai tetap ada di kuis untuk peserta terdaftar seperti biasa, cuma tidak dikirim/diterima di jalur publik. Skor publik = 100% dari pilihan ganda saja. Konsekuensinya: **tidak perlu** UI admin "Review Esai Publik" sama sekali di iterasi ini — kalau nanti butuh, tinggal dibuka lagi (skema `status=pending_review`/`reviewed_by` di `lms_quiz_results` sudah generic, siap dipakai kapan saja tanpa migration tambahan).
5. **Dropdown Perusahaan — DIPUTUSKAN: semua company di sistem**, tanpa filter berdasarkan `lms_courses.company_id`. Pilih `m_companies` yang mana saja diperbolehkan (menutup kasus training lintas holding/peserta dari company lain).

## 8. File yang Terdampak (Jalur Karyawan Ikut Berubah — Bukan Lagi "Tanpa Perubahan")

Beda dari revisi sebelumnya, keputusan "hilangkan `user_id` total" berarti jalur kuis/umpan balik karyawan yang **sudah jalan di production** ikut disentuh. Daftar lengkap hasil pengecekan kode:

**Model & migration:**
- `LmsQuizUserAnswers` → rename jadi `LmsQuizAnswers` (+ update `$table`), sama untuk `LmsQuizUserResults` → `LmsQuizResults`.
- `LmsCourseFeedbacks` — `user_id` → `respondent_id`, relasi `user()` → `respondent()`.
- Model baru `LmsRespondent`.

**`App\Repositories\LMS\QuizRepository`** — 10 method, semua parameter/query `$userId`/`user_id` → `$respondentId`/`respondent_id`: `saveUserAnswer`, `saveUserResult`, `findUserResultByMeetingQuizId`, `getEssayAnswers`, `getAllAnswers`, `updateAnswerScore`, plus `getPendingReviewResults()`/`findResultById()` yang eager-load relasi `user` → jadi `respondent`.

**`App\Services\LMS\QuizService`** — `getQuizDetail()`, `submitQuiz()`, `reviewEssayAnswers()`, `updateMeetingUserData()`: tiap terima `$userId`, sekarang perlu resolve ke `$respondentId` dulu (§3.1 `firstOrCreate`) sebelum manggil repository. `updateMeetingUserData()` tetap nulis ke `lms_course_meeting_users` pakai `user_id` asli (tabel itu **tidak** ikut berubah, cuma dipakai buat karyawan — publik tidak pernah masuk situ karena publik tidak generate progress/completion tracking).

**Pemanggil (titik masuk)**:
- `App\Livewire\Pelatihan\QuizAttempt.php` (portal, employee) — semua `Auth::id()` yang dilempar ke `QuizService`/`QuizRepository` diganti resolve-respondent dulu.
- `App\Http\Controllers\Api\LMS\QuizController` (mobile) — sama.
- `App\Repositories\LMS\CourseRepository::storeFeedback()` + `App\Services\LMS\CourseService::storeFeedback()` (dipakai `Api\LMS\CourseController::storeFeedback`, endpoint yang barusan diubah sesi ini buat terima array jawaban per-pertanyaan) — `user_id` jadi resolve-respondent juga.

**Delete-guard yang perlu ikut dicek** (referensi tabel lama, cuma exists()-check, kemungkinan tinggal ganti nama tabel):
- `App\Filament\Resources\LMS\QuestionBanks\Pages\EditQuestionBanks.php`, `App\Services\LMS\QuestionBankService.php` — guard "soal sudah dipakai jawaban, tidak bisa dihapus".

**Tidak berubah**: `QrCodeService`, skema `lms_feedback_questions`, `lms_course_meeting_users` (progress tracking karyawan tetap pakai `user_id` asli, tidak pernah diisi untuk respondent publik).

## 9. Urutan Eksekusi yang Disarankan

1. **Migration data (§3.6)** — paling berisiko, dites dulu di data non-kosong (copy production/staging). Buat `lms_respondents`, backfill dari data existing, buat `lms_quiz_answers`/`lms_quiz_results` baru + pindahkan data, ubah `lms_course_feedbacks` in-place, verifikasi jumlah baris, baru drop tabel lama.
2. **Refactor jalur karyawan** (§8: `QuizRepository`, `QuizService`, `QuizAttempt.php`, `QuizController`, `CourseRepository`/`CourseService::storeFeedback`) — dikerjakan & dites **sebelum** ada satu baris kode publik ditulis, supaya kalau ada yang rusak, itu murni soal refactor internal, bukan bercampur sama fitur baru.
3. Full regression test jalur kuis & umpan balik karyawan (portal + mobile) — pastikan tidak ada yang rusak dari langkah 1-2 sebelum lanjut.
4. Model event/hook auto-generate token saat `LmsCourses` di-publish (§4) + tombol "Lihat QR" di portal (reuse modal QR absensi).
5. Halaman publik identitas + render soal/pertanyaan (read-only dulu, belum submit).
6. Logic submit publik (resolve respondent dari form, panggil `submitQuiz()`/`storeFeedback()` yang sudah di-refactor §8 — bukan method terpisah).
7. Rate limiting (§7) — throttle route + guard `exists()` anti-submit-ganda.

Semua keputusan terbuka sudah final (§7) — tidak ada lagi yang perlu dikonfirmasi sebelum eksekusi.
