# Hasil Testing — Alur Payroll End-to-End (Komponen Gaji → Generate → Slip) RSU Syifa Medika Banjarbaru

**Tanggal eksekusi:** 19 Juli 2026
**Tool:** Playwright MCP (browser otomatis) + tinker (setup data pendukung & verifikasi DB) + `queue:work` (proses job generate)
**Environment:** `http://localhost:8000` — database lokal (bukan data dummy, 458 karyawan RSU Banjarbaru asli)
**Referensi:** Tidak ada test plan tertulis sebelumnya — dijalankan langsung dari instruksi bertahap user (10 langkah).

## Akun yang dipakai

| Peran | Email | Nama |
|---|---|---|
| Admin Keuangan | ahyaghifari288@gmail.com | Muhammad Ahya Ghifari, S. Kom |

## Data uji yang dibuat/diubah

| Entitas | Detail |
|---|---|
| Tipe Karyawan | `Karyawan Tetap` (`01kxtvk9s6gs48ps9ngjj2yf7b`), `Karyawan Kontrak` (`01kxtvk9t1bbxwawnsfv6yqagy`) — baru dibuat untuk RSU Banjarbaru (sebelumnya 0) |
| Assignment tipe karyawan | 458 karyawan aktif RSU Banjarbaru, diacak: 225 Tetap / 233 Kontrak (lewat tinker, bukan lewat UI edit satu-satu — atas arahan user) |
| Komponen Gaji | 16 komponen diaktifkan (semua kecuali always-on) untuk RSU Banjarbaru |
| Penerima Komponen Gaji | 17 komponen × 2 tipe karyawan = 34 baris (Karyawan Tetap + Kontrak, semua komponen) |
| Mapping | Gaji Pokok 6 pangkat, Tunjangan Jabatan 72 sel (6 pangkat × 12 kelas), Tunjangan Transportasi 12 kelas, Tunjangan Masa Kerja 3 jenjang (0-1th/3-4th/6th+), BPJS basis 6 pangkat (mix UMP/Nilai Tetap) |
| Koperasi | 3 anggota aktif: Akhmad Rizali (pre-existing, Rp120.000), Ahmad Budi Setiawan (baru, Rp150.000), Siti Amalia (baru, Rp125.000) |
| Periode Gaji | 01–31 Mei 2026, tanggal bayar 05 Jun 2026 (`01kxvhtrftkjyr650bxe1teqp0`) |
| Bon manual | Muhammad Zaini Rp300.000, Siti Amalia Rp200.000 |
| Payroll ter-generate | **458/458 baris**, payroll contoh dicek: `01kxvjf8hzv20a9qezsrc5865b` (Muhammad Zaini) |

## Ringkasan hasil

| Langkah | Hasil |
|---|---|
| 1. Login | ✅ |
| 2. Isi Komponen Gaji (16 komponen) | ✅ tersimpan, diverifikasi lewat DB |
| 3. Penerima Komponen Gaji (buat tipe karyawan + assign random + isi 17×2) | ✅ 34 baris tersimpan |
| 4. Mapping seluruh komponen | ✅ Gaji Pokok/Tunjangan Jabatan/Transportasi/BPJS tersimpan langsung; Tunjangan Masa Kerja **gagal di percobaan pertama** (lihat Bug #1 & #2), berhasil di percobaan kedua. Kelebihan Jam Kerja & Lembur (metode shift) dilewati — butuh data Master Shift yang belum ada, di luar scope sesi ini |
| 5. Tambah anggota Koperasi | ✅ 2 anggota baru ditambahkan lewat modal Tom Select (sempat gagal 1x karena field Potongan kosong — placeholder terbaca seolah nilai asli, lihat catatan) |
| 6. Import Koperasi & Bon | ⚠️ **asumsi awal salah** — tidak ada file Excel untuk Koperasi/Bon (lihat Catatan Desain di bawah). Koperasi sudah cukup dari step 5 (roster), Bon diisi manual lewat modal setelah Periode Gaji dibuat |
| 7. Buat Periode Payroll | ✅ |
| 8. Generate payroll | ⚠️ **awalnya terlihat gagal** — root cause: dispatch ke queue job, tidak ada worker jalan (Bug #3). Setelah `queue:work` dijalankan manual: ✅ 458/458 karyawan tergenerate |
| 9. Cetak slip gaji | ✅ PDF render sempurna, [screenshot](screenshots/payroll-slip-gaji-preview.png) — Bon, Status Karyawan, terbilang semua konsisten |

Data dipilih untuk verifikasi ketepatan hitung, bukan cuma "tidak error":
- **Ahmad Budi Setiawan** (anggota Koperasi Rp150.000): potongan payroll persis Rp150.000. ✅
- **Muhammad Zaini** (Bon Rp300.000): gaji kotor Rp6.380.000, potongan Rp300.000, bersih Rp6.080.000 — semua cocok dengan slip PDF. ✅

## Catatan Desain (bukan bug, ralat asumsi awal user selama sesi)

- **Koperasi tidak lagi pakai import Excel** — sejak Fase 4 redesign, sudah diganti roster (`user_koperasi`, halaman `/keuangan/koperasi`) yang auto-sync ke `payroll_manual_lines` tiap generate. Tombol import lama sengaja dicabut dari dropdown "Import" di halaman Payroll (dikomentari, bukan dihapus).
- **Bon juga bukan file import** — itu modal input manual per periode (`openManualLineModal('bon')`), dan **mensyaratkan Periode Gaji sudah ada duluan** (`periodId` wajib). Urutan langkah 6-7 di instruksi awal user terbalik dari cara kerja sebenarnya sistem.

## Bug/Gap Ditemukan

### 1. `TenureAllowance::saveTenure()` — false-positive overlap untuk jenjang bersebelahan tepat
**File**: `app/Livewire/Keuangan/MappingKomponenGajiPage.php` (method `saveTenure()`)
**Root cause**: perbandingan overlap pakai `$aMin <= $bMax && $bMin <= $aMax` — dua jenjang yang bersebelahan PAS di titik yang sama (mis. 0-2 tahun dan 2-5 tahun, titik temu di angka 2) ke-flag sebagai tumpang tindih karena operator `<=` bukan `<`. Akibatnya seluruh `saveTenure()` `return` lebih awal lewat guard `getErrorBag()->isNotEmpty()`, **tidak ada notifikasi error yang terlihat user**, dan data tidak tersimpan sama sekali (silent fail dari sudut pandang UI).
**Reproduksi**: isi 3 jenjang 0-2/2-5/5-keatas → Simpan → tidak ada perubahan di DB, tidak ada pesan.
**Workaround dipakai**: ganti ke jenjang dengan gap (0-1/3-4/6-keatas) — berhasil.
**Saran perbaikan**: ganti operator jadi `<` di salah satu sisi perbandingan, supaya batas yang bersentuhan tepat (bukan overlap sungguhan) tidak ditolak.

### 2. Sesi login expired di tengah pengisian form, tanpa peringatan sebelumnya
Saat mengisi Tunjangan Masa Kerja (percobaan pertama), muncul dialog browser native "This page has expired. Would you like to refresh the page?" — setelah refresh, otomatis logout ke `/login`. Kombinasi dengan Bug #1 (silent fail), sempat sulit dibedakan apakah datanya gagal tersimpan karena validasi atau karena sesi habis. Tidak diselidiki lebih lanjut apakah ini `SESSION_LIFETIME` pendek di `.env` lokal atau perilaku default — dicatat sebagai potensi UX gap (tidak ada peringatan sebelum sesi benar-benar habis).

### 3. "Generate Payroll" dispatch ke queue job — tidak jalan kalau tidak ada worker aktif
**File**: `app/Livewire/Keuangan/PayrollPeriodsPage.php:144` (`generatePayroll()`) → `GeneratePayrollJob::dispatch()`
**Temuan**: tombol "Generate Payroll" langsung menampilkan notifikasi sukses ("Generate payroll dimulai") meski job belum benar-benar diproses — notifikasi ini menandai job berhasil **di-dispatch**, bukan berhasil **selesai**. Di environment testing ini tidak ada `queue:work`/`composer run dev` yang jalan, jadi job menumpuk di tabel `jobs` (9 job pending ditemukan, 7 di antaranya sisa dari sesi-sesi sebelumnya yang juga belum pernah diproses). Payroll baru benar-benar tergenerate setelah `php artisan queue:work --stop-when-empty` dijalankan manual.
**Dampak produksi**: kalau queue worker tidak dikonfigurasi jalan terus-menerus di server production (mis. lewat Supervisor), HR akan mengira payroll sudah digenerate (notifikasi sukses tampil) padahal belum — **perlu masuk checklist deploy**.
**Saran**: tambahkan ke `deployment/CHECKLIST.md` — pastikan queue worker (Supervisor/systemd) aktif sebelum HR pertama kali pakai fitur Generate/Regenerate Payroll di production.

### Catatan kecil (bukan bug, cuma observasi)
- Field "Potongan Per Bulan" di modal Tambah Anggota Koperasi menampilkan nilai default sebagai **placeholder**, bukan value asli — kalau tidak diisi ulang manual, validasi `required` menolak submit (baik, tapi bisa membingungkan karena angka "150000" terlihat sudah terisi).
- Widget Tom Select (dropdown karyawan) di modal Koperasi kadang tidak sinkron visual setelah `selectOption()` di underlying `<select>` — perlu dipanggil lewat API `tomselect.setValue()` langsung untuk memastikan Livewire ikut ke-update. Kemungkinan cuma soal cara testing (Playwright), bukan bug asli untuk user manusia yang klik langsung.
