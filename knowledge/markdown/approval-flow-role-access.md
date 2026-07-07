# Analisis Approval Flow & Role Access (per 2026-07-03)

Dokumen ini menjawab pertanyaan: **apakah sistem approval saat ini sudah mendukung alur persetujuan yang berbeda-beda tergantung struktur organisasi, jabatan, dan company?** Ditulis berdasarkan pembacaan kode (`app/Services/Approval`, `app/Repositories/Approval`, `app/Models/ApprovalManagement`) DAN query langsung ke database `hris_db` (bukan cuma migration), supaya merefleksikan kondisi data riil, bukan cuma skema.

## TL;DR

**Ya, sistem ini multi-company (holding + anak perusahaan), dan mekanisme approval flow SUDAH bisa menghasilkan skenario yang kamu jelaskan** (programmer → supervisor → manager; supervisor sendiri langsung ke manager; finance beda jalur) — **tapi bukan lewat penelusuran hierarki organisasi secara dinamis.** Ini dicapai dengan cara manual: admin membuat *flow terpisah* per jabatan/kelompok jabatan (`flow_type = specific`), di-assign ke job_position tertentu. Kolom `parent_id` yang ada di `m_org_structures` dan `m_job_positions` (hierarki atasan-bawahan) **sama sekali tidak dipakai** oleh service approval untuk mencari "siapa atasan langsung" — itu cuma dipakai untuk tampilan struktur & filter `specific_department`.

Ditemukan juga **bug nyata yang sedang aktif**: minimal 3 karyawan sungguhan yang masih aktif di company holding akan mendapat error 500 kalau mengajukan cuti/izin/lembur, karena profil jabatan mereka tidak (atau belum) match flow manapun dan flow default company mereka soft-deleted. Detail di bagian "Gap & Bug Ditemukan" dan audit lengkap di bagian 7.

## 1. Konteks Organisasi (dari data riil)

Sistem ini menaungi **1 holding + minimal 6 anak perusahaan** (`m_companies`):

| Company | Kode | Users | Org Structure | Job Position |
|---|---|---|---|---|
| Syifa Global Group (holding/head office) | 0000 | 24 | 18 | 27 |
| RSU Syifa Medika Banjarbaru | 0001 | **479** | 46 | 127 |
| RSU Syifa Medika Barabai | 0002 | 1 | 20 | 0 |
| Syifa Global Medica | 0003 | 1 | 0 | 0 |
| Syifa Global Lestari Banjarbaru | 0004 | 1 | 0 | 0 |
| Syifa Global Lestari Surabaya | 0005 | 1 | 0 | 0 |
| Lerina Inn | 0006 | 1 | 0 | 0 |
| Syifa Agro Natura | 0007 | 1 | 0 | 0 |

Jadi baru **2 company yang benar-benar "hidup"**: holding (kantor pusat — isinya IT, HCGA, Finance, Accounting, dsb — cocok dengan cerita kamu jadi programmer IT) dan RSU Banjarbaru (rumah sakit dengan 479 karyawan). Company lain baru placeholder (1 user saja, kemungkinan akun admin/testing).

`m_org_structures` dan `m_job_positions` keduanya **company-scoped** (`company_id` wajib) dan punya hierarki asli via `parent_id` self-referencing + `level` — tapi seperti disebutkan, hierarki ini menganggur untuk approval.

## 2. Cara Approval Flow Bekerja Saat Ini

Alur resolusi (`ApprovalSnapshotRepository::resolveApprovalFlowForUser()`):

1. Ambil `job_position_id` pengaju dari `user_profiles` (bukan dari tabel histori `user_job_positions`).
2. Cari flow `flow_type = specific` di company yang sama, modul yang sama (`attendance_request` — cuti/izin/lembur berbagi 1 modul yang sama, tidak dipisah), yang di-*assign* ke job_position tsb lewat `m_approval_flow_assignments`.
3. Kalau ketemu → pakai itu. Kalau tidak → fallback ke flow `flow_type = default` company tsb.
4. **Kalau tidak ada keduanya → `throw new \Exception(...)`** (lihat `AttendaceReqService.php:59-61`, dst) — request GAGAL total, bukan fallback diam-diam.

Begitu flow ditemukan, langkah-langkahnya (`steps`) **di-*snapshot* (dibekukan)** ke `approval_snapshots.steps_snapshot` (JSON) saat request dibuat. Progres approval selanjutnya murni baca dari snapshot ini (`current_step_order + 1`), tidak ada evaluasi kondisi runtime apa pun (tidak cek siapa pengaju vs siapa approver).

Yang bisa dikonfigurasi admin lewat Filament (`ApprovalFlowResource`):
- Flow selalu terikat ke 1 company (otomatis dari company admin yang login).
- Flow `default` (satu-satunya per company+modul) vs `specific` (di-assign ke 1+ job_position).
- Per step: approver berupa `role`, `user`, atau `job_position`; `scope_type` (`same_department` / `specific_department` / `global`); ada field **"source_company"** yang secara eksplisit mengizinkan memilih approver dari **company sendiri ATAU dari holding company** (ID holding di-hardcode: `01J2F0C8KQ9M7D4S8RBX6E1ZVP`) — ini mekanisme eskalasi lintas-company yang sudah dipakai (lihat bagian 3).

## 3. Bukti Konkret yang Cocok Dengan Skenario Kamu

Data di company **holding (Syifa Global Group)** ternyata sudah dikonfigurasi persis seperti cerita kamu:

- **"Alur Perizinan IT"** (specific, di-assign ke IT Programmer/Network/Support/R&D Staff): step 1 = job_position **IT Supervisor**, step 2 (final) = job_position **PJ HCGA Manager**. → *Programmer → Supervisor → Manager.* Cocok persis dengan ceritamu.
- **"Alur Persetujuan Supervisor"** (specific, di-assign ke **HC Supervisor** dan **IT Supervisor** sendiri): cuma 1 step, langsung ke **PJ HCGA Manager**, final. → *Kalau supervisor sendiri yang izin, langsung ke manager* — persis seperti ceritamu.
- **"Alur Perizinan Finance"** (untuk Finance Analyst Staff): step 1 = role **Finance Manager**, step 2 = **PJ HCGA Manager** (final) — bukan langsung ke Direktur seperti dugaanmu, tapi tetap jalur terpisah dari flow HCGA/IT biasa.
- **"Alur Perizinan Manajer"** (untuk Finance Manager & Accounting Manager sendiri): step 1 = **PJ HCGA Manager**, step 2 = role **Direktur** (final).
- **"Alur Persetujuan HCGA Manager"** (untuk PJ HCGA Manager sendiri): langsung ke role **Direktur**, 1 step final.

Jadi mekanisme "kalau level X yang mengajukan, mulai dari level yang lebih tinggi" **sudah dicapai** — tapi dengan cara *admin membuat flow terpisah untuk tiap tingkat jabatan yang butuh perlakuan beda*, bukan sistem yang otomatis mendeteksi "requester == approver step ini, skip". Konsekuensinya: setiap ada jabatan/posisi baru yang butuh jalur khusus, HR/admin harus **manual bikin flow baru + assignment baru**. Tidak auto-scale kalau struktur organisasi berubah.

**Temuan tambahan penting:** RSU Banjarbaru (479 karyawan) ternyata memakai jalur eskalasi **lintas-company** ke holding — step 1 & 2 flow default RSU menunjuk ke job_position "HC Supervisor" dan "PJ HCGA Manager" milik **holding**, bukan milik RSU sendiri (dikonfirmasi lewat field `source_company`/hardcoded holding ID di atas). Hanya **2 user** di RSU yang punya `job_position_id` cocok dengan posisi holding tsb — artinya **seluruh 479 karyawan RSU saat ini cuma diproses lewat 1 flow flat 2-step, dan disetujui oleh cuma 2 orang di kantor pusat** (bukan supervisor/kepala unit lokal RSU sendiri, padahal RSU sudah punya 127 job position & 46 unit organisasi sendiri). Ini kemungkinan besar akan jadi bottleneck kalau volume pengajuan bertambah, dan belum mencerminkan struktur organisasi riil rumah sakit (per unit/departemen).

## 4. Gap & Bug yang Ditemukan (perlu diputuskan sebelum lanjut desain portal)

1. **Bug aktif — request akan gagal (500) untuk minimal 3 karyawan aktif riil**: company holding punya flow `default` untuk modul `attendance_request` ("Alur Perizinan"), tapi **statusnya sudah soft-deleted** (`deleted_at = 2026-04-07`), dan tidak ada penggantinya. Setelah verifikasi silang users×profiles×flow assignment (lihat bagian 7), user aktif **Muhammad Fauzul Akbar**, **Yudith Melani Wael (Sekretaris Corporate)**, dan **Dimas Permadi, S.Tr.T** tidak punya `job_position_id` yang cocok dengan flow `specific` manapun — begitu mereka mengajukan cuti/izin/lembur akan kena `throw new \Exception('No active approval flow found...')` (tidak ada fallback/notifikasi yang baik, cuma exception mentah). *(Catatan: 2 akun bernama "Direktur" yang sempat teridentifikasi di analisis awal ternyata sudah soft-deleted/tidak aktif — dikoreksi di sini setelah audit lebih teliti.)*

   **Konfirmasi dari pemilik project (2026-07-03):** ketiga orang ini BUKAN data yang salah input — mereka memang pemegang jabatan puncak (Akbar adalah pucuk pimpinan tertinggi di Syifa Global Group, holding yang menaungi seluruh lini bisnis dari rumah sakit sampai hotel). Jadi ini bukan "3 karyawan lupa di-assign flow", tapi **gap desain**: sistem saat ini mengharuskan SETIAP job_position (termasuk yang paling atas dalam grup) match ke sebuah flow approval, padahal secara logis orang di puncak hierarki tidak punya atasan untuk approve. Solusinya bukan sekadar "assign flow yang benar", tapi perlu diputuskan: apakah level puncak butuh flow 1-step yang final ke diri sendiri/HR-record-only, atau approver_type baru (mis. "Board"/"self-acknowledge"), atau `AttendaceReqService` perlu izinkan bikin request tanpa approval flow kalau pengaju adalah top-of-hierarchy.
2. **Job position duplikat/yatim**: ditemukan beberapa jabatan dengan nama sama tapi ID beda di holding (mis. 3 "HC Supervisor", 3 "Accounting Manager", 2 "Direktur", 2 "IT Supervisor") — sebagian tidak pernah di-assign ke flow apa pun. Kemungkinan sisa reorganisasi/percobaan, atau bakal jadi jebakan kalau user baru kebetulan ditaruh di posisi yang "yatim" ini.
3. **`scope_type = same_department` tidak benar-benar difilter** di kode (`ApprovalPermissionService::checkRolePermission()` dan `NotificationService::getFirstApproverFromSnapshot()`) — hanya `specific_department` yang benar difilter. Jadi kalau step approval pakai `role` + scope "Satu Departemen", di kode itu efeknya sama seperti "Global" (siapa saja dengan role itu di company yang sama bisa approve, tidak dibatasi departemen). Perlu diperbaiki dulu sebelum dipakai serius untuk kasus "approval oleh siapapun di departemen yang sama".
4. **1 modul untuk 3 jenis pengajuan**: cuti, izin, lembur semua pakai `module = 'attendance_request'` yang sama di `AttendaceReqService`. Kalau butuh flow yang beda total per jenis (bukan cuma per jabatan), kolom `module` di DB sudah bebas string — tinggal ubah 3 pemanggil di `AttendaceReqService` untuk pakai module_type berbeda (`leave_request`/`permission_request`/`overtime_request`). Perubahan kecil kalau dibutuhkan.
5. **Sumber jabatan user tidak konsisten**: resolusi approval pakai `user_profiles.job_position_id` (field tunggal, tanpa histori), padahal ada tabel `user_job_positions` dengan histori (`start_date`/`end_date`/`status`) dan relasi `User::activeJobPosition()` yang sebenarnya lebih tepat dipakai. Kalau mutasi jabatan tidak sinkron meng-update `user_profiles.job_position_id`, approval routing bisa salah target.
6. **RSU (mayoritas karyawan, 479 orang) belum punya flow granular per unit/departemen** — masih 1 flow flat yang eskalasi ke 2 orang di kantor pusat. Perlu diklarifikasi ke PM/HR: apakah ini memang disengaja (sentralisasi HR-GA), atau memang belum sempat dikonfigurasi granular seperti di holding.

## 5. Rekomendasi Arah Desain (untuk portal absensi & ke depan)

- **Untuk fitur "web attendance" yang mau langsung digarap**: konsumsi API attendance yang sudah ada apa adanya — logic approval-nya sudah berjalan (snapshot-based), jadi portal cukup menampilkan status/histori dari `approval_activities`/`approval_snapshots`, tidak perlu bikin ulang logic approvalnya.
- **Sebelum portal dipakai serius**, selesaikan dulu 2 bug di atas (poin 1: flow default holding yang ke-soft-delete, dan pastikan tidak ada job_position aktif yang orphan) — kalau tidak, begitu portal live, user seperti Direktur/Sekretaris Corporate akan langsung gagal submit.
- **Keputusan besar yang perlu diambil bareng PM**: apakah tetap pakai pola "manual flow per job_position" (sudah terbukti jalan untuk holding, effort admin makin besar seiring jumlah jabatan unik) atau investasi ke **resolusi hierarki dinamis** (pakai `parent_id` di `m_org_structures`/`m_job_positions` yang sudah ada di skema tapi menganggur) sehingga approval flow bisa auto-derive "atasan langsung" tanpa perlu admin bikin flow baru tiap ada jabatan baru. Untuk skala RSU (127 job position, 479 karyawan), pola manual-per-jabatan kemungkinan tidak akan scale — worth didiskusikan.

## 7. Audit Kualitas Data Master (Deep Dive)

Pertanyaan: *"dari tabel-tabel master yang berkaitan, apakah ada yang harus diperbaiki dulu karena datanya sangat banyak/berantakan?"* — dicek dengan query langsung ke tiap tabel master yang relevan ke approval flow, di seluruh 8 company (bukan cuma holding). Hasilnya **jauh lebih bersih dari dugaan**: sebagian besar tabel master OK, masalah nyata terkonsentrasi di 2 area.

### 7.1 Tabel yang TERNYATA BERSIH (tidak perlu diapa-apakan)

| Tabel | Yang dicek | Hasil |
|---|---|---|
| `m_job_positions` | Nama duplikat aktif per company | 0 — semua "duplikat nama" (Direktur, HC Supervisor, Accounting Manager, dst di holding) ternyata cuma 1 versi yang aktif, sisanya sudah soft-deleted dengan benar (histori percobaan/reorganisasi, wajar) |
| `m_job_positions` | `parent_id` orphan (nunjuk ID yang tidak ada) | 0 |
| `m_job_positions` | `parent_id` lintas-company (parent di company lain) | 1 (lihat 7.2) |
| `m_job_positions` | `org_structure_id` company mismatch / tidak ditemukan | 0 |
| `m_org_structures` | Nama duplikat per company+level | 0 |
| `m_org_structures` | `parent_id` orphan | 0 |
| `m_org_structures` | `parent_id` lintas-company | 0 |
| `m_org_structures` | Konsistensi `level` (anak harus lebih dalam dari induk) | 0 pelanggaran |
| `roles` | Nama duplikat per company, role tanpa `company_id` | 0 — 8 company semua sudah dapat set role standar (10-14 role), scoping rapi |
| `m_approval_flow_steps` | `approver_ref_id` nunjuk job_position/role/user yang tidak ada atau sudah soft-deleted | 0 broken reference |
| `m_approval_flow_steps` | `scope_ref_id` (org structure) tidak ditemukan | 0 |
| `m_approval_flow_assignments` | `assignable_id` nunjuk job_position yang tidak ada/soft-deleted | 0 |
| `m_approval_flows` | Flow `default` duplikat (harusnya unique per company+module) | 0 |
| `user_profiles` | `job_position_id` user nunjuk job_position di company LAIN | 0 |
| `user_profiles` | `job_position_id` user aktif nunjuk job_position yang sudah soft-deleted | 0 |

Kesimpulan: **integritas referensial antar tabel master ini sebenarnya terjaga dengan baik** — bukan "berantakan" dalam arti korup, seed data-nya rapi. Jadi tidak perlu proyek "bersih-bersih data" besar-besaran sebelum lanjut.

### 7.2 Yang BENAR-BENAR Perlu Diperbaiki/Diputuskan

1. **1 job position di holding dengan `parent_id` lintas-company**: "Finance Analyst Staff" (company holding) → `parent_id` menunjuk job position di company RSU Banjarbaru. Kemungkinan salah pilih saat input data (harusnya parent-nya "Finance Manager" di company yang sama). Perlu dicek & diperbaiki manual oleh admin/HR — 1 baris saja, bukan masalah besar, tapi bisa bikin bingung kalau ada fitur yang nanti traverse hierarki jabatan.

2. **6 dari 8 company TIDAK punya approval flow untuk modul `attendance_request` sama sekali** (baik `default` maupun `specific`): RSU Barabai, Syifa Global Medica, Syifa Global Lestari Banjarbaru, Syifa Global Lestari Surabaya, Lerina Inn, Syifa Agro Natura. Saat ini tiap company itu baru punya 1 user (kemungkinan akun admin placeholder), jadi belum jadi masalah nyata — **tapi begitu ada karyawan sungguhan on-boarding ke company-company ini, mereka tidak akan bisa mengajukan cuti/izin/lembur sama sekali** sampai admin bikin minimal 1 flow `default`. Ini perlu jadi checklist wajib di proses onboarding company baru (bukan bug kode, tapi gap operasional/SOP).

3. **Company holding (Syifa Global Group) kehilangan flow default-nya** — dulu ada ("Alur Perizinan"), tapi di-soft-delete 2026-04-07 dan tidak digantikan. Dampak nyata: 3 karyawan aktif (Muhammad Fauzul Akbar, Yudith Melani Wael, Dimas Permadi — dicek satu-satu, bukan akun admin/test) **tidak punya `job_position_id` ter-assign ke flow spesifik manapun**, jadi kalau mereka submit sekarang akan dapat error. Solusi: aktifkan lagi 1 flow `default` untuk company holding modul `attendance_request` (atau pastikan setiap job position di holding sudah ter-cover oleh flow `specific`).

4. **50 user aktif di RSU Banjarbaru & 5 di holding belum punya `job_position_id` sama sekali di profil mereka** (`user_profiles.job_position_id` NULL). Untuk RSU ini masih "aman" karena ada flow `default` sebagai fallback. Untuk holding ini **berkontribusi ke poin 3 di atas** (3 dari 5 user tanpa job_position adalah karyawan aktif sungguhan). Ini pekerjaan rumah HR: lengkapi data jabatan di profil karyawan, bukan cuma untuk approval flow tapi juga karena data ini dipakai di banyak fitur lain (payroll, shift, dsb).

### 7.3 Ringkasan Prioritas Perbaikan

| Prioritas | Item | Effort |
|---|---|---|
| **Tinggi** | Aktifkan ulang/buat flow `default` attendance_request untuk company holding | Kecil — 1 flow + steps via Filament admin |
| **Tinggi** | Lengkapi `job_position_id` untuk 3 karyawan aktif holding yang belum ter-cover | Kecil — update data via admin panel |
| **Sedang** | Buat checklist SOP: company baru wajib ada minimal 1 flow default sebelum user pertama di-onboard | Proses, bukan kode |
| **Rendah** | Perbaiki `parent_id` "Finance Analyst Staff" yang salah nunjuk company lain | Kecil — 1 baris data |
| **Rendah** | Lengkapi `job_position_id` untuk 50 user RSU yang masih kosong | Sedang — data entry, tidak urgent karena ada fallback default flow |

## 8. Referensi File Kode Terkait

- `app/Repositories/Approval/ApprovalSnapshotRepository.php` — `resolveApprovalFlowForUser()`
- `app/Services/Approval/ApprovalCreationService.php` — `createSnapshot()`
- `app/Services/Approval/ApprovalFlowService.php` — `getNextApprover()`, `advanceStep()`, `checkStepsSync()`
- `app/Services/Approval/ApprovalPermissionService.php` — validasi siapa boleh approve step aktif (lokasi bug `same_department`)
- `app/Services/Attendance/AttendaceReqService.php` — pemanggil resolve flow untuk cuti/izin/lembur (lokasi `throw new \Exception`)
- `app/Filament/Resources/ApprovalFlows/Schemas/ApprovalFlowForm.php` — form konfigurasi flow (lokasi hardcode ID holding company)
- `app/Models/Master/OrgStructure.php`, `app/Models/Master/JobPositions.php` — hierarki `parent_id` yang belum dipakai approval
