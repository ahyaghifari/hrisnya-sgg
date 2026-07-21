# Migrasi Sistem LMS ke Portal — Kategori, Honorarium, Pelatihan (list/create/detail) (2026-07-21)

Sesi ini melanjutkan arah besar "pindahkan modul dari admin Filament ke portal karyawan" (pola yang sama seperti modul Kepegawaian sebelumnya), khusus untuk sistem **LMS**. Dimulai dari rename URL Master Honorarium, lalu berkembang jadi: Kategori Pelatihan, list Pelatihan, form Tambah Pelatihan, dan halaman Detail Pelatihan dengan sub-modul Peserta/Pertemuan/Umpan Balik.

## Ringkasan Status Akhir

- **Namespace Livewire LMS dipindah**: `App\Livewire\Pelatihan\*` (yang sebenarnya bukan untuk karyawan) → `App\Livewire\LMS\*`. Folder `Pelatihan` sekarang murni untuk halaman employee-facing (`TrainingList`, `TrainingDetail`, `QuizAttempt`, route `/pelatihan`).
- **4 halaman portal baru berjalan penuh**: Kategori Pelatihan (CRUD modal), Pelatihan (list), Tambah Pelatihan (create), Detail Pelatihan (Peserta/Pertemuan/Umpan Balik — termasuk CRUD pertemuan+materi+quiz).
- **39 test** di `tests/Feature/LMS/` (5 file), semua lolos: `LmsCategoriesPageTest` (8), `MasterHonorariumPageTest` (7), `LmsCoursesPageTest` (6), `CreateLmsCourseTest` (5), `LmsCourseDetailPageTest` (13).
- **Belum disentuh**: Edit/Hapus course dari portal (cuma create+detail), Penilaian Trainer (sengaja di-skip, lihat §5), verifikasi manual browser (baru via `php artisan test`).

## 1. Rename & Migrasi Namespace

- URL Master Honorarium: `/pelatihan/master-honorarium` → `/lms/honorarium` (route name TETAP `pelatihan.honorarium` — sengaja tidak diubah, cuma prefix URL-nya).
- Semua halaman LMS baru pakai prefix URL `/lms/*`, tapi route NAME tetap pakai prefix `pelatihan.*` (konvensi yang sudah disepakati sebelumnya): `pelatihan.honorarium`, `pelatihan.kategori`, `pelatihan.kelola` (list), `pelatihan.kelola.create`, `pelatihan.kelola.show`.
- **PENTING — urutan route**: `/lms/pelatihan/tambah` HARUS didaftarkan di `routes/web.php` SEBELUM `/lms/pelatihan/{course}` (wildcard), kalau kebalik "tambah" akan ditangkap sebagai `{course}` dan gagal resolve.
- File & folder yang pindah:
  - `app/Livewire/Pelatihan/{MasterHonorariumPage,LmsCategoriesPage}.php` → `app/Livewire/LMS/`
  - `resources/views/livewire/pelatihan/{master-honorarium-page,lms-categories-page}.blade.php` → `resources/views/livewire/lms/`
  - `tests/Feature/Pelatihan/{MasterHonorariumPageTest,LmsCategoriesPageTest}.php` → `tests/Feature/LMS/`

## 2. Kategori Pelatihan (`/lms/kategori`, `pelatihan.kategori`)

- `App\Livewire\LMS\LmsCategoriesPage` — CRUD kategori (`lms_categories`) lewat modal, gate `CustomPermissionEnum::SISTEM_LMS`.
- **Kolom baru** `training_category` di `lms_categories` (migration `2026_07_21_193245_add_training_category_to_lms_categories_table.php`, nullable) — menyimpan value `App\Enum\LMS\TrainingCategoryEnum` (Umum/Sertifikasi-Dokter Umum/Dokter Spesialis/Eksternal), dipakai untuk menentukan skema honor trainer (lihat `knowledge/markdown/analisis-honorarium-trainer-pelatihan.md`).
- Label field ini di UI **"Jenis Pelatihan"** (bukan "Jenis Honorarium" — sempat dinamai begitu lalu diganti atas permintaan user, biar tidak rancu dengan modul Honorarium terpisah).
- **`TrainingCategoryEnum::internalCases()`** (baru, method statis) — 3 case internal (Umum/Sertifikasi-Dokter Umum/Dokter Spesialis), mengecualikan `EKSTERNAL`. Dipakai di dropdown "Jenis Pelatihan" (baik portal maupun admin `LmsCategoriesResource`) karena `lms_categories` **khusus pelatihan internal** — course dengan `source_type='external'` tidak lewat kategori ini.
- Admin `LmsCategoriesResource` (`/admin/lms-categories`) TIDAK dihapus, tetap ada berdampingan (pola konsisten dengan migrasi modul lain).

## 3. List Pelatihan (`/lms/pelatihan`, `pelatihan.kelola`)

- `App\Livewire\LMS\LmsCoursesPage` — padanan portal dari admin `LmsCoursesResource` (list saja, create/edit dikerjakan menyusul, akhirnya dikerjakan juga di sesi yang sama, lihat §4).
- **Tanpa filter/kolom Perusahaan** — scoping company tetap jalan di kode (`baseQuery()`), cuma UI-nya disembunyikan karena saat ini semua pelatihan company holding.
- **Tanpa filter/kolom Tipe** — list ini otomatis cuma menampilkan `source_type = 'internal'` (paksa di query), konsisten dengan keputusan Kategori yang juga khusus internal.
- **Status** ditampilkan sebagai badge berwarna (`LsmCourseStatus::label()`/`badgeColor()`, method baru di enum) — Draft abu-abu, Published hijau.
- **Zebra striping** di tabel desktop (`rgba(0,0,0,0.045)` light / `rgba(255,255,255,0.045)` dark, class `.lms-courses-table`).
- **BUG PENTING yang ditemukan & diperbaiki**: `Livewire\WithPagination` trait meng-override `Paginator::currentPathResolver()` jadi path RELATIF tanpa leading slash (`Livewire::originalPath()` return `request()->path()` mentah). Browser me-resolve href relatif itu sebagai dobel segment terhadap direktori URL saat ini (mis. `/lms/pelatihan` + relatif `lms/pelatihan?page=2` → `/lms/lms/pelatihan?page=2`). **Fix: JANGAN pakai trait `WithPagination`** untuk paginasi biasa — cukup `->paginate(10)` langsung di komputed property, tanpa trait, sama seperti pola `KaryawanPage` dan list lain di project ini yang sudah lebih dulu ada. Reset ke halaman 1 otomatis terjadi tiap filter berubah (request Livewire AJAX tidak bawa query string `page` dari GET awal), jadi tidak perlu `resetPage()` segala.

## 4. Tambah Pelatihan (`/lms/pelatihan/tambah`, `pelatihan.kelola.create`)

- `App\Livewire\LMS\CreateLmsCourse` — REDESIGN total dari admin `LmsCoursesForm` (5 tab: Detail/Jadwal/Pertemuan/Peserta/Umpan Balik, ratusan baris, `Repeater` bersarang untuk meeting+materi+quiz+trainer assessment). Portal **cuma 1 form tanpa tab** (awalnya 2 tab Detail+Jadwal, digabung jadi 1 form atas permintaan user).
- Field: Kategori, Kode, Judul, Tipe Peserta (nakes/non-nakes/all), Skor Minimum, Batas Waktu (opsional), **Kompetensi Terkait** (Tom Select multi-select, wajib minimal 1), Thumbnail (opsional). **Tanpa field "Sumber Pelatihan"** — dipaksa `internal` selalu.
- **Jadwal**: toggle "1 hari" (pola sama persis `SubmitLeaveRequest::toggleOneDay()`/`is_one_day`) — kalau aktif cuma 1 date picker, kalau nonaktif jadi rentang mulai–selesai.
- **1 tombol Simpan saja** (sempat ada 2 tombol Draft/Publish, disederhanakan jadi 1 — selalu simpan sebagai `LsmCourseStatus::Draft`).
- **Trik penting**: di balik layar, `save()` otomatis bikin 1 baris `LmsMeetings` (judul = judul pelatihan) karena `LmsCourses::start_date`/`end_date` di-derive dari meeting lewat hook `syncDatesFromMeetings()` (`LmsCourses::boot()` 'saving') — course TANPA meeting akan selalu punya `start_date`/`end_date` null. Setelah `$course->meetings()->create(...)`, kode manggil `$course->save()` LAGI supaya hook itu jalan ulang (sekarang meeting-nya sudah ada, jadi tanggal ke-derive benar). Ini quirk model yang sudah ada dari sebelum sesi ini, bukan bug baru.
- Redirect setelah simpan ke `pelatihan.kelola.show` (Detail Pelatihan).

## 5. Detail Pelatihan (`/lms/pelatihan/{course}`, `pelatihan.kelola.show`)

`App\Livewire\LMS\LmsCourseDetailPage` — 3 tab (pola tab sama seperti `ScheduleDetailPage`/Lihat Jadwal Absensi: underline tab, bukan pill).

### Header info
Di bawah `<x-page-header>`: grid 2 kolom full-width TANPA card/background — **Tanggal Pelaksanaan** (pill teal `#0f766e` / dark `#5eead4`, class `.lms-course-schedule-badge`) dan **Kompetensi yang Didapatkan** (badge polos, dari relasi `competencies`).

### Tab Peserta
- Tambah Peserta **BUKAN modal** — form inline yang muncul/hilang di tempat (tombol hilang saat form terbuka), Tom Select multi-select karyawan yang belum jadi peserta (`availableUserOptions` exclude yang sudah enrolled, computed ulang tiap render).
- Setelah Simpan, form otomatis tertutup + `selectedUserIds` reset.
- **Batalkan vs Hapus** (beda penting, sempat ditanya user): Batalkan (`cancelParticipant`) cuma ubah status jadi `canceled`, baris `lms_course_users` tetap ada (riwayat skor/progress tidak hilang). Hapus (`removeParticipant`) betul-betul menghapus baris — model `LmsCourseUsers` py hook `deleting` yang ikut menghapus `meetingUsers` terkait.

### Tab Pertemuan — REDESIGN dari admin, paling banyak simplifikasi
- **Tanpa tab "Penilaian Trainer" sama sekali** — dihapus total dari portal, tidak wajib diisi, pelatihan tetap bisa berjalan tanpa itu.
- **Tanpa filter Kategori di Quiz** — kuis otomatis difilter dari `category_id` pelatihan induknya (`getQuizOptionsProperty()`), user tinggal pilih kuis + durasi.
- **Tipe Absensi dipaksa `'offline'`** — tidak ada field-nya sama sekali di form.
- **`isSingleDayCourse`** (computed: `$course->start_date && $course->start_date === $course->end_date`) menentukan 2 mode tampilan yang BERBEDA JAUH:
  - **Course 1 hari**: TANPA accordion sama sekali (langsung tampil, di-preload di `mount()`), TANPA field "Judul Pertemuan" (otomatis = judul pelatihan), langsung ke field **Trainer**. Tombol "Tambah Pertemuan" disembunyikan total (memang cuma boleh 1 pertemuan, sudah auto-dibuat pas Tambah Pelatihan).
  - **Course multi-hari**: accordion collapse per pertemuan (klik header buat expand, chevron muter), tombol "Tambah Pertemuan" muncul, field Judul Pertemuan + Tanggal Mulai/Selesai per pertemuan ikut muncul.
- **Trainer WAJIB dipilih** (validasi `required`) — berlaku di kedua mode (1 hari maupun multi-hari), sempat opsional lalu diubah wajib atas permintaan user.
- Materi Pertemuan & Quiz: masing-masing list + form tambah inline (bukan modal), hapus per baris. Konten (Detail Pertemuan+Materi+Quiz) diekstrak ke partial `resources/views/livewire/lms/partials/meeting-sections.blade.php` supaya tidak duplikat antara mode accordion dan mode langsung; form Detail Pertemuan sendiri di `partials/meeting-detail-form.blade.php`.
- **Quirk model penting buat diketahui**: `LmsMeetings::booted()` py hook `saved`/`deleted` yang OTOMATIS re-sync `start_date`/`end_date` course dari SEMUA meeting-nya (min/max) — jadi tiap kali meeting baru dibuat/diubah/dihapus lewat portal, course ikut ke-update otomatis TANPA perlu kode tambahan manual (cuma perlu `$course->refresh()` di sisi Livewire biar properti PHP-nya ikut ke-refresh untuk re-render).

### Tab Umpan Balik
Read-only — rata-rata rating + daftar komentar dari `lms_course_feedbacks`. Belum ada cara submit feedback dari portal (itu alur karyawan yang terpisah, belum disentuh sesi ini).

## 6. Tom Select — Multi-Select Custom (dipakai pertama kali di sesi ini)

- Package `tom-select` **sudah ter-install** (`package.json`) dan sudah ada convention `initTomSelects()` di `resources/js/app.js` (dipakai 2 tempat sebelumnya, keduanya single-select). Sesi ini pertama kali pakai untuk **multi-select** (Kompetensi Terkait, Tambah Peserta).
- **Pola wajib**: `<select data-tom-select data-placeholder="..." multiple wire:model="propertyArray">`, dibungkus `<div wire:ignore>` (biar Livewire tidak morph ulang DOM yang dikelola Tom Select), **JANGAN tambahkan `class="form-input"` ke `<select>`-nya** — styling kotak sudah ditangani penuh oleh `.ts-wrapper .ts-control` (nambahin class itu bikin efek "kotak di dalam kotak").
- `initTomSelects()` di `app.js` di-update: `plugins: el.multiple ? ["remove_button"] : []` — otomatis kasih tombol hapus per tag kalau elemennya multi-select.
- CSS baru di `app.css` (`.ts-wrapper.multi .ts-control .item`, dll) — tag terpilih jadi pill navy (`rgba(4,44,108,0.08)` / dark `rgba(110,168,224,0.16)`), konsisten brand.
- Livewire native support multi-select (`el.target.multiple` di source JS Livewire) — tidak perlu workaround tambahan buat binding array.
- **Ingat jalankan `npm run build`** setelah ubah `app.js`/`app.css` (bukan cuma `resources/css`).

## 7. Belum Dikerjakan / Ditunda

1. Edit/Hapus Pelatihan dari portal — baru Create + Detail (yang detail sudah termasuk edit Detail Pertemuan per meeting, tapi field course-level seperti Judul/Kategori/Kompetensi/Thumbnail belum bisa diedit dari portal).
2. Force-delete/hapus permanen peserta atau materi yang sudah dihapus — belum ada (yang ada baru hard-delete langsung tanpa soft-delete/trash).
3. Penilaian Trainer — sengaja tidak dibangun sama sekali di portal (beda dari admin), belum ada rencana kapan.
4. Verifikasi manual browser (Playwright/manual click-through) — belum dilakukan sesi ini, baru lewat `php artisan test`.
5. Filter Perusahaan di list Pelatihan sengaja disembunyikan (bukan dihapus dari kode) — kalau company lain mulai pakai LMS, tinggal munculkan lagi UI-nya.

## Referensi

- `knowledge/markdown/analisis-honorarium-trainer-pelatihan.md` — konteks kenapa `training_category`/`TrainingCategoryEnum` ada (§6.4 gap yang mulai ditutup sesi ini: klasifikasi kategori).
- `app/Filament/Resources/LMS/LmsCourses/Schemas/LmsCoursesForm.php` — baseline admin yang jadi REFERENSI (bukan dicontek 1:1) untuk `CreateLmsCourse` & tab Pertemuan Detail.
- `app/Models/LMS/LmsCourses.php`, `app/Models/LMS/LmsMeetings.php` — baca dulu method `boot()`/`booted()` sebelum sentuh field `start_date`/`end_date`, ada 2 hook saling terkait (course sync dari meetings, meetings sync balik ke course).
- `resources/js/app.js` §`initTomSelects()`, `resources/css/app.css` §"Tom Select" — pola wajib dipakai tiap butuh multi-select baru di portal.
- Test: `tests/Feature/LMS/` (5 file) — jalankan `php artisan test tests/Feature/LMS` sebelum lanjut sesi berikutnya buat pastikan belum ada regresi.
