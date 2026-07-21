# Honorarium Trainer & Biaya Pelatihan — Analisis Konsep vs Kode (2026-07-21)

Dokumen ini membandingkan konsep bisnis riil honorarium pelatihan (dijelaskan langsung oleh user) dengan mesin skor yang sudah ada di cluster `Trainer` modul LMS. Tujuannya: jadi rujukan sebelum memutuskan penyesuaian apa yang perlu dibangun. §1-4 murni analisis awal (sebelum ada kode baru). §6 (di bawah) mencatat progres implementasi yang sudah berjalan sejak analisis ini.

**Addendum 2026-07-21 — Status Implementasi**: lihat §6. Ringkas: tabel & model master baru sudah dibuat dan berjalan (bukan menambah kolom ke tabel `trainer_score_*` lama seperti diusulkan di §4/§5 — user minta tabel BARU `lms_honorarium*`, terpisah total dari cluster `Trainer` lama). Halaman portal CRUD-nya sudah bisa dipakai. **Belum** ada yang menghubungkan master data ini ke alur assessment/payroll sesungguhnya — lihat §6.4 "Belum Dikerjakan".

## 1. Konsep Bisnis (dari user, 2026-07-21)

Pelatihan (Training) terbagi jadi 2:

- **Pelatihan Eksternal** — karyawan ikut program yang diselenggarakan di luar perusahaan, dibiayai perusahaan.
- **Pelatihan Internal** — pematerinya dari lingkup perusahaan sendiri. Terbagi tetap jadi 3 tipe:
  1. **Pelatihan Umum** — pemateri siapa saja.
  2. **Pelatihan Sertifikasi / Dokter Umum** — pemateri bersertifikat, atau dokter umum.
  3. **Pelatihan Dokter Spesialis** — pemateri dokter spesialis.

Untuk Umum dan Sertifikasi/Dokter Umum, **honorarium sudah fixed** (nominal tetap, tidak dihitung).

Khusus **Dokter Spesialis** dan **biaya Pelatihan Eksternal**, honor/biayanya **dihitung lewat skor**:

- **Honorarium** punya rentang skor (mis. skor 5, skor 6-7) → tiap rentang skor punya nominal honorarium sendiri.
- Skor didapat dari kombinasi **Variabel** dan **Kategori**:
  - **Variabel** mengelompokkan kategori. Contoh: Dokter Spesialis → variabel *lama praktik*, *reputasi*, *spesialisasi*. Biaya Pelatihan Eksternal → variabel *lama pelaksanaan*, *meninggalkan waktu kerja*, *total biaya*.
  - **Kategori** adalah anak dari variabel, tiap kategori punya skor. Contoh variabel *lama praktik*: "< 10 Tahun" = skor 1, "10-19 Tahun" = skor 2, ">= 20 Tahun" = skor 3. Contoh variabel *lama pelaksanaan*: "Online" = skor 1, "Offline" = skor 2.
- Gabungan skor dari kategori-kategori terpilih menentukan honorarium/biaya final lewat pencocokan ke rentang skor.

## 2. Yang Sudah Ada di Kode

Mesin skor Variabel→Kategori→Skor→Range **sudah dibangun**, di cluster `Trainer` (`app/Models/LMS/Trainer/`):

| Model | Tabel | Peran |
|---|---|---|
| `Trainers` | `trainers` | Master trainer, `type` = `internal`/`external` (klasifikasi ORANG, bukan jenis pelatihan) |
| `TrainerScoreVariables` | `trainer_score_variables` | Variabel penilai. `company_id`, `name`, `type` (**cuma 2 pilihan: `honor` / `validity`**) |
| `TrainerScoreCategories` | `trainer_score_categories` | Anak dari Variable, `name` + `score` |
| `TrainerScoreRanges` | `trainer_score_ranges` | `company_id`, `type` (honor/validity), `min_score`, `max_score`, `honor_amount`, `validity_months` |
| `TrainerAssessments` | `trainer_assessments` | Per meeting: `score_honor`/`score_validity` — otomatis dijumlah dari `TrainerAssessmentDetails` yang variabelnya bertipe honor/validity (`updateTrainerAndScores()`) |
| `TrainerAssessmentDetails` | `trainer_assessment_details` | Baris skor per variabel+kategori per assessment |
| `TrainerAssessmentResults` | `trainer_assessment_results` | Hasil akhir per course+trainer: rata-rata skor semua meeting → dicocokkan ke `TrainerScoreRanges` → `honor_amount`, `validity_months`, `valid_until` |

Alur eksekusi (`TrainerAssessmentService::generateTrainerAssessmentResult()`, `app/Services/LMS/TrainerAssessmentService.php`):

1. Ambil semua meeting dalam 1 course.
2. Ambil `TrainerAssessments` tiap meeting, rata-ratakan `score_honor` dan `score_validity` lintas meeting.
3. Cocokkan rata-rata skor ke `TrainerScoreRanges` (`type='honor'` dan `type='validity'` terpisah) → dapat `honor_amount` dan `validity_months`.
4. Simpan/update `TrainerAssessmentResults` per course+trainer, `valid_until` = now + validity_months.

Ini **persis** mekanisme yang dijelaskan user: isi skor kategori per meeting → sistem jumlah/rata-rata → cocok ke range → keluar nominal.

Untuk klasifikasi Pelatihan Eksternal vs Internal, `LmsCourses` (migration `2025_09_12_230628_create_lms_courses_table.php`) sudah punya `source_type` enum `internal`/`external` — ini sudah cocok dan bisa dipakai.

## 3. Gap — Belum Cocok dengan Konsep Bisnis

1. **Tidak ada pemisah konteks "Dokter Spesialis" vs "Biaya Pelatihan Eksternal".** `TrainerScoreVariables.type` cuma `honor`/`validity` (dimensi: jenis output skor), tidak ada dimensi ketiga untuk membedakan variabel itu dipakai untuk menilai apa (honor trainer dokter spesialis vs biaya program eksternal). Kalau dua konteks ini sama-sama pakai `type='honor'`, kategori-kategorinya (mis. "lama praktik" skor 1-3 vs "lama pelaksanaan" skor 1-2) akan tercampur di satu kolam skor per company tanpa cara memisahkan mana untuk konteks yang mana saat assessment diisi.

2. **Tidak ada klasifikasi tetap 3 tipe Pelatihan Internal (Umum / Sertifikasi-Dokter Umum / Dokter Spesialis).** `LmsCourses` cuma punya `category_id` bebas ke `LmsCategories` (tabel master kategori generik/user-defined), bukan enum tetap. Belum ada tempat menyatakan "course ini Umum → honor fixed" vs "course ini Dokter Spesialis → honor dihitung dari skor".

3. **Honor fixed untuk Umum/Sertifikasi/Dokter Umum belum direpresentasikan sama sekali.** Tidak ada field/tabel yang menyimpan nominal honor tetap per tipe pelatihan. Kandidat tempatnya: Payroll `SalaryComponents`, atau resource **`Payroll\TrainerSalaries`** yang sudah ada scaffolding-nya tapi **form-nya kosong total** (`TrainerSalaryForm::configure()` → `components([])`, `TrainerSalariesTable` cuma tampilkan read-only trainer/course/honor/valid_until tanpa create/edit action) — jadi kemungkinan besar bagian ini memang belum pernah selesai dibangun.

4. **`Trainers.type` (internal/external) adalah klasifikasi ORANG**, bukan jenis pelatihan — jangan disamakan dengan `source_type` di `LmsCourses` atau dengan 3 tipe Pelatihan Internal. Trainer internal bisa saja mengisi Pelatihan Umum, Sertifikasi, atau Dokter Spesialis — tipe pelatihannya ditentukan course-nya, bukan trainernya.

## 4. Pertanyaan Terbuka (belum dijawab user)

Apakah pemahaman berikut benar:
- **Skor Dokter Spesialis** → menghasilkan **honor untuk trainer** (karena trainernya staff/dokter internal).
- **Skor Biaya Pelatihan Eksternal** → menghasilkan **biaya yang ditanggung perusahaan** (bukan honor trainer, karena trainer & programnya di luar perusahaan) — jadi secara konsep ini bukan "honor trainer" sama sekali, melainkan estimasi/plafon biaya reimbursement.

Kalau benar, keduanya perlu **variabel-set yang terpisah** walau berbagi mesin (Variable→Kategori→Skor→Range) yang sama — perlu semacam dimensi baru (`context`/`applies_to`, misal `dokter_spesialis` vs `biaya_eksternal`) di `TrainerScoreVariables` dan `TrainerScoreRanges` supaya kategori dari 2 konteks tidak tercampur.

## 5. Belum Diputuskan / Belum Dikerjakan (saat analisis ditulis)

- Skema penyesuaian tabel (`context` field atau pemisahan tabel per konteks).
- Representasi honor fixed untuk Umum/Sertifikasi/Dokter Umum (isi `TrainerSalaryForm` yang masih kosong, atau lewat `SalaryComponents` Payroll).
- Representasi 3 tipe tetap Pelatihan Internal + Eksternal di level `LmsCourses` (enum baru vs pakai `category_id` yang sudah ada).
- Tidak ada kode baru yang ditulis pada tahap ini — dokumen ini murni analisis untuk pengambilan keputusan berikutnya.

## 6. Status Implementasi (2026-07-21, addendum)

Keputusan user setelah analisis di atas: **jangan sentuh tabel `trainer_score_*` lama** (yang tadinya diusulkan di §4/§5 untuk ditambah kolom `context`) — bikin **tabel baru terpisah total**, `lms_honorarium*`, dan **tanpa `company_id`** (berlaku global lintas perusahaan, bukan per company seperti `trainer_score_*`). Baru cluster `Trainer`/`trainer_score_*` lama TIDAK diubah/dipakai sama sekali oleh fitur baru ini — keduanya kini berjalan independen.

### 6.1 Tabel & Model Baru

| Model | Tabel | Kolom | Peran |
|---|---|---|---|
| `LmsHonorarium` | `lms_honorarium` | `context`, `min_score`, `max_score`, `honor_amount` | Range skor → nominal (pengganti peran `trainer_score_ranges`, tapi global) |
| `LmsHonorariumVariable` | `lms_honorarium_variables` | `context`, `name`, `description` | Variabel penilai (mis. "Lama Praktik") |
| `LmsHonorariumCategory` | `lms_honorarium_categories` | `variable_id`, `name`, `score` | Anak dari Variable, skor acuan per kategori |

`context` = `App\Enum\LMS\TrainingCategoryEnum::scoredCases()` — cuma 2 nilai yang dipakai kolom ini: `dokter_spesialis` dan `eksternal` (2 kasus lain di enum, `umum` dan `sertifikasi_dokter_umum`, tidak scored jadi tidak pernah muncul sebagai `context`).

Migration: `2026_07_21_143249_create_lms_honorarium_table.php`, `..._143423_..._variables_table.php`, `..._143424_..._categories_table.php`. Semua sudah `migrate` di lokal.

### 6.2 Enum Baru

`App\Enum\LMS\TrainingCategoryEnum` (`app/Enum/LMS/TrainingCategoryEnum.php`) — 4 kasus (`UMUM`, `SERTIFIKASI_DOKTER_UMUM`, `DOKTER_SPESIALIS`, `EKSTERNAL`), method `label()`, `usesScoredHonor()` (true cuma utk 2 kasus terakhir), `scoredCases()` (filter helper), `color()` (solid/soft/text hex per konteks — dipakai buat beda-in tampilan tab), `icon()` (FontAwesome per konteks). **Belum dipasang di mana pun sebagai kolom klasifikasi course** — `LmsCourses` belum tersentuh, jadi gap §3 poin 2 (belum ada klasifikasi tetap 4 tipe pelatihan di level course) **masih terbuka**.

### 6.3 Halaman Portal

`App\Livewire\Pelatihan\MasterHonorariumPage` (`/pelatihan/master-honorarium`, route name `pelatihan.master-honorarium`) — gate permission baru `CustomPermissionEnum::SISTEM_LMS` (sudah di-seed & diberikan ke kedua role "Super Admin"). Nav: dropdown "Learning Management System" di topnav desktop (`nav.blade.php`, warna teal) & menu sheet mobile (`pwa-bottom-nav.blade.php`), item pertama "Master Honorarium".

Desain akhir (setelah 3 kali redesign atas feedback user):
- Tab pilih konteks (Dokter Spesialis = indigo, Eksternal = gold — warna beda biar tidak ketuker).
- Layout 2 kolom: kiri 3/4 = tabel Variabel & Kategori (variabel di-rowspan, meniru contoh spreadsheet real dari user), kanan 1/4 = tabel Honorarium (Total Skor → Rp).
- **Variabel dan Kategori adalah 2 aksi/modal terpisah** (bukan 1 form gabungan) — variabel dibuat dulu (nama+deskripsi), kategori ditambah belakangan lewat modal sendiri (pilih variabel dari dropdown, atau klik "+" kecil di baris variabel utk pre-select).
- Modal Range punya toggle **Skor Tunggal** (default, cuma 1 input, `max_score` = `min_score` otomatis) vs **Rentang Skor** (2 input) — nyamain pola spreadsheet user yang campur skor tunggal (5, 6, 11, 12) dan rentang (7-8, 9-10).
- Semua tombol tambah/hapus diperkecil (class `.btn-xs`).

Test: `tests/Feature/Pelatihan/MasterHonorariumPageTest.php` (7 test — permission gate, save variable, save category scoped ke variable, scoping per context, save range mode rentang, save range mode skor tunggal).

### 6.4 Belum Dikerjakan (gap yang MASIH terbuka setelah addendum ini)

1. **`LmsCourses` belum diklasifikasi** pakai `TrainingCategoryEnum` — belum ada kolom/field yang menyatakan course tertentu itu Umum/Sertifikasi/Dokter Spesialis/Eksternal. Makanya belum ada cara program tahu course mana yang honornya fixed vs dihitung skor.
2. **Assessment flow belum ada** — belum ada UI/logic buat "pilih kategori per variabel untuk trainer/pelatihan tertentu, jumlahkan skornya, cocokkan ke `lms_honorarium`". Yang sudah ada baru CRUD master data (variabel/kategori/range) — analog `TrainerScoreVariables` dkk, TAPI belum ada padanan `TrainerAssessments`/`TrainerAssessmentResults` versi baru yang menghubungkan skor pilihan ke honor final.
3. **Honor fixed Umum/Sertifikasi-Dokter Umum masih belum direpresentasikan** — sama seperti gap §3 poin 3, `TrainerSalaryForm` masih kosong. Belum disentuh di addendum ini.
4. **Belum ada tempat edit inline** kategori/range yang sudah ada — cuma bisa tambah/hapus, belum bisa ubah nama/skor kategori atau nominal range yang sudah tersimpan (ponytail: sengaja di-skip dulu, tambahkan kalau dibutuhkan).
