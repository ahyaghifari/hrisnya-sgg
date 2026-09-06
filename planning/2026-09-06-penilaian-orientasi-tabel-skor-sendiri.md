# Penilaian Orientasi — Tabel Skor Sendiri + Reviewer Berbasis Jabatan (lepas Approval Engine)

> Dibuat 2026-09-06. **Merevisi** `planning/2026-09-03-modul-orientasi-dan-nav-status-saya.md`
> §0 (dulu: "penilaian orientasi reuse `assessment_*`" + "lewat Approval Engine"). Sekarang:
> orientasi punya tabel skor sendiri, DAN penilaiannya **lepas dari Approval Engine** —
> ganti ke reviewer berbasis jabatan (tabel `sikartap_reviewers` yang general).
>
> Terkait: `user_orientations` (ledger periode), `orientation_submissions` (workflow),
> `OrientationService`, `OrientasiDetailPage`, `SikartapIdeInovasiReviewer` (dimigrasi),
> modul Penilaian Karyawan `assessment_*` (TETAP terpisah — cuma performance appraisal).

---

## 0. Keputusan (2026-09-06)

### Skor orientasi

| Topik | Keputusan | Alasan |
|---|---|---|
| Orientasi vs `assessment_*` | **Terpisah total.** `assessment_*` khusus performance appraisal karyawan tetap/kontrak. | Modul Penilaian masih setengah jadi; alur & bentuk orientasi beda; "duplikasi" nyatanya kecil. Riset HRIS luar: probation review memang 1 modul performance — tapi modul kita belum matang cukup buat menampung. |
| Bentuk simpan skor | **2 tabel parent/child** — pola sama `assessment_users` → `assessment_scores`. | `orientation_submissions.scores` JSON blob susah di-query + butuh dimensi bulan. |
| Dimensi | **Per indikator PER BULAN.** 3 bln × 5 indikator = 5 baris indikator + 15 baris skor. | Instruksi user. |
| Penamaan | **Opsi B**: `orientation_submission_assessments` + `orientation_submission_scores`. TIDAK rename `orientation_submissions`. | Rename kena 19 file + Shield + Policy + folder. Kosmetik. |
| `Setting orientation_criteria` | **Tetap** (bukan enum — ditolak), turun jadi "label saran" buat prefill. Tab "Indikator Orientasi" ditambah kontrol urutan ▲/▼. | Kriteria = data jarang berubah, jangan hardcode. Urutan → `sort_order` baris saat prefill. |
| Snapshot | Indikator di-capture sebagai baris nyata per submission. Ubah `Setting` nanti tidak mengubah penilaian lama. | — |
| Skala | **1–5** fixed (dropdown), samakan `assessment_*`. | Reporting lintas-penilaian nanti murah. |
| Keputusan penilai | **2 opsi saja**: `lanjut_kontrak` (Lanjut Kontrak) \| `perpanjang` (Perpanjang Orientasi). **Tidak ada `tidak_lulus`** (`OrientationDecisionEnum` di-rewrite). | User: "tidak ada tuh yang tidak lulus". |
| Tindak lanjut keputusan | Penilai **hanya** simpan skor + keputusan (status `dinilai`). HR lalu isi kelanjutannya: `perpanjang` → durasi + tgl mulai → `user_orientations` baru; `lanjut_kontrak` → diarahkan ke Perubahan Status Karyawan (UserTransition). Status jadi `selesai`. | User: "setelah simpan keputusan harusnya diisi oleh hr untuk kelanjutannya". Bikin kontrak reuse validasi UserTransition, jangan duplikat. |

### Reviewer / penilai — lepas Approval Engine

| Topik | Keputusan | Alasan |
|---|---|---|
| Approval Engine buat orientasi | **DIBUANG.** Modul `ORIENTATION_REQUEST`, `ApprovalSnapshots`, multi-step — semua dilepas dari alur orientasi. | Keputusan orientasi = 1 penilai mutusin, bukan routing. Approval Engine bikin overhead config (harus set `MasterApprovalFlows` per company dulu — sekarang 0 flow = karyawan gak bisa submit sama sekali). |
| Ganti dengan | **Tabel `sikartap_reviewers`** (general, dipakai orientasi + Sikartap ide-inovasi sekarang; kontrak nyusul). Reviewer = **jabatan** (`job_position_id`), bukan user langsung. | Jabatan tahan personil ganti — "Kepala Dept X" selalu resolve ke pemegang terkini. Pola sama `SikartapIdeInovasiReviewer` yang sudah ada, tinggal digeneralisasi + jabatan-based. |
| Sikartap **director-decision** | **BUKAN** di `sikartap_reviewers`. Direktur pemutus = **Setting langsung** per company (mis. `Setting` key nunjuk jabatan/user Direktur). Terpisah, dan tetap di luar scope sesi ini. | Cuma 1 pemutus per company, gak perlu per-departemen/berjenjang seperti reviewer biasa. |
| Level org | **Departemen level-1.** Naik dari jabatan karyawan orientasi sampai node `LEVEL_DEPARTMENT`. | Konsisten keputusan Sikartap 2026-08-26 ("disederhanakan jadi Departemen saja"). |
| Scope | Per `(context, company_id, org_structure_id [departemen], job_position_id)`. **Tidak ada** baris global lintas-departemen. | User: "per perusahaan masing2, tidak berpengaruh" (kasus 1 jabatan lintas semua departemen tidak dipakai). |
| Jabatan reviewer 0 pemegang aktif | **Fallback naik** ke departemen parent, ambil reviewer di situ. Kalau sampai atas tetap kosong → **tolak** aksi + pesan jelas "Jabatan penilai untuk departemen ini kosong (belum ada pemegang). Hubungi HR." | Tetap dalam model penunjukan, tidak balik ke auto org-hierarchy. |
| `SikartapIdeInovasiReviewer` (user-based, live) | **Ikut dimigrasi** ke `sikartap_reviewers` sesi ini. **0 baris data** → tidak ada konversi, tinggal swap kode + tabel. | Cheap, sekalian. |
| Sikartap director-decision & Kontrak/`UserTransition` | **TIDAK disentuh sesi ini.** Tetap Approval Engine. Kalau nanti dilepas: director-decision → Setting langsung; Kontrak → `sikartap_reviewers`. Plan terpisah. | Dua-duanya live & jalan; ripping out sekarang = risiko besar, gain gak jelas. |

---

## 1. Tabel `sikartap_reviewers`

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `context` | string | `orientation` \| `sikartap_idea` (nanti: `contract`). **`sikartap_director` TIDAK di sini** — Direktur pemutus lewat Setting langsung per company. |
| `company_id` | foreignUlid → `m_companies` | `cascadeOnDelete`. Scoping per perusahaan (`org_structures` tidak punya `company_id`). |
| `org_structure_id` | foreignUlid → `org_structures` | `cascadeOnDelete`. Node level Departemen. |
| `job_position_id` | foreignUlid → `m_job_positions` | `cascadeOnDelete`. Jabatan reviewer. |
| `timestamps` | | |

Unique: `(context, company_id, org_structure_id, job_position_id)`.
Index: `(context, company_id, org_structure_id)`.

### Resolver — `SikartapReviewerResolver` (service baru) atau helper di model

```
reviewersFor(string $context, User $target): Collection<User>
  1. $dept = departemen level-1 dari $target->activeJobPosition->jobPosition->orgStructure
             (naik parent sampai level LEVEL_DEPARTMENT)
  2. loop: cari sikartap_reviewers (context, company_id=$target->company_id, org_structure_id=$dept->id)
     - ada → resolve tiap job_position_id ke pemegang AKTIF (UserJobPositions aktif) → return
     - kosong → $dept = $dept->parent; ulang
  3. mentok atas tanpa hasil → return collect() kosong  (pemanggil yang tolak + pesan)

canReview(string $context, User $target, User $actor): bool
  → reviewersFor(...)->contains('id', $actor->id)
```

### Kelola (UI penunjukan)

- Section **"Penilai Orientasi" di `SikartapSettingsPage`** (bukan halaman baru, bukan tab di
  OrientasiKelolaPage) — pilih Departemen + Jabatan, CRUD baris `sikartap_reviewers`
  (`context=orientation`). **Tidak** digate toggle "wajib Ide Inovasi". Permission
  `EDIT_PENGATURAN_SIKARTAP`.

---

## 2. Skema tabel skor orientasi

### `orientation_submission_assessments` (indikator per submission — snapshot)

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `orientation_submission_id` | foreignUlid → `orientation_submissions` | `cascadeOnDelete` |
| `label` | string | mentah, mis. "Kedisiplinan" |
| `comment` | text nullable | catatan penilai per indikator (ringkasan lintas bulan) |
| `sort_order` | int default 1 | |
| `timestamps` | | |

Index: `(orientation_submission_id, sort_order)`.

### `orientation_submission_scores` (skor per bulan)

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `orientation_submission_assessment_id` | foreignUlid → parent | `cascadeOnDelete` |
| `period_month` | date | tanggal 1 bulan ybs |
| `score` | decimal(4,2) nullable | 1–5; null = belum dinilai bulan itu |
| `comment` | text nullable | catatan per sel — opsional |
| `timestamps` | | |

Unique: `(orientation_submission_assessment_id, period_month)`.

### Perubahan `orientation_submissions`

- **Drop kolom `scores`** (JSON). Cek data — kalau ada isi, migrasikan di `up()` sebelum drop.
- Kolom terkait Approval Engine yang jadi mati: cek apa ada FK/kolom `approval_snapshot`
  langsung (kemungkinan tidak — snapshot lewat `ApprovalSnapshots.module_id`). Tambah kolom
  status baru kalau perlu (lihat §3 alur).

---

## 3. Alur orientasi TANPA Approval Engine

Status `orientation_submissions.status` baru (ganti yang lama):
`draft` → `diajukan` → `dinilai` → `selesai`.  (`dibatalkan` tetap ada.)

`dinilai` = penilai sudah putuskan + skor masuk, **nunggu tindak lanjut HR**.
`selesai` = HR sudah proses kelanjutan (periode perpanjangan dibuat / diarahkan ke kontrak).

1. **Karyawan** (`OrientasiPage`): `startApplication()` → butuh `user_orientations` aktif
   (sudah ada guard). Buat submission `draft`, link `user_orientation_id`.
   Rejected/dibatalkan → `reopenApplication()` pakai baris yang **sama** (bukan record baru).
2. Upload bukti (sudah ada). `submit()`:
   - Resolve `SikartapReviewerResolver::reviewersFor('orientation', $user)`.
   - Kosong → tolak: "Jabatan penilai untuk departemen Anda kosong (belum ada pemegang). Hubungi HR." Submission tetap `draft`.
   - Ada → status `diajukan`, `submitted_at = now()`. Notif `OrientationReviewRequested` ke tiap reviewer.
3. **Reviewer** (`OrientasiDetailPage`): gate `OrientationService::canReview()` (resolver).
   - Isi grid skor (indikator × bulan, dropdown 1–5) + keputusan (`lanjut_kontrak`/`perpanjang`)
     + `decision_note` (wajib). Penilai **tidak** bisa tambah/edit indikator (diatur di tab Indikator).
   - Simpan (`OrientationService::review($submission, $actorId, $rows, $decision, $note)`): tulis
     `orientation_submission_assessments` + `orientation_submission_scores` (hapus-buat-ulang),
     set status `dinilai`, `decision`, `decided_by`, `decided_at`. **Tidak ada** side-effect
     periode di sini. Notif `OrientationReviewed` ke karyawan.
   - Tombol: "Simpan Keputusan & Penilaian".
4. **HR tindak lanjut** (`OrientasiDetailPage`, gate `canFollowUp` = status `dinilai` + HR):
   - `perpanjang` → `followUpExtend($submission, $startDate, $months, $actorId)`: bikin
     `UserOrientation` (`source='extension'`, `previous_orientation_id`, `source_submission_id`),
     set `extended_until`, status `selesai`. Ada preview rentang tanggal live.
   - `lanjut_kontrak` → `followUpContract($submission, $employeeTypeId, $start, $end, $actorId)`:
     angkat langsung di halaman ini — flip `user_profiles.employee_type_id` (+ `kelas_id` dari
     `pangkat.start_kelas_id`), catat 1 baris `user_contracts`, status `selesai`. Opsi tipe
     karyawan dibatasi kategori Kontrak milik perusahaan pengajuan. **Tidak** lewat UserTransition.
   - Jenis transisi "Pengangkatan Orientasi" (`EMPLOYMENT_STATUS_CHANGE`) & "Perpanjangan Kontrak"
     (`CONTRACT_EXTENSION`) DIHAPUS dari picker Perubahan Status Karyawan
     (`UserTransitionTypeEnum::assignable()`) — case enum tetap ada buat data lama & approval
     yang masih jalan. Modul kontrak lengkap = task terpisah berikutnya.
5. **Batal** (karyawan, sebelum dinilai): status `dibatalkan`.
6. `OrientationSubmission` `diajukan` muncul di widget "Butuh Persetujuan Anda" untuk reviewer.

### Yang dibuang

- `OrientationService::submit()` bagian resolve `MasterApprovalFlows` + `createSnapshot`.
- `OrientationService` inject `ApprovalSnapshotRepository`/`ApprovalCreationService`/`ApprovalFlowService`.
- `review()`/`reject()`/`cancel()` bagian `advanceStep()`.
- `OrientationSubmission::approvalSnapshot()` / `approvalHistories()` relations + `ORIENTATION_REQUEST`
  di `ApprovalModuleEnum` (cek dulu tidak dipakai di `PendingApprovalsCard` — line 499 pakai,
  itu ikut diganti ke query `sikartap_reviewers` + submission `diajukan`).
- `OrientasiDetailPage` logika `canApprove`/`isFinalStep`/`forwardToNextApprover` → ganti
  `canReview` tunggal.
- Timeline approval di `OrientasiDetailPage` blade → ganti info "diajukan / dinilai oleh X".

---

## 4. Berapa kolom bulan

- Jumlah = `user_orientations.duration_months` periode yang di-link submission.
- `period_month` di-generate: `start_date` periode → `startOfMonth()` +1 bln × `duration_months`.
- Perpanjangan → submission penilaian BARU pakai periode baru → `duration_months` baru.

---

## 5. Perubahan kode (ringkas)

### Model baru
- `App\Models\SikartapReviewer` — belongsTo company/orgStructure/jobPosition. Scope helper `forContext()`.
- `App\Models\OrientationSubmissionAssessment` — belongsTo submission, hasMany scores.
- `App\Models\OrientationSubmissionScore` — belongsTo assessment.

### Service baru
- `App\Services\SikartapReviewerResolver` — `reviewersFor()`, `canReview()`, fallback naik parent.

### `OrientationService`
- Buang dependency Approval Engine (lihat §3 "yang dibuang").
- `submit()` → resolve reviewer, tolak kalau kosong, set `diajukan`, notif reviewer.
- `review($submission, $actorId, $rows, $decision, $note)` → `$rows` =
  `[{label, comment, sort_order, monthly:[{period_month, score, comment}]}]`, simpan 2 tabel
  (transaksi: `assessments()->delete()` lalu buat ulang), set status `dinilai` + decision.
  **Tidak** ada side-effect periode.
- `followUpExtend($submission, $startDate, $months, $actorId)` — bikin `UserOrientation`
  `source=extension`, set `extended_until`, status `selesai`. Guard status `dinilai` + decision `perpanjang`.
- `followUpContract($submission, $actorId)` — status `selesai`. Guard status `dinilai` + decision `lanjut_kontrak`.
- `reject()` DIHAPUS (tidak ada keputusan tidak-lulus).
- `criteria()` / `saveCriteria()` TETAP (flat, cuma `name`).

### `SikartapIdeInovasiReviewer` → `sikartap_reviewers`
- Migration: drop tabel lama (0 baris). `Sikartap::ideInovasiAtasanCandidates()` /
  `canReviewIdeInovasiAsAtasan()` → panggil `SikartapReviewerResolver` dgn `context='sikartap_idea'`.
- UI penunjukan reviewer ide-inovasi lama (kalau ada halaman terpisah) → arahkan ke halaman
  "Penilai" general yang baru, atau sesuaikan.

### Livewire
- `OrientasiKelolaPage`: tab `pengajuan` (label "Periode Orientasi", gabung eks-tab Periode:
  1 baris per karyawan status-orientasi, periode + sisa hari + status pengajuan + keputusan +
  aksi) + tab `riwayat` (karyawan yang statusnya bukan orientasi lagi tapi punya history) +
  tab `indikator` (flat `name`, ▲/▼). Buang kolom "Sumber Orientasi" + filter pills.
- `OrientasiDetailPage`: grid form (baris = indikator read-only, kolom = bulan, sel = dropdown
  1–5), gate `canReview`; section "TINDAK LANJUT HR" gate `canFollowUp` (perpanjang: tgl+bulan
  +preview; lanjut_kontrak: link ke Perubahan Status Karyawan).
- `OrientasiPage` + detail read-only: matriks indikator × bulan + rata-rata on-the-fly.
- `SikartapSettingsPage`: section "Penilai Orientasi" (CRUD `sikartap_reviewers`).
- `PendingApprovalsCard` (~line 495): blok orientasi → `OrientationSubmission` status `diajukan`
  di-`filter` `OrientationService::canReview()`.
- **DIHAPUS**: `OrientasiMonitorPage` + blade (redundan dgn tab Periode Orientasi).

### Cleanup
- Hapus `AssessmentTemplateItemType::ORIENTATION` (+ blok Repeater "Orientasi Pegawai Baru" di
  `UserAssessmentForm.php`, 4 tempat) — artefak rencana lama.
- `AssesmentTypeResource` yang rusak — **jangan** disentuh sesi ini (nunggu keputusan enum
  `assessment_types` terpisah).

---

## 6. Urutan eksekusi

1. Migration: `sikartap_reviewers`; `orientation_submission_assessments` + `orientation_submission_scores`;
   drop `orientation_submissions.scores`; drop `sikartap_idea_inovasi_reviewers`.
2. Model: `SikartapReviewer`, `OrientationSubmissionAssessment`, `OrientationSubmissionScore`;
   update `OrientationSubmission` (buang `scores`, buang relations approval, tambah `assessments()`).
3. `SikartapReviewerResolver` service + test resolver (fallback naik parent, jabatan kosong).
4. `OrientationService`: lepas Approval Engine, `submit()` pakai resolver, `review()` bentuk baru.
5. `Sikartap::ideInovasiAtasanCandidates()` → resolver `context='sikartap_idea'`.
6. Halaman "Penilai" (CRUD `sikartap_reviewers`) + nav + permission.
7. `OrientasiKelolaPage` tab Indikator — tombol urut.
8. `OrientasiDetailPage` + blade — grid form, gate `canReview`, buang timeline approval.
9. `OrientasiPage` + detail read-only — matriks + rata-rata.
10. `PendingApprovalsCard` — blok orientasi pakai resolver.
11. Cleanup `AssessmentTemplateItemType::ORIENTATION`.
12. Test: resolver; `review()` simpan baris benar + re-review hapus-buat-ulang; grid bulan ikut
    `duration_months`; submit tolak kalau reviewer kosong.

### Tambahan sesudah plan awal

- **DB hierarki dibetulkan**: `user_orientations` = INDUK, `orientation_submissions` = anak
  (`orientation_submissions.user_orientation_id`). Kolom balik `user_orientations.source_submission_id`
  DIBUANG (migration `2026_09_06_120000`). Rantai perpanjangan lewat `previous_orientation_id`.
- **Keputusan penilai final** — tidak ada reopen untuk `perpanjang`/`lanjut_kontrak` (cuma
  `dibatalkan`). `perpanjang`: periode lama tutup; karyawan ngajukan LAGI untuk periode BARU
  (submission baru, 1 per `user_orientation_id`). Selama HR belum bikin periode baru → tidak
  bisa ngajukan apa pun.
- **Jendela pengajuan per company** — Setting `kepegawaian_pengajuan_orientasi_lead_hari` (+
  `..._kontrak_lead_hari` buat modul kontrak nanti) di `KepegawaianSettingEnum` /
  halaman Pengaturan Kepegawaian. Pengajuan orientasi baru boleh dibuat mulai N hari sebelum
  periode berakhir; `OrientasiPage::canStartNew` + `startApplication` enforce. 0 = bebas.
- **OrientasiPage**: periode aktif = user_orientation terbaru; periode lama di-`<details>`
  collapse (label "nonaktif").
- **Tindak lanjut HR `lanjut_kontrak`** dikerjakan inline di OrientasiDetailPage (bukan
  UserTransition) — lihat §0/§3. Jenis "Pengangkatan Orientasi" & "Perpanjangan Kontrak"
  dibuang dari picker Perubahan Status Karyawan (`UserTransitionTypeEnum::assignable()`).
- **Minta revisi bukti** — penilai bisa kembalikan pengajuan `diajukan` ke `draft` +
  `decision_note` (alasan); notif `OrientationRevisionRequested` ke KARYAWAN saja. `submit()`
  bersihkan `decision_note`/`decided_by`/`decided_at` saat re-submit. Karyawan lihat banner
  revisi di OrientasiPage.
- **Guard skor** — `review()` tolak kalau tidak ada satu pun skor bulan terisi.
- Notifikasi ke penilai/HR untuk "menunggu tindak lanjut" TIDAK dibuat (keputusan user) — HR
  pantau lewat status `dinilai` di OrientasiKelolaPage.

---

## 7. Di luar scope

- Rename `orientation_submissions` → `user_orientation_*` (Opsi A ditolak).
- **Sikartap director-decision** — tetap Approval Engine dulu. Kalau nanti dilepas, pemutusnya
  lewat **Setting langsung** per company (jabatan/user Direktur), BUKAN `sikartap_reviewers`.
- Migrasi **Kontrak/`UserTransition`** ke `sikartap_reviewers` — plan terpisah, tetap Approval
  Engine dulu.
- Enum-kan `assessment_types` — topik terpisah.
- Ambang kelulusan otomatis dari skor — keputusan tetap manual penilai.
- Bobot/weighted per indikator — ranah `assessment_*`.
- Nyambungin `lulus` → auto `UserTransition` (Orientasi→Kontrak) — di-defer user ("nanti").
