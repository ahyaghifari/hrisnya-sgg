# Porting Admin Rekrutmen ke HRIS (shared DB)

> Keputusan user 2026-09-03. Menggantikan pendekatan API sync (`RekrutmenSyncService`) +
> ide tabel draft lokal. Lihat juga `knowledge/INTEGRASI-REKRUTMEN.md` (skema & alur portal).

## Keputusan

- **Admin/pipeline rekrutmen pindah ke HRIS.** Sisi pelamar (job board publik, form lamar,
  login kandidat, data diri kandidat, tes online, upload/unduh offering) **tetap 100% di repo
  `rekrutmen`** (`d:\Projects\rekrutmen`). Kandidat tidak pernah login ke HRIS.
- **Shared database.** HRIS baca-tulis DB rekrutmen **langsung** lewat koneksi `recruitment`
  (`config/database.php`). Repo `rekrutmen` **tetap pemilik skema** — semua migration tabel
  rekrutmen ada di sana. HRIS **tidak** membuat migration untuk koneksi `recruitment`.
- **UI di HRIS = panel Filament khusus `rekrutmen`** (revisi 2026-09-03, ide user). Panel
  `candidate` yang sudah ada tapi NONAKTIF (`bootstrap/providers.php`) di-repurpose:
  `app/Filament/Candidate/` → `app/Filament/Rekrutmen/`, `CandidatePanelProvider` →
  `RekrutmenPanelProvider` (`id('rekrutmen')`, `path('rekrutmen')`, **guard `web`** =
  staff HRIS, buang registration/email-verification, gate Shield). Resource candidate-facing
  lama (`CandidateProfiles`/`JobApplicants`/`JobVacancies`) dibuang.
  `app/Livewire/Rekrutmen/*` + route `/rekrutmen/*` lama → pensiun / redirect ke panel baru.
- **Repo rekrutmen = Filament v3.3.54, HRIS = Filament 4.** Port resource/page = terjemah
  FL3→FL4 per file (`Forms\Form`→`Schemas\Schema`, `Tables\Actions`→`Actions`,
  `Resources\Resource` namespace, dst). Logika tabel/form/aksi kebawa hampir langsung.
- **Folder tunggal "Rekrutmen"**: `app/Models/Rekrutmen/`, `app/Livewire/Rekrutmen/`,
  `app/Services/Rekrutmen/`, `app/Notifications/Rekrutmen/`, `resources/views/livewire/rekrutmen/`.
  `app/Models/Candidate/*` lama ikut pindah ke `app/Models/Rekrutmen/`.
- `RekrutmenSyncService` (HTTP) **dipensiunkan** setelah semua fase selesai.
- Storage: HRIS & rekrutmen satu server → disk `rekrutmen` (`config/filesystems.php`) diarahkan
  ke `storage/app/private` repo rekrutmen via `REKRUTMEN_STORAGE_PATH`.

## Fase 0 — Infra (SELESAI)

- [x] `config/database.php` → koneksi `recruitment` (`DB_REKRUTMEN_*`).
- [x] `config/filesystems.php` → disk `rekrutmen` (`REKRUTMEN_STORAGE_PATH`).
- [x] `.env.example` → dokumentasi key baru.
- [ ] **User isi `DB_REKRUTMEN_*` + `REKRUTMEN_STORAGE_PATH` di `.env`** (lokal & server).
- [ ] Cek koneksi: `php artisan tinker` → `DB::connection('recruitment')->getPdo()`.

## Titik kritis yang WAJIB diputuskan sebelum Fase 1 (jalur tulis)

1. **`job_application_status_logs.changed_by`** — FK ke `users` DB rekrutmen (akun kandidat).
   Kalau HRIS yang mengubah status, `auth()->id()` HRIS = id user HRIS, **bukan** user rekrutmen
   → FK salah. Opsi:
   - (a) `changed_by` nullable, HRIS tulis `null` + kolom `changed_by_name` (string) buat jejak.
     → butuh migration kecil di repo rekrutmen.
   - (b) HRIS tulis `null` saja, jejak aktor HRIS ke `AuditLog` HRIS terpisah.
   - (c) Tabel `hris_actors` mirror — overkill.
   → **Rekomendasi (a)**. Perlu koordinasi 1 migration di repo rekrutmen.
2. **State machine `JobApplication::booted()`** (auto status-log, auto-create `Candidate` saat
   onboarding/passed, auto-close lowongan saat kuota penuh). Model porting HRIS harus
   mereplikasi `booted()` ini **persis** supaya tulis dari HRIS punya efek samping yang sama.
   Alternatif: pindahkan efek samping ke Service HRIS dan model porting tanpa `booted()`.
   → **Rekomendasi**: port `booted()` apa adanya ke model HRIS (koneksi `recruitment`), biar
   satu perilaku baik ditulis dari HRIS maupun (sisa) dari repo rekrutmen.
3. **Notifikasi.** Repo rekrutmen kirim email via mailer + `CompanyMessageTemplate`. Setelah
   porting, HRIS yang kirim. Draft/tahan-kirim jadi kolom/tabel di DB rekrutmen (mis.
   `application_notifications.sent_at` nullable = draft). Cek apakah sudah ada; kalau belum,
   migration kecil di repo rekrutmen.
4. **Filament admin repo rekrutmen** — dibiarkan dormant (masih bisa dipakai darurat) atau
   dihapus resource-nya. Putuskan di Fase 8.

## Inventaris admin panel repo rekrutmen (yang harus diport)

Panel FL3 `admin` (`/kelola-rekrutmen`), top-nav, grup: **Lowongan / Kandidat / Master / Pengguna**.

### Resource (7)
| Resource | Model | Grup | Isi |
|---|---|---|---|
| `CompanyResource` | `Company` | Master | Perusahaan (branding, `requires_mcu`) |
| `FormSectionResource` | `FormSection` (+`FormField`) | Master | Builder Form Lamaran |
| `QuizResource` | `Quiz` | Master | CRUD kuis |
| `CompanyAgreementResource` | `CompanyAgreement` | Master | Template persetujuan onboarding |
| `JobOpeningResource` | `JobOpening` | Lowongan | CRUD lowongan + aksi `stageLogs` |
| `PelamarResource` | `User` (`is_staff=false`) | Pengguna | Daftar akun pelamar + reset verifikasi |
| `StaffResource` | `User` (`is_staff=true`) | Pengguna | Akun staff/admin |

### Halaman pipeline (11)
| Halaman | Grup/Sort | Aksi kunci |
|---|---|---|
| `ApplicantsPage` | Lowongan/2 | seleksiBerkas (**draft**), publishScreening, applyAndNotify, kirimNotifLolosBerkas, kirimNotifPenolakanBerkas, tandaiTidakLolosBerkas, **bulkMoveToScreening**, lihatBerkas |
| `ApplicantDetailPage` | (detail) | tidakDilanjutkan, kirimNotifTidakDilanjutkan |
| `TesOnlinePage` | Kandidat/1 | pilihKuis, aktifkanTes, tandaiTidakLulusTes, kirimNotifPenolakanTes, tandaiHasilTes, lihatHasil |
| `WawancaraPage` | Kandidat/2 | jadwalWawancara (**draft**), publishInterview, tandaiTidakLulusWawancara, kirimNotifPenolakanWawancara, tandaiHasilWawancara |
| `OfferingLetterPage` | Kandidat/3 | uploadSurat, unduhSuratMasuk, unduhSuratSigned, negosiasi, lanjutkanSetelahOffering |
| `McuPage` | Kandidat/4 | jadwalMcu (**draft**), publishMcu, tandaiTidakLulusMcu, kirimNotifPenolakanMcu, putuskanHasilMcu |
| `McuStationPage` | Kandidat/5 | isiPerawat / isiLab / isiRadiologi / isiDokter |
| `TtdOnboardingPage` | Kandidat/6 | tandaiOnboardingSelesai |
| `SelesaiPage` | Kandidat/7 | lihat (read-only) |
| `QuizQuestionsPage` | Master/5 | CRUD soal + template + import Excel |
| `HasilMcuDetailPage` / `HasilTesDetailPage` | (detail) | read-only |

Pola **draft→publish** dan aksi **kirimNotif*** terpisah ada di HAMPIR semua tahap — bukan cuma
screening. Diport apa adanya (jangan bikin mekanisme draft baru di HRIS).

### Model repo rekrutmen (37) — semua diport ke `App\Models\Rekrutmen\*` (connection `recruitment`)
Company, CompanyAgreement, CompanyMessageTemplate, FormSection, FormField, JobOpening,
JobOpeningStageLog, JobApplication, JobApplicationAnswer, JobApplicationStatusLog,
ScreeningDecision, Quiz, QuizQuestion, QuizAttempt, QuizAnswer, InterviewSchedule,
InterviewInvitee, OfferingLetter, OfferingNegotiation, McuSchedule, McuInvitee,
McuNurseAssessment, McuLabResult, McuRadiologyResult, McuDoctorExamination, Candidate + 9 anak,
ApplicationNotification, User (pelamar/staff — read-only dari HRIS).

## Fase 1 — Daftar & Detail Pelamar (read + tulis status)

- Port model: `JobApplication`, `JobOpening`, `Company` (rekrutmen), `JobApplicationStatusLog`,
  `JobApplicationAnswer`, `FormField`/`FormSection` → `app/Models/Rekrutmen/` (connection
  `recruitment`).
- `app/Livewire/Rekrutmen/ApplicantsPage` + `ApplicantDetailPage`: ganti semua panggilan
  `RekrutmenSyncService` → query Eloquent langsung.
- Bulk actions + (nanti) draft: tetap, tapi lewat model langsung.
- Test: parity dengan perilaku sekarang (status transition, log, guard company).

## Fase 2 — Draft/publish Seleksi Berkas

- Port `ScreeningDecision` (repo rekrutmen) → `app/Models/Rekrutmen/`. Pola draft→publish-nya
  sudah ada di sana; HRIS tinggal pakai + UI banner draft + "kirim".

## Fase 3 — Kuis

- Port `Quiz`, `QuizQuestion`, `QuizAttempt`, `QuizAnswer`.
- Halaman: kelola soal (`QuizQuestionsPage`), aktifkan tes (`TesOnlinePage` bagian admin),
  hasil/nilai (`HasilTesDetailPage`), catat keputusan lulus/tidak.
- Pengerjaan kuis oleh kandidat **tetap di repo rekrutmen**.

## Fase 4 — Wawancara

- Port `InterviewSchedule`, `InterviewInvitee`. Draft→publish jadwal, catat hasil.

## Fase 5 — Offering Letter

- Kolom di `job_applications` (bukan model terpisah). File via disk `rekrutmen`.
- Upload surat rekruter, lihat surat TTD kandidat, tombol lanjut ke MCU/Onboarding.

## Fase 6 — MCU

- Port `McuSchedule`, `McuInvitee`, `McuNurseAssessment`, `McuLabResult`,
  `McuRadiologyResult`, `McuDoctorExamination`. Halaman `McuPage`, `McuStationPage` (4 stasiun),
  `HasilMcuDetailPage`. Gate per-stasiun pakai permission HRIS.

## Fase 7 — TTD & Onboarding + Selesai

- `TtdOnboardingPage`, `SelesaiPage`. `CandidateOnboardingService` HRIS sudah ada sebagian
  (buat akun karyawan dari kandidat) — sambungkan.

## Fase 8 — Notifikasi + retire sync

- [x] Port `App\Mail\Rekrutmen\ApplicantNotificationMail` + view `mail/rekrutmen/applicant-notification`.
- [x] `ApplicationNotification`: `send()` (kirim draft ke email pelamar + tandai `sent`),
      `createAndSend()` (event positif), `flushDraftsFor(ids, ?event)` (bulk), `cta()`
      (tombol -> app pelamar via `config('services.rekrutmen.url')`, key `REKRUTMEN_URL`).
- [x] Event positif auto-kirim: QUIZ_SENT, QUIZ_PASSED, INTERVIEW_SCHEDULED (model),
      MCU_SCHEDULED (model), MCU_PASSED, OFFERING_LETTER_READY, ONBOARDING_INVITE.
- [x] Event penolakan tetap draft; tiap halaman pipeline dapat aksi bulk
      "Kirim Email Pemberitahuan" (`SendsApplicantNotifications` trait) —
      ApplicantsPage, TesOnlinePage, WawancaraPage, McuPage. ApplicantDetailPage dapat
      aksi single-record.
- [x] `CompanyMessageTemplate` sudah diport (Fase 1b). Kirim sinkron (bukan ShouldQueue) —
      parity dengan repo rekrutmen; upgrade ke queue khusus kalau bulk penolakan berat.
- [ ] **DITUNDA**: hapus `RekrutmenSyncService` + `app/Livewire/Rekrutmen/*` + route
      `/rekrutmen/{lowongan,mcu,pelamar}` lama. Alasan: `CandidateOnboardingService` +
      `routes/web.php` lagi diedit sesi lain (modul Orientasi) — tunggu itu merge dulu.
- [ ] Putuskan nasib Filament admin repo rekrutmen (dormant vs hapus resource).

## Catatan

- Semua fase = shippable + tes sendiri. Tidak big-bang.
- Tiap fase yang butuh migration di repo rekrutmen: catat di sini + kerjakan di repo itu dulu.
- HRIS `.env` server WAJIB dapat `DB_REKRUTMEN_*` + `REKRUTMEN_STORAGE_PATH` saat deploy fase 1.
