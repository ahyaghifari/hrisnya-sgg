# Rencana: Tipe Jadwal IBS (Instalasi Bedah Sentral)

Status: **rencana, belum diimplementasi** (2026-10-15). Dokumen ini merangkum kebutuhan, rancangan, dan
keputusan yang masih terbuka sebelum coding dimulai.

## 1. Latar belakang

Perawat IBS di RS bekerja dengan pola yang tidak cocok dengan 3 tipe jadwal yang ada
(`ScheduleType`: `fixed`, `flexible`, `shift`).

Contoh nyata: ada operasi sore. Kepala ruangan meminta beberapa perawat datang lebih dulu untuk
persiapan, lalu setelah siap mereka pulang (scan), istirahat, dan kembali sore (scan) untuk operasi
sampai selesai, lalu pulang (scan lagi). Dalam satu hari ada beberapa kali masuk dan pulang, tanpa jam
baku dan tanpa penentu per tanggal.

Kenapa Flexible tidak cukup: di Flexible, jam kerja tetap terhitung walau karyawan tidak scan pulang.
Di IBS jam kerja harus **efektif mengikuti scan**: hanya pasangan masuk-pulang yang dihitung.

## 2. Aturan hitung

- Scan diproses berurutan dalam satu hari: scan 1 = masuk, scan 2 = pulang, scan 3 = masuk, scan 4 =
  pulang, dan seterusnya.
- Jam kerja = jumlah durasi tiap pasangan masuk-pulang. Jeda antar pasangan tidak dihitung.
- Scan ganjil (masuk tanpa pulang) = pasangan terbuka, 0 menit, ditandai "belum pulang" sampai ada scan
  penutup.
- Tap ganda (selisih <= 60 detik) sudah dibuang oleh `AccessLogScanReader::dropRepeatedTaps()`, tidak
  perlu penanganan baru.
- Tidak ada hitung telat dan pulang cepat; status selalu hadir.
- Lembur tetap lewat pengajuan lembur yang disetujui, bukan otomatis dari scan.

## 3. Penyimpanan

**Keputusan: tetap di `attendance_records`, tambah satu kolom JSON `scan_sessions`. Tidak membuat tabel
terpisah.**

| Kolom | Isi untuk jadwal IBS |
|---|---|
| `check_in_time` | masuk pertama hari itu |
| `check_out_time` | pulang terakhir hari itu |
| `working_minutes` | total menit semua pasangan |
| `scan_sessions` (baru, JSON, nullable) | daftar pasangan, mis. `[{"in":"08:00","out":"09:30","minutes":90},{"in":"16:00","out":"21:00","minutes":300}]` |

Alasan:

- Satu baris per karyawan per hari, jadi rekap presensi, payroll (`work_day_weight`, status), lembur,
  dan koreksi manual HC tetap membaca satu sumber dan tidak perlu diubah.
- Laporan total jam kerja per periode cukup `SUM(working_minutes)`.
- `scan_sessions` hanya untuk tampilan rincian per hari dan audit.

Kapan layak dipisah jadi tabel `attendance_sessions`: bila perlu laporan per sesi (mis. sesi di atas 4
jam) atau HC mengedit satu pasangan dengan jejak audit per sesi. Migrasi dari JSON ke tabel bisa
dilakukan kapan saja tanpa mengubah laporan total.

## 4. Laporan yang dihasilkan

- Per karyawan per periode: total jam kerja, jumlah hari hadir, rata-rata jam per hari.
- Per hari: daftar pasangan masuk-pulang beserta durasinya.

## 5. Titik perubahan kode

1. **Enum dan form jadwal**: `ScheduleType::IBS` (label "Jadwal IBS"), warna badge di
   `SchedulesPage`, opsi di `CreateSchedulePage`. Tipe ini tidak punya baris per hari.
2. **`ScheduleResolver::resolveForUserDate()`**: kembalikan `type => 'ibs'` untuk karyawan yang
   ter-assign jadwal IBS (lewat `ScheduleAssignment`, seperti Fixed/Flexible).
3. **`AccessLogScheduleReader::process()`**: jalur khusus `ibs`, ambil semua scan hari itu,
   pasangkan berurutan. Tambah `IbsScheduleCalculator` di `app/Services/Attendance/Calculators/`.
4. **Migrasi**: kolom `scan_sessions` di `attendance_records`.
5. **Tampilan**: rincian sesi di modal rekap presensi, serta laporan total jam kerja IBS.

## 6. Keputusan terbuka

| # | Pertanyaan | Usulan |
|---|---|---|
| 1 | Pasangan yang melewati tengah malam masuk ke tanggal mana? | Tanggal scan **masuk** |
| 2 | Scan ganjil | Pasangan terbuka, 0 menit, tampil "belum pulang" |
| 3 | `work_day_weight` payroll: 1 hari penuh = berapa jam? | **Perlu jawaban.** Usulan default 8 jam, di-set per jadwal |
| 4 | Siapa yang memakai tipe ini | Di-assign per jabatan atau personal lewat `ScheduleAssignment` |

## 7. Rencana kerja bertahap

1. Migrasi `scan_sessions` + `ScheduleType::IBS` + form jadwal.
2. Resolver + `IbsScheduleCalculator` + jalur di reader, lengkap dengan tes pairing (genap, ganjil,
   lewat tengah malam).
3. Tampilan rincian sesi dan laporan total jam kerja.
4. Resweep data yang sudah ada untuk karyawan IBS.
