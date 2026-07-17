# Planning: Halaman Mapping Komponen Gaji (2026-07-17)

Status: **dieksekusi penuh (kode + test), tidak ada migrasi baru** (5 tabel config sudah dibuat Fase 0, cuma belum ada UI — lihat `planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md`). **BELUM diverifikasi manual di browser dengan data riil**, **BELUM di-commit**, **BELUM di-deploy**.

## 1. Latar belakang

5 tabel config payroll (`pangkat_salaries`, `position_allowances`, `transport_allowances`, `tenure_allowances`, `pangkat_bpjs_bases`) sengaja dibuat kosong di Fase 0/2 — "kerjaan finance lewat UI, bukan migration" — tapi UI-nya belum pernah dibangun. Selain itu 2 komponen lain (Kelebihan Jam Kerja, Lembur) sudah punya data + Filament Resource sendiri (`ExtraHourAllowances`, `OvertimeRates`) tapi cuma bisa diakses lewat panel admin `/admin`, tidak lewat portal.

Halaman baru `/keuangan/mapping-komponen-gaji` menyatukan ke-7 nya: pilih company → daftar komponen yang **aktif** (toggle ON di halaman Komponen Gaji untuk company itu, `CompanyPayrollComponent.is_active=true`) → form spesifik sesuai struktur data komponen itu.

## 2. Struktur data per komponen (7 total)

| Komponen | Tabel | Key | Bentuk UI |
|---|---|---|---|
| Gaji Pokok | `pangkat_salaries` | `pangkat_id` (1:1) | List pangkat + 1 input nominal per baris |
| Tunjangan Jabatan | `position_allowances` | `pangkat_id` + `kelas_id` (matrix) | Tabel grid Pangkat × Kelas, 1 input per sel |
| Tunjangan Transportasi | `transport_allowances` | `kelas_id` (1:1) | List kelas + 1 input nominal per baris |
| Tunjangan Masa Kerja | `tenure_allowances` | `company_id` + bracket `min_years`/`max_years` | Baris dinamis (tambah/hapus), bukan per pangkat/kelas |
| BPJS Kesehatan & Ketenagakerjaan | `pangkat_bpjs_bases` | `pangkat_id` (1:1) | List pangkat + dropdown (kosong / UMP / Nilai Tetap) — **digabung 1 form** karena kedua komponen baca tabel yang sama, muncul kalau salah satu aktif |
| Kelebihan Jam Kerja | `extra_hour_rules` (`ExtraHourAllowanceRules`) | `company_id` + polimorfik (Jabatan/Divisi) | Reuse `ExtraHourAllowancesForm`/`Table` (Filament) apa adanya |
| Lembur | `shift_overtime_costs` (metode "shift", default) ATAU `pangkat_lembur_rates` (metode "per_pangkat") | `company_id`+`shift_id`+`overtime_type_id`, atau `pangkat_id` (1:1) | Metode "shift": reuse `OvertimeRatesForm`/`Table` (Filament) apa adanya. Metode "per_pangkat": list pangkat + 1 input rate/jam, pola sama Tunjangan Transportasi |

## 3. Keputusan desain

- **BPJS Kesehatan & Ketenagakerjaan digabung jadi 1 entry** di daftar komponen (bukan 2 form terpisah) — keduanya baca `pangkat_bpjs_bases` yang sama persis, memisahkan cuma akan bikin 2 form identik.
- **BPJS kosong = hapus row, bukan simpan basis kosong** — konsisten sama desain Fase 2 (pangkat belum dipetakan = skip potongan total, bukan fallback). Pilih "Belum dipetakan" di dropdown lalu Simpan akan `delete()` row `pangkat_bpjs_bases`-nya kalau sebelumnya ada.
- **Kelebihan Jam Kerja & Lembur reuse Form/Table Filament yang sudah ada** (`ExtraHourAllowancesForm`/`Table`, `OvertimeRatesForm`/`Table`) lewat pola yang sama seperti `IncentivesPage.php` (`HasActions`/`HasSchemas`/`HasTable` di Livewire component polos) — bukan ditulis ulang. Query di-scope paksa ke company yang dipilih di halaman ini lewat `modifyQueryUsing()`, dan `CreateAction` di-override `mutateFormDataUsing()` supaya `company_id` yang tersimpan selalu ikut company yang dipilih di halaman Mapping (bukan `Auth::user()->company_id` bawaan form, yang cuma benar buat admin yang company-nya sama dengan yang dia pilih).
- **Field kosong = skip, bukan overwrite ke 0** — pola sama semua halaman config lain di project ini (`SalaryComponentsPage`, dst): baris yang tidak diisi tidak disentuh saat Simpan, supaya tidak menghapus data lain tanpa sengaja.
- **Tunjangan Jabatan berupa matrix** (bukan daftar linear) karena rule-nya memang keyed 2 dimensi (`pangkat_id` + `kelas_id`) — beda dari 4 komponen lain yang cuma 1 dimensi.

### 3.1 Addendum: Lembur dapat metode kedua — "per_pangkat" (2026-07-17, lanjutan sesi yang sama)

Awalnya direncanakan pakai rumus khusus Holding dari Excel (analisis-payroll-4 §3.6: base 3,5jt/3jt tergantung kategori "TL"/"TM" karyawan, dibagi hari×7, dikali multiplier clamp 2–4 jam). Setelah user klarifikasi **TL = Team Leader, TM = Team Member**, keluar arahan eksplisit: **jangan pakai formula itu — bikin sesederhana Tunjangan Transportasi** (1 pangkat → 1 nominal, bukan kategori+base+multiplier+pembagi).

**Hasil akhir**:
- Tabel baru `pangkat_lembur_rates` (`pangkat_id` unique FK, `amount_per_hour` decimal) — pola PERSIS `transport_allowances`, BUKAN `pangkat_lembur_categories` (nama yang sempat dibuat lalu dihapus lagi sebelum sempat dipakai).
- Setting baru `lembur_metode` (`'shift'` default | `'per_pangkat'`), required setting LEMBUR bareng `lembur_minimum_jam` yang sudah ada — dropdown khusus di `component-card.blade.php` (pola sama `insentif_metode`).
- `lembur_minimum_jam` (setting yang sudah ada dari Fase 1 tapi sebelumnya TIDAK PERNAH dibaca di kode manapun — dead setting) sekarang **benar-benar dipakai** sebagai gate: metode "per_pangkat" skip detail lembur yang jam-nya di bawah minimum ini. Ini yang menjawab pertanyaan terbuka ">2 jam" di analisis-payroll-4 §5.2 — bukan lewat clamp di rumus (rumus Holding dibatalkan), tapi lewat gate di setting yang sudah ada.
- `LemburCalculator::calculate()` baca `Setting::resolve('lembur_metode', ...)` di awal, cabang ke `calculatePerPangkat()` (baru) atau lanjut ke logic shift-based lama (`processDetail()`, tidak diubah sama sekali).
- Basis 3,5jt/3jt, multiplier clamp, dan pembagi ×7 dari rumus Excel **TIDAK dipakai** — 3 dari 4 pertanyaan terbuka di analisis-payroll-4 (§5.1 arti TL/TM, §5.4 basis tetap/aktual, §5.5 pembagi ×7 universal/per-shift) jadi **tidak relevan lagi** untuk arah ini, biarkan tetap tercatat di dokumen itu sebagai histori keputusan, bukan dihapus.
- Halaman Mapping Komponen Gaji: section "Lembur" sekarang bercabang berdasar `lembur_metode` company terpilih — metode "shift" tetap reuse tabel Filament `OvertimeRates` seperti semula, metode "per_pangkat" tampilkan list pangkat + input rate (masuk lewat `getLemburMetodeProperty()`).
- Test baru: `tests/Feature/Payroll/LemburCalculatorPerPangkatTest.php` (4 test: hitung rate×jam, skip pangkat belum dipetakan, skip di bawah minimum jam, pastikan metode shift tidak ikut kepicu) + 1 test tambahan di `MappingKomponenGajiPageTest.php`.

## 4. File baru
- `app/Livewire/Keuangan/MappingKomponenGajiPage.php`
- `resources/views/livewire/keuangan/mapping-komponen-gaji-page.blade.php`
- Route `keuangan/mapping-komponen-gaji` (`routes/web.php`), permission `SISTEM_KEUANGAN` (`config/portal-pages.php`), link di `nav.blade.php` + `pwa-bottom-nav.blade.php` (dropdown "Penggajian").
- `tests/Feature/Payroll/MappingKomponenGajiPageTest.php` — 10 test: filter daftar komponen per company/active, save tiap 1 dari 5 tipe form, render Kelebihan Jam Kerja/Lembur tidak error, permission gate. Semua lulus.

## 5. Yang TIDAK termasuk sesi ini
- Verifikasi manual di browser dengan data riil (test yang ada baru lewat `Livewire::test()`, bukan klik manual).
- Validasi input (misal `min_years` tenure tidak boleh tumpang tindih antar bracket, `base_salary` tidak boleh negatif) — form saat ini polos tanpa validasi custom, cuma `type="number"` HTML biasa.
- Bulk import/export buat kelima tabel ini (beda dari fitur Import Insentif yang sudah ada) — kalau datanya banyak (puluhan pangkat × kelas buat matrix Tunjangan Jabatan), isi manual lewat halaman ini bisa melelahkan; belum ada rencana import untuk ini.
- Deploy ke production.

## 6. Referensi
- `planning/2026-07-16-payroll-fase-0-fondasi-data-aman-produksi.md` — asal-usul 5 tabel kosong ini.
- `planning/2026-07-17-payroll-fase-2-bpjs-toggle-gabung-izin-izin-seharian.md` §10 — desain BPJS skip-otomatis yang jadi dasar keputusan "kosong = hapus row" di atas.
- `app/Livewire/Keuangan/IncentivesPage.php` — pola reuse Filament Form/Table di Livewire polos yang dicontek untuk Kelebihan Jam Kerja/Lembur.
