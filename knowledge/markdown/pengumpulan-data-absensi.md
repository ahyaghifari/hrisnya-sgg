# Pengumpulan Data Absensi — Analisis Teknis (2026-07-09)

Dokumen ini menjawab pertanyaan "bagaimana data absensi harian karyawan masuk ke sistem?" — ditulis dari pembacaan kode langsung (model, service, migration, route), bukan asumsi. Untuk alur pengajuan cuti/izin/lembur (beda topik, sering tertukar dengan "absensi"), lihat `knowledge/changes/2026-07-05-portal-karyawan-dan-pengajuan-absensi.md` dan `planning/2026-07-05-portal-pengajuan-absensi.md`.

## Temuan Utama

**Tidak ada mekanisme clock-in/clock-out mandiri** (mobile/web dengan validasi lokasi atau foto) di codebase ini. Data absensi harian dikumpulkan lewat dua jalur yang sama sekali terpisah dan tidak saling tahu satu sama lain:

1. **Import batch Excel** — sumber `attendance_records` **saat ini** (lihat §2). Ini bukan desain akhir — konfirmasi user: sumber kebenarannya adalah **alat presensi Hikvision (sidik jari)**, datanya rencananya ditarik **langsung dari database alat tersebut**, bukan lewat file Excel perantara (lihat §3 — ini rencana yang sudah tercatat sebelumnya di `planning/2026-07-05-pwa-portal-karyawan.md` bagian D, tapi **belum ada satu baris kode pun** yang mengimplementasikannya).
2. **Pengajuan oleh karyawan** (cuti/izin/lembur) — sumber `attendance_requests`, mempengaruhi status akhir record via override, bukan sumber jam datang/pulang.

## 1. Model & Tabel Terlibat

| Model | Lokasi | Peran |
|---|---|---|
| `AttendanceRecords` | `app/Models/Attendance/AttendanceRecords.php` | Record harian per karyawan — hasil akhir absensi (jam masuk/pulang, telat, lembur, dst) |
| `AttendanceUploads` | `app/Models/Attendance/AttendanceUploads.php` | Tracking 1 sesi import Excel (status, jumlah baris sukses/gagal, error log) |
| `AttendanceRequests` + detail (`...LeaveDetails`, `...PermissionDetails`, `...OvertimeDetails`) | `app/Models/Attendance/` | Pengajuan cuti/izin/lembur — modul terpisah, lihat dokumen lain |
| `AttendanceRequestAttachments` | `app/Models/Attendance/` | Lampiran file, praktiknya cuma dipakai request Lembur |
| `AttendancePolicies` | `app/Models/Attendance/AttendancePolicies.php` | Config level company — **hanya** dipakai untuk `min_total_working_hours_per_month` (payroll), **bukan** aturan jam masuk/telat (lihat §4) |
| `MasterShift`, `ShiftScheduleAssignments` | `app/Models/Shift/` | Jadwal shift — jadi acuan jam masuk/pulang seharusnya |
| `ShiftTemplates`, `ShiftTemplateDays` | `app/Models/Shift/` | Pola siklus N-hari (`day_number` → shift/libur) untuk generate assignment — datanya ada, tapi tidak ditemukan job/scheduler yang mengonsumsinya jadi assignment otomatis |
| `ShiftSwapRequests` | `app/Models/Shift/` | Pengajuan tukar shift antar karyawan (lihat §6) |

Kolom kunci `attendance_records` (gabungan 2 migration: `2025_10_14_140501_create_attendance_records_table.php` + `2026_03_04_154425_adjustment_attendance_records_table.php`):

`user_id`, `nip`, `date`, `schedule_id` → FK `ShiftScheduleAssignments`, `shift_id` → FK `MasterShift`, `check_in_time`, `check_out_time`, `working_minutes`, `late_minutes`, `early_leave_minutes`, `overtime_minutes`, `permission_minutes`, `status`, `source`.

**Tidak ada kolom lokasi (lat/long), foto, atau device-id sama sekali** — baik di migration awal maupun adjustment-nya.

## 2. Alur Import Excel (Implementasi Saat Ini — Bukan Sumber Akhir)

File: `app/Services/Attendance/AttendanceImportService.php`.

1. User (biasanya HC) upload file Excel — kemungkinan besar export dari mesin fingerprint/access-control kantor (header terdeteksi punya alias `mesin`/`machine`/`kantor`/`location`, tapi ini cuma informasi asal baris, **bukan** hasil validasi GPS real-time).
2. Baris-baris "scan" digabung per NIP + tanggal — scan pertama jadi `check_in_time`, scan terakhir jadi `check_out_time`.
3. Untuk tiap baris hasil grouping, sistem resolve jadwal shift karyawan pada tanggal itu (`AttendanceImportRepository::findShiftAssignmentForDate`), lalu `computeMetricsFromCheckTimes()` (baris 516–669) menghitung:
   - `late_minutes` — selisih kalau `check_in_time` > jam mulai shift.
   - `early_leave_minutes` — selisih kalau `check_out_time` < jam selesai shift.
   - `working_minutes` — 4 cabang logic (tepat waktu/telat × tepat waktu/pulang cepat), termasuk handle shift overnight (`is_overnight`).
4. Sebelum disimpan final, status & minutes bisa di-override oleh pengajuan yang sudah **approved**: prioritas leave (cuti) tertinggi, lalu overtime/permission.
5. Progress & error per baris dicatat di `attendance_uploads` (`record_count`, `processed_count`, `error_count`, `error_log` json).

Tidak ada langkah validasi geofencing atau syarat foto di mana pun dalam service ini — karena input datanya memang bukan hasil interaksi karyawan real-time, tapi hasil import bulk.

## 3. Rencana Real: Integrasi Langsung ke Database Hikvision (Belum Diimplementasikan)

Dikonfirmasi user: sumber data presensi yang sebenarnya adalah **alat presensi Hikvision berbasis sidik jari** — data **ditarik langsung dari database alat/software Hikvision**, bukan lewat file Excel perantara seperti §2. Ini pertama kali tercatat di `planning/2026-07-05-pwa-portal-karyawan.md` bagian D ("Integrasi Presensi Hikvision") sebagai item riset terbesar sebelum fitur "Presensi" bisa dikerjakan — sampai saat ini **belum ada kode/koneksi/job yang mengimplementasikannya** (grep `hikvision`/`fingerprint`/`ISAPI` ke seluruh `app/`, `config/`, `database/` nihil, cuma nongkrong di dokumen planning).

**Keputusan yang sudah dikonfirmasi (2026-07-09)**: HikCentral (platform access-control Hikvision) sebenarnya punya modulnya sendiri — bukan cuma mesin scan mentah, tapi juga **Time & Attendance (T&A) module** yang bisa menghitung sendiri telat/lembur berdasarkan shift yang dikonfigurasi di HikCentral. ini membuka 2 opsi integrasi yang beda konsekuensi:

- **Opsi A — tarik data mentah (raw scan log), HRIS yang hitung sendiri** telat/lembur/shift lewat logic yang sudah ada (`computeMetricsFromCheckTimes`, §2). **✅ Ini yang dipilih.** HikCentral cuma diperlakukan sebagai sumber jam scan pengganti Excel — seluruh logic shift/holding-vs-RS/multi-company (§5) tetap sepenuhnya dikendalikan dari sisi HRIS, tidak bergantung pada konfigurasi T&A di HikCentral.
- **Opsi B — pakai hasil kalkulasi T&A HikCentral** (ditolak): akan berarti aturan telat/lembur (termasuk kasus khusus holding "masuk sebelum 08:35, pulang bebas") harus dikonfigurasi ulang di HikCentral, bukan di kode kita — dan HikCentral tidak tentu fleksibel untuk kasus non-standar seperti itu.

Konsekuensi dari keputusan ini: integrasi Hikvision **hanya perlu akses ke tabel transaksi scan mentah** (bukan tabel hasil T&A) — modul Access Control, Video, Visitor Management, Alarm, dll milik HikCentral **sama sekali tidak relevan/tidak disentuh**, cuma bagian log kehadiran per orang per device yang dipakai.

Pertanyaan yang masih terbuka dari dokumen tersebut (belum dijawab, jadi berlaku ulang di sini):

1. **Jenis akses ke DB alat** — Hikvision biasanya expose data lewat DB on-prem software-nya (mis. iVMS-4200/HikCentral, umumnya SQL Server) atau lewat ISAPI/HTTP event push dari device. Perlu dipastikan skema di lapangan yang mana; "ditarik langsung dari database" mengarah ke opsi konek-DB-langsung, bukan API.
2. **Koneksi DB kedua** — kalau benar konek langsung, perlu `config/database.php` connection baru (kemungkinan driver `sqlsrv` kalau SQL Server), **read-only**, jangan pernah tulis ke DB tersebut.
3. **Mapping karyawan** — field employee ID di sisi Hikvision (biasanya `pin`/`employeeNo`) perlu dipetakan ke `nip`/`user_id` di `attendance_records` — perlu dicek dulu apakah NIP HRIS ini identik dengan yang didaftarkan di alat.
4. **Mekanisme sync** — kemungkinan job terjadwal (`php artisan schedule`) yang poll tabel log presensi Hikvision (mis. `CheckInOut`/`ACC_TransactionRecords`, tergantung versi software), insert ke `attendance_records` dengan `source` baru (kolom `source` sudah ada, tinggal tambah value mis. `hikvision`) — perlu hindari duplikasi (cek `user_id`+`date`+`check_in_time` sebelum insert, atau `firstOrCreate`).
5. **Frekuensi sync** — real-time (device push event) atau polling interval? Menentukan apakah butuh queue listener atau cukup scheduled command.
6. **Akses jaringan** — kalau device Hikvision ada di jaringan lokal kantor/RS/hotel yang berbeda dari server hosting (staging/production di-deploy lewat Bitbucket Pipelines), server Laravel harus bisa reach DB/device itu — ini berpotensi jadi **blocker infrastruktur**, bukan cuma soal kode.
7. **Konsekuensi ke perhitungan §4-§5 (shift/multi-company)** — kalau nanti sumbernya per-device (per lokasi kantor/RS/hotel), pemetaan device→company perlu jelas juga, terutama kalau 1 company (misal holding) punya banyak device presensi di lokasi berbeda.

Begitu ada keputusan konkret soal 7 poin di atas, `AttendanceImportService` (§2) kemungkinan besar akan **digantikan atau disandingkan** dengan service sync Hikvision baru — logic perhitungan telat/lembur (`computeMetricsFromCheckTimes`) kemungkinan tetap reusable karena inputnya sama-sama "pasangan check-in/check-out per NIP per tanggal", cuma sumber datanya beda (DB eksternal vs file Excel).

## 4. Endpoint API — Semuanya untuk Pengajuan, Bukan Absen Harian

`routes/api.php`, prefix `attendance` (baris 55–63), ditangani `App\Http\Controllers\Api\Attendance\AttendanceRequestController` → `AttendaceReqService`:

- `GET /attendance/requests`, `GET /attendance/requests/{id}`
- `POST /attendance/overtime`, `/attendance/leave`, `/attendance/permission`
- `GET /attendance/leave/balance/{typeId}`

Parameter yang diterima murni field bisnis (alasan, rentang tanggal, jam, lampiran) — **tidak ada param lat/long, foto, atau device_id**.

Satu-satunya endpoint bernama "clock-in" di seluruh aplikasi adalah `POST /meeting/{meetingId}/clock-in` (`app/Http/Controllers/Api/LMS/AttendanceController.php`) — ini absensi **peserta meeting/training LMS**, tidak berhubungan dengan absensi kepegawaian harian.

## 5. Keterkaitan dengan Shift — dan Kenyataan Multi-Company yang Lebih Rumit

`AttendanceRecords` punya relasi ke `MasterShift` (`shift_id`) dan `ShiftScheduleAssignments` (`schedule_id`). Proses import me-resolve shift yang berlaku untuk karyawan pada tanggal tersebut, lalu jam mulai/selesai shift itu jadi acuan perhitungan telat/pulang cepat/jam kerja (lihat §2 poin 3).

Realitas bisnisnya: ini sistem HRIS **multi-company** dengan aturan jam kerja yang beda-beda per jenis perusahaan — holding (jam kantor tetap, mis. masuk paling lambat 08:35, pulang bebas) vs rumah sakit/hotel (shift bergilir pagi/siang/malam). Yang ditemukan di kode:

- **Tidak ada kolom "tipe company"** di `m_companies` (`app/Models/Master/Companies.php`) — fillable-nya cuma `code, name, location, is_active`. Tidak ada `category`/`type` (holding/RS/hotel) di migration manapun. Sama seperti temuan gap payroll di `knowledge/markdown/analisis-payroll.md` §6.1, sistem **tidak tahu secara struktural** company mana yang "harusnya" pakai jam tetap vs shift — itu murni soal siapa yang mengisi data assignment-nya.
- **Tidak ada jalur "non-shift" di kode.** `AttendanceImportRepository::findShiftAssignmentForDate` (`app/Repositories/Attendance/AttendanceImportRepository.php:17-23`) cuma cari baris `ShiftScheduleAssignments` — kalau tidak ketemu, `computeMetricsFromCheckTimes()` (`AttendanceImportService.php:516-618`) jatuh ke cabang lain:

```php
if ($shift) {
    // hitung late_minutes, early_leave_minutes, working_minutes vs shift start/end
} elseif ($checkInTime && $checkOutTime) {
    // Tidak ada data shift — fallback murni selisih check-in/out
    $workingMinutes = $checkInTime->diffInMinutes($checkOutTime);
}
```

  Di cabang fallback ini, `late_minutes`/`early_leave_minutes` **tidak pernah dihitung** (tetap `null`), status default `'present'` — **tidak peduil jam berapa pun karyawan check-in**. Fallback ini **bukan** ke `AttendancePolicies` (itu cuma dipakai payroll, lihat §1) — jadi aturan "masuk sebelum 08:35" milik holding **tidak bisa ditegakkan** kecuali karyawan holding tetap diberi baris `ShiftScheduleAssignments` harian (workaround: shift dengan `start_time = 08:35`, dipakai bukan untuk makna "shift" sungguhan tapi supaya lolos jalur perhitungan yang sama seperti RS).
- Kesimpulan: skema shift sekarang **cukup untuk RS** (yang memang berbasis shift asli), tapi untuk holding ini jadi solusi paksa, bukan jalur native — dan **belum ada bukti kode/dokumen** yang menunjukkan hotel dibedakan pola shift-nya dari RS (pagi/siang/malam generik dipakai di mana-mana, `App\Enum\ShiftType`: `PAGI/SIANG/MALAM`).

## 6. Tukar Shift ("Shift Bertukar-tukar Sewaktu Waktu")

Mekanismenya sudah ada dan cukup matang: `app/Services/Shift/ShiftSwapRequestService.php`, model `ShiftSwapRequests`.

- Alur: request dibuat (`status='in_review'`) → snapshot dibuat lewat `ApprovalCreationService::createSnapshot(moduleType: 'swap_shift_request', ...)` — **ikut Approval Engine generik** yang sama, persis seperti didokumentasikan di `knowledge/markdown/manajemen-persetujuan.md:181`.
- 2 mode: **schedule swap** (dua assignment existing saling tukar) dan **off-day** (requester kasih shift-nya ke orang yang hari itu libur/tidak ada assignment).
- Validasi (`validateScheduleSwapRules`, baris 93-139): harus bulan kalender yang sama, tidak boleh bentrok dengan assignment tujuan, dan kompatibilitas jenis shift (pagi↔siang boleh, malam cuma sesama malam).
- **Tidak ada batas H-berapa** (lead-time) — swap untuk besok pun tidak diblokir. Ini relevan kalau "bertukar sewaktu-waktu" di lapangan berarti sangat mendadak (hari-H) — sistem tidak mencegahnya sama sekali, cuma soal apakah approver sempat approve tepat waktu.
- Efek saat approved (`ApprovalFlowService.php:216-270`): swap tanggal sama → tukar `shift_id` antar 2 baris assignment (menghindari constraint unique `user_id`+`date`); swap tanggal beda → tukar `user_id` antar baris; kasus off-day → pindahkan `user_id` requester ke baris target. Ada re-check konflik yang mungkin muncul setelah submit (lempar `RuntimeException` kalau bentrok). Reject cuma ubah `status`, tidak mengubah assignment.

## 7. Kesimpulan — Gap Nyata, Bukan Sekadar "Belum Sempat"

Tidak ada dokumen lain di `knowledge/markdown/` yang membahas geofencing/GPS/foto, integrasi Hikvision, perbedaan aturan jam kerja holding vs RS vs hotel, maupun batas waktu tukar shift secara teknis mendalam — pencarian eksplisit ("shift", "holding", "rumah sakit", "hotel", "tukar shift", "hikvision") cuma menemukan satu referensi di `planning/2026-07-05-pwa-portal-karyawan.md` bagian D (lihat §3), sisanya nihil. Artinya:

- Arsitektur `attendance_records` didesain sebagai **target import batch**, bukan tabel yang menerima write real-time per kejadian absen — dan itu berlaku baik untuk import Excel (§2, jalan sekarang) maupun rencana sync Hikvision (§3, belum jalan): keduanya sama-sama batch/periodik, bukan per-scan real-time.
- **Sumber data presensi akan berubah** dari Excel manual (§2) ke tarik-langsung-DB-Hikvision (§3) — ini perubahan besar yang masih di tahap perencanaan, 7 pertanyaan terbuka di §3 perlu dijawab dulu sebelum implementasi.
- Skema shift **cukup untuk RS** (shift asli, tukar shift jelas), tapi holding harus "dipaksakan" masuk skema shift juga supaya aturan jam masuknya bisa ditegakkan — bukan jalur native berbasis `AttendancePolicies` atau company-type seperti yang mungkin diasumsikan.
- Belum ada bukti sistem membedakan pola shift hotel dari RS — kalau kebutuhannya beda (mis. shift housekeeping/FO vs shift perawat), ini gap tambahan yang perlu didalami/didesain terpisah.
- Kalau ke depan dibutuhkan rule engine berbasis company-type (holding/RS/hotel) atau pemetaan device Hikvision → company/lokasi, ini butuh **keputusan desain baru** — bukan cuma nambah endpoint/kolom, karena skema tabel, service layer, dan asumsi "1 baris dari hasil grouping import" / "1 shift = 1 sumber kebenaran jam kerja" semuanya perlu dipikirkan ulang.

## 8. Relasi Antar Tabel (Skema Saat Ini)

Dibaca langsung dari migration (`database/migrations/*attendance*`, `*shift*`), bukan asumsi dari nama kolom.

### 8.1 Diagram Ringkas

```
m_companies ──┬─▶ m_shifts ──┬─▶ shift_schedule_assignments ──┬─▶ attendance_records ◀── attendance_uploads
              │              │   (user_id, company_id,        │   (schedule_id, shift_id
              │              │    shift_id, created_by:users)  │    ditambah belakangan,
              │              │                                 │    lihat §1)
              │              └─▶ shift_template_days ◀── shift_templates
              │                  (shift_id nullable)            (company_id)
              │
              ├─▶ shift_swap_requests
              │   (requester_id/target_id → users,
              │    req_schedule_id/target_schedule_id → shift_schedule_assignments,
              │    approved_by/rejected_by → users)
              │
              ├─▶ shift_overtime_costs ◀── m_overtime_types
              │   (shift_id)                (overtime_type_id nullable)
              │
              └─▶ attendance_policies (company_id nullable)

users ──▶ attendance_requests ──┬─▶ attendance_req_leave_details ──▶ m_leave_types
      (user_id required,        ├─▶ attendance_req_permission_details ─┬▶ m_permission_types
       approved_by/             │                                      └▶ users (substitute_user_id)
       cancelled_by nullable)   ├─▶ attendance_req_overtime_details ──▶ m_overtime_types
                                 └─▶ attendance_req_attachments
```

### 8.2 Detail FK per Tabel

| Tabel | Kolom FK | Referensi | Wajib? | Perilaku delete |
|---|---|---|---|---|
| `attendance_records` | `user_id` | `users` | nullable | `nullOnDelete` |
| | `schedule_id` *(ditambah belakangan)* | `shift_schedule_assignments` | nullable | `nullOnDelete` |
| | `shift_id` *(ditambah belakangan)* | `m_shifts` | nullable | `nullOnDelete` |
| `attendance_uploads` | `uploaded_by` | `users` | nullable | `nullOnDelete` |
| `attendance_requests` | `user_id` | `users` | **wajib** | `cascadeOnDelete` |
| | `approved_by`, `cancelled_by` | `users` | nullable | `nullOnDelete` |
| `attendance_req_leave_details` | `request_id` | `attendance_requests` | **wajib** | `cascadeOnDelete` |
| | `leave_type_id` | `m_leave_types` | nullable | `nullOnDelete` |
| `attendance_req_permission_details` | `request_id` | `attendance_requests` | **wajib** | `cascadeOnDelete` |
| | `permission_type_id` | `m_permission_types` | nullable | `nullOnDelete` |
| | `substitute_user_id` | `users` | nullable | `nullOnDelete` |
| `attendance_req_overtime_details` | `request_id` | `attendance_requests` | **wajib** | `cascadeOnDelete` |
| | `overtime_type_id` | `m_overtime_types` | nullable | `nullOnDelete` |
| `attendance_req_attachments` | `request_id` | `attendance_requests` | **wajib** | `cascadeOnDelete` |
| `attendance_policies` | `company_id` | `m_companies` | nullable | `nullOnDelete` |
| `m_shifts` | `company_id` | `m_companies` | **wajib** | `cascadeOnDelete` |
| | `org_structure_id` | `m_org_structures` | nullable | `nullOnDelete` |
| `shift_schedule_assignments` | `company_id` | `m_companies` | **wajib** | `cascadeOnDelete` |
| | `user_id` | `users` | **wajib** | `cascadeOnDelete` |
| | `shift_id` | `m_shifts` | **wajib** | `restrictOnDelete` (satu-satunya yang restrict, bukan cascade/null) |
| | `created_by` | `users` | nullable | `nullOnDelete` |
| `shift_templates` | `company_id` | `m_companies` | **wajib** | `cascadeOnDelete` |
| `shift_template_days` | `shift_template_id` | `shift_templates` | **wajib** | `cascadeOnDelete` |
| | `shift_id` | `m_shifts` | nullable (kosong = hari libur, `is_off_day`) | `nullOnDelete` |
| `shift_swap_requests` | `company_id` | `m_companies` | **wajib** | `cascadeOnDelete` |
| | `requester_id`, `target_id` | `users` | **wajib** | `cascadeOnDelete` |
| | `req_schedule_id`, `target_schedule_id` | `shift_schedule_assignments` | **wajib** | `cascadeOnDelete` |
| | `approved_by`, `rejected_by` | `users` | nullable | `nullOnDelete` |
| `shift_overtime_costs` | `shift_id` | `m_shifts` | **wajib** | `cascadeOnDelete` |
| | `overtime_type_id` | `m_overtime_types` | nullable | `nullOnDelete` |

### 8.3 Catatan Desain

- **Unique constraint kunci**: `attendance_records` unik per (`nip`, `date`); `shift_schedule_assignments` unik per (`user_id`, `date`, nama constraint `user_date_unique`) — inilah yang membuat mekanisme tukar shift (§6) harus "saling tukar nilai" antar 2 baris, bukan sekadar update satu baris, supaya tidak melanggar constraint ini.
- **`schedule_id`/`shift_id` di `attendance_records` ditambahkan belakangan** (migration `2026_03_04`), bukan dari migration awal — konsisten dengan temuan §5 bahwa keterkaitan shift-ke-attendance adalah penambahan setelah desain awal, bukan fondasi dari awal.
- **`shift_id` di `shift_schedule_assignments` pakai `restrictOnDelete`** (satu-satunya FK dengan perilaku ini di seluruh cluster attendance/shift) — sengaja mencegah shift dihapus kalau masih dipakai assignment aktif, beda dari FK lain yang lebih longgar (`nullOnDelete`/`cascadeOnDelete`).
- **`m_companies` jadi FK wajib** di `m_shifts`, `shift_schedule_assignments`, `shift_templates`, `shift_swap_requests` — artinya seluruh siklus shift memang di-scope per company (konsisten dengan sistem multi-company), tapi seperti dicatat di §5, **tidak ada kolom yang membedakan *jenis* company** (holding/RS/hotel) di `m_companies` itu sendiri.
- **Tidak ada FK sama sekali dari cluster shift ke cluster attendance_requests** — keduanya cuma "bertemu" secara logis lewat override status di `AttendanceImportService` (§2 poin 4), bukan lewat relasi tabel.

## Referensi Terkait

- `planning/2026-07-05-pwa-portal-karyawan.md` bagian D — catatan awal rencana integrasi Hikvision (lihat §3), belum ada progres implementasi.
- `knowledge/markdown/manajemen-persetujuan.md` — approval engine yang menangani hasil pengajuan cuti/izin/lembur & tukar shift (`attendance_request`, `swap_shift_request`).
- `knowledge/markdown/analisis-payroll.md` — payroll ikut baca `attendance_records` untuk komponen berbasis kehadiran; §6.1 punya gap serupa (tidak ada kolom kategori company).
- `planning/2026-07-05-portal-pengajuan-absensi.md` — histori pembangunan portal pengajuan (bukan clock-in).
