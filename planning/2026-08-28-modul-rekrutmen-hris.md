# Modul Rekrutmen di HRIS — Migrasi Portal Penuh

> Dibuat 2026-08-28, direvisi dari "frontend-only" jadi **migrasi modul penuh** (instruksi
> user: "kita akan membuat portalnya lagi secara lengkap ke employee portal"). Pola sama
> Sikartap: portal Livewire di dalam HRIS, reuse Approval Engine / `users` / `notifications`,
> project legacy jadi referensi business-logic — BUKAN port kode Filament-nya.
>
> Terkait: `planning/2026-08-28-integrasi-hris-rekrutmen-dua-arah.md` (API yang sudah ada),
> `knowledge/INTEGRASI-REKRUTMEN.md` (analisis project eksternal + alur + skema).

---

## 1. Batas Modul (DIPUTUSKAN 2026-08-28 — opsi B)

HRIS **cuma mengadopsi sisi "admin panel"** project eksternal: lihat hasil, tentukan proses,
gerakkan status. **Tidak ada halaman publik di HRIS.**

| Tetap 100% di project eksternal | Pindah ke portal HRIS (Livewire) |
|---|---|
| Halaman lowongan publik, SEO | Daftar pelamar per lowongan (baca via API) |
| Form lamar + akun pelamar | Seleksi berkas — keputusan lolos/tolak |
| **Dashboard pelamar** (status, jadwal, unduh surat) | Jadwal + hasil wawancara |
| Pengerjaan tes online (guest) | Offering letter — upload/download/TTD |
| Notifikasi email ke pelamar | MCU — jadwal + 4 stasiun + fit/unfit |
| | Keputusan akhir + TTD & Onboarding |
| | `passed` → auto-create karyawan HRIS |

Alur data: HRIS baca lamaran/kandidat via API (`getJobOpeningApplications`/`getCandidate`
sudah ada), HRIS gerakkan status → push balik ke eksternal via endpoint `PATCH .../status`
(belum ada, Fase 1). Pelamar tetap lihat progresnya di dashboard project eksternal.

Panel `/candidate` HRIS lama & jalur pendaftaran internal = **dihapus** (Fase 9), bukan
dihidupkan lagi.

---

## 2. Peta Fitur (dari grup nav Filament eksternal → target portal HRIS)

Panel eksternal `/kelola-rekrutmen` (Filament, `topNavigation`) — **6 Resource + 12 Page**:

| Grup Filament eksternal | Item | → Target di HRIS |
|---|---|---|
| **Perusahaan & Lowongan** | Perusahaan | `m_companies` HRIS (sudah ada) + `requires_mcu` per company (Setting) |
| | Form Lamaran (`FormSection`/`FormField`) | **(B): tidak diport** (form di eksternal). (A): halaman config portal |
| | Lowongan | Sudah ada (`JobOpeningsPage` dari ManpowerRequest) — tambah: kuis terpasang, `requires_psychotest` |
| | Kuis + Soal Kuis | **(B): tidak diport** (tes di eksternal). (A): config portal + reuse pola LMS Kuis HRIS |
| *(tanpa grup)* | Daftar Pelamar | **Halaman portal baru** — list lamaran per lowongan (API), bulk → screening |
| **Kandidat** | Tes | (B): baca hasil/keputusan via API. (A): engine kuis |
| | Wawancara | **Halaman portal baru** — jadwal (draft+publish) + catat hasil |
| | Offering Letter | **Halaman portal baru** — upload/download surat, upload balik TTD |
| | MCU + Isi Pemeriksaan MCU | **Halaman portal baru** — jadwal + 4 stasiun (reuse pola `sikartap_mcu_*`) + fit/unfit |
| | TTD & Onboarding | **Halaman portal baru** — tandai selesai → `passed` |
| | Selesai | Bagian dari halaman di atas / list read-only |
| **Pengguna** | Staff & Admin, Pelamar | Tidak diport — user HRIS + `users` |

---

## 3. Reuse — Jangan Duplikat (pelajaran Sikartap)

- **Approval Engine** — `ApprovalModuleEnum` case baru untuk keputusan berjenjang (mis.
  keputusan akhir penerimaan). Keputusan 1-role (seleksi berkas, fit/unfit MCU, hasil
  wawancara) = kolom status + action langsung, **bukan** Approval Engine.
- **`users` / `user_profiles`** — `passed` → create `users` + `user_profiles` +
  `UserProfileDocument`. Dedupe by email/NIK (pola `candidates:import-ktp-kk` sudah ada).
- **`notifications`** (Laravel native) + `NotificationTypeEnum` — port `ApplicationNotification`
  + `CompanyMessageTemplate` eksternal jadi Notification classes (channel `database` + WebPush).
- **MCU 4 stasiun** — contek skema `sikartap_mcu_nurse_assessments`/`_lab_results`/
  `_radiology_results`/`_doctor_examinations` + permission granular (`INPUT_MCU_*`).
- **`OrgHierarchyService`** — routing approver / scope atasan.
- **`RekrutmenSyncService`** (sudah ada) — semua komunikasi ke eksternal.
- **`ManpowerRequests` → `JobOpenings`** (sudah ada, Approval Engine) — hulu rekrutmen,
  tetap dipakai apa adanya.

---

## 4. Prasyarat / Blocker

1. **Endpoint tulis status ke eksternal** — `PATCH /api/integrations/hris/applications/{id}/status`
   di project eksternal, panggil `$application->update(['status' => ...])` (biar `booted()`
   jalan), whitelist transisi yang boleh dari HRIS. Tanpa ini portal HRIS cuma bisa baca.
2. **Endpoint proxy dokumen kandidat** — stream file dari disk `local` eksternal.
3. **Skema data HRIS** — cek `Candidate\*` (12 model) HRIS existing: kompatibel dengan
   `candidates` + 8 anak eksternal, atau bikin `rekrutmen_*` baru?
4. **`.env`** — `REKRUTMEN_API_URL` / `REKRUTMEN_API_TOKEN` (dua project) belum di `.env.example`.

---

## 5. Urutan Fase

| Fase | Isi |
|---|---|
| **0** | Nav group "Rekrutmen" (SUDAH, 2026-08-28) + namespace + container permission Shield + skema/reuse data (§4.3) + varian warna |
| **1** | ✅ SELESAI 2026-08-28 — endpoint tulis-status + proxy dokumen di project eksternal (lihat §7) |
| **2** | ✅ SELESAI 2026-08-28 — Daftar Pelamar + Detail Pelamar (read-only, API) + proxy dokumen HRIS + route modul pindah ke `/rekrutmen` (lihat §8). **Update**: `ApplicantsPage` juga bisa kelola lowongan Rekrutmen yang **belum terhubung ke HRIS** (`hris_job_opening_id` null) — `{opening}` route param jadi string bebas (bukan model binding), eksternal `jobOpeningApplications` cocokkan `hris_job_opening_id` ATAU `id` lowongan sendiri; panel "belum terhubung" dapat tombol "Pelamar". **Update 2**: `ApplicantsPage` fetch semua pelamar 1x + **tab per status** (client-side filter + hitungan per tab) menggantikan dropdown status. Ide "halaman per-tahap di nav" dibatalkan (keputusan user) — kelola per tahap via tab di detail lowongan. |
| **3** | ✅ SELESAI 2026-08-28 — Seleksi berkas: tombol Proses/Loloskan/Tolak di ApplicantDetailPage → `updateApplicationStatus`. Tanpa draft/publish, alasan tolak belum dicatat (follow-up: kolom `note` di status log eksternal). Gate `Update:JobOpenings`. Test 8 PASS. |
| **4** | ✅ SELESAI 2026-08-28 — Wawancara: form jadwal (tanggal/jam/lokasi/catatan) + "Catat Hasil" (Lulus→offering / Tidak Lulus→rejected) di ApplicantDetailPage. Eksternal: `POST .../interview` (upsert schedule 1:1, set `quiz_result=lulus`, status `interview`, notif draft) + `POST .../interview-result`. `showApplication` +`interview`/`quiz_result`/`interview_result`. `RekrutmenSyncService`: `scheduleInterview`/`submitInterviewResult` + helper `writeAction()` (DRY PATCH/POST). Tanpa draft/publish batch (1 pelamar per aksi). Re-schedule form tidak prefill. Test 11 PASS. |
| **5** | ✅ SELESAI 2026-08-28 — Offering letter: unggah surat (multipart), unduh surat recruiter/signed (proxy), "Lanjut ke MCU/Onboarding" (guard: signed letter ada, next status by `requires_mcu`). Eksternal: `POST .../offering-letter`, `GET .../offering-letter/{recruiter\|signed}`, `POST .../offering-letter/advance` + `showApplication` +`offering`/`requires_mcu`. `CandidateDocumentProxyController::offeringLetter`. Test 13 PASS (upload multipart tidak di-assert — batas harness Livewire file-upload). |
| **6** | ✅ SELESAI 2026-08-28 — MCU: **halaman & route per stasiun** (`/rekrutmen/pelamar/{id}/mcu/{nurse\|lab\|radiology\|doctor}`, pola Sikartap), `McuFormPage` station-scoped, gate per-permission stasiun. `McuQueuePage` (`/rekrutmen/mcu`) + tombol per stasiun sesuai permission (`McuFormPage::availableStationLinks()`). Keputusan fit/unfit di ApplicantDetailPage (gate `Update:JobOpenings` + pemeriksaan dokter). **Tanpa jadwal MCU**. Permission reuse `INPUT_MCU_*`/`EDIT_INPUT_MCU`. Nav "Rekrutmen" jadi dropdown (+ Antrian MCU). Eksternal: `GET /mcu-queue`, `GET .../mcu`, `POST .../mcu/{station}` (multipart), `POST .../mcu/decision`; McuInvitee+McuSchedule dummy auto-dibuat. `writeAction()` dapat param `$file`. Test 19 PASS. |
| **7** | ✅ SELESAI 2026-08-28 — status `onboarding` → "Tandai Selesai" (eksternal `POST .../onboarding/complete`: `is_onboarded`, status `passed`, OnboardingInviteNotification). Status `passed` → "Buat / Sinkron Akun Karyawan" (`CandidateOnboardingService`: tarik `getCandidate`, lookup HRIS `JobOpenings` by `hris_job_opening_id` → company/job_position, dedupe by email, create/update `User` + `UserProfiles`). Gate `create User`. **Tabel anak kandidat belum diimpor** (keluarga/pendidikan/dll — perlu padanan HRIS). Test 19 PASS. |
| **8** | ~~Notifikasi~~ **DI-SKIP 2026-08-28** (keputusan user) — semua notifikasi ke pelamar sudah ditangani di project Rekrutmen eksternal; HRIS tidak perlu tambahan. Notifikasi in-app ke staff HRIS tidak dibuat (halaman Daftar Pelamar / Antrian MCU sudah menampilkan kerjaan tertunda). |
| **9** | ✅ SELESAI 2026-08-28 — panel `/candidate` **dinonaktifkan** (comment `CandidatePanelProvider` di `bootstrap/providers.php`), file/model/resource TIDAK dihapus. Aktifkan lagi = uncomment 1 baris. Lebur/hapus penuh & pensiun sisi admin eksternal ditunda (keputusan user). |

Tiap fase diakhiri test Livewire (`tests/Feature/Rekrutmen/`), pola Sikartap.

---

## 7. Fase 1 — yang dikerjakan (2026-08-28)

**Project eksternal** (`D:\Projects\rekrutmen`):
- `routes/api.php` — `PATCH /api/integrations/hris/applications/{id}/status`,
  `GET /api/integrations/hris/candidates/{id}/documents/{documentId}`
- `HrisIntegrationController::updateApplicationStatus()` — lewat Eloquent `update()` (biar
  `JobApplication::booted()` jalan: status log + bikin candidate + email pelamar). Whitelist
  transisi `HRIS_TRANSITIONS` (applied→screening/rejected, screening→shortlisted/rejected,
  shortlisted→interview/rejected, interview→offering/rejected, offering→mcu/onboarding/rejected,
  mcu→onboarding/rejected, onboarding→passed/rejected). Di luar itu → 422. `changed_by` log
  jadi null (request pakai token, bukan user).
- `HrisIntegrationController::downloadCandidateDocument()` — stream 1 dokumen kandidat dari
  disk `local`, cek dokumen memang milik kandidat dari lamaran itu.
- `database/migrations/2026_08_08_000006_*` — fix `dropIndex` sebelum `dropColumn candidate_id`
  (latent bug: kolom dibuat dengan `->index()`, `migrate:fresh` di SQLite gagal). Tidak
  berpengaruh ke MySQL yang sudah termigrasi.
- `tests/Feature/HrisIntegrationTest.php` — 6 test (whitelist transisi, 401/404/422,
  candidate_created). **Belum bisa dijalankan**: suite test rekrutmen tidak jalan di SQLite —
  beberapa migration pakai raw `ALTER TABLE ... MODIFY COLUMN ... ENUM` (MySQL-only,
  `add_shortlisted_to_job_applications_status` dst). Pre-existing, di luar scope Fase 1.
  Test lolos begitu suite dijalankan di MySQL / raw statement-nya di-guard per driver.

**HRIS** (`d:\Projects\hris`): `RekrutmenSyncService`:
- `updateApplicationStatus($jobApplicationId, $status)` — **bukan non-fatal**, pemanggil wajib
  cek `ok` + tampilkan `message` (aksi HRIS bisa gagal karena transisi ditolak).
- `candidateDocumentUrl($jobApplicationId, $documentId)` — URL upstream (butuh token, dipanggil
  server-side oleh proxy HRIS di Fase 2, bukan `<a href>` browser).

---

## 8. Fase 2 — yang dikerjakan (2026-08-28)

**Route modul pindah `/kepegawaian/lowongan*` → `/rekrutmen/lowongan*`** (instruksi user:
modul terpisah, route jangan lagi `kepegawaian`). Route name `kepegawaian.lowongan*` →
`rekrutmen.lowongan*`. 28 referensi di-update (`EditJobOpeningPage` + 5 blade + nav). Redirect
301 `/kepegawaian/lowongan` & `/kepegawaian/lowongan/{record}` → path baru. **Permintaan
Tenaga Kerja TETAP** `kepegawaian.permintaan*` (bagian Jabatan, bukan modul Rekrutmen).

**Project eksternal**: `GET /api/integrations/hris/applications/{id}` (`showApplication`) —
info dasar + `status_logs`, selalu ada (beda dari `showCandidate` yang butuh onboarding+).

**HRIS baru**:
- `App\Livewire\Rekrutmen\ApplicantsPage` (`rekrutmen.lowongan.pelamar`,
  `/rekrutmen/lowongan/{record}/pelamar`) — daftar pelamar 1 lowongan dari
  `getJobOpeningApplications`, filter status, read-only. API gagal → empty state.
- `App\Livewire\Rekrutmen\ApplicantDetailPage` (`rekrutmen.pelamar.show`,
  `/rekrutmen/pelamar/{jobApplication}`) — stepper status + riwayat status (`getApplication`)
  + data diri kandidat (`getCandidate`, cuma onboarding/passed). Kolom tabel anak masih tebakan
  nama field — refine saat lihat payload asli.
- `App\Http\Controllers\Rekrutmen\CandidateDocumentProxyController` (`rekrutmen.pelamar.dokumen`) —
  stream dokumen kandidat dari Rekrutmen (server-side + token), gate `View:JobOpenings`.
- `RekrutmenSyncService::getApplication()`, entry "Lihat Pelamar" di list & detail lowongan.
- `config/portal-pages.php` — key `rekrutmen.*` + 3 entry baru.
- Test `tests/Feature/Rekrutmen/ApplicantsPageTest.php` (5, `Http::fake`) — PASS.

---

## 6. Sudah Ada di HRIS & Masuk Modul

- `app/Livewire/Rekrutmen/` — `JobOpeningsPage`, `JobOpeningDetailPage`, `EditJobOpeningPage`,
  `ManpowerRequestsPage`, `ManpowerRequestDetailPage`, `CreateManpowerRequestPage`,
  `CandidateApplicationDetailPage`.
- `app/Filament/Resources/ManPower/` — `ManpowerRequests`, `JobOpenings`, `JobPositions`.
- `app/Models/Candidate/*` (12 model), `app/Models/Job/*` (`ManpowerRequests`, `JobOpenings`).
- `app/Services/Job/RekrutmenSyncService.php` (+ `pushCompany`/`getJobOpeningApplications`/
  `getCandidate`/`getUnlinkedJobOpenings` — ditambah 2026-08-28), `JobOpeningService.php`.
- Nav group "Rekrutmen" (2026-08-28) — sekarang cuma "Lowongan Kerja".
