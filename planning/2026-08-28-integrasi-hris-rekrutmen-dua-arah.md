# Integrasi HRIS ↔ Rekrutmen — Dua Arah

> Dibuat 2026-08-28. Lanjutan dari `knowledge/INTEGRASI-REKRUTMEN.md` (identik dengan
> `knowledge/INTEGRASI-HRIS.md` di project Rekrutmen). Dokumen ini: analisis proses
> Rekrutmen end-to-end + katalog API lengkap (yang sudah ada, yang ditambah sesi ini,
> yang sengaja ditunda) + keputusan arah.
>
> Dua project, satu mesin, DB terpisah (`hris` vs `sgg_rekrutmen`). Rekrutmen = Laravel
> 12 + Filament, port 8001. Integrasi lewat **REST API + Bearer token statis**, bukan
> direct-DB — alasan di § Kenapa API, Bukan Direct-DB.

---

## 1. Analisis Proses Rekrutmen (project terpisah)

### 1.1 Alur lamaran (state machine di `job_applications.status`)

```
applied → screening → shortlisted → interview → offering → mcu → onboarding → passed
              │            │            │           │        │
              └────────────┴────────────┴───────────┴────────┴──→ rejected (terminal, dari titik manapun)
```

- **`mcu` kondisional per perusahaan**: `companies.requires_mcu = false` → `offering` lompat ke `onboarding`.
- **`passed`** = terminal sukses, kandidat resmi diterima.
- **`rejected`** = terminal gagal, bisa dari hampir semua tahap.
- Tiap perubahan `status` otomatis:
  - tulis `job_application_status_logs` (`from_status`/`to_status`/`changed_by`/`changed_at`) — via `JobApplication::booted()`.
  - **saat `onboarding`/`passed`**: bikin row `candidates` otomatis (`Candidate::firstOrCreate` by `user_id`).
- Milestone yang TIDAK ubah kolom status (mis. hasil tes lulus, status tetap `shortlisted`) di-log manual lewat `JobApplication::logMilestone()`.

**Konsekuensi buat integrasi**: menulis `status` lewat raw SQL dari HRIS akan melewati
`booted()` → tidak ada log, tidak ada row candidate, tidak ada email. Mutasi status
Rekrutmen **wajib** lewat Eloquent di sisi Rekrutmen (endpoint API), bukan direct-DB.

### 1.2 Trigger tiap tahap (semua di Filament `/kelola-rekrutmen`, sisi admin)

| Transisi | Halaman / file | Catatan |
|---|---|---|
| `applied → screening` | `ApplicantsPage` (bulk action) | |
| `screening → shortlisted/rejected` | `ScreeningDecision` + `ApplicantsPage` | pola draft → publish; email lolos dikirim manual |
| Tes (tetap `shortlisted`) | `TesOnlinePage` (aktivasi), `QuizController` (pengerjaan) | skor referensi, kelulusan manual (`quiz_result`) |
| `shortlisted → interview` | `InterviewSchedule::apply()`, `WawancaraPage` | draft → publish jadwal + email |
| `interview → offering/rejected` | `WawancaraPage::tandaiHasilWawancaraAction()` | **selalu lewat `offering` dulu** sejak 27 Agu |
| Offering letter (tetap `offering`) | `OfferingLetterPage`, `OfferingLetterController` | upload/download manual, TTD fisik di luar sistem |
| `offering → mcu/onboarding` | `OfferingLetterPage::lanjutkanAction()` | kondisional `requires_mcu` |
| MCU (tetap `mcu`) → `onboarding/rejected` | `McuPage`, `McuStationPage` | 4 stasiun (perawat/lab/radiologi/dokter), keputusan fit/unfit manual HRD |
| `onboarding → passed` | `TtdOnboardingPage` | set `candidates.is_onboarded = true`, email `OnboardingInviteNotification` |
| `* → rejected` manual | `ApplicantDetailPage` ("Tidak Dilanjutkan") | mengundurkan diri, dll |

### 1.3 Data kandidat (`candidates` + 8 tabel anak)

Diisi kandidat sendiri lewat `/data-diri-kandidat` (`CandidateProfileController`), muncul
begitu row `candidates` ada (status `onboarding`+). Pola form "replace-all" (submit ulang
hapus + tulis ulang, tidak ada histori).

| Tabel | Isi |
|---|---|
| `candidates` | NIK, no_kk, alamat KTP (village/district/city/province), BPJS kes+tk, rekening BSI, agama, status kawin, dll. `overall_status` = dead column. |
| `candidate_family_members` | keluarga (hasMany) |
| `candidate_educations` | pendidikan (hasMany) |
| `candidate_trainings` | pelatihan (hasMany) |
| `candidate_organizations` | organisasi (hasMany) |
| `candidate_work_experiences` | pengalaman kerja (hasMany) |
| `candidate_references` | referensi (hasMany) |
| `candidate_additional_infos` | hobi, bahasa, ekspektasi gaji (hasOne) |
| `candidate_self_assessments` | penilaian diri (hasOne) |
| `candidate_documents` | KTP/ijazah/dll (hasMany, disk `local` privat, `doc_type`/`document_label`/`file_path`) |
| `candidate_agreements` | persetujuan kontrak/tata tertib (`agreed_at` + IP + UA) |

ID: `candidates` + 8 anak pakai ULID; `companies`/`users` pakai bigint auto-increment.

### 1.4 Perusahaan (`companies` di Rekrutmen)

- `hris_code` (varchar 10, unique, nullable) = kunci penghubung ke HRIS (`m_companies.code`).
- `requires_mcu` menentukan alur MCU.
- `is_active` → nonaktif = lowongan perusahaan disembunyikan dari halaman publik.
- Kolom `management_mode` **sudah dihapus** (migration 27 Agu) — gating edit/hapus lowongan
  sekarang murni per-lowongan lewat `job_openings.hris_job_opening_id`.

### 1.5 Lowongan (`job_openings` di Rekrutmen)

- `hris_job_opening_id` (unique, nullable): **null** = dibuat manual di Rekrutmen (bebas
  edit/hapus); **terisi** = dari HRIS (tombol Edit/Hapus disembunyikan, cuma "Lihat").
- `job_opening_quiz` (pivot) + `requires_psychotest` + config form lamaran per-perusahaan
  = **cuma diatur di admin Rekrutmen**, tidak bisa dari HRIS.

---

## 2. Katalog API

Semua endpoint di project Rekrutmen, prefix `/api`, middleware `HrisApiAuth`
(Bearer `Authorization`, dibanding `config('services.hris.api_token')` = env
`REKRUTMEN_API_TOKEN`). Di sisi HRIS dipanggil lewat `App\Services\Job\RekrutmenSyncService`
(config `services.rekrutmen.base_url`/`token`, semua non-fatal).

### 2.1 Sudah ada sebelum sesi ini

| Arah | Method | Path | HRIS caller |
|---|---|---|---|
| test | `GET` | `/hris-test` | `testConnection()` |
| HRIS→R | `PUT` | `/integrations/hris/job-openings/{hrisJobOpeningId}` | `sync(JobOpenings)` — dipanggil `JobOpeningService` tiap Simpan/Publish/Tutup |
| HRIS←R | `POST` | `/integrations/hris/job-openings/applicant-counts` | `getApplicantCounts(array $ids)` |

### 2.2 Ditambah sesi ini (2026-08-28)

| Arah | Method | Path | HRIS caller | Guna |
|---|---|---|---|---|
| HRIS←R | `GET` | `/integrations/hris/job-openings/unlinked` | `getUnlinkedJobOpenings()` | Lowongan `hris_job_opening_id IS NULL` — panel peringatan di `JobOpeningsPage` portal ("N lowongan di Rekrutmen belum terhubung ke HRIS") |
| HRIS→R | `POST` | `/integrations/hris/companies` | `pushCompany(Companies)` — auto lewat `Companies::saved()` | Upsert perusahaan by `hris_code`. Menutup blocker "company harus dibuat manual di Rekrutmen dulu". Cuma set name/is_active; branding & `requires_mcu` tetap milik admin Rekrutmen |
| HRIS←R | `GET` | `/integrations/hris/job-openings/{hrisJobOpeningId}/applications` | `getJobOpeningApplications($id, ?$status)` | Progres tiap lamaran per lowongan (bukan cuma jumlah). Filter `?status=passed`. Sumber `job_application_id` buat `showCandidate` |
| HRIS←R | `GET` | `/integrations/hris/candidates/{jobApplicationId}` | `getCandidate($jobApplicationId)` | Payload lengkap `candidate` + 8 tabel anak. 409 kalau row candidate belum ada (status belum onboarding/passed). Dokumen fisik = metadata + `file_path` saja, biner belum ditransfer |

### 2.3 Sengaja DITUNDA (butuh keputusan bisnis dulu)

1. **Proxy download dokumen kandidat** — `GET /integrations/hris/candidates/{jobApplication}/documents/{document}`
   yang stream file dari disk `local` Rekrutmen. Perlu supaya KTP/ijazah/dll ikut kebawa ke
   HRIS. Belum dibuat karena data terstruktur (JSON) sudah 90% nilai; dokumen menyusul.
2. **Webhook Rekrutmen → HRIS saat `passed`** — Rekrutmen `POST` ke URL HRIS yang dikonfigurasi,
   dipicu di `JobApplication::booted()`. Alternatif: HRIS polling `.../applications?status=passed`
   berkala. Polling lebih simpel, tidak perlu endpoint baru di HRIS. Pilih polling dulu.
3. **Sinkron status lamaran granular ke HRIS** (progress bar per kandidat di sisi HRIS) —
   `.../applications` sudah menyediakan datanya; yang belum ada cuma UI di HRIS. Bukan
   pekerjaan API.
4. **Tulis status dari HRIS** (mis. HRIS approve sesuatu → Rekrutmen lanjut tahap) —
   BELUM ADA use-case konkret. Kalau muncul: endpoint `PATCH .../applications/{id}/status`
   yang panggil `$application->update(['status' => ...])` di sisi Rekrutmen (biar `booted()`
   jalan), dengan whitelist transisi yang diizinkan dari HRIS.
5. **Bikin `users`/`user_profiles` + dokumen HRIS dari payload `getCandidate()`** — ini
   fitur HRIS, bukan endpoint. Keputusan yang perlu diambil: dedupe by email vs NIK,
   mapping field `candidates.*` → `user_profiles.*`, tabel anak mana yang diimpor (keluarga,
   pendidikan, dst punya padanan di HRIS?), siapa yang trigger (tombol HRD di detail
   lowongan / otomatis saat polling nemu `passed`). Lihat § 4.

---

## 3. Kenapa API, Bukan Direct-DB

Sempat dipertimbangkan HRIS konek langsung ke DB `sgg_rekrutmen` (connection kedua di
`config/database.php`). Ditolak:

- **Write lompat business logic**: `JobApplication::booted()` bikin row `candidates` +
  status log + email saat status berubah. Raw `UPDATE` melewati semua itu → korupsi data
  diam-diam.
- **Coupling schema**: migration Rekrutmen (contoh nyata: `management_mode` dihapus 27 Agu)
  bisa diam-diam mecahin HRIS. API = kontrak eksplisit.
- **Production**: API cuma butuh HTTP. Direct-DB butuh HRIS server bisa reach DB host
  Rekrutmen + kredensial DB — belum tentu satu server di produksi.
- **Konsistensi**: push lowongan + pull count sudah lewat API. Campur 2 paradigma = bingung.

Direct-DB baru masuk akal kalau nanti butuh JOIN berat lintas banyak tabel Rekrutmen
(daftar pelamar + kandidat + hasil MCU sekaligus) dan bikin puluhan endpoint jadi tidak
praktis — di titik itu pertimbangkan read-replica connection, read-only.

---

## 4. Langkah Berikutnya (belum dikerjakan)

1. **HRIS: konsumsi `getCandidate()` jadi karyawan baru.** Halaman/aksi di sisi HRIS
   (kemungkinan tombol di Detail Lowongan atau job scheduled) yang: polling
   `.../applications?status=passed` per lowongan aktif → untuk tiap `job_application_id`
   baru, `getCandidate()` → map ke `users` + `user_profiles` (+ `UserProfileDocument` kalau
   proxy dokumen § 2.3.1 sudah ada). Dedupe by email (`ImportCandidateKtpKkToUserProfile`
   sudah pakai pola match-by-email).
2. **Proxy dokumen** (§ 2.3.1).
3. **Test**: `RekrutmenSyncService` sampai sekarang 0 test (konsisten — semua method-nya
   wrapper non-fatal). Kalau langkah 1 dibangun, di situ baru perlu `Http::fake()` +
   test mapping candidate→user.
4. **`.env`**: `REKRUTMEN_API_URL` (HRIS) & `REKRUTMEN_API_TOKEN` (dua-duanya) belum ada
   di `.env.example` masing-masing project — tambahkan.

---

## 5. Berkas yang Disentuh Sesi Ini

**Rekrutmen** (`D:\Projects\rekrutmen`):
- `routes/api.php` — 3 route baru (`companies`, `job-openings/{id}/applications`, `candidates/{id}`)
- `app/Http/Controllers/Api/HrisIntegrationController.php` — `upsertCompany()`, `jobOpeningApplications()`, `showCandidate()` (+ `unlinkedJobOpenings()` dari langkah sebelumnya)

**HRIS** (`d:\Projects\hris`):
- `app/Services/Job/RekrutmenSyncService.php` — `pushCompany()`, `getJobOpeningApplications()`, `getCandidate()` (+ `getUnlinkedJobOpenings()`)
- `app/Models/Master/Companies.php` — `static::saved()` panggil `pushCompany()` (non-fatal)
- `app/Livewire/Rekrutmen/JobOpeningsPage.php` + `resources/views/livewire/rekrutmen/job-openings-page.blade.php` — panel "lowongan belum terhubung"
