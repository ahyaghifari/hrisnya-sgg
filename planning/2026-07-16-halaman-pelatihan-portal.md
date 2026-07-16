# Halaman Portal "Pelatihan" (LMS peserta) — Rencana

## Context

`/dashboard` punya card "Kursus Saya" yang masih 100% placeholder hardcoded (lihat `knowledge/markdown/analisis-lms.md` §9). Modul LMS backend (course, meeting, materi, follow, absensi per-meeting, quiz) sudah lengkap dan dipakai aplikasi mobile lewat `routes/api.php` prefix `lms` + `CourseService`/`CourseUserService`/repository-nya — tapi belum ada versi portal Livewire (session-based, pola yang sama dipakai semua halaman portal lain). User menambahkan 3 mockup UI mobile (`knowledge/pelatihan-mobile-ui/*.jpg`) yang jadi acuan desain: halaman **"Daftar Pelatihan"** (list, tab kategori, avatar member, tombol Follow) dan **"Detail LMS"** (jadwal per meeting, indikator absensi, daftar materi file/PDF, tombol Follow). Faktanya `CoursesResource::toArray()` (`app/Http/Resources/LMS/CoursesResource.php:34`) sudah membentuk judul persis `"{code} - {title}"` seperti di mockup ("BHD2026 - BHD", "00001 - K3RS") — data sumbernya sama.

Scope disepakati dengan user (2026-07-16):
- Quiz **belum** ditampilkan di iterasi ini — materi + absensi dulu, quiz menyusul kalau memang dipakai.
- Absensi ditampilkan sebagai **indikator status saja** (bukan tombol clock-in interaktif) — pencatatan kehadiran tetap lewat jalur lain (mobile app/API).
- Halaman ini jadi **menu baru top-level** di navigasi (sejajar Kanban/Pengajuan Absensi), bukan disisipkan ke dashboard atau bottom-sheet "Menu".

## Pola yang direplikasi (bukan dibuat baru)

- List+detail Livewire pair: `app/Livewire/MyAttendanceRequests.php` + `AttendanceRequestDetail.php` — struktur class, `#[Layout(...)]`, `mount()` dengan route-model-binding + `abort_unless`.
- `baseQuery()` dipakai bareng oleh Filament `table()` (desktop) dan `getXxxProperty()` (mobile, paginated) — pola bersih di `app/Livewire/MyEmployees.php:44-58` (bukan pola duplikat query di `MyAttendanceRequests`).
- Mobile/desktop split: `<div class="md:hidden">` → `.pwa-list`/`.pwa-list-row` vs `<div class="hidden md:block">` → `{{ $this->table }}`. Contoh: `resources/views/livewire/my-attendance-requests.blade.php`.
- `<x-page-header title="..." :back="..." />` di setiap halaman.
- Route registrasi: dalam grup `Route::middleware(['auth', 'portal.access'])` di `routes/web.php:38-103`, mengikuti pola `kanban`/`kanban.show` (baris 92-102).
- `config/portal-pages.php` — entry `'permission' => null, 'guard' => null` (akses publik ke semua user login, sama seperti Kanban — tidak ada gating role khusus untuk pelatihan).
- Nav: item flat baru di `resources/views/components/nav.blade.php` (`.nav-links`, setelah item Kanban) dan `resources/views/components/pwa-bottom-nav.blade.php` (slot ke-5 di `.pwa-bottom-nav-inner`, mengikuti pola penambahan Kanban).

## File baru

- `app/Livewire/Pelatihan/TrainingList.php` — list course. Query dibangun langsung di atas `LmsCourses` (model), **bukan** lewat `CourseRepository::getAllCourse()` (repo itu filter kategori pakai `LIKE` nama, kurang cocok untuk tab by `category_id`; dan portal butuh pola `baseQuery()` yang dipakai dua kali seperti `MyEmployees`, bukan bentuk repo yang sudah dibungkus `paginate()` tetap).
  - `baseQuery()`: `LmsCourses::query()->where('status', 'published')->with('category')->withCount('courseUsers')->when($this->categoryId, fn($q) => $q->where('category_id', $this->categoryId))->orderBy('title')`.
  - Public property `?string $categoryId = null`, di-set lewat `wire:click` pada pill kategori (data dari `LmsCategories::orderBy('name')->get()`, sumber: `CourseRepository::getAllCategories()` — reuse repo untuk ini karena methodnya persis pas).
  - `table()` untuk desktop (kolom: judul `code - title`, periode, jumlah member, status/Follow action) + `getCoursesProperty()` untuk mobile (paginated).
  - Avatar stack "+N Members": replikasi ringkas dari `CoursesResource::toArray()` (`app/Http/Resources/LMS/CoursesResource.php:41,54-65`) — `courseUsers->take(3)` + fallback `ui-avatars.com` kalau tidak ada `profile_picture`. Field `total_members` dari `withCount('courseUsers')`.
  - Follow action: `followCourse(string $courseId)` method, panggil `app(\App\Repositories\LMS\CourseUserRepository::class)->assignUserToCourse(Auth::id(), $courseId)` langsung (reuse repo — method sudah generik, terima `$userId` sebagai parameter, tidak hardcode `auth('sanctum')` seperti `CourseUserService::assignUserToCourse()`). Guard: kalau `isUserEnrolledInCourse()` sudah true, tombol jadi badge status, bukan tombol.
- `resources/views/livewire/pelatihan/training-list.blade.php` — mobile `.pwa-list` + desktop Filament table, pill tab kategori di atas (elemen baru, tidak ada pola tab existing di portal — pakai `wire:click="$set('categoryId', ...)"` sederhana, styling ikuti existing pill/badge classes di `app.css`, bukan komponen Filament Tabs yang dipakai admin panel).
- `app/Livewire/Pelatihan/TrainingDetail.php` — detail course. `mount(LmsCourses $course)` dengan route-model-binding, eager load `with(['category', 'meetings.materials'])`. Ambil `LmsCourseUsers` milik user saat ini lewat `CourseUserRepository::getByUserIdAndCourseId(Auth::id(), $course->id)` (beri `courseUser`/status ke view, termasuk `meetingUsers` untuk cek `is_attended` per meeting — **jangan** pakai relasi `LmsMeetings::attendance()` (hasOne yang di API resource diperlakukan sebagai koleksi via `firstWhere` — bug laten yang jangan direplikasi, lihat `app/Http/Resources/LMS/MeetingsResource.php:11`); pola yang benar: `$courseUser->meetingUsers->firstWhere('meeting_id', $meeting->id)?->is_attended`).
  - Follow action sama seperti di list.
- `resources/views/livewire/pelatihan/training-detail.blade.php` — banner/thumbnail, judul, periode, accordion per meeting (tanggal/jam, badge indikator hadir/belum kalau sudah follow, daftar materi). Link materi: `Storage::disk('public')->url($material->file_path)` **eksplisit** (jangan `Storage::url()` tanpa `->disk('public')` — itu bug laten di `MeetingMaterialsResource`/`CoursesResource` karena default disk `local` tidak support `url()`, lihat temuan eksplorasi). Buka di tab baru (`target="_blank"`), tanpa controller gated baru — mirror pola akses publik-lewat-URL yang sudah dipakai API (di luar scope untuk menambah auth gate baru di sini).

## File yang diubah

- `routes/web.php` — tambah use statement + 2 route baru dalam grup `auth`/`portal.access`:
  ```php
  Route::get('/pelatihan', TrainingList::class)->name('pelatihan');
  Route::get('/pelatihan/{course}', TrainingDetail::class)->name('pelatihan.show');
  ```
  (ditaruh dekat blok Kanban, urutan tidak masalah karena tidak ada wildcard collision)
- `config/portal-pages.php` — tambah entry `'pelatihan'` dan `'pelatihan.show'` (`permission: null, guard: null`).
- `resources/views/components/nav.blade.php` — 1 `<a class="nav-item">` baru setelah item Kanban (baris ~38), ikon `fa-graduation-cap`, label "Pelatihan".
- `resources/views/components/pwa-bottom-nav.blade.php` — 1 `<a class="pwa-bottom-nav-item">` baru di `.pwa-bottom-nav-inner` (setelah Kanban, sebelum Akun).
- `resources/views/livewire/dashboard.blade.php` (atau partial terkait) — **tidak diubah** di iterasi ini (user pilih menu top-level baru, bukan mengganti card dashboard); dicatat sebagai known gap kalau nanti mau di-link-kan juga dari card "Kursus Saya" yang masih placeholder.

## Verifikasi

- `php artisan route:list --name=pelatihan` — pastikan 2 route baru terdaftar dengan benar.
- Test baru `tests/Feature/Portal/TrainingTest.php` (pola sama seperti `tests/Feature/Portal/DashboardTest.php`, `RefreshDatabase`): guest redirect ke login; user login melihat daftar course published; filter kategori mengembalikan subset benar; klik Follow membuat row `LmsCourseUsers` baru (status `requested`) dan tidak duplikat kalau diklik lagi; halaman detail menampilkan materi dengan URL disk `public` yang benar (assert string URL, bukan exception); course yang **belum** `published` tidak muncul di list.
- Manual: jalankan dev server (user selalu punya `npm run watch` jalan sendiri — jangan jalankan `npm run build`/dev server sendiri), login sebagai karyawan biasa, buka `/pelatihan` di breakpoint mobile (`<768px`) dan desktop, cek pill kategori, klik Follow, buka detail, klik salah satu materi (harus terbuka file, bukan error `RuntimeException`).

## Status

Belum dieksekusi — baru tahap rencana (2026-07-16), menunggu konfirmasi mulai coding.
