# Modul Perpanjangan Kontrak — self-service, lepas Approval Engine

> **STATUS 2026-09-06: DIEKSEKUSI.** Backend + UI + test jalan (14 test hijau: 6 modul kontrak
> + 8 orientasi regresi). Yang DITUNDA: step 12 (hapus dead code `CONTRACT_EXTENSION` di
> `UserTransitionService`/`CreateUserTransitionPage`/`UserTransitionForm`) — dibiarkan dorman
> karena mungkin masih ada transisi `contract_extension` pending di DB yang butuh
> `applyTransitionChanges()` saat di-approve. Belum di-commit.

> Dibuat 2026-09-06. Mirror `planning/2026-09-06-penilaian-orientasi-tabel-skor-sendiri.md`
> (modul Orientasi) — pola & infra yang sama, versi lebih ringkas.
>
> **Scope: HANYA perpanjangan kontrak.** Kontrak → Tetap = modul Sikartap (`pengajuan_karyawan_tetap`),
> pengajuan terpisah, TIDAK disentuh di sini. Penilaian kinerja = ranah `assessment_*`,
> BUKAN bagian modul ini (reviewer boleh lihat appraisal di modul itu secara terpisah).
>
> Menggantikan: legacy `another-app/sikartap/perpanjangan_kontrak*.php` (PHP prosedural) +
> jalur `UserTransition` tipe `CONTRACT_EXTENSION` (Approval Engine).

---

## 0. Keputusan

| Topik | Keputusan | Alasan |
|---|---|---|
| Approval Engine | **DIBUANG.** 1 penilai memutuskan, bukan routing berjenjang. | Konsisten Orientasi (2026-09-06). Approval Engine butuh setup `MasterApprovalFlows` per company dulu = overhead. |
| Reviewer | **`sikartap_reviewers` context `contract`** (jabatan → departemen level-1, fallback naik parent, tolak kalau kosong). 1 penilai. | Reuse `SikartapReviewerResolver` yang sudah general. Jabatan tahan personil ganti. |
| Penilaian kinerja / skor | **TIDAK di modul ini.** Tidak ada tabel skor. Reviewer cukup `perpanjang` / `tidak_perpanjang` + catatan + lihat lampiran. | Performance appraisal = `assessment_*`. Jangan duplikasi. |
| Kontrak → Tetap | **BUKAN di sini.** Tetap lewat Sikartap. | Pengajuan beda, pertimbangan beda. |
| Durasi perpanjangan | **Diisi REVIEWER** saat memutuskan `perpanjang`, prefilled dari `PERPANJANGAN_KONTRAK_BULAN` per company, boleh override. Karyawan TIDAK pilih durasi (beda dari legacy). | Panjang kontrak = keputusan manajemen, bukan hak karyawan. Legacy naruh di karyawan itu kebalik. |
| Usulan durasi karyawan | Kolom opsional `usulan_durasi_bulan` (non-binding, cuma masukan buat reviewer). | Tetap kasih suara ke karyawan tanpa mengikat. |
| Tindak lanjut | **1 langkah.** Reviewer `perpanjang` → langsung bikin baris `user_contracts` baru. Tidak ada langkah HR terpisah (beda dari Orientasi — di sana tanggal butuh keputusan HR, di sini formulaic). | Tanggal kontrak baru dihitung: start = hari setelah kontrak lama berakhir (atau hari ini kalau sudah lewat), end = start + durasi. |
| Guard | `resolveContractEndDate()` yang sudah ada — `MAX_KONTRAK_TAHUN` (dari Tanggal Bergabung) + `USIA_PENSIUN_TAHUN`. Tolak dengan pesan jelas. | Sudah dipakai `UserTransitionService`, tinggal dipanggil ulang. |
| Jendela pengajuan | `PENGAJUAN_KONTRAK_LEAD_HARI` per company (sudah ada di `KepegawaianSettingEnum`). Karyawan baru bisa ajukan mulai N hari sebelum kontrak berakhir. 0 = bebas. | Sama pola jendela pengajuan Orientasi. |
| Dokumen lampiran | **Dinamis per company** — daftar dokumen yang diminta diatur HR (Setting `contract_extension_documents`, JSON `[{label, required}]`). Default: Kontrak Terakhir + Surat Rekomendasi Atasan. "Penilaian Kinerja" legacy DIBUANG (→ `assessment_*`). | Tiap company beda kebutuhan dokumen; slot fixed cepat mentok. Pola = `orientation_criteria`. |
| Penyimpanan dokumen | Tabel anak **`contract_submission_documents`** (1 baris = 1 file: `label`, `path`, `original_name`), BUKAN JSON `attachments`. File di disk `public`, folder `contract-extension/{user_id}/`. | `label` per baris (dokumen apa) + query per submission gampang. Mirip legacy `dokumen_perpanjangan` tapi baris-per-file, bukan kolom fixed. |
| Siapa boleh ajukan | Karyawan kategori **KONTRAK** (`EmployeeTypeCategory::KONTRAK`) yang punya `currentContract`. | Perpanjangan cuma relevan buat yang lagi berkontrak. |

---

## 1. Tabel `contract_submissions`

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `user_id` | foreignUlid → `users` | cascadeOnDelete |
| `user_contract_id` | foreignUlid → `user_contracts` | **INDUK** — kontrak berjalan yang mau diperpanjang. nullOnDelete. |
| `company_id` | foreignUlid → `m_companies` | cascadeOnDelete |
| `status` | string | `draft` → `diajukan` → `selesai`. (`ditolak` = reviewer `tidak_perpanjang`; `dibatalkan` = pengaju batalkan sebelum dinilai.) |
| `usulan_durasi_bulan` | unsignedSmallInt nullable | usulan karyawan (non-binding) |
| `alasan` | text nullable | alasan perpanjangan (dari pengaju) |
| `decision` | string nullable | `perpanjang` \| `tidak_perpanjang` |
| `decision_durasi_bulan` | unsignedSmallInt nullable | durasi final yang dipakai reviewer (kalau `perpanjang`) |
| `decision_note` | text nullable | catatan reviewer; juga dipakai buat catatan "minta revisi" (pola Orientasi) |
| `decided_by` | foreignUlid → `users` nullable | nullOnDelete |
| `decided_at` | timestamp nullable | |
| `new_contract_id` | foreignUlid → `user_contracts` nullable | baris `user_contracts` hasil perpanjangan (kalau `perpanjang`). nullOnDelete. |
| `submitted_at` | timestamp nullable | |
| `timestamps` | | |

Index: `(company_id, status)`, `(user_id, status)`.

Nama FK pendek eksplisit (MySQL 64 char) — mis. `cs_user_contract_fk`, `cs_new_contract_fk`.

**Hierarki:** `user_contracts` (induk) ─< `contract_submissions` (anak, via `user_contract_id`)
─< `contract_submission_documents` (anak, lampiran). `new_contract_id` = pointer maju ke hasil,
BUKAN FK bolak-balik yang jadi induk (pelajaran dari `user_orientations.source_submission_id`
yang dibuang).

### `contract_submission_documents` (lampiran — 1 baris per file)

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `contract_submission_id` | foreignUlid → `contract_submissions` | cascadeOnDelete, FK `csd_submission_fk` |
| `label` | string | keterangan dokumen (snapshot dari config saat unggah — mis. "Kontrak Terakhir") |
| `path` | string | relatif ke disk `public` |
| `original_name` | string | nama file asli buat ditampilkan |
| `timestamps` | | |

Index: `(contract_submission_id)`.

**Bukan JSON `attachments`** (beda dari `orientation_submissions`) — dipisah jadi tabel supaya
tiap lampiran punya `label` sendiri (dokumen apa) & gampang di-query per submission. Pola legacy
`dokumen_perpanjangan` tapi 1 baris = 1 file (bukan 3 kolom fixed).

### Daftar dokumen yang diminta — dinamis per company

Setting `contract_extension_documents` (module `kepegawaian`/`sikartap`, JSON) =
`[{label, required}]`. Default seed: `[{label:"Kontrak Terakhir", required:true},
{label:"Surat Rekomendasi Atasan", required:true}]`. Pola & kode = `OrientationService::criteria()`
/ `saveCriteria()` (flat list JSON di `settings`, snapshot saat dipakai).

- Form karyawan render 1 slot upload per entri config, `label` jadi keterangan slot.
- `submit()` guard: tiap entri `required` harus punya baris `contract_submission_documents`
  dengan `label` itu.
- Ubah config nanti tak ganggu pengajuan lama (label sudah kesimpan per baris dokumen).
- Diatur HR di tab "Dokumen Perpanjangan Kontrak" (halaman settings kepegawaian/sikartap,
  sebelah "Penilai Perpanjangan Kontrak").

---

## 2. `sikartap_reviewers` — context `contract`

- Tambah konstanta `SikartapReviewer::CONTEXT_CONTRACT = 'contract'`.
- `SikartapReviewerResolver` sudah general — cukup panggil `reviewersFor('contract', $user)` / `canReview(...)`.
- UI penunjukan: section **"Penilai Perpanjangan Kontrak"** di `SikartapSettingsPage` (sebelah
  "Penilai Orientasi"), gate `EDIT_PENGATURAN_SIKARTAP`. Pola sama persis.

---

## 3. Alur

`draft` → `diajukan` → `selesai`. (`ditolak`, `dibatalkan`.)

1. **Karyawan** (`KontrakSayaPage`, portal — mirror `OrientasiPage`):
   - Gate: kategori KONTRAK + punya `currentContract`.
   - `getWindowOpensAt()` = `currentContract.contract_end_date - PENGAJUAN_KONTRAK_LEAD_HARI`.
     Sebelum itu → tak bisa ajukan (tombol disable + note tanggal buka).
   - 1 pengajuan aktif per `user_contract_id` (guard unik, pola `canStartNew` Orientasi).
   - Isi: alasan (wajib), usulan durasi (opsional), upload dokumen per slot config
     (`contract_extension_documents`).
     - Validasi upload: `file|max:20480|mimes:pdf,ppt,pptx,jpg,jpeg,png` (pola `OrientasiPage::uploadBukti()`).
     - Simpan: `->store('contract-extension/'.Auth::id(), 'public')` → buat baris
       `contract_submission_documents` (`label` dari slot, `path`, `original_name`).
       Hapus = `Storage::disk('public')->delete($path)` + hapus baris. Boleh unggah/hapus
       selama status `draft`.
     - Dilihat reviewer via `Storage::disk('public')->url($path)`, dikelompokkan per `label`.
   - `submit()` → resolve reviewer (`sikartap_reviewers` context `contract`), kosong → tolak
     "Jabatan penilai perpanjangan kontrak untuk departemen Anda kosong. Hubungi HR."
     Status `diajukan`, notif `ContractReviewRequested` ke reviewer.
2. **Reviewer** (`KontrakDetailPage` — mirror `OrientasiDetailPage`, TANPA grid skor):
   - Gate `ContractExtensionService::canReview()`.
   - Lihat: data karyawan, kontrak berjalan, alasan, usulan durasi, lampiran (+ link ke
     modul `assessment_*` karyawan itu, kalau ada — sekadar tautan, bukan embed).
   - Keputusan: `perpanjang` + `decision_durasi_bulan` (prefill `PERPANJANGAN_KONTRAK_BULAN`,
     boleh ubah) + catatan, ATAU `tidak_perpanjang` + catatan.
   - **Minta revisi bukti** (pola Orientasi): balik ke `draft` + `decision_note`, notif
     `ContractRevisionRequested` ke karyawan. `submit()` bersihkan `decision_*`.
   - `perpanjang` → `ContractExtensionService::approve()`:
     - `$anchor` = `currentContract.contract_end_date->max(today)`.
     - `$end` = `resolveContractEndDate($user, $anchor, $companyId, months: $decisionDurasi)`
       — **refactor**: tambah param opsional `$months` (default tetap dari setting), guard
       `MAX_KONTRAK_TAHUN` + `USIA_PENSIUN_TAHUN` tetap jalan, lempar exception kalau lewat.
     - Bikin `UserContract::create([...])` (`contract_start_date`=$anchor, `contract_end_date`=$end,
       `employee_type_id` = `currentContract.employee_type_id`, `source_transition_id`=null).
     - `contract_submissions`: `status='selesai'`, `decision='perpanjang'`, `decision_durasi_bulan`,
       `new_contract_id`, `decided_by`, `decided_at`.
     - Notif `ContractReviewed` ke karyawan.
   - `tidak_perpanjang` → `status='ditolak'`, `decision`, `decided_by/at`, notif `ContractReviewed`.
     **Tidak ada efek lain** — kontrak habis alami; penonaktifan karyawan proses manual/terpisah.
3. **Batal** (karyawan, sebelum dinilai): `status='dibatalkan'`.
4. Muncul di widget "Butuh Persetujuan Anda" untuk reviewer (pola Orientasi di `PendingApprovalsCard`).

### Notifikasi (ke KARYAWAN & reviewer saja, TIDAK ke HR — konsisten Orientasi)

`NotificationTypeEnum` tambah:
- `CONTRACT_REVIEW_REQUESTED` → ke reviewer saat karyawan ajukan.
- `CONTRACT_REVIEWED` → ke karyawan saat diputuskan.
- `CONTRACT_REVISION_REQUESTED` → ke karyawan saat diminta revisi bukti.

---

## 4. Kelola (HR) — `KontrakKelolaPage`

Mirror `OrientasiKelolaPage`, 1 tab (atau 2: "Aktif" + "Riwayat"):
- 1 baris per karyawan kategori KONTRAK dengan `currentContract`: nama/NIP, kontrak berjalan
  (start–end + sisa hari), status pengajuan terakhir, keputusan, aksi (link ke detail).
- Riwayat: pengajuan `selesai`/`ditolak` lama.

Nav admin: resource `ContractSubmissionResource` (read-mostly, pantau) masuk grup
**"Kontrak & Orientasi"** yang sudah ada.

---

## 5. Perubahan kode (ringkas)

### Migration
- `create_contract_submissions_table` + `create_contract_submission_documents_table` (§1).

### Model
- `App\Models\ContractSubmission` — belongsTo `user`, `userContract` (induk), `company`,
  `decidedBy`, `newContract`. hasMany `documents`. Casts tanggal.
- `App\Models\ContractSubmissionDocument` — belongsTo `submission`. fillable `label`, `path`, `original_name`.
- `App\Models\User\UserContract` — tambah `submissions(): HasMany(ContractSubmission)`.
- `SikartapReviewer` — konstanta `CONTEXT_CONTRACT`.

### Service
- `App\Services\ContractExtensionService` (mirror `OrientationService` minus skor):
  Method: `requiredDocuments()` / `saveRequiredDocuments()` (Setting `contract_extension_documents`,
  pola `criteria()`/`saveCriteria()`), `reviewersFor`, `canReview`, `submit` (guard tiap dokumen
  `required` ada barisnya), `review` (isi decision, TANPA baris skor), `requestRevision`,
  `approve` (bikin `user_contracts`), `cancel`.
- `UserTransitionService::resolveContractEndDate()` — refactor: param opsional
  `?int $monthsOverride = null` (default tetap baca `PERPANJANGAN_KONTRAK_BULAN`). Guard tak berubah.
  Dipanggil dari `ContractExtensionService::approve()` juga.

### Livewire
- `KontrakSayaPage` + blade — portal karyawan (mirror `OrientasiPage`).
- `KontrakDetailPage` + blade — reviewer + (kalau perlu) info (mirror `OrientasiDetailPage`, tanpa grid).
- `KontrakKelolaPage` + blade — HR (mirror `OrientasiKelolaPage`).
- `SikartapSettingsPage` — section "Penilai Perpanjangan Kontrak" (mirror "Penilai Orientasi")
  + section/tab "Dokumen Perpanjangan Kontrak" (CRUD daftar `contract_extension_documents`,
  pola tab Indikator Orientasi: input `label` + checkbox `required` + reorder).
- `PendingApprovalsCard` — blok `contract_submissions` status `diajukan` yang reviewer-nya user ini.

### Notifikasi
- `ContractReviewRequested`, `ContractReviewed`, `ContractRevisionRequested` (copy pola Orientasi).
- `NotificationTypeEnum` + 3 case + label + description.

### Enum
- `UserTransitionTypeEnum::CONTRACT_EXTENSION` — sudah lepas dari `assignable()`. **Hapus** branch
  `CONTRACT_EXTENSION` di `UserTransitionService` (build data + `applyTransitionChanges`) +
  `CreateUserTransitionPage` + `UserTransitionForm` (blok visible-nya) — jadi dead code setelah
  modul ini live. Case enum sendiri **dibiarkan** buat data lama.

### Routes / nav
- `routes/kepegawaian.php`: `/kontrak-saya` (KontrakSayaPage), `/kontrak/kelola` (KontrakKelolaPage),
  `/kontrak/{record}` (KontrakDetailPage) — nama `kontrak.saya` / `kontrak.kelola` / `kontrak.show`.
- Nav portal: link "Kontrak Saya" di menu karyawan (samping "Orientasi Saya"), muncul kalau
  kategori KONTRAK. Nav "Kelola" di dropdown Kepegawaian/Sikartap.

### Test (`tests/Feature/...`)
1. Resolver context `contract` nemu reviewer by jabatan + fallback parent.
2. `submit` tolak kalau reviewer kosong / di luar jendela pengajuan.
3. `review` `perpanjang` → bikin `user_contracts` baru dgn tanggal benar; `contract_submissions.status=selesai`, `new_contract_id` keisi.
4. `approve` lempar kalau `MAX_KONTRAK_TAHUN` terlewati.
5. `requestRevision` balik ke `draft` + note; `submit` ulang bersihkan `decision_*`.
6. `tidak_perpanjang` → `status='ditolak'`, tak ada `user_contracts` baru.
7. `submit` tolak kalau dokumen `required` (dari config) belum lengkap; dokumen non-required boleh kosong.

---

## 6. Urutan eksekusi

1. Migration `contract_submissions` + `contract_submission_documents`.
2. Model `ContractSubmission` (+`documents`), `ContractSubmissionDocument`,
   `UserContract::submissions()`, `SikartapReviewer::CONTEXT_CONTRACT`.
3. `resolveContractEndDate()` refactor (param `$monthsOverride`).
4. `ContractExtensionService` (termasuk `requiredDocuments()`/`saveRequiredDocuments()`)
   + test resolver/approve/guard/dokumen-wajib.
5. `NotificationTypeEnum` + 3 notifikasi.
6. `KontrakSayaPage` + blade (portal karyawan) — form slot dokumen dinamis.
7. `KontrakDetailPage` + blade (reviewer + minta revisi).
8. `KontrakKelolaPage` + blade (HR) + `ContractSubmissionResource` admin.
9. `SikartapSettingsPage` section "Penilai Perpanjangan Kontrak" + "Dokumen Perpanjangan Kontrak".
10. `PendingApprovalsCard` blok kontrak.
11. Routes + nav portal.
12. Hapus dead code `CONTRACT_EXTENSION` di `UserTransitionService` / `CreateUserTransitionPage` / `UserTransitionForm`.
13. Test end-to-end (§5).

---

## 7. Di luar scope

- Kontrak → Tetap (Sikartap `pengajuan_karyawan_tetap`) — modul terpisah.
- Penilaian kinerja terstruktur — `assessment_*`.
- Approval berjenjang / Approval Engine — sengaja dilepas.
- Auto-nonaktifkan karyawan saat `tidak_perpanjang` / kontrak habis — proses manual/terpisah.
- Migrasi data legacy `another-app/sikartap` `pengajuan_perpanjangan_kontrak` — tidak diporting
  (sistem lama beda DB, mulai bersih).
- Surat kontrak hasil (file PDF kontrak baru) di `user_contracts.file_path` — belakangan
  (sama seperti orientasi followUpContract, ditunda).
- Reminder "kontrak mau habis" ke HR — belakangan (sama kayak reminder orientasi, di-defer).
