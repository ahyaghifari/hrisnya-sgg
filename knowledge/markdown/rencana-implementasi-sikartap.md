# Rencana Implementasi Sikartap ke HRIS — 2026-08-25

Lanjutan dari `knowledge/markdown/analisis-sikartap-migrasi.md` (analisis kode legacy + temuan red flag). Dokumen ini fokus pada urutan eksekusi implementasi — belum ada kode dieksekusi.

**Prinsip dasar (sudah diputuskan)**: bukan port kode legacy, tapi rewrite modul baru pakai pola arsitektur HRIS existing (Approval Engine, `User` model, Laravel Notification). Sikartap cuma referensi business-logic.

**Database**: satu DB yang sama dengan HRIS (bukan DB terpisah) — reuse Approval Engine/`users`/notifications butuh FK join langsung, DB terpisah bikin semua itu jadi cross-DB query/API call. Pisah cukup di level tabel/model/namespace.

**Konvensi nama tabel**: prefix `sikartap_`, ikut pola existing di codebase ini (`lms_*`, `kanban_*`, `m_*` untuk master).

## 0. Blocker — wajib selesai sebelum coding dimulai

1. ~~3 file source hilang dari snapshot~~ — **SELESAI 2026-08-25**. `verifikasi_berkas.php`, `verifikasi_ide_inovasi.php`, `unit_kategori.php` sudah didapat & dianalisis (lihat `analisis-sikartap-migrasi.md` § F, update 2026-08-25). Temuan penting: **alur berjenjang ide inovasi (Kabag→Komisaris→Direktur) tidak pernah benar-benar berfungsi** — `dashboard_verifikator_inovasi.php` link ke `verifikasi_ide_inovasi.php` buat semua role, tapi file itu cuma terima role `hrd`/`direktur`, jadi Kabag/Kabid/Komisaris kena dead-end redirect. Tidak ada logic kerja yang bisa direferensikan buat tier itu — desain Fase 5 murni dari nol.
2. **Dump DB ketinggalan zaman** — `database/db_pengajuan_karyawan.sql` (1 Des 2025) tidak punya tabel MCU per-role, kolom diskualifikasi, kolom ide-inovasi berjenjang yang dipakai kode aktif. Perlu dump production terkini.
3. **FK ke `users_backup2`** — semua FK di dump mengarah ke tabel backup, bukan `users(id)`. Kemungkinan bug ekspor phpMyAdmin, tapi wajib diverifikasi di DB live sebelum desain skema baru final.
4. **Rotate kredensial** — DB password (`config.php`), SMTP password (`email_config.php`), kredensial terpisah di `import_wilayah_indonesia.php`. Zip 112MB di root sikartap kemungkinan berisi kredensial produksi asli.

Sisa blocker (item 2-4) masih butuh akses server produksi — belum bisa mulai desain skema `sikartap_innovation_ideas`/`sikartap_mcu_*` final sebelum itu kelar. Fase 1 (`sikartap`) sudah bisa jalan duluan (lihat progres di bawah).

## 1. Cek Konflik dengan Modul Existing (sebelum desain skema)

- **Perpanjangan Kontrak**: **diluar scope migrasi** (keputusan user). HRIS sudah punya `user_contracts` + `app/Livewire/Kepegawaian/KontrakPage.php` — alur `pengajuan_perpanjangan_kontrak`/`dokumen_perpanjangan` legacy Sikartap tidak diport sama sekali, tidak perlu dibandingkan lagi.
- **Master data wilayah**: sudah dicek — HRIS **tidak punya** tabel provinsi/kabupaten/kecamatan/kelurahan sama sekali. Modul `Candidates` existing (`candidates` table) menangani alamat pelamar cuma pakai kolom `text` bebas (`ktp_address`, `domicile_address`), tanpa tabel wilayah berjenjang. **Keputusan: jangan bikin tabel wilayah.** Data wilayah legacy Sikartap terduplikasi masif (bukti `import_wilayah_indonesia.php` dijalankan berulang tanpa dedup) dan tidak ada modul lain di HRIS yang butuh reporting per-wilayah — pakai kolom `text` di `sikartap` ikut pola `candidates`. Kalau nanti ada kebutuhan nyata (reporting per-wilayah), baru ditambah — bukan keputusan mahal untuk ditunda.
- **Approval Engine**: sudah dicek — tabel `approval_activities`, `approval_histories`, `approval_snapshots`, `m_approval_flows`/`m_approval_flow_steps` sudah ada. Baca `knowledge/markdown/manajemen-persetujuan.md` untuk pola `ApprovalActivities`/`ApprovalModuleEnum`/`scope_type`, supaya alur berjenjang Sikartap (berkas→tes→wawancara→MCU→direktur, dan Kabag→Komisaris→Direktur untuk ide inovasi) tinggal nambah module baru di enum, bukan bikin tabel approval sendiri.
- **Notifikasi**: sudah dicek — tabel `notifications` (Laravel native) + `user_notification_preferences` sudah ada. Port 11 jenis notifikasi Sikartap jadi Notification classes.
- **Attachment/dokumen**: HRIS **tidak punya** 1 tabel Media polymorphic generik — pola yang dipakai per-domain: `task_attachments`, `candidate_documents`, `user_documents`. Ikut pola ini: `sikartap_document_attachments` per-domain, bukan bikin sistem Media baru.

- **Filament Resources (sisi admin/HRD)**: sebelumnya belum masuk plan — HRIS pakai Filament (`app/Filament/Resources`), modul sekelas (LMS) punya `app/Filament/Resources/LMS`. Legacy Sikartap punya sisi internal-staff (`dashboard_hrd.php`, `dashboard_direktur.php`, `periode_pengajuan.php`, `bulk_keputusan_direktur.php`) yang lebih pas jadi **Filament Resource/Page** (`app/Filament/Resources/Sikartap`), bukan Livewire portal — portal Livewire cuma buat sisi pelamar/karyawan (upload berkas, submit ide inovasi, cek status). HRD/Direktur/Kabag/Komisaris pantau & putuskan lewat Filament, sama seperti admin lain di HRIS.

## 2. Desain Skema (setelah blocker §0 kelar)

Struktur folder terisolasi: `app/Models/Sikartap`, `app/Services/Sikartap`, `app/Livewire/Sikartap` (portal pelamar/karyawan), `app/Filament/Resources/Sikartap` (sisi HRD/Direktur/Kabag/Komisaris).

**Routing**: `routes/sikartap.php` — **UPDATE 2026-08-25**, atas permintaan eksplisit user, `require __DIR__ . '/sikartap.php'` dipanggil dari dalam grup middleware `auth`+`portal.access` di `routes/web.php` (jadi rute di dalamnya tetap kena middleware yang sama). Ini nyimpang dari konvensi kodebase (semua modul lain termasuk LMS ada di satu `web.php`, tidak ada split route per-modul di manapun) — sengaja didokumentasikan di sini supaya kalau nanti ada yang heran kenapa cuma Sikartap yang beda, jawabannya ada di sini, bukan bug/inkonsistensi tak sengaja.
- Halaman portal yang butuh permission/kondisi spesifik (bukan cuma login) didaftarkan sebagai entry baru di `config/portal-pages.php` (field `permission`/`guard`) — dicek otomatis oleh middleware `EnsurePortalPageAccess`, bukan ditulis manual di `mount()` komponen.
- Sisi Filament tidak lewat `web.php`/`portal-pages.php`/`routes/sikartap.php` — resource terdaftar sendiri via Filament panel provider.

Tabel domain-specific baru (prefix `sikartap_`, lihat §1 untuk konfirmasi):
- `sikartap` (`Sikartap`) — ganti state-machine "banyak kolom status enum" (`status_berkas`/`status_tes`/`status_wawancara`/`status_mcu`/`status_hasil_direktur`/`status_ide_inovasi`) dengan workflow eksplisit (`spatie/laravel-model-states` atau tabel riwayat approval terpisah) supaya `get_keputusan_akhir()` yang terduplikasi 2 file di legacy jadi satu sumber kebenaran. Kolom alamat pakai `text` bebas (ikut pola `candidates.ktp_address`/`domicile_address`), bukan FK ke tabel wilayah.
- `sikartap_document_attachments` (`DokumenAdministratif`) + status approve/reject per dokumen — ikut pola attachment per-domain existing (`task_attachments`, `candidate_documents`) + policy-based auth (perbaiki IDOR §0 legacy: legacy cuma cek login, bukan kepemilikan file, nama file predictable).
- `sikartap_test_schedules`, `sikartap_interview_schedules`, `sikartap_mcu_schedules`.
- `sikartap_mcu_nurse_assessments`, `sikartap_mcu_lab_results`, `sikartap_mcu_radiology_results`, `sikartap_mcu_doctor_examinations` (dokter = gate final fit/unfit).
- `sikartap_innovation_ideas` (`IdeInovasi`) — approval berjenjang Kabag/Kabid unit asal → Komisaris → Direktur, reuse Approval Engine.
**Tidak dibuat** (keputusan user): `sikartap_contract_extensions` — perpanjangan kontrak Sikartap **di-skip sepenuhnya**, cukup `user_contracts`/`KontrakPage.php` existing. Scope Sikartap cuma alur pengajuan karyawan tetap + ide inovasi.

**Tidak dibuat** (lihat §1): tabel wilayah (provinsi/kabupaten/kecamatan/kelurahan) — pakai kolom `text` bebas.

Reuse, jangan duplikat:
- `users`/`user_profiles` — jangan bikin model User/karyawan versi Sikartap sendiri.
- `approval_activities`/`approval_histories`/`m_approval_flows` existing untuk semua alur berjenjang.
- `notifications` (Laravel native) menggantikan `email_queue` custom — port 11 jenis notifikasi (`queue_*_email()` di legacy `email_queue_functions.php`) jadi Notification classes.

## 3. Urutan Fase Implementasi (usulan)

1. **Fase 0 — Prasyarat**: selesaikan blocker §0, cek konflik §1.
2. **Fase 1 — Pengajuan Karyawan Tetap inti** (progres 2026-08-25):
   - ✅ Model `Sikartap` + tabel `sikartap` (state machine per-tahap, `isEligibleForDirectorDecision()` satu sumber kebenaran).
   - ✅ Model `SikartapDocumentAttachment` + tabel `sikartap_document_attachments`, enum `App\Enum\Sikartap\SikartapDocumentType` (11 jenis dokumen, 6 wajib).
   - ✅ Filament Resource `app/Filament/Resources/Sikartap` (list + view, read-only, sisi HRD/Direktur) — Policy+permission Shield digenerate (`ViewAny/View/Create/Update/Delete/Restore/ForceDelete:Sikartap`), `canAccess()` digerbangi permission bukan role literal supaya HRD/Direktur bisa diberi akses tanpa harus jadi role Admin.
   - ✅ Livewire portal `app/Livewire/Sikartap/PengajuanKaryawanTetapPage.php` (route `/sikartap`) — mulai pengajuan + upload per dokumen. Fix IDOR legacy: file disimpan disk `public` dengan nama file random bawaan Laravel `store()` (bukan `user_id+field+time()` yang predictable di legacy), ikut pola `DocumentFormPage`/`KontrakPage` existing. Reupload dokumen yang ditolak otomatis reset status ke `pending`. Terkunci begitu `status_berkas = approved`.
   - ✅ Test smoke `tests/Feature/Sikartap/PengajuanKaryawanTetapPageTest.php` (3 test, semua pass).
   - ✅ Aksi verifikasi HRD di Filament (rewrite `verifikasi_berkas.php`) — **FASE 1 SELESAI**:
     - `app/Filament/Resources/Sikartap/RelationManagers/DocumentsRelationManager.php` — approve/reject per dokumen (BUKAN lewat Approval Engine, lihat analisis di bawah), gate `Update:Sikartap`.
     - `app/Filament/Resources/Sikartap/Pages/ViewSikartap.php` — action "Setujui Semua & Lanjut Proses" (gate `Sikartap::hasAllRequiredDocumentsUploaded()`) & "Tolak Pengajuan" (catatan wajib, semua dokumen terupload ikut ditolak) — port `verifikasi_berkas.php::$is_complete`/approve-all/reject-all.
     - `Sikartap::hasAllRequiredDocumentsUploaded()` model helper baru.
     - Test `tests/Feature/Sikartap/SikartapResourceTest.php` (5 test, semua pass) — cakupan: approve/reject per dokumen, gate dokumen wajib, approve-all, reject-all.
3. **Fase 2 — Tes & Wawancara** — **SELESAI 2026-08-25** (rewrite Tahap 2/3 `detail_pengajuan.php`):
   - ✅ Tabel `sikartap_test_schedules`/`sikartap_interview_schedules` (1:1 per pengajuan, sama seperti `jadwal_tes`/`jadwal_wawancara` legacy) + kolom hasil (`nilai_tes`/`nilai_psikotes`/`catatan_tes`/`catatan_wawancara`) langsung di tabel `sikartap` (bukan tabel terpisah — 1 kejadian per pengajuan, sama pola legacy `pengajuan_karyawan_tetap`).
   - ✅ Model `SikartapTestSchedule`/`SikartapInterviewSchedule` + relasi `hasOne` di `Sikartap`.
   - ✅ 4 action di `ViewSikartap.php`: `scheduleTest`/`submitTestResult`/`scheduleInterview`/`submitInterviewResult`, gate berurutan sama seperti legacy (jadwal tes cuma muncul kalau `status_berkas=approved`, hasil tes cuma muncul kalau jadwal ada, jadwal wawancara cuma muncul kalau `status_tes=lulus`, dst).
   - ✅ Kolom status (`status_tes`/`status_wawancara`) tetap polos — gate hasil entri data domain-spesifik, bukan keputusan berjenjang, jadi bukan kandidat Approval Engine (lihat analisis § Approval Engine di bawah).
   - ✅ Test `tests/Feature/Sikartap/TesWawancaraTest.php` (3 test, semua pass) — cakupan gate urutan + simpan jadwal/hasil.
   - ⚠️ Simplifikasi sengaja: form penilaian wawancara detail per-kriteria (`penilaian_wawancara`/`form_penilaian_wawancara.php` legacy, ada `total_nilai` breakdown beda per kategori nakes/non-nakes) **tidak diport** — cuma status akhir (`disarankan`/`tidak_disarankan`) + catatan bebas. Legacy sendiri gate keputusan cuma pakai `status_wawancara`, breakdown nilai cuma pendukung tampilan. Tambah kalau nanti user minta breakdown-nya balik.
4. **Fase 3 — MCU paralel** — **SELESAI 2026-08-25** (rewrite `form_asesmen_perawat.php`/`form_hasil_laboratorium.php`/`form_hasil_radiologi.php`/`form_pemeriksaan_dokter.php` legacy). Sesi ini dimulai dari sisa kerja remote-mobile (model/migration/2 dari 4 Livewire page sudah ada tapi belum lengkap — 3 blade view hilang, 0 route terdaftar, tidak ada gate urutan, tidak ada cara HRD memutuskan `status_mcu` final, tidak ada test):
   - ✅ 4 Livewire page portal internal per-role (`app/Livewire/Sikartap/Mcu/*`) — perawat/lab/radiologi/dokter, bukan Filament (input cepat staff klinis di lapangan, sama alasan Fase 1-2 dokumen upload pakai Livewire bukan Filament). Ditambah gate `status_wawancara === 'disarankan'` di `mount()` tiap page (sebelumnya cuma cek permission, MCU bisa diisi sebelum wawancara lulus).
   - ✅ 3 blade view yang hilang dibuat (`hasil-laboratorium-page`, `hasil-radiologi-page`, `pemeriksaan-dokter-page`) — cuma `asesmen-perawat-page.blade.php` yang sempat ada, 3 lainnya akan fatal `view not found` kalau dipanggil.
   - ✅ Route `/sikartap/mcu/{sikartap}/{perawat,laboratorium,radiologi,dokter}` + `/sikartap/mcu` (antrian) didaftarkan di `routes/sikartap.php` — sebelumnya 0 route, halaman-halaman ini tidak bisa diakses sama sekali.
   - ✅ `McuQueuePage` (`/sikartap/mcu`) — pengganti 4 dashboard staff legacy (`dashboard_staff_lab.php`/`dashboard_staff_perawat.php`/`dashboard_staff_radiologi.php`/`dashboard_dokter.php`) yang disatukan 1 halaman, filter `status_wawancara=disarankan` & `status_mcu=pending`, tombol per form cuma tampil sesuai permission user.
   - ✅ `config/portal-pages.php` — 5 entry baru buat 4 form + antrian, gate `User::canInputSikartapMcu()` (OR dari 4 permission) buat halaman antrian.
   - ✅ Nav `Kepegawaian` dropdown: link "Tetap (Sikartap)" yang tadinya placeholder "Segera" diaktifkan ke `route('sikartap')`, + link baru "Antrian MCU" (gated `canInputSikartapMcu()`).
   - ✅ `submitMcuDecision` action di `ViewSikartap.php` — HRD memutuskan `status_mcu` fit/unfit final (bukan otomatis dari rekomendasi dokter, sama seperti legacy `$rek_dokter` cuma saran), digerbangi `mcuDoctorExamination` sudah diisi.
   - ✅ Test `tests/Feature/Sikartap/McuTest.php` (8 test) — cakupan: gate permission per form, gate urutan (blocked sebelum wawancara disarankan), submit tiap 1 dari 4 form, `submitMcuDecision` blocked sebelum ada pemeriksaan dokter, `isEligibleForDirectorDecision()` jadi true setelah `status_mcu=fit`.
   - ⚠️ Simplifikasi sengaja: **tidak ada tabel `sikartap_mcu_schedules`** (beda dari legacy `jadwal_mcu` dan beda dari rencana awal §2 yang menyebut tabel jadwal). Gate akses MCU cukup `status_wawancara=disarankan` — staff klinis isi kapan saja tanpa perlu dijadwalkan eksplisit dulu, mengikuti pola realistis input cepat di lapangan. Tambah tabel jadwal kalau nanti user minta MCU beneran perlu dijadwalkan (booking slot, dsb).
   - ⚠️ Known gap: link self-service "Tetap (Sikartap)" cuma ada di dropdown nav "Kepegawaian" (konteks HC/atasan, digerbangi `ViewAny:UserContract`) — karyawan biasa yang mau mulai pengajuan sendiri belum punya entry point nav (halaman `/sikartap` sendiri tidak digerbangi portal-pages, bisa diakses langsung kalau tahu URL-nya). Perlu ditambah entry point self-service yang lebih pas (mis. Dashboard) di iterasi berikutnya.
5. **Fase 4 — Keputusan Direktur** — **SELESAI 2026-08-25** (rewrite Tahap 5 `detail_pengajuan_direktur.php`/`bulk_keputusan_direktur.php` legacy). Lewat Approval Engine sepenuhnya, bukan Filament action custom nulis status langsung:
   - ✅ `ApprovalModuleEnum::SIKARTAP_DIRECTOR_DECISION` — module baru, label + notificationLabel.
   - ✅ `Sikartap::approvalSnapshot()`/`approvalHistories()` — relasi 1:1/1:N ke Approval Engine, pola identik `AttendanceRequests`.
   - ✅ `ApprovalFlowService::advanceStep()` — 2 branch baru (reject & approve-final) sync `sikartap.status_keputusan_direktur` ('approved'/'rejected') sebagai kolom cache, satu sumber kebenaran tetap di `approval_snapshots`/`approval_activities` (pola sama Training Request, bukan ditulis dari Filament action).
   - ✅ `app/Services/Sikartap/SikartapDirectorDecisionService.php` — `submitForDecision()` (resolve flow via `resolveApprovalFlowForUser()` keyed job_position PELAMAR bukan HRD yang klik, guard `isEligibleForDirectorDecision()` + belum pernah diajukan sebelumnya), `approve()`/`reject()` (wrap `ApprovalFlowService::advanceStep()`, pola sama `ManPowerReqService`).
   - ✅ 3 action baru `ViewSikartap.php`: `ajukanKeputusanDirektur` (HRD, visible kalau eligible & belum ada snapshot), `approveDirektur`/`rejectDirektur` (siapa saja yang gate `ApprovalPermissionService::canApprove()` meloloskan — biasanya Direktur sesuai flow company, tapi flow-nya configurable dari `/admin/approval-flows` seperti modul lain, bukan role literal "Direktur" di-hardcode).
   - ✅ Test `tests/Feature/Sikartap/DirectorDecisionTest.php` (6 test) — cakupan: submit ditolak kalau belum eligible, submit bikin snapshot, action `ajukanKeputusanDirektur` hidden kalau belum eligible/sudah diajukan, approve/reject via Filament action men-sync `status_keputusan_direktur` & `approvalSnapshot.status`, non-approver tidak lihat tombol keputusan.
   - ⚠️ Catatan debugging: `Livewire::test(ViewSikartap::class, ...)->callAction(...)` awalnya gagal misterius (`ErrorException: Attempt to read property "mountedActions" on null`) — ternyata bukan bug kode, tapi user test (`$direktur`) belum dikasih permission `ViewAny:Sikartap`/`View:Sikartap` (`SikartapResource::canAccess()` gate), beda dari izin approve itu sendiri yang dicek terpisah lewat `ApprovalPermissionService::canApprove()`. `mountAction()`/`callAction()` Filament melakukan full request lifecycle (termasuk cek `canAccess()` resource), beda dari render awal `Livewire::test()` yang tidak selalu menjalankan authorize-nya — kalau ada pola serupa lagi di modul lain, cek dulu permission akses resource-nya sebelum curiga bug action.
6. **Fase 5 — Ide Inovasi berjenjang** — **SELESAI** (dikerjakan di commit `f6f303e`, dokumen ini baru direkonsiliasi 2026-08-26 — implementasi menyimpang dari rencana awal, lihat catatan di bawah):
   - ✅ Tabel `sikartap_ide_inovasis` (bukan `sikartap_innovation_ideas` seperti nama draft awal) — 1:1 per `sikartap`, kolom `status_atasan`/`status_direktur`/`status_komisaris` + rubrik skor atasan (`skor_identifikasi_masalah`/`skor_analisis_masalah`/`skor_kualitas_inovasi`/`skor_potensi_manfaat`/`skor_presentasi`, bobot beda per kriteria, `SikartapIdeInovasi::hitungNilaiAkhir()`/`klasifikasiDariNilai()`, ambang lulus 75/100).
   - ✅ Tabel `sikartap_ide_inovasi_reviewers` — penunjukan manual reviewer atasan per node Divisi (OrgStructure level 2), gantinya `unit_kategori.php`/`get_kategori_unit()` legacy.
   - ✅ `IdeInovasiUploadPage` (karyawan submit file ide inovasi, setelah wawancara lulus), `IdeInovasiAtasanQueuePage` (antrian atasan) + `IdeInovasiAtasanReviewPage` (form input skor 5 kriteria) — 2 halaman terpisah gantinya `dashboard_verifikator_inovasi.php`+`verifikasi_ide_inovasi.php` legacy yang dead-end (§0 item 1).
   - ⚠️ **Menyimpang dari rencana**: bagian rencana ini awalnya bilang Fase 5 **wajib** lewat Approval Engine (`ApprovalModuleEnum`/`MasterApprovalFlows`/`ApprovalFlowService`) dan eksplisit **melarang** replikasi kolom `status_atasan`/`status_komisaris`/`status_direktur` gaya legacy sebagai sumber kebenaran (instance bug legacy §0 item 1). Implementasi aktual justru pakai kolom status itu langsung di `sikartap_ide_inovasis` — **tidak ada** `SIKARTAP_INNOVATION_IDEA` di `ApprovalModuleEnum`, tidak lewat `approval_activities`/`approval_snapshots` sama sekali. Desain akhir juga beda struktur dari rencana: bukan 3 step sequential (Kabag/Kabid → Komisaris → Direktur), tapi **atasan wajib & blocking** + **Direktur dan Komisaris opsional, tidak blocking, independen satu sama lain** (masing-masing kolom status jalan sendiri, tidak menunggu yang lain). Belum dikonfirmasi apakah ini keputusan sadar yang tidak sempat didokumentasikan ulang, atau penyimpangan tak sengaja dari rencana — dicatat di sini biar tidak dikira konsisten dengan §D analisis kalau ada yang baca ulang nanti.

### Analisis Approval Engine vs custom (2026-08-25)

Dicek `TrainingRequestService`/`ApprovalCreationService`/`ApprovalFlowService` untuk nentuin mana proses Sikartap yang duplikat Approval Engine kalau dibangun manual, dan mana yang memang beda domain:

- **Verifikasi berkas (Fase 1)**: BUKAN kandidat Approval Engine — 1 role (HRD) cek 11 dokumen independen tanpa chain approver berjenjang. Engine ini didesain buat sequential multi-approver dengan flow configurable per company, bukan checklist. Kolom status + action langsung yang sudah dibangun sudah benar, tidak diubah.
- **Tes/Wawancara/MCU (Fase 2-3)**: sama seperti di atas — gate hasil entri data domain-spesifik, bukan keputusan yang didelegasikan berjenjang. Kolom polos tetap benar.
- **Keputusan Direktur (Fase 4) & Ide Inovasi berjenjang (Fase 5)**: INI cocok Approval Engine — approver beda per step, urutan jelas, butuh riwayat siapa-approve-kapan-catatan-apa. Kalau dibangun manual (tombol approve/reject custom + kolom status ditulis langsung dari Filament action), itu duplikat fungsi `ApprovalCreationService`/`ApprovalFlowService` yang sudah ada.
- **Temuan sampingan**: `ApprovalModuleEnum::APPROVAL_CANDIDATE_APPLICANT` ada di enum tapi 0 referensi di codebase manapun — kemungkinan sisa niat lama yang tidak pernah diselesaikan, di luar scope Sikartap, dicatat saja di sini buat referensi kalau nanti ditemukan lagi.
7. **Fase 6 — Notifikasi** — **SELESAI 2026-08-26** (dikerjakan sesi ini, setelah baca langsung `email_queue_functions.php` legacy — ternyata isinya **10 fungsi** `queue_*_email()`, bukan 11 seperti perkiraan awal dokumen ini, `email_queue_functions1.php` cuma duplikat identik/dead code):
   - ✅ 9 dari 10 kategori di-port jadi `NotificationTypeEnum` case baru (`sikartap_*`) + class `App\Notifications\Sikartap\*`, channel `database`+`WebPushChannel`, gerbang preferensi via `NotificationPreferenceService` (bisa di-off user) — pola identik `ApprovalNeedsAction`/`RequestStatusChanged`: `SikartapDocumentRejected`, `SikartapBerkasStatusChanged`, `SikartapJadwalTesDitetapkan`, `SikartapHasilTes`, `SikartapJadwalWawancaraDitetapkan`, `SikartapHasilWawancara`, `SikartapHasilMcu`, `SikartapIdeInovasiDireview` (1 class dipakai bergantian 3 tier atasan/direktur/komisaris), `SikartapKeputusanDirekturFinal`.
   - ⚠️ **1 kategori sengaja di-skip**: notifikasi jadwal MCU (legacy `queue_jadwal_mcu_email()`) — tidak relevan lagi, Fase 3 sengaja tidak punya tabel jadwal MCU (staff klinis isi bebas tanpa booking slot, lihat catatan Fase 3).
   - **Dipasang di 2 entry point sekaligus** (bukan cuma portal) supaya HRD yang kerja lewat `/admin` (Filament `ViewSikartap`/`DocumentsRelationManager`) maupun portal native (`PengajuanDetailPage`/`IdeInovasiAtasanReviewPage`) sama-sama memicu notifikasi — konsekuensi dari arsitektur existing yang memang menduplikasi business logic tulis-status di 2 layer UI (lihat catatan §3.3, bukan hal baru yang diperkenalkan sesi ini).
   - **Keputusan Direktur final** sengaja dipasang di **1 titik** (`SikartapDirectorDecisionService::approve()`/`reject()`), bukan di tiap caller — otomatis kecover di portal, Filament, DAN halaman `DirekturBulkKeputusanPage` sekaligus. Beda dari notifikasi generik `RequestStatusChanged` (dikirim `ApprovalFlowService::advanceStep()` ke `created_by` snapshot = HRD yang klik "Ajukan", BUKAN pelamar) — notifikasi baru ini ditujukan ke pelamar (`sikartap->user`), jalur terpisah, bukan pengganti.
   - `approve()` cuma notify kalau `status_keputusan_direktur` ke-sync jadi `'approved'` SETELAH `advanceStep()` (artinya step final, bukan masih ada approver berikutnya di flow) — `reject()` selalu notify karena reject di step manapun langsung mengakhiri flow.
   - Sengaja **tidak** notify approve dokumen individual maupun approve-all/reject-all (di luar reject per-dokumen) yang lain sebagai kandidat awal — instruksi user eksplisit 2026-08-26: cuma reject per-dokumen dulu yang actionable buat karyawan.
   - Test: 45 test existing Sikartap semua tetap PASS setelah wiring (dijalankan per-file/batch kecil karena `php artisan test` full-suite kena memory limit CLI 128MB bawaan environment, bukan terkait perubahan ini).
8. ~~Fase 7 — Perpanjangan Kontrak~~ — **di-skip** (keputusan user), cukup `user_contracts`/`KontrakPage` existing.
9. **Fase 7 — Cleanup & housekeeping** — **audit dijalankan 2026-08-26, HASIL BERSIH**: dicek 3 sudut kemungkinan dead-code/duplikat gaya legacy (≈34% file root Sikartap legacy) kejadian lagi di modul baru:
   - Semua 13 Livewire component `app/Livewire/Sikartap/**` direferensikan minimal 1 tempat (route/blade) — tidak ada file "versi lama" nganggur kayak `PengajuanListPage`/`McuQueuePage` yang disebut di §3.1/§3.3 (sudah bersih dihapus pas digantikan, tidak ditinggal).
   - Semua blade view `resources/views/livewire/sikartap/**` direferensikan tepat 1 Livewire component — tidak ada view yatim.
   - `routes/sikartap.php` dan `config/portal-pages.php` 1:1 cocok, tidak ada key config nganggur tanpa route atau sebaliknya.
   - **Duplikasi yang MEMANG ada** (bukan temuan baru, sudah didokumentasikan §3.3): business logic tulis-status (`->update()` mentah) terduplikasi di 2 layer UI (`PengajuanDetailPage` native vs `ViewSikartap`/`DocumentsRelationManager` Filament) untuk verifikasi berkas/tes/wawancara/MCU — ini **trade-off arsitektur sadar** (Filament resource sengaja TIDAK dihapus, tetap bisa diakses `/admin`), bukan pola dead-code legacy yang tidak disengaja. Konsekuensinya: notifikasi Fase 6 juga harus dipasang 2x (lihat Fase 6) - kalau nanti ada mutasi status baru lagi, ingat ada 2 tempat yang perlu diupdate barengan.

Setiap fase harus diakhiri dengan test coverage Livewire (ikuti pola test existing di `tests/Feature/`), bukan cuma manual check.

## 3.1 Revisi Navigasi (2026-08-25, instruksi eksplisit user)

Sebelumnya link "Tetap (Sikartap)" nempel jadi 1 baris di dalam dropdown Kepegawaian (row 2) dan "Antrian MCU" juga di situ. User minta dipisah:

- **Row 1** (nav-links, area self-service milik karyawan sendiri) — link baru "Sikartap" (`route('sikartap')`), gate `User::isKontrakKaryawan()` (kontrak AKTIF SAAT INI berkategori `EmployeeTypeCategory::KONTRAK` via `currentContract->employeeType->category` — bukan siapa saja yang login, bukan juga dari kolom `status_karyawan` yang ternyata TIDAK ADA di skema HRIS, itu konsep legacy Sikartap doang). Ini yang dipakai karyawan buat MULAI pengajuan.
- **Row 2** (topnav-row2, dropdown per modul) — dropdown baru `<x-nav.sikartap-dropdown>`, terpisah dari Kepegawaian:
  - "Pengajuan Karyawan Tetap" → link ke Filament resource index (`SikartapResource::getUrl('index')`), gate `ViewAny:Sikartap`. Verifikasi berkas, jadwal+hasil Tes, jadwal+hasil Wawancara, keputusan MCU final, dan Keputusan Direktur semuanya ADA DI DALAM halaman detail resource ini (Filament action di `ViewSikartap.php`) — bukan halaman portal terpisah per tahap, jadi 1 link ini sudah mencakup semua istilah yang disebut user ("tes, wawancara").
  - "Antrian MCU" → `route('sikartap.mcu.queue')`, gate `canInputSikartapMcu()` — dari sini staff lanjut ke 4 form MCU (perawat/lab/radiologi/dokter) sesuai permission masing-masing.
  - "Ide Inovasi" — placeholder disabled "Segera" (Fase 5 belum dibangun).
- Gate baru `canAccessSikartap`/`canAccessSikartapSaya` dihitung sekali di `layout.blade.php` (`$navGates`), dioper ke `<x-nav>` dan `<x-pwa-bottom-nav>` — pola sama seperti gate `SISTEM_*` lain, supaya tidak dicek dobel.
- Mobile (`pwa-bottom-nav.blade.php`) turut disesuaikan sekalian (bukan diminta eksplisit, tapi konvensi kodebase ini "desktop dan mobile harus selalu sinkron", lihat § Design System README) — `<x-pwa-nav.sikartap-menu>` di sheet Menu + item standalone "Sikartap" (self-service) gate `canAccessSikartapSaya`.
- Route `/sikartap` sekarang terdaftar juga di `config/portal-pages.php` (gate `isKontrakKaryawan`) — sebelumnya lolos tanpa cek granular (gap yang dicatat di § Fase 3 sebelumnya), sudah diperbaiki sekalian.
- Verifikasi: `git stash` dipakai buat konfirmasi test yang gagal (`AttendanceApprovalPortalTest`, `Portal\DashboardTest`) sudah gagal juga di baseline sebelum perubahan nav — pre-existing, bukan regresi dari revisi ini.

## 3.2 Identitas Visual (2026-08-25, instruksi eksplisit user "samakan stylenya, background ungu gradiasi")

Semua halaman portal Sikartap (pengajuan, 4 form MCU, antrian MCU) sebelumnya cuma pakai `<x-page-header>` polos (header tanggal+judul standar) — beda dari pola halaman submission lain di portal (Cuti/Izin/Lembur/Pelatihan) yang pakai `.page-hero` (banner besar gradient + breadcrumb) sesuai § Layout DESIGN.md.

- Ditambah varian baru `.page-hero.violet` di `resources/css/app.css` (`linear-gradient(130deg, #3b0764 0%, #7c3aed 100%)`) — mengikuti pola existing varian navy(default)/gold/green, jadi Sikartap sekarang punya identitas warna sendiri konsisten dengan konvensi "1 domain = 1 warna" di DESIGN.md.
- **Sengaja beda titik warna** dari `.pwa-menu-grid-icon.violet`/`.pwa-card-header-violet` (`#5b21b6`/`#ede9fe`) yang sudah jadi identitas LMS/Kursus — DESIGN.md eksplisit larang 1 domain baru pakai warna identitas domain lain, jadi dipilih ungu lebih gelap/dalam (`#3b0764`) supaya tetap "ungu" sesuai instruksi tapi tidak collide dengan LMS.
- Diterapkan ke: `pengajuan-karyawan-tetap-page.blade.php`, 4 blade MCU (perawat/lab/radiologi/dokter), `mcu-queue-page.blade.php` — breadcrumb + judul + subtitle di dalam hero, markup identik pola `submit-permission-request.blade.php` (`.hero-deco-1/2/3` dekorasi lingkaran translucent, sudah generic lintas varian warna).
- Halaman form (pengajuan + 4 MCU) dibungkus `.form-page-wrap` (max-width 680px, center) sama seperti halaman submission lain; halaman antrian (list, butuh lebar penuh) TIDAK dibungkus `form-page-wrap`.
- Sudah `npm run build` (bukan cuma `npm run watch`) supaya `public/build/assets/app-*.css` ikut ter-update — lihat § Known Gaps README soal jebakan CSS "tidak muncul" kalau lupa build ulang.

## 3.3 Portal HRD/Direktur — pengganti link admin panel (2026-08-25, instruksi eksplisit user)

Sebelumnya link dropdown "Sikartap" > "Pengajuan Karyawan Tetap" ke Filament (`SikartapResource::getUrl('index')`, `/admin`) — user minta portal (di luar `/admin`) dan label dipendekkan jadi "Pengajuan".

- **`app/Livewire/Sikartap/PengajuanListPage.php`** (`/sikartap/pengajuan`) — daftar pengajuan sisi HRD/Direktur, pola sama `MyEmployees` (search + filter company kalau `canBrowseAllCompanies()` + pagination server-side, tabel desktop/card mobile dari sumber data yang sama). Gate `ViewAny:Sikartap`.
- **`app/Livewire/Sikartap/PengajuanDetailPage.php`** (`/sikartap/pengajuan/{sikartap}`) — rewrite NATIVE Livewire dari `ViewSikartap.php` (Filament) + `DocumentsRelationManager.php`, mencakup SEMUA action yang sebelumnya cuma ada di admin panel: approve/reject per dokumen, Setujui Semua/Tolak Pengajuan (berkas), jadwal+hasil tes, jadwal+hasil wawancara, keputusan MCU, dan Ajukan/Setujui/Tolak (Direktur). Gate `View:Sikartap` buat akses halaman, `Update:Sikartap` buat tombol aksi, `ApprovalPermissionService::canApprove()` khusus tombol Setujui/Tolak Direktur — persis sama gate yang dipakai versi Filament, tidak ada yang dilonggarkan/diperketat.
- **Business logic TIDAK diduplikasi** — detail page manggil service yang sama persis (`SikartapDirectorDecisionService::submitForDecision()/approve()/reject()`), method Eloquent yang sama (`hasAllRequiredDocumentsUploaded()`, `isEligibleForDirectorDecision()`). Yang beneran baru cuma View (Blade + Livewire state per-section, bukan Filament Schema/Action) — nggak bisa dihindari karena target render-nya beda total (portal karyawan vs admin panel), tapi aturan mutasi datanya satu sumber kebenaran.
- **Filament Resource TIDAK dihapus** — `SikartapResource`/`ViewSikartap`/`DocumentsRelationManager` tetap ada, masih bisa diakses langsung lewat `/admin` (mis. buat superadmin yang lebih familiar UI Filament, atau kalau portal ada gap). Nav utama saja yang sekarang default ke portal.
- Route baru: `sikartap.pengajuan` (list), `sikartap.pengajuan.show` (detail) — didaftarkan di `routes/sikartap.php` + `config/portal-pages.php` (gate `ViewAny:Sikartap`/`View:Sikartap`, sama seperti gate Filament resource-nya).
- Nav dropdown desktop (`nav/sikartap-dropdown.blade.php`) dan mobile (`pwa-nav/sikartap-menu.blade.php`) diarahkan ke route baru, label dipendekkan "Pengajuan Karyawan Tetap" → "Pengajuan" (instruksi user).
- Test `tests/Feature/Sikartap/PortalPengajuanTest.php` (4 test) — cakupan: list page blocked tanpa permission, list page nampilin nama pelamar, `approveAllBerkas()`, dan **1 test full-flow** yang jalanin seluruh tahap dari tes sampai keputusan Direktur lewat komponen Livewire baru ini (bukan Filament lagi) buat mastiin tidak ada tahap yang keskip pas port ke native Livewire.

## 3.4 Halaman Jadwal Tes & Wawancara (2026-08-25, instruksi eksplisit user)

Sebelumnya jadwal Tes/Wawancara cuma bisa dilihat/diatur per-record lewat `PengajuanDetailPage` — tidak ada satu tempat buat HRD scan semua jadwal sekaligus lintas pengajuan.

- **`app/Livewire/Sikartap/JadwalPage.php`** (`/sikartap/jadwal`) — list Sikartap yang punya `testSchedule` dan/atau `interviewSchedule` (query `whereHas`), kolom Jadwal Tes + Hasil Tes + Jadwal Wawancara + Hasil Wawancara per baris, filter company + search sama pola `PengajuanListPage`. Gate `ViewAny:Sikartap`.
- **READ + LINK doang, bukan tempat edit baru** — tombol "Aksi" ke `sikartap.pengajuan.show` (detail), form Jadwalkan/Ubah tetap satu-satunya sumber kebenaran di `PengajuanDetailPage` supaya tidak ada 2 tempat yang bisa menyimpang.
- **MCU sengaja tidak ikut** — MCU tidak punya tabel jadwal terpisah (keputusan Fase 3, staff klinis isi kapan saja setelah `status_wawancara=disarankan`, tanpa booking slot eksplisit), jadi tidak ada "jadwal MCU" buat dilist di sini.
- Ditaruh di dropdown nav Sikartap **di atas "Antrian MCU"** (instruksi eksplisit user) — urutan: Pengajuan → Jadwal Tes & Wawancara → Antrian MCU → Ide Inovasi (placeholder).
- Route `sikartap.jadwal` + `config/portal-pages.php` (gate `ViewAny:Sikartap`, sama seperti `sikartap.pengajuan`).
- Test `tests/Feature/Sikartap/JadwalPageTest.php` (2 test) — blocked tanpa permission, cuma nampilin pengajuan yang beneran punya jadwal (bukan semua pengajuan).

## 3.5 Keputusan Direktur Massal + Widget "Butuh Persetujuan Anda" (2026-08-25, instruksi eksplisit user)

Masalah yang diselesaikan: kalau ada pengangkatan karyawan tetap massal (mis. 100 orang sekaligus diajukan HRD yang sama ke Direktur), widget "Butuh Persetujuan Anda" (dipakai lintas modul, `PendingApprovalsCard.php`) akan numpuk 100 baris terpisah — tidak praktis buat di-scan.

- **`PendingApprovalsCard::buildRequests()`** — blok baru khusus `sikartap_director_decision`, DIKELOMPOKKAN per pengaju (`snapshot->created_by`, siapa yang klik "Ajukan ke Direktur"), bukan 1 baris per karyawan seperti modul lain. 1 baris per pengaju, `keterangan` = "N orang diajukan".
  - Kalau pengaju itu cuma ngajuin **1 orang** → `url` langsung ke `PengajuanDetailPage` biasa (alur approve/reject satuan yang sudah ada, tidak berubah).
  - Kalau **lebih dari 1** → `url` ke halaman baru `DirekturBulkKeputusanPage` (`/sikartap/keputusan-direktur`).
  - `bulk_supported = false` sengaja — checkbox multi-select bawaan widget (`confirmBulkApprove()`) pakai `ApprovalFlowService` generik, sedangkan Sikartap WAJIB lewat `SikartapDirectorDecisionService` (biar `status_keputusan_direktur` cache ikut ke-sync, lihat § Fase 4). Approve massal Sikartap sengaja dipisah ke halaman sendiri, bukan dipaksa masuk mekanisme generik widget.
  - Badge warna `purple` (di-reuse, bukan warna baru) — kebetulan dekat dengan identitas ungu Sikartap (`page-hero.violet`), jadi tetap kebaca konsisten meski `badgeStyle()` di blade widget tidak diubah.
- **`app/Livewire/Sikartap/DirekturBulkKeputusanPage.php`** (`/sikartap/keputusan-direktur`) — rewrite `bulk_keputusan_direktur.php` legacy (FIX21). List SEMUA pengajuan yang jadi tanggung jawab Direktur yang login (bukan cuma dibatasi 1 pengaju spesifik yang diklik dari widget — begitu masuk halaman ini, tampilkan semua yang pending, lebih berguna). Checkbox per baris + "Pilih Semua", tombol Setujui Terpilih/Tolak Terpilih (catatan wajib buat tolak), tiap baris tetap ada link "Lihat Detail" kalau approver mau tinjau dulu satu-satu sebelum approve massal. 404 kalau tidak ada apa-apa yang pending (baik diakses langsung maupun setelah semua selesai diproses).
- Route `sikartap.direktur.bulk` + `config/portal-pages.php` (gate `ViewAny:Sikartap`, plus `mount()` sendiri abort 404 kalau kosong).
- Test `tests/Feature/Sikartap/DirekturBulkKeputusanTest.php` (6 test) — widget grouping 1-orang vs banyak-orang, 404 kalau kosong, approve/reject sebagian (bukan semua) tercentang, toggle pilih semua.

## 4. Red Flags Legacy yang Wajib Dihindari di Implementasi Baru

Referensi § I di `analisis-sikartap-migrasi.md` — daftar cepat supaya tidak terulang:
1. Kredensial hardcoded → pakai `.env`.
2. Endpoint tanpa auth check (`generate_password_direktur.php`) → semua endpoint baru wajib policy/middleware.
3. IDOR file download → cek kepemilikan, bukan cuma login.
4. Query string-concat rawan SQLi → pakai Eloquent/query builder parameterized, bukan raw concat.
5. FK ke tabel backup → desain FK baru bersih, tidak ada tabel `_backup`/`_backup2` di skema produksi.
6. Data wilayah terduplikasi → sudah diputuskan tidak diport, ganti kolom `text` bebas (lihat §1/§2).
7. Business rule terduplikasi di banyak file (`get_keputusan_akhir()`) → satu service/class sebagai sumber kebenaran.
