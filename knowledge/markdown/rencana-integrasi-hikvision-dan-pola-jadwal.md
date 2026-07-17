# Rencana Integrasi Hikvision & Pola Jadwal (Fixed/Flexible/Shift) — 2026-07-17

Dokumen ini merangkum diskusi awal (belum ada kode dieksekusi kecuali 1 enum) soal dua hal yang saling terkait: (1) integrasi data presensi dari Hikvision (`access_logs`), dan (2) rombak pola jadwal karyawan mengikuti model fingerspot.io (Jadwal Tetap/Fleksibel/Shift). Latar belakang lengkap ada di `knowledge/markdown/pengumpulan-data-absensi.md` (analisis sebelum integrasi Hikvision dikonfirmasi) — dokumen ini melanjutkan dari situ.

**Keputusan eksplisit user**: perubahan langsung di aplikasi ini (bukan sistem/aplikasi terpisah semacam fingerspot.io berdiri sendiri). Mulai pelan-pelan, per langkah kecil.

## 1. Temuan dari Data Nyata `access_logs`

Tabel `access_logs` **sudah ada di DB yang sama** (`hris_db`, bukan koneksi DB terpisah) — kemungkinan sudah pernah di-import manual oleh user sebagai contoh. Skema: `employee_id`, `access_datetime`, `access_date`, `access_time`, `first_name`, `last_name`, `person_name`, `person_group`. 2.649 baris pada saat pengecekan (rentang 2026-06-28 s/d 2026-07-13), tiap baris = 1 event scan mentah (bukan pasangan in/out) — konsisten dengan Opsi A (raw scan log, HRIS yang hitung sendiri) yang sudah diputuskan sebelumnya (lihat `pengumpulan-data-absensi.md` §3).

**Gap kritis ditemukan**: dari 219 `employee_id` unik di `access_logs`, cuma **115 (~52%) yang cocok langsung** dengan `users.nip`. Sisanya kemungkinan bukan karyawan sungguhan (contoh: `KELAS12C216`, polanya beda total — mungkin kartu test/magang) atau ID pendek tidak lazim (`9` untuk seorang dokter). **Konsekuensi**: sync otomatis tidak bisa asumsi `employee_id == nip`. Wajib ada tabel mapping eksplisit `employee_id ↔ user_id` + UI resolusi manual untuk yang belum ke-mapping, sebelum job sync bisa jalan penuh otomatis.

Tidak ada kolom `device_id`/lokasi di `access_logs` — kalau nanti ada banyak titik scan berbeda lokasi, tabel ini tidak membedakannya.

`person_group` berisi hierarki organisasi sebagai teks bebas (mis. `RSU SYIFA MEDIKA BANJARBARU > DIREKTUR > ... > CLEANING SERVICE`, dipisah ` > `) — berpotensi bantu tebak company/departemen saat mapping pertama kali dibuat, tapi jangan dijadikan sumber kebenaran permanen (rawan berubah kalau struktur di HikCentral di-rename).

## 2. Analisis Gap Fitur vs fingerspot.io

Yang **sudah ada** di codebase (jangan dibangun ulang):
- Manajemen shift & jadwal: `MasterShift` (`m_shifts`), `ShiftScheduleAssignments`, `ShiftTemplates`+`ShiftTemplateDays`.
- Tukar shift: `ShiftSwapRequests` — approval flow generik, sudah matang.
- Perhitungan telat/lembur/jam kerja dari scan: `computeMetricsFromCheckTimes()` di `AttendanceImportService`.
- Cuti/izin/lembur + approval: `attendance_requests` + `ApprovalFlowService`.
- Kalender libur/cuti bersama (baru, lihat `planning/2026-07-16-master-hari-libur-cuti-bersama.md`).
- Rekap & export absensi: `AttendanceHistoryExport`, `AttendanceReport` (Filament page), `EmployeeAttendanceStatistics`.

Yang **belum ada** — gap nyata:
1. **Toleransi/grace period keterlambatan** — `attendance_policies` cuma `name`/`type`/`value` generik, tidak ada kolom toleransi menit. **Keputusan user**: toleransi diatur **per company** (bukan per shift, bukan global) — cocok ditaruh di `attendance_policies` (kolom `company_id` sudah nullable+scoped di situ), tinggal tambah `type` baru misal `late_tolerance_minutes`.
2. **Rounding/pembulatan jam kerja & lembur** — tidak ada logic pembulatan di manapun.
3. **Pengajuan koreksi absensi** ("lupa absen", "salah scan") — tidak ada jalur resmi sama sekali, karyawan cuma bisa lewat Izin.
4. **Auto shift assignment / roster generator** — `ShiftTemplateDays` datanya ada (pola siklus N-hari) tapi tidak ada job/scheduler yang mengonsumsinya jadi assignment otomatis.
5. **Notifikasi proaktif** (telat, alpha, lupa pulang) — `AttendanceStatus` enum cuma status pasif, tidak ada trigger notifikasi.
6. **Mapping/reconciliation `employee_id` (Hikvision) ↔ `user_id` (HRIS)** — lihat §1, prasyarat teknis sebelum sync bisa jalan.
7. **Monitoring device/sync real-time** — belum ada dashboard status device online/offline atau riwayat sync job.
8. **Multi check-in window** (mis. scan istirahat siang) — `computeMetricsFromCheckTimes` cuma pakai scan pertama & terakhir per hari.

Prioritas yang disepakati secara implisit dari diskusi: #6 (mapping) itu prasyarat teknis mutlak; #1 (toleransi per company) sedang dikerjakan sekarang; #3 (koreksi absensi) sering dibutuhkan harian tapi belum digarap.

## 3. Pola Jadwal: Fixed / Flexible / Shift

User ingin mengikuti pola fingerspot.io: ada 3 jenis jadwal — **Jadwal Tetap**, **Jadwal Fleksibel**, **Jadwal Shift**. Contoh kasus nyata dari user:

- **Holding**: default "Jadwal Karyawan" masuk 08:30 (dengan toleransi dari pengaturan company) pulang 16:30 — tapi ada 1 orang yang dikustom jadi masuk 08:00 pulang 16:00 ("Jadwal Office Boy" misalnya, atau custom perorangan).
- **RS**: shift normal (rotasi pagi/siang/malam, sudah didukung skema sekarang).

### Keputusan desain sejauh ini

**Tidak bikin tabel `schedules` baru terpisah.** `m_shifts` (model `MasterShift`) ternyata **sudah** berperan sebagai master jadwal umum, bukan cuma "shift" dalam arti sempit — kasus fixed holding bahkan sudah dipaksakan masuk situ lewat workaround (bikin baris shift `start_time=08:35` cuma buat lolos jalur perhitungan yang sama, dicatat di `pengumpulan-data-absensi.md` §5). `MasterShift` juga sudah punya kolom `shift_type` (enum `ShiftType`: PAGI/SIANG/MALAM) — pola "1 tabel, kolom tipe menentukan kolom lain relevan atau tidak" sudah ada duluan di tabel yang sama.

Alasan menolak tabel terpisah: `ShiftScheduleAssignments`, `ShiftSwapRequests`, `ShiftOvertimeCosts` semuanya FK langsung ke `shift_id` (bukan polymorphic) — pecah jadi beberapa tabel master berarti 3 tabel itu harus diubah jadi relasi polymorphic, migrasi besar untuk struktur kolom yang sebenarnya hampir identik antar jenis jadwal.

**Rencana kolom tambahan di `m_shifts`** (belum dieksekusi):
- `schedule_type` (enum baru, **sudah dibuat**: `app/Enum/ScheduleType.php` — `FIXED`/`FLEXIBLE`/`SHIFT`). `shift_type` (PAGI/SIANG/MALAM) yang sudah ada cuma relevan/diisi kalau `schedule_type = shift`, null kalau fixed/flexible.
- `required_minutes` (rencana, belum dibuat) — target jam kerja per hari, dipakai `flexible` (dan mungkin fixed/shift buat validasi), null kalau tidak relevan.

**Assignment 3-level (rencana, belum diimplementasi)** — mengikuti pola yang sudah dipakai Approval Engine (`approver_type` + `scope_type: specific_department` di `ApprovalPermissionService`, lihat `knowledge/markdown/manajemen-persetujuan.md`):

| Level | Cara tandai |
|---|---|
| Perusahaan (default company) | `m_shifts.company_id` diisi, `org_structure_id` = `null` |
| Departemen (mis. semua Office Boy) | `company_id` + `org_structure_id` diisi |
| Perorangan | Tidak lewat `m_shifts` — assign langsung lewat `ShiftScheduleAssignments` |

Ditemukan: kolom `org_structure_id` di `m_shifts` **sudah ada dari awal** tapi **tidak dipakai di manapun** (`app/Services/Shift/` tidak ada referensinya) — kolom nganggur, tinggal dimanfaatkan, bukan nambah utang baru.

**Urutan resolusi default shift untuk 1 user di 1 tanggal** (fungsi baru yang perlu ditulis, belum ada — usulan nama `resolveDefaultShiftForUser($user, $date)`):
1. Ada `ShiftScheduleAssignments` eksplisit untuk user+tanggal itu? → pakai itu (paling spesifik).
2. Tidak ada → cari `m_shifts` yang `org_structure_id` cocok departemen user → pakai itu.
3. Tidak ada juga → fallback ke `m_shifts` company itu yang `org_structure_id` null → default company.

**Konsekuensi ke asumsi lama**: assignment `fixed` sebaiknya tidak perlu 1 baris per tanggal selamanya (beda dari `shift` rotasi yang memang butuh per tanggal) — tapi ini mengubah asumsi lama "1 baris `ShiftScheduleAssignments` = 1 hari nyata" yang mungkin dipakai di laporan/swap shift. **Belum ditelusuri** dampaknya ke tempat-tempat yang mengasumsikan itu — perlu dicek sebelum implementasi final.

## 4. Yang Sudah Dieksekusi

- `app/Enum/ScheduleType.php` — enum `FIXED`/`FLEXIBLE`/`SHIFT`, pola sama seperti `ShiftType` yang sudah ada. **Belum** disambungkan ke `m_shifts` atau tabel manapun.

## 5. Belum Diputuskan / Pertanyaan Terbuka

- Apakah rename `m_shifts`/`MasterShift` → `m_schedules`/`Schedule` (supaya nama jujur sesuai peran barunya) atau tetap nama lama + tambah kolom saja. User belum menjawab, minta dibahas lagi nanti.
- Detail kolom `flexible`: target jam kerja tetap 8 jam/hari atau ada aturan lain (boleh kurang asal ditutup hari lain)?
- Urutan kerja: pola jadwal dulu baru Hikvision, atau digarap sekaligus — user masih ingin "tau dulu" sebelum memutuskan.
- Dampak assignment fixed "sekali set" ke fitur yang mengasumsikan "1 baris assignment = 1 hari" (laporan, swap shift) — belum ditelusuri.
- Apakah "assign ke departemen" perlu granularitas lebih dari 3 level `OrgStructure` yang sudah ada (department/division/sub-division), atau cukup pakai `org_structure_id` apa adanya.

## Referensi Terkait

- `knowledge/markdown/pengumpulan-data-absensi.md` — analisis teknis sebelum integrasi Hikvision dikonfirmasi, termasuk 7 pertanyaan terbuka soal akses DB/mapping/sync yang sebagian sudah terjawab di §1 dokumen ini.
- `knowledge/markdown/manajemen-persetujuan.md` — pola `approver_type`+`scope_type` yang dijadikan referensi desain assignment 3-level.
- `app/Models/Shift/MasterShift.php`, `app/Enum/ShiftType.php`, `app/Enum/ScheduleType.php` — kode yang relevan langsung.
