# Dokumentasi Integrasi HRIS — Portal Rekrutmen Syifa Global Group

> Dibuat 28 Agustus 2026. Tujuan: referensi lengkap alur, status, route, dan skema data
> portal rekrutmen ini, supaya tim/proyek HRIS bisa terhubung dengan benar — baik untuk
> menarik data lowongan & pelamar, maupun (nantinya) menerima kandidat yang sudah lulus
> seluruh proses jadi data karyawan baru.
>
> Disusun berdasarkan kode aktual per tanggal dokumen ini dibuat, bukan dokumen desain
> awal (`PRD.md`, `planning/*.md` sudah banyak yang basi dan tidak dipakai lagi).

---

## 1. Ringkasan Alur Rekrutmen

```
applied ──▶ screening ──▶ shortlisted ──▶ interview ──▶ offering ──▶ mcu ──▶ onboarding ──▶ passed
                │              │              │             │          │
                └──────────────┴──────────────┴─────────────┴──────────┴──▶ rejected (bisa terjadi di titik manapun)
```

- **`mcu` bersifat kondisional per perusahaan** — kalau `companies.requires_mcu = false`, lamaran lompat dari `offering` langsung ke `onboarding`.
- **`rejected` adalah status terminal** yang bisa dicapai dari hampir semua tahap (gagal seleksi berkas, gagal tes, gagal wawancara, gagal MCU, atau ditandai manual "Tidak Dilanjutkan" — lihat §3.9).
- **`passed` adalah status terminal sukses** — kandidat resmi diterima jadi karyawan.
- Psikotes offline (`job_openings.requires_psychotest`) **tidak** punya status sendiri — cuma penanda tambahan yang dikoordinasikan manual di luar sistem, dicatat di `job_applications.psychotest_completed_at`.

### Tabel status lengkap

| Status | Arti | Kolom `job_applications.status` |
|---|---|---|
| `applied` | Lamaran baru masuk | ✓ |
| `screening` | Sedang ditinjau berkasnya | ✓ |
| `shortlisted` | Lolos seleksi berkas → resmi jadi "kandidat" (row `candidates` **belum** dibuat di sini, baru dibuat saat `onboarding`/`passed`, lihat §2.3) → mengerjakan Tes | ✓ |
| `interview` | Sedang tahap wawancara | ✓ |
| `offering` | Sudah lulus wawancara, menunggu offering letter selesai (dibuat, dikirim, ditandatangani) | ✓ |
| `mcu` | Sedang Medical Check Up | ✓ |
| `onboarding` | Lulus MCU (atau lulus wawancara langsung kalau MCU tidak wajib), menunggu TTD kontrak & orientasi | ✓ |
| `passed` | Resmi diterima jadi karyawan | ✓ |
| `rejected` | Tidak dilanjutkan (di titik manapun) | ✓ |

Riwayat setiap perubahan status otomatis tercatat di tabel `job_application_status_logs`
(kolom `from_status`, `to_status`, `changed_by`, `changed_at`) lewat `JobApplication::booted()`
— setiap `update(['status' => ...])` di manapun otomatis kelog, tidak perlu manual.

---

## 2. Model Data Inti

### 2.1 `companies` — perusahaan dalam grup (holding company)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint (bukan ULID) | PK auto-increment biasa |
| `hris_code` | varchar(10) | **Kunci penghubung ke HRIS** — job opening dari HRIS harus menyertakan `hris_company_code` yang cocok dengan ini |
| `name`, `logo_path`, `hero_image_path`, `brand_color`, `email`, `phone` | — | Data tampilan/branding portal publik |
| `is_active` | bool | Nonaktifkan → lowongan perusahaan ini disembunyikan dari halaman publik |
| `requires_mcu` | bool | Menentukan apakah lamaran lewat tahap `mcu` atau lompat ke `onboarding` |

> Catatan `management_mode`: kolom ini sempat dihapus (`2026_08_27_131700_drop_...`) lalu
> **dikembalikan** (`2026_09_07_140000_readd_...`), jadi sekarang ADA lagi — enum `free|hris`,
> default `hris`.
>
> Per 2026-09-23 nilainya **selalu `hris`**: semua rekrutmen wajib dikelola dari HRIS. Pilihan
> mode-nya sudah dibuang dari form perusahaan, `RecruitmentSync::pushCompany()` memaksa `hris`, dan
> di project `rekrutmen` lowongan jadi read-only (`JobOpeningResource::canCreate()` selalu false,
> cuma ada aksi Lihat). Kolomnya sengaja dibiarkan ada supaya tidak perlu ubah skema lagi.

### 2.2 `job_openings` — lowongan (cache lokal, bisa manual atau dari HRIS)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | ULID | PK |
| `company_id` | FK → `companies.id` | |
| `title`, `company_name`, `quota`, `max_applicants`, `applications_deadline`, `is_active`, `description`, `requirements` | — | |
| `hris_job_opening_id` | varchar, **unique**, nullable | Kalau **null** → lowongan dibuat manual di sistem ini (bisa diedit/dihapus bebas). Kalau **terisi** → lowongan berasal dari HRIS (tombol Edit/Hapus disembunyikan di admin, cuma bisa "Lihat" — lihat `JobOpeningResource::table()`) |
| `requires_psychotest` | bool | Penanda psikotes offline tambahan (di luar sistem) |
| `created_by`, `updated_by`, `deleted_by` | FK → `users.id` | Audit trail, soft-delete (`deleted_at`) |

Relasi many-to-many ke `quizzes` lewat pivot `job_opening_quiz` (satu lowongan bisa punya lebih dari satu kuis).

### 2.3 `job_applications` — satu lamaran (record inti seluruh alur)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | ULID | PK |
| `user_id` | FK → `users.id` | Akun pelamar |
| `job_opening_id` | FK → `job_openings.id` | |
| `status` | enum (lihat §1) | |
| `quiz_sent_at` | timestamp nullable | Kapan tes diaktifkan admin |
| `quiz_result` | enum `lulus`/`tidak_lulus` nullable | Keputusan manual rekruter (skor kuis cuma referensi) |
| `psychotest_completed_at` | timestamp nullable | Dicatat manual kalau lowongan mewajibkan psikotes offline |
| `interview_result` | enum `lulus`/`tidak_lulus` nullable | |
| `offering_letter_path` | varchar nullable | Path file surat penawaran yang diupload rekruter (disk `local`, privat) |
| `offering_letter_uploaded_at` | timestamp nullable | |
| `offering_letter_signed_path` | varchar nullable | Path file surat yang sudah diupload balik oleh kandidat (sudah TTD) |
| `offering_letter_signed_at` | timestamp nullable | |
| `deleted_at` | — | Soft delete |

**Satu user hanya boleh punya satu lamaran per lowongan** (tidak ada constraint DB eksplisit, tapi
dienforce di level aplikasi lewat `JobApplication::scopeActive()` — **catatan: scope ini baru mencakup
status `applied`, `screening`, `interview`, belum lengkap sampai ke `shortlisted`/`offering`/`mcu`/`onboarding`,
ini gap lama yang belum dibereskan, bukan sesuatu yang baru**).

Begitu status jadi `onboarding` **atau** `passed`, row `candidates` otomatis dibuat
(`JobApplication::booted()`, method `updated`) — **bukan** saat `shortlisted` seperti draft desain awal di
`PRD.md`. Ini keputusan yang sudah dikonfirmasi: data diri lengkap kandidat baru relevan diisi menjelang
tahap akhir.

### 2.4 `candidates` + 8 tabel anak — data diri lengkap kandidat

Diisi sendiri oleh kandidat lewat halaman **Data Diri Kandidat** (`/data-diri-kandidat`,
`CandidateProfileController`), muncul begitu row `candidates` ada (lihat §2.3).

| Tabel | Isi |
|---|---|
| `candidates` | Identitas inti: NIK, KK, alamat KTP, BPJS, rekening, dll. Kolom `overall_status` **ada di skema tapi tidak pernah diisi/dipakai di kode manapun — abaikan, dead column.** |
| `candidate_family_members` | Data keluarga (hasMany) |
| `candidate_educations` | Riwayat pendidikan (hasMany) |
| `candidate_trainings` | Riwayat pelatihan (hasMany) |
| `candidate_organizations` | Pengalaman organisasi (hasMany) |
| `candidate_work_experiences` | Pengalaman kerja (hasMany) |
| `candidate_references` | Referensi (hasMany) |
| `candidate_additional_infos` | Info tambahan: hobi, bahasa, ekspektasi gaji, dll (hasOne) |
| `candidate_self_assessments` | Penilaian diri (hasOne) |
| `candidate_documents` | Dokumen upload bebas (KTP, ijazah, dll — hasMany, disk `local`) |
| `candidate_agreements` | Persetujuan kontrak/peraturan/tata tertib (checkbox, dicatat `agreed_at` + IP + user agent) |

Form-nya pakai pola "replace-all": submit ulang akan hapus semua row lama lalu tulis ulang dari data yang
dikirim (bukan diff per-baris) — sederhana tapi berarti **tidak ada histori perubahan** untuk data-data ini.

---

## 3. Detail Tiap Tahap — Trigger, File, dan Data Terkait

### 3.1 `applied` → `screening`

- **Trigger:** admin klik bulk action di halaman **Daftar Pelamar**.
- **File:** `app/Filament/Pages/ApplicantsPage.php` (`bulkMoveToScreeningAction`)

### 3.2 `screening` → `shortlisted` / `rejected`

- **Trigger:** admin isi "Seleksi Berkas" (draft dulu, baru diterapkan — pola draft+publish supaya pelamar
  tidak langsung lihat perubahan sebelum siap).
- **File:** `app/Models/ScreeningDecision.php`, `ApplicantsPage.php`
- **Notifikasi:** draft dibuat (`EVENT_SCREENING_PASSED` / `EVENT_SCREENING_REJECTED`), dikirim manual oleh admin (email ditahan sampai admin klik kirim — kebijakan: hanya yang lolos yang otomatis diberi tahu cepat, penolakan bisa ditunda).

### 3.3 Tes (tetap status `shortlisted`)

- **Aktivasi:** admin klik "Aktifkan Tes" di halaman **Tes** → `quiz_sent_at = now()`, email `EVENT_QUIZ_SENT` terkirim.
  **File:** `app/Filament/Pages/TesOnlinePage.php` — tombol ini digate: kalau lowongan belum punya kuis
  terpasang (`job_openings.quizzes` relasi kosong), tombol tidak muncul.
- **Kuis dikunci** begitu tes sudah diaktifkan ke ≥1 kandidat (`TesOnlinePage::isQuizLocked()`) — supaya
  soal tidak berubah di tengah jalan.
- **Pengerjaan kandidat:** `app/Http/Controllers/QuizController.php` + route `/tes-online/*`. Kandidat bisa
  mengerjakan lebih dari satu kuis (kalau lowongan punya beberapa), bebas urutan. Sekali mulai
  (`quiz_attempts.started_at`), ada batas waktu (`deadline_at`) — submit setelah lewat waktu otomatis
  ditolak (status `expired`).
- **Skor tidak menentukan kelulusan otomatis** — keputusan tetap manual rekruter lewat "Catat Keputusan"
  (`quiz_result`). Lulus → tetap `shortlisted`, lanjut ke pool Wawancara. Tidak lulus → `rejected`.
- **Model:** `Quiz`, `QuizQuestion` (opsi jawaban disimpan `json` array of string, BUKAN array of object —
  gotcha ini pernah bikin bug parsing di frontend, lihat `app/Models/QuizQuestion.php`), `QuizAttempt`,
  `QuizAnswer`.

### 3.4 `shortlisted` (quiz_result=lulus) → `interview`

- **Trigger:** admin bikin jadwal wawancara (draft dulu) lalu **publish** → `InterviewSchedule::apply()`
  set semua lamaran terundang jadi status `interview` + kirim email jadwal (`EVENT_INTERVIEW_SCHEDULED`).
- **File:** `app/Models/InterviewSchedule.php`, `app/Filament/Pages/WawancaraPage.php`
- **Jadwal terlihat kandidat** di dashboard & detail lamaran (tanggal/jam/lokasi/catatan) begitu status
  `interview` — lewat relasi `JobApplication::interviewInvitee()->schedule`.

### 3.5 `interview` → `offering` / `rejected`

- **Trigger:** admin "Catat Hasil" wawancara per kandidat.
- **File:** `WawancaraPage::tandaiHasilWawancaraAction()` — **PENTING: lulus wawancara SELALU menuju
  `offering` dulu, bukan langsung `mcu`/`onboarding`** (perubahan alur per 27 Agustus 2026 — sebelumnya
  langsung ke mcu/onboarding, sekarang wajib lewat offering letter dulu).
- Tidak lulus → `rejected` + draft notif `EVENT_INTERVIEW_REJECTED`.

### 3.6 Offering Letter (tetap status `offering`)

**Desain sengaja dibuat bebas/manual — bukan generate PDF terstruktur.** Percobaan awal pakai template
per-perusahaan (tabel kompensasi dinamis) dibongkar lagi karena variasi antar-jabatan/kandidat terlalu
besar untuk dipaksa masuk struktur baku (lihat commit terkait). Sekarang murni alur upload-download:

1. Rekruter bikin surat penawaran **di luar sistem** (Word/PDF, format bebas) lalu upload lewat halaman
   **Offering Letter** → `offering_letter_path` + `offering_letter_uploaded_at` terisi.
2. Kandidat unduh dari dashboard (`GET /offering-letter/unduh`), tanda tangan **secara fisik/manual di
   luar sistem**, lalu upload balik file yang sudah ditandatangani lewat form drag-and-drop di dashboard
   (`POST /offering-letter/upload-signed`) → `offering_letter_signed_path` + `offering_letter_signed_at`
   terisi.
3. Rekruter cek surat yang sudah ditandatangani, lalu klik **"Lanjut ke MCU"** atau **"Lanjut ke
   Onboarding"** (tombol muncul begitu `offering_letter_signed_path` terisi) — **manual, tidak ada
   validasi konten surat oleh sistem**.

- **File:** `app/Filament/Pages/OfferingLetterPage.php` (admin), `app/Http/Controllers/OfferingLetterController.php` (download admin + download/upload kandidat).
- **Tidak ada tabel/model terpisah** — semua disimpan langsung sebagai kolom di `job_applications` (lihat §2.3). File fisik di disk `local` (privat), folder `offering-letters/{job_application_id}/recruiter/` dan `.../signed/`.
- **Catatan housekeeping:** ada model `app/Models/OfferingLetter.php` + tabel `offering_letters` yang
  **dari scaffold awal proyek (1 Agustus 2026), tidak pernah dipakai, dan sekarang jadi dead code**
  (fitur asli pakai kolom di `job_applications`, bukan tabel terpisah ini). Aman diabaikan/dihapus kalau
  mau beres-beres, tidak direferensikan di controller/halaman manapun kecuali relasi kosong
  `JobApplication::offeringLetter()`.

### 3.7 `offering` → `mcu` / `onboarding`

- Kondisional: `mcu` kalau `job_openings->company->requires_mcu === true`, selain itu langsung `onboarding`.
- **File:** `OfferingLetterPage::lanjutkanAction()`

### 3.8 MCU (tetap status `mcu`, lalu → `onboarding` / `rejected`)

- **Jadwal:** sama pola dengan wawancara — draft lalu publish (`McuSchedule::apply()`), kandidat lihat
  jadwal di dashboard/detail lamaran lewat `JobApplication::mcuInvitee()->schedule`.
- **Pemeriksaan klinis** dicatat terpisah dari keputusan HRD — 4 stasiun independen, masing-masing digate
  permission Spatie sendiri (`fill_mcu_nurse`, `fill_mcu_lab`, `fill_mcu_radiology`, `fill_mcu_doctor`):
  - `mcu_nurse_assessments` — vital sign, riwayat penyakit keluarga, alergi, kebiasaan, EKG
  - `mcu_lab_results` — hasil lab, HBsAg, HIV
  - `mcu_radiology_results` — hasil rontgen thorax
  - `mcu_doctor_examinations` — pemeriksaan fisik lengkap + diagnosa + rekomendasi dokter
  - **File:** `app/Filament/Pages/McuStationPage.php`
- **Keputusan final fit/unfit** tetap manual HRD (rekomendasi dokter cuma saran, bukan penentu otomatis),
  digate: harus sudah ada pemeriksaan dokter (`McuInvitee::doctorExamDone()`).
  - Fit → `onboarding`. Unfit → `rejected` + draft notif `EVENT_MCU_REJECTED`.
  - **File:** `app/Filament/Pages/McuPage.php` (`putuskanHasilMcuAction`)
- **Model:** `McuSchedule`, `McuInvitee` (kolom `status_mcu` enum `pending`/`fit`/`unfit`, `catatan_mcu`),
  4 model hasil pemeriksaan di atas.
- Ada halaman ringkasan read-only **"Lihat Hasil MCU"** (`HasilMcuDetailPage.php`) buat HRD lihat semua 4
  stasiun sekaligus tanpa buka form edit satu-satu.

### 3.9 `onboarding` → `passed`

- **Trigger:** admin "Tandai Selesai" di halaman **TTD & Onboarding**.
- **File:** `app/Filament/Pages/TtdOnboardingPage.php`
- **Efek:** `candidates.is_onboarded = true`, status → `passed`, kirim email `OnboardingInviteNotification`
  (bukan lewat sistem draft `ApplicationNotification` seperti event lain — ini notification class Laravel
  biasa).
- Halaman **Selesai** (`SelesaiPage.php`) menampilkan semua kandidat berstatus `passed` per lowongan —
  murni read-only, buat rekap siapa saja yang sudah resmi diterima.

### 3.10 Rejected manual di luar alur (mengundurkan diri, dll)

- Tombol **"Tidak Dilanjutkan"** di halaman **Detail Pelamar** (`ApplicantDetailPage.php`) — dipakai untuk
  kasus di luar alur normal (paling umum: kandidat mengundurkan diri), bukan cuma gagal di satu tahap.
  Tidak muncul kalau status sudah `rejected`/`passed`.

---

## 4. Integrasi HRIS yang Sudah Ada

### 4.1 Autentikasi

Semua endpoint API di-gate middleware `App\Http\Middleware\HrisApiAuth` — cek `Bearer <token>` di header
`Authorization`, dibandingkan (constant-time, `hash_equals`) dengan `config('services.hris.api_token')`
yang diambil dari env var **`REKRUTMEN_API_TOKEN`**.

> ⚠️ **`REKRUTMEN_API_TOKEN` belum ada contohnya di `.env.example`** — harus ditambahkan manual ke `.env`
> server ini, lalu dibagikan ke tim HRIS sebagai bearer token mereka.

### 4.2 Endpoint yang tersedia (`routes/api.php`, prefix otomatis `/api`)

| Method | Path | Controller@method | Fungsi |
|---|---|---|---|
| `GET` | `/api/hris-test` | `healthCheck` | Cek koneksi/token valid — balas `{"status":"ok"}` |
| `POST` | `/api/integrations/hris/job-openings/applicant-counts` | `applicantCounts` | HRIS kirim daftar `hris_job_opening_id`, dapat balik jumlah pelamar tiap lowongan |
| `PUT` | `/api/integrations/hris/job-openings/{hrisJobOpeningId}` | `upsertJobOpening` | HRIS **push** data lowongan ke sini (create kalau belum ada, update kalau sudah) |

**File:** `app/Http/Controllers/Api/HrisIntegrationController.php`

#### `POST /api/integrations/hris/job-openings/applicant-counts`

Body:
```json
{ "ids": ["HRIS-JOB-001", "HRIS-JOB-002"] }
```
Balasan:
```json
{ "counts": { "HRIS-JOB-001": 12, "HRIS-JOB-002": 4 } }
```
(lowongan yang tidak ditemukan otomatis tidak muncul di hasil — bukan error)

#### `PUT /api/integrations/hris/job-openings/{hrisJobOpeningId}`

Body:
```json
{
  "hris_job_opening_id": "HRIS-JOB-001",
  "hris_company_code": "SGG",
  "title": "Staff IT Programmer",
  "quota": 2,
  "max_applicants": 50,
  "applications_deadline": "2026-09-30",
  "description": "<p>...</p>",
  "requirements": "<p>...</p>",
  "is_active": true
}
```
- **`hris_company_code` wajib cocok dengan salah satu `companies.hris_code`** yang sudah ada di sistem ini
  — kalau tidak ketemu, balas `422` dengan pesan `"Company not found for hris_company_code: ..."`.
  **Company harus dibuat lebih dulu secara manual lewat admin panel** (belum ada endpoint HRIS untuk
  push data company).
- Match lowongan existing berdasarkan `hris_job_opening_id` (bukan `title`) — aman dipanggil berulang
  (idempotent upsert).
- Balasan `201` (baru dibuat) atau `200` (update), body `{"message", "id", "hris_job_opening_id"}`.
- **Field yang TIDAK bisa diatur dari HRIS** (harus diisi manual di admin panel sistem ini):
  `requires_psychotest`, kuis yang dipasang, dan semua konfigurasi form lamaran per-perusahaan.

### 4.3 Yang BELUM ada (celah integrasi, perlu didiskusikan)

Sistem ini **tidak mengirim apapun ke HRIS secara otomatis** — semua komunikasi sejauh ini cuma satu arah
(HRIS → sistem ini, lewat 2 endpoint di atas). Belum ada mekanisme untuk:

1. **Memberi tahu HRIS begitu kandidat `passed`** — supaya HRIS bisa langsung tarik data `candidates` +
   8 tabel anaknya (§2.4) jadi record karyawan baru, tanpa entry manual ulang.
2. **Menyinkronkan status lamaran balik ke HRIS** (misal HRIS mau tampilkan progress kandidat per lowongan
   di sisi mereka).
3. **Push data company dari HRIS** — saat ini `companies` + `hris_code` harus dibuat manual duluan di
   admin panel sistem ini sebelum HRIS bisa push lowongan.

Rekomendasi paling sederhana kalau mau ditutup: tambah 1 endpoint baru
`GET /api/integrations/hris/candidates/{jobApplicationId}` (atau webhook keluar `POST` ke URL yang
dikonfigurasi HRIS, dipicu di `JobApplication::booted()` saat status jadi `passed`) yang mengembalikan
seluruh data `candidates` + relasinya dalam satu payload JSON — datanya sudah lengkap tinggal di-serialize,
tidak perlu bikin struktur data baru.

---

## 5. Route Lengkap yang Relevan

### 5.1 Sisi Pelamar/Kandidat (web, butuh login — `routes/web.php`)

| Method | Path | Fungsi |
|---|---|---|
| `GET` | `/dashboard` | Ringkasan lamaran aktif + lowongan terbuka |
| `GET` | `/lamaran` | Daftar semua lamaran milik user |
| `GET` | `/lamaran/{application}` | Detail satu lamaran (timeline, jadwal wawancara/MCU, dst) |
| `GET` | `/lamar/{jobOpening}` | Form lamar |
| `POST` | `/lamar/{jobOpening}` | Submit lamaran |
| `GET`/`PATCH` | `/data-diri-kandidat` | Form data diri kandidat lengkap |
| `GET` | `/offering-letter/unduh` | Unduh surat penawaran dari rekruter |
| `POST` | `/offering-letter/upload-signed` | Upload surat yang sudah ditandatangani |
| `GET` | `/tes-online`, `/tes-online/{quiz}`, dst | Alur pengerjaan kuis |

### 5.2 Sisi Admin (Filament panel, prefix `/kelola-rekrutmen`)

Nav utama: **Perusahaan & Lowongan** (Perusahaan, Form Lamaran, Lowongan, Kuis) →
**Daftar Pelamar** (applied/screening/shortlisted) →
**Kandidat** (Tes → Wawancara → Offering Letter → MCU → Isi Pemeriksaan MCU → TTD & Onboarding → Selesai).

### 5.3 API HRIS (lihat §4.2)

---

## 6. Ringkasan Model & File Kunci (untuk referensi cepat)

| Konsep | Model | Halaman Admin | Controller Kandidat |
|---|---|---|---|
| Lowongan | `JobOpening` | `JobOpeningResource` | `PublicController` |
| Lamaran | `JobApplication` | `ApplicantsPage`, `ApplicantDetailPage` | `JobApplicationController` |
| Kuis | `Quiz`, `QuizQuestion`, `QuizAttempt`, `QuizAnswer` | `QuizQuestionsPage`, `TesOnlinePage` | `QuizController` |
| Wawancara | `InterviewSchedule`, `InterviewInvitee` | `WawancaraPage` | — (info-only di dashboard) |
| Offering Letter | *(kolom di `JobApplication`, bukan model terpisah)* | `OfferingLetterPage` | `OfferingLetterController` |
| MCU | `McuSchedule`, `McuInvitee`, `Mcu{Nurse,Lab,Radiology,Doctor}*` | `McuPage`, `McuStationPage`, `HasilMcuDetailPage` | — (info-only di dashboard) |
| Data diri kandidat | `Candidate` + 8 model anak (§2.4) | *(read-only lewat `ApplicantDetailPage` begitu status onboarding+)* | `CandidateProfileController` |
| Notifikasi email | `CompanyMessageTemplate`, `ApplicationNotification` | tiap halaman kandidat punya banner kirim-notif sendiri | — |

---

## 7. Catatan Penting Lain

- **ULID vs bigint:** hampir semua tabel baru pakai ULID (`HasUlids` trait) sebagai PK — kecuali
  `companies`, `users`, dan tabel anak `candidates` (pakai `bigint` auto-increment biasa). Perhatikan tipe
  ini kalau HRIS perlu query lintas tabel.
- **Soft delete** dipakai di `job_openings`, `job_applications`, `candidates`, `users` — record yang
  "dihapus" masih ada di DB dengan `deleted_at` terisi, bukan benar-benar hilang.
- **Semua file upload kandidat** (dokumen, offering letter signed) disimpan di disk `local` (privat, bukan
  `public`) — akses download digate manual per controller (cek `user_id`/`company_id` yang sesuai), tidak
  bisa diakses langsung lewat URL publik.
- Dokumen desain lama (`knowledge/PRD.md`, `planning/*.md`) **sudah banyak yang tidak sinkron dengan kode
  aktual** — dokumen ini (`INTEGRASI-HRIS.md`) yang jadi rujukan utama, bukan dokumen-dokumen itu.
