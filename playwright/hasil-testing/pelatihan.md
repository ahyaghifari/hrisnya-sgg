# Hasil Testing — Alur Pelaksanaan Pelatihan (Follow → Approve → Quiz)

**Tanggal eksekusi:** 16 Juli 2026
**Tool:** Playwright MCP (browser otomatis) + tinker (setup & approval)
**Environment:** `http://localhost:8000` — **database production** (`hris_db`), bukan data dummy
**Referensi plan:** [../test-plan-pelatihan.md](../test-plan-pelatihan.md)

## Akun yang dipakai

| Peran | Email | Nama |
|---|---|---|
| Peserta | ahyaghifari288@gmail.com | Muhammad Ahya Ghifari, S. Kom |

Approval dilakukan lewat tinker (setara tombol "Approve" di Filament `ParticipantCourses`), bukan akun approver sungguhan.

## Data uji yang dibuat

| Entitas | ID | Detail |
|---|---|---|
| Course | `01kxmg5skqx6kdcc54f6g5fvxa` | `QATEST-01` — Uji Alur Pelatihan Playwright, 1 pertemuan |
| Meeting | `01kxmg6vxqj0wjyfbxvspqhc0d` | Sesi Uji Coba, trainer "dr. QA Tester" |
| Meeting Quiz | `01kxmg6w348r86yy33kbtbjke1` | Quiz Uji Coba, 2 soal pilihan ganda, durasi 10 menit |

## Ringkasan hasil

| Langkah | Hasil |
|---|---|
| 1. Setup course+quiz+trainer (tinker) | ✅ (1 penyesuaian: kolom `meeting_date` tidak ada lagi di skema production) |
| 2. Follow sebagai peserta (Playwright) | ✅ status `requested`, tombol & seal "Menunggu" |
| 3. Approve (tinker) | ✅ status `approved` |
| 4. Kerjakan quiz (Playwright) | ✅ 1 halaman semua soal, submit → skor 100/100, retry diblokir ([screenshot](screenshots/pelatihan-quiz-options-selected.png)) |
| 5. Verifikasi akhir | ⚠️ **bug ditemukan** — lihat di bawah |

Alur end-to-end **berfungsi dari sisi user** (follow → approve → quiz → lihat skor) — semua yang terlihat di browser benar dan sesuai desain. Ditemukan 1 bug backend di lapisan bawahnya yang tidak terlihat dari UI.

## Bug ditemukan

### `LmsCourseMeetingUsers.quiz_completed`/`quiz_score` gagal ter-update — race condition queue job

**Root cause**: `CreateMeetingUsersJob` (dipicu event `LmsCourseUsers::created` saat follow) adalah job **queued**, dan tidak ada `queue:work` berjalan di environment ini (2 job job menumpuk pending di tabel `jobs`; ditemukan juga 18 `failed_jobs` lama tak terkait, pra-eksisting). Kalau user submit quiz sebelum job itu diproses, `QuizService::updateMeetingUserData()` menemukan `LmsCourseMeetingUsers` belum ada (`null`) — update jadi tidak match baris apa pun, dan nilainya tidak pernah dikoreksi ulang setelah job akhirnya jalan (job cuma bikin row baru dengan default `quiz_completed=false`, tidak tahu bahwa skor sudah pernah disubmit).

**Dampak**: skor individual quiz (`LmsQuizUserResults`) selalu benar (diverifikasi: 100/100 tersimpan & tampil benar). Yang salah adalah progress roll-up per-meeting/course yang bergantung pada `quiz_completed`.

**Scope**: bug di backend LMS lama (`QuizService`/`CreateMeetingUsersJob`), bukan di halaman portal Pelatihan yang dibangun sesi ini. Test PHPUnit (`QuizAttemptTest.php`) tidak mendeteksi ini karena `phpunit.xml` set `QUEUE_CONNECTION=sync` (job selalu selesai instan di test, race condition-nya tidak pernah muncul).

**Rekomendasi**: lihat bagian "Saran Pelaksanaan Pelatihan" di [test-plan-pelatihan.md](../test-plan-pelatihan.md) — perbaikan wajib #1 (jalankan queue worker permanen) dan #2 (`updateOrCreate` alih-alih `update`, atau job dibuat sinkron).

## Temuan tambahan (bukan blocker)

- 403 Forbidden saat load foto profil user (`/storage/profile-pictures/...`) — masalah permission storage yang sudah ada sebelumnya, tidak terkait fitur Pelatihan.
- Warning meta tag `apple-mobile-web-app-capable` deprecated — pra-eksisting, kosmetik.
- 2 warning "resource preloaded tapi tidak dipakai" untuk CSS build — kemungkinan sisa asset build lama (2 hash berbeda ter-preload), tidak berdampak fungsional.
- Snapshot Playwright kadang menangkap state DOM sebelum Livewire selesai re-render (bukan bug aplikasi) — konsisten dengan temuan sesi test Cuti/Lembur/Izin sebelumnya, solusinya reload/snapshot ulang.

## Data uji — belum dibersihkan

Course `QATEST-01` beserta meeting/quiz/course_user/quiz_result terkait **masih ada di production** menunggu konfirmasi cleanup dari user (sesuai catatan di plan — tidak dihapus tanpa izin eksplisit).

## File terkait
- Plan: [../test-plan-pelatihan.md](../test-plan-pelatihan.md)
- Kode diuji: `app/Livewire/Pelatihan/*`, `resources/views/livewire/pelatihan/*`
- Bug ditemukan di: `app/Services/LMS/QuizService.php` (`updateMeetingUserData()`), `app/Jobs/CreateMeetingUsersJob.php`
