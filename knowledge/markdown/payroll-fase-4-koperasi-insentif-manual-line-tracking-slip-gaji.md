# Payroll Fase 4 — Koperasi jadi Roster, Insentif-Import Disatukan ke Manual Line, Tracking Generate, Slip Gaji Dirapikan (2026-07-18)

Lanjutan dari Fase 3 (`knowledge/markdown/payroll-fase-3-generate-portal-dan-slip-gaji.md`). Sesi ini fokus di 3 area: (1) menyatukan sumber data manual (Koperasi/Bon/Insentif-import) di bawah pola `payroll_manual_lines` yang sama, (2) menambah jejak audit "siapa & kapan generate/regenerate" di level periode maupun per-payroll, dan (3) merapikan tampilan slip gaji PDF sesuai referensi visual yang dikasih user secara iteratif.

## 1. Insentif metode "import" disatukan ke `payroll_manual_lines`

Sebelumnya `IncentiveImport` (Excel per-NIP, RSU) nulis **langsung** ke `payroll_items` + increment total `Payroll` yang sudah ada — bypass total dari pola manual-line yang dipakai Koperasi/Bon. Konsekuensinya: regenerate periode akan menghapus insentif yang sudah diimport tanpa cara otomatis menulis ulang, jadi harus diblokir pakai guard (`PayrollService::periodHasImportedIncentives()`).

Sekarang: `IncentiveImport` upsert baris `payroll_manual_lines` (component=`insentif`, idempotent per periode+user — re-upload mengganti nominal, bukan nambah baris). `IncentiveCalculator` metode "import" baca dari situ (`calculateFromImport()`), persis pola `AbstractManualLineCalculator` yang dipakai Koperasi/Bon. `AbstractManualLineCalculator::calculate()` di-generalisasi supaya `addEarning`/`addDeduction` ditentukan dari `componentType()` (dulu hardcode `addDeduction`, cuma valid buat Koperasi/Bon yang sama-sama potongan).

**Efek nyata**: guard `periodHasImportedIncentives()` dan blokir regenerate-nya **dihapus total** (di `PayrollService`, `PayrollPeriodsPage`, dan `PayrollPeriodsTable` Filament) — regenerate sekarang aman buat semua kasus, karena semua sumber manual (Koperasi/Bon/Insentif-import) reproducible dari `payroll_manual_lines`. Konsekuensi UX: insentif-import baru kelihatan di payroll setelah Generate/Regenerate dijalankan ulang (sama seperti Koperasi/Bon), bukan langsung apply saat import selesai.

## 2. Koperasi jadi roster (`user_koperasi`) + auto-sync

Sebelumnya Koperasi = manual line yang harus diinput ulang finance tiap periode (modal di halaman Payroll). Karena Koperasi pada praktiknya cicilan tetap per bulan (bukan input ad-hoc), dibuat tabel roster baru:

- `user_koperasi` (`user_id` unique, `amount`, `is_active`) — model `App\Models\Payroll\UserKoperasi`, relasi `User::koperasi()`.
- `PayrollService::syncKoperasiManualLines()` — dipanggil otomatis di awal `generatePayroll()` (jadi ikut jalan juga saat regenerate, karena `regeneratePayroll()` cuma delete+re-run `generatePayroll()`): upsert `payroll_manual_lines` (component=koperasi) dari semua anggota `is_active` di company itu. Finance tinggal atur roster sekali, tidak perlu input tiap periode.
- Halaman baru `/keuangan/koperasi` (`App\Livewire\Keuangan\KoperasiPage`, custom List.js — bukan Filament Table) — filter perusahaan, search, checkbox multi-select + tombol "Ubah Amount" bulk, per-baris toggle Aktif/Nonaktif + Edit + Hapus, modal "Tambah Anggota Koperasi" pakai Tom Select buat cari karyawan (re-add anggota yang sempat nonaktif = reaktivasi + update amount, bukan bikin baris baru, lewat `updateOrCreate` keyed `user_id`).
- Tombol "Koperasi" di dropdown Import halaman Payroll **disembunyikan** (dikomentari, bukan dihapus) — koperasi sekarang diatur dari halaman roster, bukan input manual per periode lagi. Bon TETAP manual line seperti biasa (sifatnya pinjaman ad-hoc per kejadian, bukan cicilan rutin).

## 3. Halaman Insentif direbuild ke pola custom List.js

`IncentivesPage` yang sebelumnya reuse Table/Form Filament standalone (rawan bug seperti dicatat Fase 3 §3) dibangun ulang total mengikuti pola `PayrollPeriodsPage`/`KoperasiPage`: filter perusahaan + search + filter status (Semua/Belum/Sudah Diproses) pakai List.js, modal Tambah/Edit custom (dropdown Penerima pakai Tom Select), otorisasi `Create/Update/Delete:Incentive` dicek manual (policy Shield yang sama, `IncentivePolicy`), tombol Edit/Hapus disembunyikan sesuai izin DAN status `is_processed` (bukan error setelah diklik). Ketemu bug sekalian: `Incentive::incentive_date`/`is_processed` belum ada di `$casts`, bikin `->format()` di form edit crash — ditambahkan.

## 4. Tracking "siapa & kapan generate/regenerate"

Tiga lapis, dari yang paling umum ke paling spesifik:

1. **`payroll_periods.generated_by`** — user yang terakhir menekan Generate/Regenerate untuk SELURUH periode. Di-set di `PayrollService::generatePayroll()`. Karena job (`GeneratePayrollJob`/`RegeneratePayrollJob`) jalan di queue tanpa `Auth::user()`, id aktor harus ditangkap SAAT DISPATCH (di `PayrollPeriodsPage` & `PayrollPeriodsTable` Filament) dan dibawa lewat constructor job → service.
2. **`payrolls.generated_by` + `payrolls.generated_at`** — per-baris payroll, di-set di `PayrollService::generateForUser()` (method baru, hasil extract dari body loop `generatePayroll()` supaya bisa dipakai ulang). Ini yang benar-benar menjawab kebutuhan "siapa yang terakhir regenerate payroll KHUSUS orang ini", karena regenerate per-karyawan (§5) cuma nyentuh 1 baris, tidak nyentuh `payroll_periods.generated_by` semua.
3. **Slip gaji "Dibuat oleh"** dibaca dari `payroll_periods.generatedBy` (bukan dari `payrolls.generated_by`) — fallback ke nama hardcode lama ("NOOR AFINI, SE" / Accounting) buat data lama yang belum punya `generated_by`.

## 5. Regenerate per-karyawan

Tombol baru "Regenerate Payroll Ini" di halaman detail payroll (`PayrollDetailPage`) — cuma hapus & hitung ulang 1 baris payroll itu, tidak menyentuh payroll karyawan lain di periode yang sama. `PayrollService::regenerateSinglePayroll()`: guard `is_paid` (lempar `RuntimeException`), hapus payroll lama (cascade payroll_items), panggil `generateForUser()` yang sama dipakai batch generate. ID payroll berubah (baris baru, bukan update in-place) — Livewire component redirect ke URL detail yang baru setelah selesai.

## 6. Kolom baru `payroll_periods.payment_date`

Tanggal gajian aktual (beda dari `end_date` periode) — diisi lewat form Tambah/Edit di halaman Periode Gaji, ditampilkan di slip gaji sebagai "Tanggal Bayar".

## 7. Slip gaji (PDF) — perombakan tampilan (iteratif, banyak putaran revisi kecil dari referensi visual user)

- **Header**: logo (`public/assets/logo-sgg.png`) menggantikan teks "Syifa Global Group" yang dihapus; stempel RAHASIA (kiri) sejajar blok Periode Gaji + Tanggal Bayar (kanan) — Periode Gaji cuma tampilkan tanggal akhir periode (bukan rentang), Tanggal Bayar dari kolom baru §6.
- **Info karyawan**: 1 kolom (bukan lagi 2 kolom side-by-side) — Nama, Jabatan, NIP, **Masa Kerja** (dihitung dari `profile.joining_date` ke `period.end_date`, format "N Bulan" kalau < 1 tahun atau "N Tahun M Bulan", selalu integer), **Status Karyawan** (dari `profile.employeeType.name`, field baru diminta user).
- **Pendapatan/Potongan**: label sekarang menempel aturan yang berlaku langsung di teksnya (mis. "BPJS Kesehatan 1%", "Keterlambatan >5 Menit", "Lembur >2 Jam") — bukan anotasi terpisah lagi. Jumlah kejadian (mis. "3x/Bulan" buat Keterlambatan, "Nx/Hari" buat Cuti/Izin Seharian) dipindah ke KOLOM TERPISAH di sisi kanan (sejajar nominal), bukan nempel di label. Potongan tetap sebelahan dengan Pendapatan (bukan ditumpuk — sempat dicoba ditumpuk supaya label panjang macam "Izin Terlambat Masuk / Pulang Cepat" muat 1 baris, tapi user minta tetap menyamping) — solusinya kolom Potongan dikasih porsi lebar lebih besar (58% vs 42%) dan kolom nominal/keterangan dikasih lebar PASTI kecil (px, bukan %) supaya label otomatis dapat sisa lebar terbanyak dari layout tabel auto.
- **Nominal Rp**: rata KIRI (nempel ke titik dua), bukan kanan. Kalau nominalnya 0, tampilkan "Rp" polos tanpa angka nolnya (helper `$rupiah()` inline di blade).
- **Total Gaji / Total Potongan**: dikasih jarak (padding-left 28px) biar tidak nempel. **Total Box** (Total Gaji Diterima + Terbilang) dikasih `width: 75-80%` (bukan full width) + jarak lebih (`margin-top: 20px`).
- **Tanda tangan**: jarak antara "Dibuat oleh,"/"Diterima oleh," dan nama yang ditandatangani diperbesar (`.ttd-space`, 60px) — sempat gagal pakai `<div>` kosong nested di `display:table-cell` (dompdf error `"Frame not found in cellmap"`), fix pakai `<span display:block>` bukan `<div>`.
- **Footer**: alamat+kontak (bold, tabel 2 kolom — baris alamat pertama sejajar website di kanan) dipindah jadi `position: fixed` supaya benar-benar nempel di bagian BAWAH halaman fisik (dulu cuma ngikut alur konten, posisinya naik-turun tergantung panjang isi).

### Bug dompdf yang perlu diingat
`<div>` kosong (tanpa isi sama sekali) yang jadi anak langsung dari elemen `display: table-cell` bikin dompdf lempar `"Frame not found in cellmap"` — dompdf's table layout engine tidak tahan sama block kosong di posisi itu. Solusi: kasih isi non-kosong (mis. `&nbsp;`) DAN jangan pakai `<div>` polos, pakai elemen `display: block` seperti `<span>` yang dipaksa block lewat CSS.

## 8. Test baru sesi ini

`KoperasiPageTest`, `KoperasiSyncTest`, `IncentivesPageTest`, `PayrollPeriodGeneratedByTest`, `RegenerateSinglePayrollTest` — total ~24 test baru, semua hijau bareng seluruh suite Payroll lama (63 test total di akhir sesi). 1 file test lama dihapus (`PayrollServiceRegenerateGuardTest` — menguji `periodHasImportedIncentives()` yang sudah dihapus total, lihat §1).

## 9. Yang belum dikerjakan / masih terbuka

- Verifikasi visual manual (buka PDF asli di browser) buat memastikan tata letak slip gaji akhir — sepanjang sesi divalidasi lewat render `->preview()` tanpa error PHP/dompdf saja, bukan review visual pixel-perfect oleh Claude (user yang kasih feedback visual tiap putaran).
- Data riil `user_koperasi` (roster anggota Koperasi asli) belum diisi — tabelnya baru infrastruktur kosong.
- `keterlambatan_threshold_menit` dkk perlu dicek sudah keisi benar per company supaya label "Keterlambatan >N Menit" di slip tidak nongol ">0 Menit" secara tidak sengaja.
