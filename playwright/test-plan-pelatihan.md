# Uji Alur End-to-End Pelaksanaan Pelatihan (Playwright)

## Context

Fitur portal Pelatihan (list, detail, follow, approval gate, quiz) sudah selesai dibangun dalam sesi ini (lihat `app/Livewire/Pelatihan/*`, test unit di `tests/Feature/Portal/TrainingTest.php` & `QuizAttemptTest.php`, semua lulus). Test otomatis itu jalan di sqlite in-memory (`phpunit.xml`) — belum pernah dicoba end-to-end lewat browser sungguhan dengan alur user asli: **buat pelatihan → user follow → di-approve HC/admin → user kerjakan quiz**. Dokumen ini rencana untuk verifikasi itu, pakai Playwright MCP untuk bagian yang memang harus lewat UI (login & interaksi sebagai user), dan `tinker` untuk bagian setup/approval yang eksplisit diminta **tanpa** Playwright (lebih cepat & deterministik daripada klik-klik admin panel Filament yang belum ada scenario-nya).

**PENTING — ini database production** (`.env` `DB_DATABASE=hris_db`, dikonfirmasi di sesi-sesi sebelumnya). Course/quiz yang dibuat untuk uji ini akan berupa data sungguhan di production sampai dibersihkan manual di langkah terakhir. Kode course akan diberi prefix jelas (`QATEST-...`) supaya gampang dikenali & dihapus, dan **tidak akan dieksekusi apa pun tanpa konfirmasi user dulu**, terutama langkah tinker (`create`, `update`) dan approval.

User uji: **ahyaghifari288@gmail.com** (ada di DB, `id=01krd9ew8dwkfm4gkw5f6a3dwb`). Password login: `"password"` (semua password di production sempat direset massal ke ini di sesi yang sama — lihat riwayat percakapan).

## Prasyarat sebelum mulai

- [ ] Pastikan dev server aktif di `APP_URL` (`http://localhost` per `.env`) — Playwright MCP akan navigate ke sana. `npm run watch` (aset) tetap harus jalan terpisah seperti biasa.
- [ ] Konfirmasi ke user: boleh eksekusi tinker (buat course/quiz/trainer + approve course_user) di production DB ini?
- [ ] Konfirmasi role/akun mana yang dipakai untuk approval di langkah 3 — kalau lewat tinker, tidak perlu akun approver sungguhan; kalau user nanti mau ulang lewat Filament UI manual, siapkan akun Admin/HC yang aksesnya sudah benar (di luar scope dokumen ini).

## Langkah 1 — Setup course + quiz + trainer (tinker, tanpa Playwright)

Satu script tinker yang membuat:
1. `LmsCategories` (kalau perlu kategori baru, atau reuse yang sudah ada).
2. `Trainers` — nama trainer contoh, mis. "dr. QA Tester".
3. `LmsCourses` — `code` prefix `QATEST-`, `status` = `published` (wajib, biar muncul di `/pelatihan`).
4. `LmsMeetings` — **1 pertemuan saja** (sesuai alur diminta — ini yang memicu tampilan "Aktivitas Pelatihan" versi simpel, bukan accordion), `trainer_id` diisi ke trainer di atas.
5. `LmsQuizzes` + `LmsMeetingQuizzes` (attach ke meeting itu, isi `duration`).
6. 2-3 `LmsQuestionBanks` tipe **`multiple_choice`** (pakai `App\Enum\QuestionType::MultipleChoice` sebagai value, **bukan** string `'multiple_choice'` mentah — ada bug laten di `LmsQuestionBanks::saving()` yang expect objek enum, ketemu & didokumentasikan pas nulis `QuizAttemptTest.php` sesi ini) + `LmsQuestionOptions` per soal (salah satu `is_correct = true`) + `LmsQuizQuestions` (pivot quiz↔soal).

Referensi pola pembuatan data ini persis sama dengan fixture yang sudah dipakai & terbukti jalan di `tests/Feature/Portal/QuizAttemptTest.php::createMultipleChoiceQuiz()` — tinggal port ke skrip tinker, bukan menulis ulang dari nol.

**Verifikasi langkah 1**: `LmsCourses::where('code', 'like', 'QATEST-%')->first()->meetings->first()->quizzes` tidak kosong, course `status === 'published'`.

## Langkah 2 — Playwright: login & ajukan follow sebagai ahyaghifari288@gmail.com

1. `mcp__playwright__browser_navigate` ke `/login`.
2. Isi form login (email `ahyaghifari288@gmail.com`, password `password`), submit.
3. Navigate ke `/pelatihan` — pakai kolom pencarian (`Cari pelatihan...`) ketik `QATEST` supaya cepat ketemu di antara course lain (course list production kemungkinan besar sudah banyak).
4. Klik card course QA test → masuk `/pelatihan/{course}`.
5. Klik tombol **"Ikuti Pelatihan"**.
6. Verifikasi: tombol berubah jadi **"Menunggu persetujuan admin/HC"** (state `requested`, bukan langsung "sudah mengikuti" — ini behavior yang sengaja dibuat di sesi sebelumnya), seal di hero jadi warna pending ("Menunggu").

**Verifikasi via snapshot**: `mcp__playwright__browser_snapshot` sebelum & sesudah klik follow untuk bukti visual state berubah.

## Langkah 3 — Approve (tinker, tanpa Playwright)

```php
LmsCourseUsers::where('user_id', $userId)->where('course_id', $courseId)
    ->first()
    ->update(['status' => \App\Enum\CourseUserStatus::APPROVED->value]);
```
(persis logic yang sama dengan tombol "Approve" di `ParticipantCourses.php:148` Filament — cukup tinker, tidak perlu buka admin panel).

**Verifikasi langkah 3**: query ulang status `LmsCourseUsers` = `approved`.

## Langkah 4 — Playwright: kerjakan quiz sebagai ahyaghifari288@gmail.com

1. (Kalau sesi login masih aktif dari langkah 2, lanjut; kalau tidak, login ulang.)
2. Navigate ke `/pelatihan/{course}` (course yang sama) — verifikasi tombol follow sekarang **"Kamu sudah mengikuti pelatihan ini"** (bukan lagi pending, karena status sudah `approved`).
3. Di bagian "Aktivitas Pelatihan" (mode 1-pertemuan, bukan accordion — sesuai desain), klik baris **quiz** (ikon ungu, judul quiz).
4. Di halaman `/pelatihan/quiz/{meetingQuiz}` — pastikan **semua soal pilihan ganda tampil dalam 1 halaman** (gaya Google Form, sesuai keputusan desain), pilih 1 opsi per soal (`mcp__playwright__browser_click` per tombol opsi).
5. Klik **"Kirim Jawaban"**.
6. Verifikasi: halaman pindah ke state hasil ("Quiz sudah kamu kerjakan" + skor/benar/salah), tombol submit tidak muncul lagi.
7. Reload halaman quiz yang sama → pastikan tetap tampil hasil (bukan form lagi) — buktikan tidak ada retry, sesuai desain.

## Langkah 5 — Selesai: verifikasi akhir + tanya soal cleanup

- Cek `LmsQuizUserResults` untuk `user_id` + `meeting_quiz_id` itu ada 1 baris, `is_completed = true`.
- Cek `LmsCourseMeetingUsers.quiz_completed` ikut ke-update (otomatis lewat `LmsProgresListener`, tidak perlu aksi manual — sudah dikonfirmasi alurnya waktu baca kode `QuizService::updateMeetingUserData()`).
- **Tanya user**: hapus data uji (`LmsCourses` `QATEST-%` beserta cascade meetings/materials/quiz/course_users/quiz_results) sekarang, atau dibiarkan dulu buat referensi visual? Course/meeting/materials di-cascade delete otomatis lewat FK `cascadeOnDelete` kalau `LmsCourses` row-nya dihapus (lihat migration `create_lms_meetings_table` `cascadeOnDelete`) — tapi **jangan hapus tanpa konfirmasi eksplisit**, ini production data.

## Catatan tambahan

- Tidak menulis test PHPUnit baru untuk alur ini — ini QA manual/eksploratif lewat browser sungguhan di atas data production, bukan test otomatis yang akan di-commit ke repo (test otomatis untuk logic yang sama sudah ada & lulus di `QuizAttemptTest.php`, jalan di sqlite terisolasi).
- Kalau di tengah jalan ternyata dev server tidak reachable di `http://localhost`, konfirmasi ke user URL yang benar sebelum lanjut Playwright — jangan menebak.

## Evaluasi

**Dieksekusi 16 Juli 2026.** Dev server ternyata di `http://localhost:8000`, bukan `http://localhost` (`APP_URL` di `.env` menyesatkan — port 80 kena Apache/XAMPP default, bukan aplikasi Laravel).

- **Langkah 1 (setup tinker)** — sesuai ekspektasi dengan 1 penyesuaian: kolom `meeting_date` di `lms_meetings` sudah tidak ada di skema production (`Column not found: 1054`) meski masih ada di migration awal & `$fillable` model — schema drift dari migration lanjutan yang tidak sempat ditelusuri. Setelah kolom itu dihapus dari payload insert, sisanya jalan mulus: course, trainer, quiz, 2 soal pilihan ganda semua ter-create benar.
- **Langkah 2 (follow)** — jalan sempurna. Satu catatan tooling (bukan bug app): snapshot Playwright persis setelah klik kadang menangkap state SEBELUM Livewire selesai re-render (tombol masih kelihatan "Ikuti Pelatihan" walau data di database & bagian lain halaman — list Peserta — sudah update). Reload penuh langsung menunjukkan state benar. Pola sama dengan temuan sesi test Cuti/Lembur/Izin sebelumnya ("selalu snapshot ulang setelah klik").
- **Langkah 3 (approve tinker)** — jalan sempurna, 1 baris `update()`.
- **Langkah 4 (quiz)** — jalan sempurna secara visual: 1 halaman semua soal (gaya Google Form, sesuai spek), opsi teracak, highlight biru saat dipilih (dikonfirmasi via screenshot), submit → skor 100/100 (2 benar/0 salah) tampil, reload tidak menampilkan form lagi (retry benar-benar diblokir).
- **Langkah 5 (verifikasi akhir)** — **di sini ketemu bug nyata**, lihat di bawah.

### Bug ditemukan: `quiz_completed`/`quiz_score` di `LmsCourseMeetingUsers` gagal ter-update — race condition queue

**Root cause**: `CreateMeetingUsersJob` (dipicu saat follow, lihat `LmsCourseUsers::booted()`) itu **queued** (`QUEUE_CONNECTION=database` di production, bukan `sync`), dan **tidak ada `queue:work` yang berjalan** di environment ini (dikonfirmasi: 2 job `CreateMeetingUsersJob` menumpuk pending di tabel `jobs`, plus 18 `failed_jobs` lama tak terkait yang sudah ada sejak sebelum sesi ini). Jadi urutan kejadian sebenarnya:

1. User klik "Ikuti Pelatihan" → `LmsCourseUsers` dibuat, `CreateMeetingUsersJob` di-dispatch ke antrean (**belum jalan**).
2. User (setelah di-approve) submit quiz → `QuizService::submitQuiz()` → `updateMeetingUserData()` mencari `LmsCourseMeetingUsers` row untuk user+meeting itu — **row-nya belum ada** (job belum diproses) → `$meetingUser` bernilai `null`.
3. Kode tetap jalan (PHP warning "Attempt to read property on null" bukan exception fatal di production), tapi `updateByUserIdAndMeetingId()` gagal meng-update apa pun (WHERE clause tidak match baris manapun), jadi `quiz_completed`/`quiz_score` **tidak pernah tersimpan**.
4. Setelah aku jalankan `queue:work` manual untuk verifikasi, `CreateMeetingUsersJob` baru berhasil bikin row `LmsCourseMeetingUsers` — tapi dengan nilai default (`quiz_completed=false`, `quiz_score=0`, `is_attended=false`), **tidak pernah dikoreksi ulang** walau `LmsQuizUserResults` sudah benar (skor 100/100 tersimpan sempurna di sana).

**Dampak**: skor quiz individual (`LmsQuizUserResults`) selalu benar dan tampil benar ke user (diverifikasi visual). Tapi progress roll-up per-meeting (`quiz_completed`, dipakai buat status `is_completed` meeting & ujung-ujungnya status course `LmsCourseUsers`) bisa **stuck salah secara permanen** kalau user submit quiz sebelum queue worker sempat memproses `CreateMeetingUsersJob` — kondisi yang sangat mungkin terjadi kalau user follow lalu langsung (dalam hitungan detik) mencoba quiz, atau kalau `queue:work` memang tidak dijalankan di production (situasi nyata yang kutemukan di environment ini).

**Ini bug lama di backend `QuizService`/`CreateMeetingUsersJob`, bukan di kode portal Pelatihan yang kubuat sesi ini** — portal cuma jadi jalur pertama yang benar-benar memicu race ini lewat alur user manusia (test PHPUnit `QuizAttemptTest.php` tidak mendeteksi ini karena `QUEUE_CONNECTION=sync` di `phpunit.xml`, jadi job selalu langsung selesai sebelum kode lanjut — kondisi race-nya tidak pernah tereproduksi di sqlite test).

## Saran Pelaksanaan Pelatihan

- **Perbaikan wajib**:
  1. Jalankan `queue:work` (atau `queue:listen`/supervisor) secara permanen di production — ini prasyarat, bukan cuma buat fitur Pelatihan (job lain seperti notifikasi push, email rekrutmen juga menumpuk pending/failed, lihat `failed_jobs` count=18 yang sudah ada sebelum sesi ini).
  2. Perbaiki `QuizService::updateMeetingUserData()` supaya tidak diam-diam gagal kalau `LmsCourseMeetingUsers` belum ada — opsi termudah: `updateOrCreate()` alih-alih cuma `update()`, atau jadikan `CreateMeetingUsersJob` **sinkron** (dispatch langsung, bukan `ShouldQueue`) khusus saat follow, karena user bisa saja langsung lanjut ke meeting/quiz tanpa jeda.
- **Perbaikan disarankan**: tidak ditemukan masalah UX baru dari sisi tampilan/copy di alur ini — semua state (Menunggu/Diikuti/Selesai) jelas dan konsisten.
- **Proses operasional di luar kode**: approval saat ini murni manual lewat Filament (`/admin/.../participant-courses`) atau tinker — **tidak ada notifikasi ke approver** saat ada pengajuan follow baru. Kalau volume pengajuan pelatihan nanti banyak, approver harus rajin cek manual; pertimbangkan tambah entry ke sistem notifikasi yang sudah ada (`RequestStatusChanged`-style, lihat `playwright/hasil-testing/pengajuan-cuti-lembur-izin.md`) untuk approval pelatihan juga — tapi ini scope baru, bukan bug.
- **Gap yang sudah diketahui tapi sengaja belum dikerjakan** (soal essay, retry, gate approval kaku) — tes ini **tidak mengubah prioritas gap-gap itu**, semuanya tetap berjalan sesuai desain (retry diblokir dengan benar, gate approval berfungsi). Prioritas yang justru naik dari tes ini adalah bug queue race di atas.
