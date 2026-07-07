# Developer Overview — HRIS Syifa Global Group

Dokumen orientasi untuk developer (baru maupun lama) supaya paham **apa sebenarnya aplikasi ini**, bagaimana ia tumbuh, dan istilah/konsep kunci yang sering bikin bingung kalau tidak tahu dari awal. Ditulis 2026-07-03. Riwayat git di repo ini sudah di-squash jadi 1 commit, jadi timeline di bawah disusun dari **tanggal file migration** (`database/migrations/`) — itu satu-satunya jejak sejarah yang akurat yang tersisa.

## 1. Apa Aplikasi Ini

HRIS internal untuk **Syifa Global Group** — bukan cuma 1 perusahaan, tapi **holding** yang menaungi beberapa lini bisnis berbeda:

| Company (di `m_companies`) | Lini bisnis |
|---|---|
| Syifa Global Group | Holding / kantor pusat (IT, HCGA, Finance, Accounting, dsb) |
| RSU Syifa Medika Banjarbaru | Rumah sakit (479 karyawan — basis user terbesar) |
| RSU Syifa Medika Barabai | Rumah sakit |
| Syifa Global Medica | (klinik/medis) |
| Syifa Global Lestari Banjarbaru & Surabaya | (lini bisnis lain) |
| Lerina Inn | Hotel |
| Syifa Agro Natura | Agribisnis |

Implikasinya buat kode: hampir semua tabel master (`m_companies`, `m_org_structures`, `m_job_positions`, `roles`, `m_approval_flows`, dst) **company-scoped**. Data karyawan, struktur organisasi, jabatan, sampai alur approval berbeda-beda per company, tapi tetap dalam 1 database yang sama. Ada juga mekanisme eskalasi **lintas-company** yang disengaja (unit-unit di rumah sakit bisa eskalasi approval ke posisi tertentu di holding/kantor pusat) — lihat [`approval-flow-role-access.md`](approval-flow-role-access.md) untuk detail.

Fungsi yang sudah berjalan: manajemen data karyawan & dokumen, absensi (record + pengajuan cuti/izin/lembur dengan approval flow), payroll (karyawan reguler & dokter fee-based terpisah), shift & tukar shift, rekrutmen (lowongan publik → kandidat → proses seleksi), LMS (kursus, kuis, sertifikat, ranking, trainer), assessment/penilaian, coaching & counseling, task tracking, dan approval engine generik yang dipakai lintas semua modul di atas.

## 2. Arsitektur Singkat

(Detail lengkap tech stack ada di [README.md](../../README.md) — di sini fokus ke hal yang perlu diketahui developer yang mau nulis kode baru.)

- **Layered**: Controller → Service → Repository → Model, per domain. Kalau nambah fitur baru di modul yang sudah ada, ikuti pola yang sudah ada di folder itu (jangan taruh query langsung di Controller/Filament Resource kalau modul lain di domain yang sama sudah pakai Service+Repository).
- **Dua sisi UI**: `/admin` (Filament, untuk HR/admin/approver) dan API (`routes/api.php`, dikonsumsi mobile app + rencana portal karyawan). Auth-nya beda: session untuk admin, Sanctum token untuk API.
- **ULID di mana-mana**: primary key semua tabel pakai ULID (string 26 char), bukan auto-increment int.
- **Approval Engine generik**: `MasterApprovalFlows` → `MasterApprovalFlowSteps` → di-snapshot ke `ApprovalSnapshots`/`ApprovalActivities` saat request dibuat → `ApprovalHistories` sebagai log. Dipakai oleh attendance request, task, shift swap, job position request, user transition, LMS course request, candidate approval, dan employee replacement/additional request — module baru yang butuh approval tinggal manfaatkan engine ini, jangan bikin approval logic sendiri.

## 3. Timeline Evolusi Aplikasi (dari riwayat migration)

Ini bukan changelog per-PR (karena git history sudah squash), tapi rekonstruksi **fase pembangunan fitur** dari tanggal migration dibuat — berguna untuk tahu modul mana yang lebih "matang"/lama vs yang masih baru/rentan berubah.

### September 2025 — Fondasi
RBAC (Spatie Permission, dikonversi ke ULID), master data dasar (agama, pangkat, golongan, kelas, jenis karyawan, bank), struktur jabatan (`m_job_positions`, kompetensi), profil user, dan **fondasi LMS** (kategori, kursus, meeting, materi, kuis).

### Oktober 2025 — Attendance & Approval Engine
Ini bulan paling penting: `attendance_records`, master leave/overtime/permission types, `attendance_requests` + detail per tipe, **approval engine lahir di sini** (`m_approval_flows`, `m_approval_flow_steps`, `approval_snapshots`, `approval_activities`, `approval_histories` — semua dibuat 23-26 Oktober 2025), `leave_user_balances`, dan modul **Task** (task, proof, attachment, review).

### November 2025 — Salary, Kepegawaian, Coaching/Counseling
`salary_components` (awalnya per-jenis), penyesuaian job position (kualifikasi, skor, pangkat/golongan/kelas), `counselings`/`coachings`, `user_documents`, **histori jabatan** (`user_job_positions` — 19 Nov, tabel dengan `start_date`/`end_date`/`status` yang idealnya jadi sumber kebenaran jabatan tapi ternyata belum dipakai approval engine, lihat catatan di dokumen approval), manpower request, job opening, trainer assessment, **modul Assessment** (template, score, rating scale) lengkap dibangun akhir bulan ini.

### Desember 2025 — Recruitment & Payroll
`salary_components` diubah jadi **polymorphic** (2 Des), master layanan dokter, **modul Candidate/Rekrutmen** lengkap (candidate, dokumen, job application, recruitment process — 10-15 Des), **doctor fee submission/items** (fee-based payroll khusus dokter), dan fondasi **Payroll** reguler (`payroll_periods`, `payrolls`, `payroll_items` — 28 Des).

### Januari 2026 — Payroll Dokter & Approval Refinement
`doctor_payroll_periods/payrolls/items` (payroll dokter lengkap dengan field pajak), `job_evaluation_companies`, `program_incentives`, dan **penyempurnaan approval flow**: `flow_type` (default/specific) dan flag `is_flow_changed` ditambahkan (20 Jan) — ini titik di mana mekanisme "flow specific per job_position" mulai bisa dipakai (sebelumnya kemungkinan cuma ada 1 flow per company+module).

### Februari–Maret 2026 — Shift & Company Scoping
**Modul Shift** dibangun lengkap (master shift, jadwal, template, tukar shift, biaya lembur shift — 20 Feb s/d 3 Mar), `attendance_policies`, `extra_hour_allowance_rules`. Pertengahan Maret: **`company_id` ditambahkan ke banyak tabel master sekaligus** (16 Maret, 2 migration terpisah) — indikasi kuat bahwa sistem awalnya dibangun asumsi single-company, baru di titik ini diperluas resmi jadi multi-company/holding.

### April 2026 — Recruitment Detail & Data Cleanup
Detail kandidat diperkaya (keluarga, pendidikan, pelatihan, organisasi, pengalaman kerja, referensi, self-assessment — semua 9 April), `lms_course_baselines`, penyesuaian tipe data job position, dan penambahan soft-delete ke tabel `users` (18 Mei — telat dibanding tabel lain, artinya sebelum ini user tidak bisa di-soft-delete).

### Ringkasan pola yang terlihat
- **Modul makin baru → makin kurang teruji** secara alami (LMS & attendance paling lama umurnya/paling matang; shift & data candidate detail paling baru).
- **Company-scoping ditempel belakangan** (Maret 2026), bukan didesain dari awal — ini menjelaskan kenapa masih ada gap seperti "6 dari 8 company belum ada approval flow sama sekali" (lihat dokumen approval flow) — fitur multi-company-nya "jalan" secara struktur tapi belum semua company benar-benar di-provision lengkap.
- **Approval engine (Okt 2025) lebih tua dari company-scoping (Mar 2026)** — artinya desain approval flow awalnya tidak dirancang untuk skenario multi-company serumit sekarang, dan kemampuan "specific per job_position" + eskalasi lintas-company adalah tambahan yang di-retrofit, bukan desain awal. Ini kenapa penanganannya manual (flow per jabatan) alih-alih otomatis (hierarki dinamis).

## 4. Istilah & Konsep yang Sering Bikin Bingung

- **`m_` prefix** = tabel master data (`m_job_positions`, `m_org_structures`, `m_companies`, dst) — konvensi konsisten di seluruh skema.
- **`user_profiles.job_position_id` vs `user_job_positions`**: ada DUA tempat yang menyimpan jabatan user. `user_profiles.job_position_id` adalah field tunggal (snapshot kondisi sekarang, tanpa histori) dan **ini yang dipakai approval engine**. `user_job_positions` adalah tabel histori (banyak baris per user, ada `start_date`/`end_date`/`status`) yang lebih lengkap tapi **tidak dipakai approval**. Kalau mutasi jabatan, pastikan `user_profiles.job_position_id` ikut ter-update — kalau cuma insert ke `user_job_positions`, approval routing user itu tidak akan berubah.
- **Approval Flow "default" vs "specific"**: `default` = 1 flow generik per company+module (fallback). `specific` = flow yang di-assign ke job_position tertentu lewat `m_approval_flow_assignments`, override `default` kalau match. Kalau job_position pengaju tidak match `specific` manapun DAN tidak ada `default` aktif → request gagal (exception), bukan fallback diam-diam.
- **Approval Snapshot**: begitu request dibuat, langkah-langkah approval **dibekukan** (copy) ke `approval_snapshots.steps_snapshot`. Perubahan di master flow setelahnya TIDAK otomatis memengaruhi request yang sedang berjalan, kecuali disinkronkan manual lewat `syncStepsFromMaster()`.
- **`scope_type` di approval step**: `global` (siapa saja dengan role/job_position itu), `specific_department` (difilter ke 1 org_structure tertentu — ini yang benar-benar berfungsi), `same_department` (**secara kode TIDAK difilter, bug** — efeknya sama seperti `global`, lihat dokumen approval untuk detail).
- **Holding company ID di-hardcode**: `01J2F0C8KQ9M7D4S8RBX6E1ZVP` (Syifa Global Group) muncul hardcoded di `ApprovalFlowForm.php` untuk fitur "pilih approver dari holding". Kalau suatu saat holding company diganti/company baru jadi holding, ini titik yang harus diubah manual.
- **`canAccessPanel()` di `User` model** — **update per 2026-07-05**: sekarang dibatasi `hasRole(['Admin', 'Super Admin'])`, bukan `true` lagi (kemungkinan sudah direvisi sebelum sesi 2026-07-05 dimulai, dokumen ini dulu belum sempat di-update). Role lain diarahkan ke portal karyawan (`/dashboard`, `/karyawanku`, `/pengajuan-absensi`), bukan `/admin` — lihat [README.md](../../README.md) bagian "Portal Karyawan".
- **Approve/Reject/Batalkan attendance request kini bisa dari 2 tempat**: admin (`/admin/attendance-requests/{id}/edit`) dan portal (`/pengajuan-absensi/{id}`), reuse `ApprovalFlowService`+`ApprovalPermissionService` yang sama — jadi hierarki atasan-bawahan sekarang **sebagian** sudah dipakai untuk membatasi siapa yang bisa approve (lewat `ApprovalPermissionService`), meskipun listing data biasa di Filament (lihat `filament-scoping-analysis.md`) masih belum pakai hierarki itu.

## 5. Dokumen Knowledge Lain

- [`approval-flow-role-access.md`](approval-flow-role-access.md) — analisis mendalam approval flow, role access, dan audit kualitas data master terkait (job position, org structure, approval flow config). Baca ini sebelum mengubah apa pun yang berhubungan dengan approval/persetujuan.
- [`../markdown/kesetaraan-jabatan-lintas-perusahaan.md`](../markdown/kesetaraan-jabatan-lintas-perusahaan.md) — analisis penyetaraan jabatan/golongan lintas company (dipakai buat `canBrowseAllCompanies()`, dll).
- [`../changes/`](../changes/) — log perubahan konkret per sesi kerja (bukan analisis, tapi catatan "apa yang sudah dibangun & kenapa"), urut tanggal.
