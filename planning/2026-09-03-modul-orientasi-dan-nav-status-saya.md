# Modul Orientasi Karyawan + Nav "Status Saya"

> Dibuat 2026-09-03. Pola sama Sikartap: self-service portal Livewire, reuse Approval Engine +
> modul Penilaian yang sudah ada. **Tidak** bikin engine indikator penilaian baru — indikator =
> data di `assessment_template_items`, dikelola HR lewat halaman Template Penilaian.
>
> Terkait: modul Sikartap (referensi alur), `UserTransition` (`EMPLOYMENT_STATUS_CHANGE` =
> "Pengangkatan Orientasi", `CONTRACT_EXTENSION` = "Perpanjangan Kontrak" — sudah ada),
> modul Penilaian (`assessment_*`, `AssessmentTemplateItemType::ORIENTATION` sudah ada).

---

## 0. Keputusan (2026-09-03)

| Topik | Keputusan |
|---|---|
| Bentuk alur | Sikartap-shaped: karyawan orientasi **mengajukan** + **upload bukti kegiatan** → Approval Engine |
| Penilaian | **Bagian dari step approval** — approver (atasan langsung) bukanya form skor, bukan tombol Setuju/Tolak biasa |
| Penilai | **Atasan langsung saja** (single assessor, sesuai modul Penilaian sekarang) |
| Template penilaian | **1 template GLOBAL "Orientasi"** — indikator dibedakan lewat `group_label`, bukan per jabatan |
| Kelulusan | **Keputusan manual penilai**: `Lulus` / `Perpanjang` / `Tidak Lulus` (skor jadi pertimbangan, bukan ambang otomatis) |
| Nav desktop | Dropdown baru Baris 1 label **"Status Saya"** isi: Sikartap · Perpanjangan Kontrak · Orientasi |
| Nav mobile | Section `<span class="pwa-menu-grid-divider">Menu Saya</span>` — **hanya 3 item lifecycle** itu |

Kekhawatiran "indikator penilaian statis" → **terjawab oleh modul Penilaian**: form ide-inovasi
Sikartap statis karena rubrik tetap; orientasi TIDAK meniru itu, skornya lewat
`assessment_scores` per `assessment_template_items`.

---

## REVISI 2026-09-03 (saat implementasi)

- **1 tabel saja** (`orientation_submissions`) — instruksi user. Lampiran & skor jadi kolom
  JSON di baris itu, bukan tabel `orientation_attachments`/`orientation_scores` terpisah.
- **Indikator TIDAK pakai modul Penilaian** (`assessment_templates.job_position_id` NOT NULL,
  tidak mau ubah skema tabel bersama). Indikator disimpan di `settings` key
  `orientation_criteria` (JSON per company), dikelola HR lewat halaman **Indikator Orientasi**
  (`/kepegawaian/orientasi-indikator`). Tetap non-statis, tanpa tabel tambahan.
- Sisanya sesuai rencana di bawah.

## 1. Yang sudah dibangun (perlu dilebur)

Commit sebelumnya bikin `user_orientations` (upload-only) + `UserOrientationResource` + portal
`OrientasiPage` + 7 permission `Verb:UserOrientation` + nav "Orientasi" di dropdown Kepegawaian.

**Rework:** `user_orientations` → `orientation_submissions` (punya status + decision + FK ke
`assessment_users`). Resource admin tetap berguna (HR lihat/koreksi). Portal page jadi
self-service (sisi karyawan) + sisi penilai (via halaman detail approval). Nav "Orientasi"
pindah dari dropdown Kepegawaian Baris 2 ke dropdown "Status Saya" Baris 1.

---

## 2. Skema data

### `orientation_submissions`
| kolom | ket |
|---|---|
| id (ulid) | |
| user_id (unik? tidak — boleh ajukan ulang setelah "Perpanjang") | karyawan kategori `orientasi` |
| company_id | |
| status | `draft` → `menunggu_persetujuan` → `dinilai` → `selesai` (atau `dibatalkan`) |
| decision | null / `lulus` / `perpanjang` / `tidak_lulus` |
| decision_note | wajib saat decision diisi |
| decided_by, decided_at | penilai (atasan langsung) |
| extended_until (date, nullable) | diisi kalau `perpanjang` |
| assessment_user_id → `assessment_users` | hasil skor, nullable sampai step penilaian |
| approval_snapshot_id | link ke Approval Engine (pola UserTransition) |
| timestamps | |

### `orientation_attachments`
`id, submission_id, file_path, original_name, uploaded_by, timestamps` — N lampiran laporan/bukti
kegiatan orientasi. Pola `UserTransitionAttachment`.

### Modul Penilaian (seed, bukan skema baru)
- `assessment_types`: 1 baris "Orientasi Pegawai Baru"
- `assessment_templates`: 1 baris "Penilaian Orientasi", `job_position_id = null` (global) — **cek
  dulu**: kolom `job_position_id` NOT NULL? migration `2026_08_06_..._replace_company_with_job_position`
  perlu ditinjau, mungkin butuh `->nullable()` atau template pakai jabatan placeholder.
- `assessment_template_items`: indikator awal, `group_label` per aspek (mis. Kedisiplinan,
  Kompetensi Teknis, Sikap Kerja, Kerjasama). Semua editable HR tanpa deploy.

---

## 3. Alur

1. **Ajukan** — karyawan orientasi (gate `isOrientasiKaryawan()`, mirror `isKontrakKaryawan()`)
   buka `/orientasi`, isi form ringkas + upload ≥1 bukti → submit.
   - Guard: kategori employee type = `orientasi`, kontrak/orientasi masih aktif, belum ada
     submission `menunggu_persetujuan`/`dinilai` berjalan.
2. **Approval Engine** — modul baru `ORIENTATION_REQUEST` di `ApprovalModuleEnum`. Snapshot
   dibekukan saat submit (pola attendance/manpower). `ApprovalOverrideService::supportedModules()`
   ditambah kalau perlu "Approve Paksa".
3. **Step penilaian** — step yang approver-nya atasan langsung meng-render **form skor** di
   halaman detail (pola `ManpowerRequestDetailPage`): input `score`+`comment` per template item,
   pilih `Lulus / Perpanjang / Tidak Lulus`, catatan wajib. Submit = `advanceStep()` +
   simpan `assessment_users` + `assessment_scores` + `orientation_submissions.decision`.
   - **Cek final**: efek samping (bikin `assessment_user`, set decision) hanya saat
     `snapshot->refresh()->status === 'approved'` — pelajaran `ManPowerReqService::approveManPower()`.
4. **Outcome**:
   - `lulus` → `orientation_submissions.status = selesai`; di portal karyawan & MyStaff muncul
     tombol **"Ajukan Pengangkatan"** → prefill `CreateUserTransitionPage` (`EMPLOYMENT_STATUS_CHANGE`,
     user terisi). Tidak auto-transisi (pengangkatan tetap alur approval sendiri).
   - `perpanjang` → set `extended_until`; karyawan boleh ajukan submission baru setelah tanggal itu.
   - `tidak_lulus` → `status = selesai`, terminal; notifikasi HR.
5. **Notifikasi** (`NotificationTypeEnum` baru): `orientation_submitted`, `orientation_reviewed`
   (ke karyawan, berisi decision), `orientation_needs_review` (ke atasan).

---

## 4. Navigasi

### Desktop — Baris 1 (`resources/views/components/nav.blade.php`)
Link "Sikartap" lepas → **dropdown "Status Saya"**:

```
Status Saya ▾
├─ Sikartap            @if isKontrakKaryawan            → route('sikartap')
├─ Perpanjangan Kontrak @if <gate self-service PPK>      → route('perpanjangan-kontrak')  (BELUM ADA)
└─ Orientasi            @if isOrientasiKaryawan           → route('orientasi')
```

- Gate dropdown = OR ketiganya. Kalau cuma 1 yang lolos, tetap dropdown (konsisten) atau
  degrade ke link tunggal — **putuskan saat implementasi** (default: tetap dropdown).
- Icon: `fa-route` / `fa-signs-post`.

### Mobile — `resources/views/components/pwa-bottom-nav.blade.php`
Tambah section di sheet Menu:
```
<span class="pwa-menu-grid-divider">Menu Saya</span>
  Sikartap · Perpanjangan Kontrak · Orientasi   (gate per item, sama dengan desktop)
```
Item Baris 1 lain (Tugas, Pelatihan, Pengajuan Absensi, Tukar Shift) **tidak diubah**.

### Self-service Perpanjangan Kontrak (BELUM ADA — cakupan terpisah)
Sekarang Perpanjangan Kontrak cuma HR-initiated (`UserTransition` `CONTRACT_EXTENSION`).
Butuh halaman self-service karyawan kontrak buat mengajukan sendiri → masuk `CONTRACT_EXTENSION`
approval. Bisa dikerjakan setelah Orientasi, mengisi slot ke-2 dropdown "Status Saya".

---

## 5. Permission

Lanjut Shield (pola UserContract), bukan custom enum:
- `orientation_submissions` → `ViewAny/View/Create/Update/Delete:OrientationSubmission` +
  `OrientationSubmissionPolicy`.
- Gate self-service ajukan: `isOrientasiKaryawan()` (bukan permission — sama pola Sikartap
  `isKontrakKaryawan()`).
- Portal registry `config/portal-pages.php`: `orientasi` (guard `isOrientasiKaryawan`),
  detail approval reuse gate approver.
- 7 permission `Verb:UserOrientation` yang sudah terlanjur dibuat → rename/hapus saat rework
  jadi `OrientationSubmission`.

---

## 6. Urutan kerja

1. Rework `user_orientations` → `orientation_submissions` + `orientation_attachments` + model/policy/resource.
2. Seed `assessment_types`/`templates`/`items` "Orientasi" + halaman kelola indikator (kalau
   Template Penilaian existing belum cukup buat template global).
3. `ApprovalModuleEnum::ORIENTATION_REQUEST` + service submit/advance + snapshot.
4. Portal `/orientasi`: form ajukan + upload + daftar submission milik sendiri + status.
5. Halaman detail approval + form skor step penilaian + decision.
6. Outcome handler (tombol Ajукан Pengangkatan / extended_until / terminal) + notifikasi.
7. Nav: dropdown "Status Saya" desktop + section "Menu Saya" mobile.
8. (Terpisah) Self-service Perpanjangan Kontrak.

---

## 7. Yang perlu dipastikan saat implementasi

- `assessment_templates.job_position_id` bisa null? Kalau tidak, template global butuh
  penyesuaian migration atau strategi lain.
- Modul Penilaian: `assessment_users` single assessor (`created_by`) — cukup untuk "atasan
  langsung saja". Jangan tambah multi-assessor sekarang.
- Approval step "penilaian" = step biasa yang halaman detailnya render form skor (rekomendasi),
  BUKAN tipe step khusus di master Alur Persetujuan.
- Efek samping approval hanya saat snapshot benar-benar `approved` (regresi klasik ManPower).
