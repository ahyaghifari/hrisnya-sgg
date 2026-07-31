# Analisis Modul Assessment (Penilaian Karyawan) — 2026-08-01

Dokumen ini merangkum audit modul "Penilaian Karyawan" (`app/Filament/Clusters/Assessment/`) yang dilakukan lewat percakapan eksplorasi kode — belum ada perubahan kode dari sesi ini, murni pemahaman state saat ini + gap yang ditemukan, supaya sesi berikutnya (portal, KPI engine, dst) tidak perlu menggali ulang.

## Ringkasan (kalau cuma sempat baca 1 bagian)

1. Modul ini **cuma bisa diakses lewat admin panel Filament** (`/admin`) — tidak ada satu pun halaman portal karyawan untuk melihat/mengisi penilaian.
2. Kerangka datanya sudah benar secara konsep (`Types → Templates → Items berbobot → Assessments (instance) → AssessmentUsers → AssessmentScores`), tapi implementasinya **~60% jalan** — beberapa bagian skema disiapkan tapi tidak disambung ke logic/UI.
3. **`AssesmentTypeResource` (perhatikan typo — "Assesment" satu-s, beda nama class dari `AssessmentResource`) setengah jadi**: terdaftar di cluster tapi `$shouldRegisterNavigation = false` (tidak muncul di sidebar) dan route `create`/`edit` di-comment out di `getPages()` — cuma bisa lihat list, tidak bisa tambah/edit tipe penilaian dari UI sama sekali. Satu-satunya sumber data `AssessmentTypes` sekarang adalah `database/seeders/AssessmentTypeSeeder.php` (3 tipe hardcode: Performance Appraisal, Culture Assessment, Competency Assessment).
4. **Tidak ada alur approval/submit** — kolom `AssessmentUsers.status` (default `'draft'`, komentar migrasi bilang draft/submitted/reviewed) dan `submitted_at` ada di skema tapi **tidak pernah di-set dari UI manapun**. Begitu atasan isi nilai dan simpan, langsung terhitung final dan field terkunci (disabled) — tidak ada tahap submit-lalu-direview.
5. **`AssessmentRatingScales`** (skala kategori custom per company/tipe) ada tabelnya tapi **tidak dipakai** oleh `AssessmentService` — kategori "Sangat Baik/Baik/Cukup/Kurang/Sangat Kurang" hardcoded pakai threshold tetap (≥90/≥80/≥70/≥60), tidak bisa disetel beda per company.
6. Item template bertipe `MCQ`/`SIMPLE_SCALE` (dari enum `AssessmentTemplateItemType`) **tidak render sama sekali** di form pengisian — form cuma punya 2 repeater: satu untuk item `type IS NULL` (generik) dan satu untuk `type = orientation`. Kalau HC kasih item tipe MCQ/SIMPLE_SCALE, item itu hilang dari kedua repeater.
7. `final_score`/`final_category` yang dihasilkan **tidak dikonsumsi modul manapun** — tidak nyambung ke Payroll, Coaching, atau modul lain. Berdiri sendiri.

## 1. Struktur Data

```
AssessmentTypes (assessment_types)
  └─ AssessmentTemplates (assessment_templates) — company-scoped
       └─ AssessmentTemplateItems (assessment_template_items) — indikator/pertanyaan, weight_percent, target_score, max_score, type (nullable/orientation/mcq/simple_scale), metadata (json)

Assessments (assessments) — "instance" penilaian, company-scoped, punya template_id + job_position_id (opsional) + jadwal (start_date/end_date) + status (draft/active/dst, string bebas)
  └─ AssessmentUsers (assessment_users) — 1 baris per karyawan yang dinilai dalam 1 Assessment. TIDAK punya kolom company_id sendiri — scoping company via relasi ke assessments.company_id (global scope custom di model, di-skip untuk role Super Admin)
       └─ AssessmentScores (assessment_scores) — 1 baris per template_item per assessment_user, nilai aktual + weighted_score
```

Relasi `AssessmentUsers::assessments()` **hasOne** ke `Assessments` (nama method jamak/salah eja tapi hasOne, bukan belongsTo — perhatikan kalau baca kode terkait).

## 2. Alur end-to-end (kondisi sekarang)

1. HC bikin `AssessmentTemplates` lewat menu "Form Template" — pilih tipe, isi item-item (indikator, bobot%, target, skor max) lewat relation manager `TemplateItemsRelationManager`.
2. HC/Manager bikin `Assessments` (instance, mis. "Penilaian Kinerja Q3 2026") — pilih template, jadwal, opsional target `job_position_id`.
3. Dari halaman detail Assessment, tab "Daftar Karyawan Dinilai" (`UserAssessmentsRelationManager`) → action "Buat Penilaian Karyawan" → pilih karyawan → `AssessmentUsers::create(['assessment_id'=>..,'target_id'=>..,'created_by'=>Auth::id()])` (catatan: **pakai `create()` polos, tidak ada unique constraint** di migration — kalau tombol diklik 2x, bisa numpuk baris duplikat untuk target+assessment yang sama) → redirect ke halaman edit penilaian.
4. `EditUserAssessment::mutateFormDataBeforeFill()` panggil `ensureAssessmentScoresExist()` (private method di Page, **bukan Service**) — `firstOrCreate` baris `AssessmentScores` kosong untuk tiap `AssessmentTemplateItems` di template terkait, supaya repeater form selalu punya baris sejumlah item template.
5. Atasan/HC isi nilai per indikator lewat 2 repeater (`scores` untuk item generik, `orientation_scores` untuk item tipe `orientation`) — field `score` **disabled kalau `$record->final_score !== null`** (sudah pernah dihitung sebelumnya).
6. `EditUserAssessment::afterSave()` panggil `AssessmentService::calculateUserAssessmentScore($assessmentUserId)`:
   - Loop tiap `AssessmentTemplateItems`, ambil `AssessmentScores` terkait, **skip item kalau `score` masih null** (tidak dihitung sebagai 0, item itu keluar dari total_weight juga).
   - `normalizedScore = (score / max_score) * 100`, `weightedScore = normalizedScore * weight_percent / 100` (weight_percent diperlakukan sebagai skala 0–100, bukan fraksi 0–1).
   - `final_score = sum(weightedScore)` (dibulatkan 2 desimal), `final_category` dari threshold hardcoded.
   - Update `AssessmentUsers.final_score`/`final_category`, dibungkus `DB::transaction`.
7. Notifikasi hasil ditampilkan sebagai toast Filament — **tidak ada notifikasi ke karyawan yang dinilai**, dan **karyawan tidak bisa lihat hasilnya sama sekali** (tidak ada halaman portal).

## 3. File-file kunci

| Lapisan | File |
|---|---|
| Cluster | `app/Filament/Clusters/Assessment/AssessmentCluster.php` |
| Resource: Tipe Penilaian | `app/Filament/Resources/Assessment/AssesmentTypes/AssesmentTypeResource.php` (typo nama, cuma 1 "s") |
| Resource: Form Template | `app/Filament/Resources/Assessment/AssessmentTemplates/AssessmentTemplateResource.php` |
| Resource: Assessment (instance) | `app/Filament/Resources/Assessment/Assessments/AssessmentResource.php` |
| Resource: User Assessment | `app/Filament/Resources/Assessment/UserAssessments/UserAssessmentResource.php` (`$shouldRegisterNavigation = false`) |
| Form pengisian nilai | `app/Filament/Resources/Assessment/UserAssessments/Schemas/UserAssessmentForm.php` |
| Page edit + kalkulasi | `app/Filament/Resources/Assessment/UserAssessments/Pages/EditUserAssessment.php` |
| Relation manager pembuat AssessmentUsers | `app/Filament/Resources/Assessment/Assessments/RelationManagers/UserAssessmentsRelationManager.php` |
| Service kalkulasi skor | `app/Services/Assessment/AssessmentService.php` (satu method: `calculateUserAssessmentScore()`) |
| Repository | `app/Repositories/Assessment/AssessmentRepository.php` — sangat tipis, `updateUserAssessmentScores()` malah tidak dipanggil di alur aktif manapun |
| Enum tipe item | `app/Enum/AssessmentTemplateItemType.php` (`MCQ`, `ORIENTATION`, `SIMPLE_SCALE`) |
| Models | `app/Models/Assessment/{Assessments,AssessmentUsers,AssessmentScores,AssessmentTemplates,AssessmentTemplateItems,AssessmentTypes,AssessmentRatingScales}.php` |
| Seeder tipe (satu-satunya sumber data Types sekarang) | `database/seeders/AssessmentTypeSeeder.php` |
| Mapping role → menu | `planning/2026-07-05-pemetaan-menu-ke-role.md` (baris ~40): HC Manager full akses termasuk atur template; Manager/Kepala Bidang & Supervisor view+create+edit (mengisi penilaian bawahan sendiri); HC Supervisor cuma view. |

## 4. Tiga tipe penilaian yang ada — bukan modul terpisah, cuma kategori beda tujuan

Ketiganya pakai kerangka Template→Item→Skor yang sama, bedanya cuma apa yang dinilai:

- **Performance Appraisal** — hasil kerja terhadap target (mis. "Pencapaian target bulanan", "Ketepatan waktu laporan"). Biasanya periodik (kuartal/tahun), dibandingkan target yang disepakati di awal.
- **Culture Assessment** — perilaku/sikap kerja sesuai nilai perusahaan (mis. "Integritas", "Kolaborasi", "Kepatuhan SOP"). Subjektif, observasi atasan.
- **Competency Assessment** — kemampuan/skill sesuai level jabatan (mis. "Problem-solving", "Leadership"). Biasanya dipakai untuk keputusan promosi/training-gap, bukan cuma evaluasi rutin.

Pola umum HRIS: ketiganya sering digabung jadi satu paket tahunan dengan bobot gabungan, atau dijalankan terpisah sesuai tujuan (Performance tiap kuartal untuk bonus, Competency tahunan untuk promosi). Skema sistem ini sudah mendukung itu karena tiap `AssessmentTemplates` terikat 1 `type_id`.

## 5. JANGAN disamakan dengan 3 modul lain yang kebetulan juga pakai kata "assessment"

- **`App\Models\LMS\Trainer\TrainerAssessmentResults`** + **`LmsMeetingHonorariumAssessment`** — soal hitung honor trainer LMS per pertemuan, bagian dari cluster Honorarium (lihat memori `[[lms-honorarium-cluster-lama-belum-boleh-dihapus]]`). Sama sekali beda domain dari Penilaian Karyawan.
- **`App\Models\Candidate\CandidateSelfAssessments`** — self-assessment kandidat rekrutmen (strengths/weaknesses/satisfaction), bagian modul Recruitment.

Tidak ada overlap kode/tabel di antara ketiganya dengan modul Penilaian Karyawan — kebetulan sama-sama pakai kata "assessment".

## 6. Gap prioritas kalau mau dilengkapi (urutan disarankan)

1. **`AssesmentTypeResource`** — aktifkan `create`/`edit` di `getPages()`, hapus `$shouldRegisterNavigation = false`. Blocker paling dasar: tanpa ini HC terkunci selamanya di 3 tipe seeder.
2. **Visibility karyawan** — lihat detail di `[[planning-portal-penilaian-karyawan]]`, sudah didesain sebagai tab baru di `EmployeeAttendanceStatistics` (`/data-diri` + `/my-staff/{id}`).
3. **Atasan isi form dari portal** (bukan admin panel) — juga di dokumen planning yang sama.
4. Alur submit/acknowledge (pakai kolom `status` yang menganggur) — sengaja BELUM masuk scope planning portal pertama (lihat catatan "yang tidak dikerjakan" di dokumen planning), user secara eksplisit belum minta ini dulu.
5. Sambungkan `AssessmentRatingScales` ke `AssessmentService::determineScoreCategory()` — supaya kategori bisa disetel per company/tipe.
6. Render item `MCQ`/`SIMPLE_SCALE` di form (ada placeholder comment di `UserAssessmentForm.php` yang belum diisi).

## 7. Diskusi arah besar: KPI otomatis lintas modul (belum diputuskan, jangan diasumsikan sebagai rencana resmi)

User menggambarkan visi jangka panjang: kejadian di modul lain (mis. pola keterlambatan di Attendance) otomatis memicu SP (Surat Peringatan) → notifikasi HR/atasan/karyawan → training → nambah/kurangi poin KPI, dengan Assessment jadi salah satu input skor.

**Fakta penting**: sistem sekarang **tidak punya** satupun dari komponen itu — tidak ada model/fitur SP sama sekali (`grep` untuk "Surat Peringatan"/"SP1"/dst nihil), tidak ada Observer/event listener otomatis dari Attendance ke modul lain, tidak ada "KPI Score" ledger terpusat. Assessment, Coaching, dan Payroll (calculator Keterlambatan) semuanya berdiri sendiri-sendiri.

Kesimpulan diskusi (belum jadi keputusan resmi, murni arah pemikiran):
- Struktur data Assessment (`Template → Item berbobot → final_score`) **sudah berbentuk KPI-weighted-scoring** — cocok jadi salah satu *input* ke KPI ledger terpusat nanti, bukan perlu dirombak.
- Kalau KPI engine dibangun, pola yang masuk akal: Assessment = komponen periodik/terjadwal, sedang SP/pelanggaran = komponen event-driven/kontinu — keduanya menulis ke satu ledger yang sama, tapi masing-masing modul tetap independen (tidak saling tahu satu sama lain).
- **Rekomendasi**: jangan bangun KPI engine dulu sebelum keputusan bisnis matang (aturan SP, bobot tiap komponen, dll). Prioritas realistis: benahi gap Assessment yang berdiri sendiri (§6) dulu — itu langsung berguna dan tidak akan sia-sia kalau KPI engine dibangun belakangan, karena posisinya jadi "input", bukan "digantikan".

## 8. Ide lain yang sempat dibahas: jembatan Task ("Penugasan Individu") → Assessment

User menyebut inspirasi Trello/Lark dan bertanya apakah `TaskRequestResource` ("Penugasan Individu", model `App\Models\Task\Task` di tabel `task_records` — **BUKAN** Kanban board `TaskProject`, ini sistem tiket layanan internal dengan `service_id`/`department_id`/`due_date`/`completed_date`/`risk_level`/`total_cost`) bisa dijembatani ke indikator Assessment yang melekat ke jabatan (contoh: jabatan Rekrutmen → indikator "Pelaksanaan Rekrutmen" → sub-metrik durasi, jumlah pelamar).

Temuan:
- `AssessmentTemplates` model punya relasi `jobPosition()` di kode **tapi kolomnya tidak ada di migration** (dead code) — indikasi fitur "template per jabatan" pernah direncanakan tapi tidak dituntaskan. Kalau mau eksekusi ide ini, ini titik awal paling murah: tambah kolom `job_position_id` di `assessment_templates` sungguhan.
- `TaskService` (`m_task_services`, label "Layanan Penugasan") cuma `name` + `company_id` — sekadar kategori teks, tidak ada tempat nyimpen metrik tambahan (target durasi, dst).
- Metrik seperti durasi (`completed_date` - `started_date`) dan status overdue **sudah bisa dihitung otomatis dari kolom yang ada sekarang**, tanpa migrasi tambahan. Tapi metrik spesifik domain seperti "jumlah pelamar" **bukan data Task** — itu ranah modul Recruitment/Candidate, beda sumber data sama sekali.
- Rekomendasi bertahap (belum dieksekusi, murni catatan arah): Tahap 1 — tambah field opsional "Task terkait" di form pengisian skor Assessment supaya atasan lihat data pendukung (bukti), skor tetap diisi manual. Tahap 2 (kalau Tahap 1 terbukti berguna) — baru bangun agregasi otomatis per `TaskService` jadi usulan skor yang tetap perlu di-approve atasan, bukan langsung final otomatis.

**Status: murni ide, belum ada keputusan untuk dieksekusi.** Dicatat di sini supaya tidak hilang, bukan sebagai rencana yang siap jalan.
