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
- **UI di HRIS = portal Livewire** (lanjutkan `app/Livewire/Rekrutmen/*`), bukan Filament.
  Semua di `/rekrutmen/*`.
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

- Port `ApplicationNotification` + `CompanyMessageTemplate` + notification classes.
- Hapus `RekrutmenSyncService` + endpoint API yang tak dipakai lagi.
- Putuskan nasib Filament admin repo rekrutmen.

## Catatan

- Semua fase = shippable + tes sendiri. Tidak big-bang.
- Tiap fase yang butuh migration di repo rekrutmen: catat di sini + kerjakan di repo itu dulu.
- HRIS `.env` server WAJIB dapat `DB_REKRUTMEN_*` + `REKRUTMEN_STORAGE_PATH` saat deploy fase 1.
