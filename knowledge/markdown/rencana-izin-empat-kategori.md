# Rencana: Izin 4 Kategori (Meninggalkan Jam Kerja, Tidak Masuk Seharian, Sakit, Khusus)

Ditulis 2026-07-30, hasil diskusi ulang bentuk "Izin" dengan user — belum diimplementasikan, dokumen ini adalah rencana/skema yang disepakati sebelum mulai coding. Baca [`analisis-payroll-4-pembacaan-excel-skema-aktual.md`](analisis-payroll-4-pembacaan-excel-skema-aktual.md) §7 dulu untuk konteks kenapa `PermissionTypeCategoryEnum` yang ada sekarang cuma py 4 case dan kenapa "Izin Meninggalkan Jam Kerja" tidak berdampak payroll.

## 1. Kenapa direvisi

Sistem Izin yang ada sekarang cuma dibangun buat 1 kelompok (Meninggalkan Jam Kerja: Terlambat/Pulang Cepat/Meninggalkan Jam Kerja Tengah Hari) + 1 kelompok flat (Izin Seharian). User klarifikasi bahwa kebutuhan sebenarnya ada **4 kelompok**, 2 di antaranya (Sakit, Khusus) sama sekali belum ada bentuk datanya.

## 2. Struktur final — 2 level pemilihan

**Level 1 (dipilih dulu) — 4 kelompok besar:**

| Kelompok | Bentuk tanggal | Lampiran | Punya anak (level 2)? |
|---|---|---|---|
| 1. Izin Meninggalkan Jam Kerja | 1 tanggal (`date` saja) | tidak ada | **Ya** — 3 anak |
| 2. Izin Tidak Masuk Seharian | 1 tanggal (`date` saja) | opsional | Tidak — flat |
| 3. Izin Sakit | rentang (`start_date`–`end_date`) | **wajib kalau total hari > 2**, selain itu opsional | Tidak — flat |
| 4. Izin Khusus | rentang (`start_date`–`end_date`), prefill hari per jenis | opsional | **Ya** — 8 anak |

**Level 2 (baru muncul kalau pilih kelompok 1 atau 4):**

- Kelompok 1 → 3 anak, masing-masing `category` sendiri (sudah ada di enum sekarang):
  - Izin Terlambat (`izin_terlambat_masuk`)
  - Izin Pulang Duluan (`izin_pulang_cepat`)
  - Izin Meninggalkan Jam Kerja tengah hari (`izin_meninggalkan_jam_kerja`)
- Kelompok 4 → 8 anak, **SEMUA `category` yang sama** (`izin_khusus`, 1 nilai baru), dibedakan lewat `name` + `default_days`:

  | Nama | `default_days` | Catatan |
  |---|---|---|
  | Izin Menikah (Karyawan) | 3 | Pemohon bisa geser manual jadi 5 hari kalau lokasi >150km — sistem TIDAK menghitung jarak, murni manual + divalidasi approver secara lisan |
  | Izin Menikah (Anak Sah Karyawan) | 2 | |
  | Izin Istri Melahirkan/Keguguran | 2 | |
  | Izin Keluarga Inti Meninggal (suami/istri/ortu/anak sah) | 3 | |
  | Izin Keluarga Serumah Meninggal | 2 | |
  | Izin Mengkhitankan/Membaptiskan Anak | 2 | |
  | Izin Musibah (kebakaran, kebanjiran, dst) | `null` | Durasi murni pertimbangan pimpinan, tidak ada prefill |
  | Izin Ibadah Haji | `null` | Durasi ibadah + 2 hari sebelum berangkat + 2 hari setelah tiba — variabel, tidak ada prefill |

  `default_days` cuma prefill `end_date` di form (`start_date + default_days - 1`), **tidak mengunci** — pemohon tetap bebas ubah manual, approval yang menilai kewajarannya (lewat percakapan lisan, bukan validasi sistem).

## 3. Perubahan skema

### 3.1 Enum — `App\Enum\Attendance\PermissionTypeCategoryEnum`

Tambah 2 case baru (existing 4 case tidak berubah):

```php
enum PermissionTypeCategoryEnum: string
{
    case IZIN_TERLAMBAT_MASUK = 'izin_terlambat_masuk';
    case IZIN_PULANG_CEPAT = 'izin_pulang_cepat';
    case IZIN_SEHARIAN = 'izin_seharian';
    case IZIN_MENINGGALKAN_JAM_KERJA = 'izin_meninggalkan_jam_kerja';
    case IZIN_SAKIT = 'izin_sakit';       // baru
    case IZIN_KHUSUS = 'izin_khusus';     // baru
}
```

### 3.2 Migration — `attendance_req_permission_details`

Tambah 2 kolom nullable (backward compatible, tidak sentuh baris lama):

- `end_date` (date, nullable) — dipakai Sakit & Khusus. Null = izin 1 hari (kelompok 1 & 2 tetap pakai `date` saja seperti sekarang).
- `total_days` (decimal 5,2, nullable) — dihitung saat submit, pola sama seperti `attendance_req_leave_details.total_days` yang sudah ada di Cuti.

### 3.3 Migration — `m_permission_types`

Tambah 1 kolom:

- `default_days` (integer, nullable) — prefill form untuk jenis Izin Khusus. Kosong untuk semua jenis izin lain (termasuk Musibah & Haji, lihat tabel §2).

### 3.4 Master data baru (seeder / manual lewat Filament `PermissionTypeResource` yang sudah ada)

- 1 baris **Izin Sakit** (`category = izin_sakit`).
- 8 baris **Izin Khusus** (`category = izin_khusus` semua, beda `name`+`default_days`, lihat tabel §2).

## 4. Form pengajuan (`App\Livewire\SubmitPermissionRequest`)

- Jadi 2 level select: pilih kelompok dulu (4 opsi tetap/hardcode di komponen, bukan dari DB — grouping ini konsep UI, bukan data), baru kalau kelompok 1/4 muncul dropdown ke-2 (isi dari `PermissionTypes::whereIn('category', ...)`).
- Field tanggal berubah sesuai kelompok: kelompok 1&2 → 1 date picker; kelompok 3&4 → 2 date picker (start/end) + `total_days` dihitung otomatis dari selisih.
- Field jam (`start_time`/`end_time`) **cuma wajib untuk kelompok 1** — ini benerin gap yang sudah ditemukan sebelumnya (form lama maksa isi jam untuk SEMUA jenis izin, termasuk yang seharian).
- Field upload lampiran (baru, reuse pola dari form Lembur yang sudah ada attachment) — **cuma muncul untuk kelompok 3 (Sakit) & 4 (Khusus)**. Kelompok 1 (Meninggalkan Jam Kerja) & 2 (Tidak Masuk Seharian) TIDAK punya field lampiran sama sekali (bukan cuma opsional — field-nya memang tidak ditampilkan).
- Validasi lampiran **wajib** cuma kalau kelompok = Sakit DAN `total_days > 2`. Kelompok 4 (Khusus) tetap opsional walau field-nya muncul.

## 5. Payroll & kuota — TIDAK ADA calculator baru

`IZIN_SAKIT` dan `IZIN_KHUSUS` **sengaja tidak didaftarkan** ke `PayrollCalculatorRegistry` — otomatis tidak ada dampak payroll sama sekali, persis pola `IZIN_MENINGGALKAN_JAM_KERJA` yang sudah final sekarang (lihat `analisis-payroll-4` §7B.4). Tidak perlu bikin no-op calculator, absennya entry di registry sudah cukup.

Kedua category baru ini juga **dikeluarkan** dari filter `AttendaceReqService::getIzinPeriodeUsage()`/`assertWithinIzinPeriodeLimit()` — kuota `izin_max_per_periode` cuma berlaku untuk 4 category lama (Terlambat/Pulang Cepat/Meninggalkan Jam Kerja/Seharian). Sakit & Khusus **tidak ada limit otomatis sama sekali** — semua penilaian "wajar atau tidak" diserahkan ke approver, bukan sistem (dikonfirmasi eksplisit oleh user, bukan asumsi).

## 6. Approval — tidak ada perubahan

Semua kelompok izin (termasuk Sakit & Khusus) tetap lewat 1 flow approval yang sama (`MasterApprovalFlows` generik, tidak ada routing khusus per kategori). Pertimbangan kasus per-kasus (misal validasi jarak >150km, kewajaran durasi musibah) dilakukan approver secara lisan/manual di luar sistem — bukan hal yang perlu di-enforce di kode.

## 7. Yang sengaja TIDAK dibangun (di luar scope sesi ini)

- Tidak ada perhitungan jarak otomatis (geolocation/maps API) untuk validasi >150km — pemohon isi sendiri, approver yang menilai.
- Tidak ada quota/limit "sekali seumur kerja" untuk Izin Khusus (misal nikah cuma boleh diklaim 1x) — belum ditentukan, sengaja dibiarkan tanpa enforcement dulu.
- Tidak ada UI approver untuk "override jumlah hari saat approve" — approver cuma bisa approve/reject apa adanya yang diajukan, bukan mengubah rentang tanggalnya.

## 8. Kuota Izin dipisah per kelompok (REVISI keputusan lama)

Ditambahkan 2026-07-30, hasil diskusi lanjutan — **membalik keputusan** yang sebelumnya didokumentasikan di `planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md` (dulu sengaja digabung jadi 1 kuota `IZIN_MAX_PER_PERIODE`).

- Kuota sekarang dipisah jadi **2 setting independen**, masing-masing default **2** (pola sama `AttendanceSettingEnum` yang sudah ada — integer, company-scoped, otomatis muncul sebagai field baru di halaman Pengaturan Absensi tanpa ubah blade):
  - Kelompok 1 (Izin Meninggalkan Jam Kerja — mencakup 3 sub-jenis: Terlambat/Pulang Cepat/Meninggalkan Jam Kerja Tengah Hari, dihitung gabung sebagai 1 kuota).
  - Kelompok 2 (Izin Tidak Masuk Seharian).
- Setting lama `IZIN_MAX_PER_PERIODE` **dihapus** (bukan dipertahankan sebagai fallback). **Tidak ada backfill** — semua company reset ke default 2 untuk masing-masing kuota baru, walau sebelumnya company itu pernah override `IZIN_MAX_PER_PERIODE` ke nilai lain (dikonfirmasi eksplisit oleh user, bukan asumsi).
- Kelompok 3 (Sakit) & 4 (Khusus) tetap **tidak ada limit sama sekali** (sudah diputuskan di §5) — tidak terpengaruh pemisahan ini.
- `AttendaceReqService::getIzinPeriodeUsage()`/`assertWithinIzinPeriodeLimit()` perlu jadi 2 pemanggilan terpisah (per kelompok), bukan 1 hitungan gabungan.

## 9. Periode absensi jadi statis (REVISI `PERIOD_CUTOFF_DAY`)

`PERIOD_CUTOFF_DAY` (1 angka cutoff, resolusi periode dinamis/rolling lewat `AttendancePeriodResolver`) diganti jadi **2 setting statis**, pola sama (integer, company-scoped, tabel `settings`):
- Periode Awal Absensi (tanggal-dalam-bulan).
- Periode Akhir Absensi (tanggal-dalam-bulan).

Periode dibentuk langsung dari pasangan tanggal ini (kalau akhir < awal, otomatis nyambung ke bulan berikutnya — misal awal=26, akhir=25 → periode berjalan tanggal 26 s/d tanggal 25 bulan depan), TANPA logic rolling/straddle berdasarkan `anchorDate->day` seperti resolver sekarang.

**Resolusi:** `AttendancePeriodResolver` sekarang dipakai bareng oleh limit Cuti Tahunan (`assertWithinCutiTahunanMonthlyLimit`/`getCutiTahunanPeriodeUsage`, `AttendaceReqService.php` baris ~252 & ~356) — tapi dikonfirmasi user, Cuti Tahunan **TIDAK ada hubungannya** dengan periode/cutoff Izin ini. Limit Cuti Tahunan cuma "berapa hari boleh cuti dalam 1 bulan kalender", independen total dari setting periode awal/akhir Izin yang baru. Jadi:
- Cuti Tahunan diganti pakai bulan kalender polos (`now()->startOfMonth()`/`endOfMonth()` dari tanggal pengajuan), TIDAK lagi manggil `AttendancePeriodResolver`.
- `AttendancePeriodResolver` + `PERIOD_CUTOFF_DAY` lama jadi murni dipakai buat Izin saja (§8) — setelah ini beres, resolver lama sudah tidak punya pemakai lain, bisa dihapus/diganti total dengan periode statis di §9 tanpa risiko break Cuti Tahunan.

## 10. Urutan implementasi yang direncanakan

1. Enum `PermissionTypeCategoryEnum` (+2 case).
2. Migration `attendance_req_permission_details` (+`end_date`, +`total_days`) dan `m_permission_types` (+`default_days`).
3. Seeder/master data (1 Sakit + 8 Khusus per company — lihat catatan company-scoping di analisis sesi ini).
4. `AttendanceSettingEnum` — hapus `IZIN_MAX_PER_PERIODE` & `PERIOD_CUTOFF_DAY`, tambah kuota per kelompok (§8) + periode awal/akhir statis (§9).
5. `AttendaceReqService` — exclude Sakit & Khusus dari hitungan kuota; pisah hitungan kuota jadi per kelompok; ganti pemanggilan `AttendancePeriodResolver` di jalur Izin sesuai periode statis §9; ganti jalur Cuti Tahunan (`assertWithinCutiTahunanMonthlyLimit`/`getCutiTahunanPeriodeUsage`) ke bulan kalender polos, lepas total dari `AttendancePeriodResolver`.
6. Form `SubmitPermissionRequest` — 2-level select, date picker kondisional, upload lampiran (reuse tabel `attendance_req_attachments`, TIDAK perlu migration baru), validasi wajib-lampiran Sakit >2 hari. Lampiran cuma muncul untuk kelompok 3 & 4.
7. Tampilan approval/detail (`attendance-request-detail.blade.php`, rekap izin) — pastikan rentang tanggal & lampiran ikut tampil, bukan cuma `date` tunggal.

## 11. Master `m_permission_types` dipersempit — cuma 2 kelompok yang butuh (REVISI lanjutan 2026-07-31)

Setelah §1-10 diimplementasikan, ternyata bikin baris master jadi banyak (kelompok 1 lama + Sakit + 8 Khusus = ramai). Didiskusikan ulang dan diputuskan: **cuma kelompok yang punya sub-jenis** yang butuh master data —

- **Butuh master** (`PermissionGroupEnum::hasSubKategori() === true`):
  - Kelompok 1 (Meninggalkan Jam Kerja) — 3 baris per company (Terlambat Masuk/Pulang Cepat/Meninggalkan Jam Kerja Tengah Hari), SUDAH ada dari dulu, tidak berubah.
  - Kelompok 4 (Khusus) — 8 baris per company (§2), tetap seperti semula.
- **TIDAK butuh master sama sekali**:
  - Kelompok 2 (Tidak Masuk Seharian) — flat, cuma 1 jenis, tidak ada nama sub-jenis yang perlu di-manage.
  - Kelompok 3 (Sakit) — flat juga, alasan sama. Baris master "Izin Sakit" yang sempat di-seed di §3.4 **sudah dihapus lagi** (`PermissionSakitKhususSeeder` diganti nama jadi `PermissionKhususSeeder`, cuma isi 8 Khusus).

**Mekanisme teknis — denormalisasi `category`:** sebelumnya sistem baca kategori izin (buat kuota & payroll) CUMA lewat JOIN `permission_type_id` → `m_permission_types.category`. Supaya kelompok 2 & 3 bisa jalan tanpa master, kolom `category` didenormalisasi LANGSUNG ke `attendance_req_permission_details` (migration `add_category_to_attendance_req_permission_details_table`, + backfill data lama dari relasi lama). Konsekuensinya:
- `SubmitPermissionRequest::updatedGroup()` — kelompok flat (2 & 3) langsung set `$this->category` dari `$group->categories()[0]->value`, TANPA query ke `m_permission_types`. Kelompok 1 & 4 tetap query master seperti biasa (dropdown level 2), `category` di-resolve dari `permissionType->category` yang dipilih (di service, saat submit).
- `AttendaceReqService::getIzinPeriodeUsage()`/`assertWithinIzinPeriodeLimit()` — query `whereIn('category', ...)` LANGSUNG ke `attendance_req_permission_details`, bukan lagi `whereHas('permissionType', ...)`.
- Payroll (`AbstractIzinFlatCalculator`, `IzinSeharianCalculator`) — sama, baca kolom `category` langsung, tidak JOIN ke master lagi. Berlaku surut untuk data lama juga (sudah di-backfill).
- `ResolvesTodayAttendanceStatus`, `IzinRecapPage` — juga baca `$detail->category` langsung.
- `PermissionTypeForm` (Filament, master "Jenis Izin") — dropdown "Kategori" dibatasi cuma opsi dari `PermissionGroupEnum::masterCategories()` (union kategori kelompok 1 & 4), tidak lagi semua 6 kategori.

**Payroll — gabung jadi 1 komponen untuk seluruh kelompok Meninggalkan Jam Kerja:** `IzinTerlambatPulangCepatCalculator` (dulu cuma proses Terlambat+PulangCepat) di-rename jadi `IzinMeninggalkanJamKerjaCalculator`, categories-nya nambah `IZIN_MENINGGALKAN_JAM_KERJA` (sub-jenis "tengah hari") — ini **membalik** keputusan final lama (`analisis-payroll-4` §7B.4, "tidak ada dampak payroll sama sekali"), sekarang ketiga sub-jenis kelompok 1 dipotong sebagai 1 komponen yang sama (`PayrollComponentEnum::IZIN_TERLAMBAT_PULANG_CEPAT`, value & settings key TIDAK diubah — cuma label-nya jadi "Izin Meninggalkan Jam Kerja" — supaya data histori `PayrollItem.source_type` & setting `izin_potongan_flat` tidak break).

Jenis izin lama yang belum pernah dikategorikan (di luar 4-kelompok ini, misal "Izin Keluar Kantor") TETAP dibiarkan `category = null` — tidak wajib diisi, otomatis masuk kolom "Lainnya" di Rekap Izin, tidak kena kuota/payroll manapun (perilaku sama seperti sebelum seluruh redesain ini).
