# Rencana: Fitur Extra Shift

Ditulis 2026-07-31, hasil diskusi dengan user — belum diimplementasikan, dokumen ini rencana/skema yang disepakati sebelum mulai coding.

## 1. Apa itu Extra Shift

Karyawan dengan jadwal tipe **Shift** (bukan Fixed/Flexible) bisa ditugaskan/mengajukan **1 shift tambahan penuh** di tanggal yang sama dengan shift normalnya — bukan sekadar tambah jam kerja, tapi benar-benar shift ke-2 yang berdiri sendiri (punya jam mulai-selesai sendiri dari shift type yang diambil).

Contoh kasus nyata: karyawan dijadwalkan shift pagi hari ini, tapi rekannya yang seharusnya masuk shift siang ternyata libur/berhalangan — karyawan itu bisa diminta/mengajukan diri buat cover shift siang juga di hari yang sama.

Dua jalur input direncanakan, tapi **versi pertama cuma jalur 1 yang dibangun**:
1. **Diinput atasan/pihak berwenang** — langsung `approved`, tidak perlu approval lagi (atasan sendiri yang memutuskan). **Ini yang dibangun duluan.**
2. **Diajukan karyawan sendiri** — perlu approval dulu (pola sama seperti Tukar Shift), lewat approval engine generik yang sudah ada di sistem. **Ditunda** — kolom `source`/`created_by` di skema tetap disiapkan supaya jalur ini tinggal ditambah belakangan tanpa migrasi ulang, tapi belum ada UI/service buat jalur ini di versi pertama.

## 2. Kenapa tidak bisa numpang di tabel shift yang sudah ada

Baca [`knowledge/dev/first-overview.md`](../knowledge/dev/first-overview.md) untuk konteks approval engine generik. Temuan analisis sebelum rencana ini ditulis:

- Ada **2 sistem shift paralel** di codebase: sistem lama (`m_shifts`/`shift_schedule_assignments`, masih dipakai attendance/payroll & Tukar Shift) dan sistem baru (`schedules`/`schedule_shift_types`/`schedule_shift_assignments`, dipakai UI roster/rotasi sekarang). Restrukturisasi 18 Juli 2026 baru menyalin data, belum cutover penuh.
- Kedua tabel assignment (`schedule_shift_assignments` maupun `shift_schedule_assignments`) punya `unique(user_id, date)` — **1 karyawan cuma boleh 1 baris shift per hari**. Extra shift butuh baris ke-2 di tanggal yang sama, jadi tidak bisa ditumpuk di tabel yang sama tanpa mengubah constraint yang dipakai banyak kode lain.
- Karena itu, extra shift dapat **tabel sendiri**, bukan baris tambahan di tabel assignment normal.

## 3. Skema tabel — `extra_shift_assignments`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | ulid, PK | |
| `company_id` | FK `m_companies` | |
| `user_id` | FK `users` | Karyawan yang extra shift |
| `covering_user_id` | FK `users`, nullable | Siapa yang digantikan/dicover — nullable, extra shift tidak selalu karena gantiin orang tertentu |
| `shift_type_id` | FK `schedule_shift_types` | Shift yang diambil (BUKAN shift normalnya hari itu) |
| `date` | date | |
| `status` | string | `approved` langsung (kalau `source=assigned`), atau `submitted`→`in_review`→`approved`/`rejected` (kalau `source=requested`) |
| `source` | string enum | `assigned` (atasan input) atau `requested` (karyawan ajukan sendiri) — ini yang nentuin butuh approval atau tidak |
| `reason` | text, nullable | Alasan/catatan |
| `created_by` | FK `users` | Siapa yang input barisnya (beda dari `user_id` kalau `source=assigned`) |
| `created_at`/`updated_at` | timestamp | |

**Sengaja TIDAK ada** `unique(user_id, date)` di tabel ini — itu justru poin utamanya, boleh ada extra shift di tanggal yang sudah punya shift normal.

## 4. Approval untuk jalur `requested` — DITUNDA, tidak dibangun di versi pertama

Kalau nanti dilanjutkan, reuse approval engine generik (`MasterApprovalFlows` → `ApprovalSnapshotRepository` → `ApprovalCreationService`), pola identik `ShiftSwapRequests` (lihat `app/Services/Shift/ShiftSwapRequestService.php`): tambah case `EXTRA_SHIFT_REQUEST` di `ApprovalModuleEnum`, relasi `approvalSnapshot()`/`approvalHistories()` di model.

**Versi pertama**: cuma jalur `assigned`. Baris dibuat langsung dengan `status=approved`, tidak ada snapshot approval, tidak ada halaman pengajuan buat karyawan — cuma halaman input untuk atasan/pihak berwenang.

## 5. Yang sengaja TIDAK dibangun di versi pertama (disederhanakan atas keputusan user)

- **Validasi transisi jam antar-shift** (mis. larangan malam→pagi, siang→malam) — awalnya direncanakan, tapi diputuskan **dilewati dulu**. Versi pertama bebas kombinasi shift apa pun di hari yang sama, tanpa pengecekan jarak/jeda jam. Bisa ditambahkan belakangan kalau ternyata jadi masalah nyata di lapangan — taruh sebagai method validasi terpisah (bandingkan `end_time` shift sebelumnya vs `start_time` shift baru, BUKAN cocokkan nama pagi/siang/malam, karena tidak semua company pakai 3 shift/penamaan itu — lihat `ShiftType` enum yang cuma kosmetik, tidak dipakai perhitungan apa pun).
- **Integrasi ke absensi (access log/clock-in-out)** — ini gap teknis paling besar, BELUM diselesaikan rencana ini. `AccessLogScheduleReader` sekarang menggabungkan semua scan 1 hari jadi 1 pasang check-in/check-out; `ScheduleResolver` cuma resolve 1 shift/hari (`->first()`). Extra shift assignment akan tercatat di tabel baru ini, tapi **belum otomatis** membuat sistem absensi mendeteksi & memisahkan 2 rentang kerja terpisah dalam 1 hari. Untuk versi pertama, kemungkinan pencatatan jam kerja extra shift perlu jalur manual (bukan otomatis dari mesin absen) sampai gap ini diselesaikan terpisah.
- **Insentif/bayaran extra shift di payroll** — belum ada differential per-shift di payroll sama sekali sekarang (payroll cuma peduli total `working_minutes` per periode). Kalau extra shift butuh insentif sendiri, itu permukaan payroll baru, di luar scope rencana ini.

## 6. Urutan implementasi versi pertama (jalur `assigned` saja)

1. Migration `extra_shift_assignments`.
2. Model `ExtraShiftAssignment`.
3. Halaman/form input untuk atasan — pilih karyawan, tanggal, shift type yang diambil, opsional siapa yang dicover + alasan. Langsung `status=approved`, `source=assigned`, `created_by=` user yang input.
4. Tampilan di jadwal/roster supaya extra shift kelihatan menempel di baris shift normal hari itu (bukan cuma tercatat di tabel terpisah tanpa terlihat di UI manapun).

Jalur `requested` (pengajuan karyawan + approval engine) menyusul di fase berikutnya, lihat §1 & §4.
