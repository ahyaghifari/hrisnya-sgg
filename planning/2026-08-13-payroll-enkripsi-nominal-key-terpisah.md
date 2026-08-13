# Planning: Enkripsi Nominal Payroll — Key Terpisah dari APP_KEY (2026-08-13)

Status: **BELUM DIEKSEKUSI.** Lanjutan `knowledge/markdown/analisis-payroll-6-enkripsi-nominal.md` (analisis §1-10, opsi B disepakati, scope diaudit). Sesi ini: (1) key enkripsi payroll dipisah dari `APP_KEY`, (2) verifikasi ulang row count & titik SQL langsung ke kode (bukan asumsi dokumen lama), (3) scope dipersempit — Honor Trainer DIKELUARKAN atas instruksi user, fokus payroll inti saja. Tunggu perintah eksekusi eksplisit sebelum nulis kode.

## 1. Keputusan yang sudah settled

- **Enkripsi**: `encrypted` cast custom (bukan hash — hash satu arah, gak bisa dibalik ke nominal, dibahas & ditolak sesi ini).
- **Key**: TERPISAH dari `APP_KEY` — biar kompromi `APP_KEY` (dipakai session/cookie/cache/hal lain) gak otomatis bongkar nominal payroll. Trade-off: 1 secret tambahan buat dijaga, gak ada tool bawaan Laravel buat generate/rotasi kecuali direplikasi manual (lihat §3).
- **Rotasi key**: pola `previous_keys` ala `APP_KEY`/`APP_PREVIOUS_KEYS` bawaan Laravel (`config/app.php:102-106`, sudah ada presedennya di codebase) — direplikasi manual buat key payroll karena `Encrypter::previousKeys()` cuma otomatis kepasang ke instance `Crypt` facade default, bukan instance custom.
- **Scope trainer**: Honor Trainer/`trainer_assessment_results.honor_amount` **DIKELUARKAN** dari scope sesi ini (instruksi user). Payroll Dokter tetap di luar scope (kode nol, sesuai analisis lama).

## 2. Koreksi asumsi dokumen lama — row count REAL (dicek langsung ke DB, bukan asumsi)

Dokumen analisis §2 bilang `payrolls` masih 0 baris per 2026-08-11 — **SUDAH TIDAK BENAR**. Dicek barusan:

| Tabel | Baris |
|---|---|
| `payrolls` | **72** |
| `payroll_items` | 0 |
| `incentives` | 2 |
| `user_koperasi` | 2 |
| `fee_items` | 0 |
| `omset_details` | 0 |
| `executive_allowances` | 0 |
| `position_allowances` | 37 |
| `legacy_position_allowances` | 0 |
| `transport_allowances` | 14 |
| `jasa_unit_details` | 0 |
| `payroll_manual_lines` | 8 |
| `audit_logs` | 157 |

**Implikasi**: gak bisa lagi "tinggal tempel cast di kolom kosong". `payrolls` (72), `position_allowances` (37), `transport_allowances` (14), `incentives`/`user_koperasi` (2+2), `payroll_manual_lines` (8), `audit_logs` (157) semua punya data plaintext existing yang harus di-reencrypt, bukan cuma ubah tipe kolom kosong. Strategi reencrypt-nya **belum dibahas sama sekali** di dokumen lama — jadi blocker baru, lihat §6.

## 3. Temuan baru: `payroll_manual_lines.amount` — kelewat total di audit lama

Judul dokumen analisis awal (§1) nyebut scope "`payroll_items`, `payroll_manual_lines`, `executive_allowances`" — tapi tabel audit §9.1/§10.1 **gak pernah nyebut `payroll_manual_lines` sama sekali**. Ketinggalan penuh, bukan cuma kelewat 1 baris.

Dicek sekarang (`app/Models/Payroll/PayrollManualLine.php`, dipakai `PayrollDetailPage.php`, `SalaryComponentsPage.php`, `AbstractManualLineCalculator.php`, `BonImport.php`, `BonDetailImport.php`, `PayrollService.php`): **AMAN LANGSUNG** — semua akses lewat `->get()`/`->where()->first()`/relasi model, gak ada SUM/sortable/orderBy pada kolom `amount` itu sendiri. `SalaryComponentsPage.php:361` (`$lines->sum('amount')`) itu `Collection::sum()` PHP-level (setelah `->get()`), bukan SQL — aman.

## 4. Titik refactor SQL — diverifikasi ulang ke baris kode aktual (bukan cuma daftar nama file)

Beberapa titik di dokumen lama meleset jumlah/lokasinya. Ini yang benar per baris kode saat ini:

### `Payroll` (`gross_salary`, `net_salary`, `total_deductions`) — 72 baris ada

- `app/Livewire/Keuangan/PayrollDashboardPage.php:89` — `$this->periodPayrolls()->sum('net_salary')`
- `app/Livewire/Keuangan/PayrollDashboardPage.php:121` — `->orderByDesc('net_salary')` (list "Belum Dibayar")
- `app/Filament/Resources/Payroll/Payrolls/RelationManagers/PayrollItemsRelationManager.php:218-222` — **KRITIKAL**, `applyToPayrollTotals()` pakai `$payroll->increment('gross_salary'/'net_salary'/'total_deductions', ...)`. `increment()` generate SQL `UPDATE col = col + ?` mentah — TIDAK KOMPATIBEL fundamental dengan `encrypted` cast, cast Laravel gak berlaku buat ekspresi SQL. Untungnya SEMUA titik increment/decrement ada di 1 method (`applyToPayrollTotals`, baris 215-224) — 1 titik refactor jadi read-modify-write PHP (`$payroll->gross_salary = $payroll->gross_salary + $amount; $payroll->save();`), bukan tersebar.

### `PayrollItem` (`amount`, `unit_amount`) — 0 baris sekarang

- `PayrollDashboardPage.php:137-143` — `selectRaw('source_type, type, sum(amount) as total')->groupBy(...)->orderByDesc('total')` (breakdown komponen)
- `app/Services/Payroll/Calculators/IzinSeharianGajiPokokCalculator.php:37-40` — `->where(...)->sum('amount')`, dipanggil **tiap Generate Payroll** (bukan cuma dashboard) — jalur kritis, kalau salah refactor payroll generate ikut rusak.

### `Incentive.amount` — 2 baris ada

- `app/Filament/Resources/Incentives/Tables/IncentivesTable.php:38-41` — `TextColumn::make('amount')->sortable()`
- `PayrollDashboardPage.php:186` — `sum('amount')`
- `PayrollDashboardPage.php:192` — `orderByDesc('amount')` (list modal "Insentif Aktif")

### `UserKoperasi.amount` — 2 baris ada

- `app/Filament/Resources/UserKoperasis/UserKoperasiResource.php:103-106` — `sortable()`
- `PayrollDashboardPage.php:209` — `sum('amount')`
- `PayrollDashboardPage.php:215` — `orderByDesc('amount')` (list modal "Anggota Koperasi Aktif")

### `FeeItem.amount` — 0 baris sekarang

- `app/Models/Payroll/FeeSubmission.php:49` — `$this->items()->sum('amount')` (relasi `HasMany`, `->sum()` langsung di builder = SQL-level, bukan Collection)

### `OmsetDetail.amount` — 0 baris sekarang

- `app/Services/Payroll/Calculators/JasaPelayananCalculator.php:45-47` — `->where('payroll_period_id', ...)->sum('amount')`, dipanggil **tiap Generate Payroll** (basis perhitungan Jasa Pelayanan) — jalur kritis.

### `ExecutiveAllowance.amount` — 0 baris sekarang

- `app/Filament/Resources/ExecutiveAllowances/Tables/ExecutiveAllowancesTable.php:30-33` — `sortable()`

### Aman langsung, tinggal cast (gak ada SQL nyentuh nilainya sendiri)

`PositionAllowance.amount` (37), `LegacyPositionAllowance.amount` (0), `TransportAllowance.amount` (14), `JasaUnitDetail.amount` (0), `PayrollManualLine.amount` (8, temuan baru §3).

### `AuditLog.old_value` / `new_value` — 157 baris ada

Beda alasan (bukan nominal langsung, tapi nampung nilai nominal dalam bentuk string dari 7 model yang pakai `LogsAuditChanges` — lihat dokumen lama §9.4). Cast `encrypted` BLANKET, gak ada SQL yang filter/sort berdasar isi kolom ini (cuma filter `key`/`module`, sort `created_at` — dicek ulang, cuma dipakai di `LogsAuditChanges.php`/`LogsFieldAuditChanges.php`/`AuditLog.php`, gak ada raw `DB::table('audit_logs')` yang baca `old_value`/`new_value` di tempat lain).

## 5. Infra key terpisah — rencana bentuk (belum ditulis kode)

- `config/payroll.php` baru: `key` (dari `PAYROLL_ENCRYPTION_KEY`), `cipher` (`AES-256-CBC`, samain sama `APP_KEY`), `previous_keys` (dari `PAYROLL_PREVIOUS_KEYS`, format sama kayak `APP_PREVIOUS_KEYS` — dipisah koma).
- `app/Casts/PayrollEncrypted.php` — custom cast (`CastsAttributes`), internal instansiasi `Illuminate\Encryption\Encrypter` sendiri (bukan `Crypt` facade — itu selalu `APP_KEY`), pasang `previousKeys()` dari config buat fallback decrypt data lama pas key dirotasi.
- Generate key baru: `base64:` + `base64_encode(random_bytes(32))`, format sama kayak `APP_KEY` (BUKAN `php artisan key:generate --show` — itu generate DAN langsung nimpa `APP_KEY` aktif kalau dijalanin polos, harus hati-hati/generate manual).
- `.env.example` + `.env`: tambah `PAYROLL_ENCRYPTION_KEY=`, `PAYROLL_PREVIOUS_KEYS=`.

## 6. Yang BELUM settled — wajib diputuskan sebelum eksekusi kode

1. **Strategi reencrypt data existing** (§2) — 6 tabel punya data plaintext (72+37+14+2+2+8 baris + 157 audit_logs). Urutan aman kemungkinan:
   a. Migration ubah tipe kolom `decimal(15,2)` → `text` (nampung ciphertext base64, jauh lebih panjang dari desimal), data plaintext lama TETAP UTUH sebagai teks di kolom baru.
   b. One-off command/script baca RAW lewat `DB::table()` (bukan Eloquent — biar gak kena cast yang belum aktif/nyoba decrypt plaintext), tulis ulang tiap baris pakai `Encrypter::encryptString()` manual.
   c. BARU deploy kode yang nempelin cast `PayrollEncrypted` ke model — kalau urutan kebalik (cast aktif duluan sebelum data direenkripsi), Eloquent bakal nyoba decrypt string plaintext ("150000.00") dan throw `DecryptException` pas dibaca.
   Belum diputuskan siapa yang jalanin, kapan (perlu downtime/maintenance mode?), dan gimana rollback kalau gagal di tengah.
2. **Urutan eksekusi per tabel** — kolom "aman langsung" (5 tabel) vs "butuh refactor SQL dulu" (6 tabel + `Payroll` kritikal) — bisa jalan bertahap (aman langsung duluan) atau harus serentak?
3. **Testing**: gimana verifikasi tiap titik refactor SQL gak berubah hasil (mis. `IzinSeharianGajiPokokCalculator`/`JasaPelayananCalculator` — kalau salah, nominal Gaji Pokok/Jasa Pelayanan generate payroll bisa salah tanpa ketahuan). Perlu snapshot hasil generate payroll SEBELUM refactor buat dibandingkan SESUDAH?
4. **Distribusi `PAYROLL_ENCRYPTION_KEY`/`PAYROLL_PREVIOUS_KEYS`** ke `.env` server produksi — siapa pegang, disimpan di mana (password manager tim?), backup-nya gimana (hilang key = semua nominal payroll gak bisa dibuka permanen, gak ada cara recovery).
5. Belum dicek: apakah ada tempat lain (export Excel/PDF, API, report) yang query RAW (`DB::table`/`DB::select`) ke salah satu dari 6+1 tabel scope, di luar yang udah ketemu lewat grep sesi ini — perlu 1 putaran grep terakhir yang lebih luas (`DB::table('payrolls'`, `DB::table('payroll_items'`, dst) sebelum eksekusi, biar gak ada titik kelewat pas cast diaktifkan.

## 7. Tambahan scope: `settings.value` key `bpjs_declared_value` — enkripsi selektif per-key

User minta **1 key spesifik** dari `settings.value` ikut dienkripsi: `bpjs_declared_value` (basis potongan BPJS buat pangkat yang di-mapping "Nilai Tetap Perusahaan"). Ini beda kelas dari §6 dokumen lama yang nolak enkripsi BLANKET 1 kolom `settings.value` (karena isinya campur tipe/dipakai buat logic langsung) — di sini scope-nya cuma 1 key spesifik, bukan seluruh kolom.

**Row count**: `settings` total 65 baris, `key = 'bpjs_declared_value'` 3 baris (dari 3 company yang sudah override — sisanya masih default global via `Setting::resolve()` fallback).

**Verifikasi jalur baca/tulis** (`app/Models/Setting.php`, `AbstractBpjsCalculator.php:43`): SATU-SATUNYA cara baca nilai ini adalah `Setting::resolve('bpjs_declared_value', $companyId)` — `WHERE key = ? AND (company_id = ? OR company_id IS NULL) ORDER BY (company_id IS NULL) ASC LIMIT 1`, baru `match($row->type)` buat cast tipe. **Tidak ada SQL yang SUM/WHERE/ORDER berdasarkan ISI `value` itu sendiri** — filter selalu berdasarkan `key`/`company_id`, bukan `value`. Aman buat dienkripsi tanpa refactor SQL apa pun (beda dari kolom `amount` di §4 yang banyak kena SUM/sortable).

**Desain enkripsi selektif per-key** (belum ditulis kode) — kolom `settings.value` TETAP nampung 64 baris lain plaintext (persentase, threshold, string metode dst yang HARUS tetap plaintext buat logic langsung), cuma baris `key = 'bpjs_declared_value'` yang ciphertext. Opsi:
- **Accessor/mutator kondisional** di model `Setting` (`Illuminate\Database\Eloquent\Casts\Attribute`, closure `get`/`set` yang terima `$attributes` array) — cek `$attributes['key'] === 'bpjs_declared_value'` sebelum decrypt/encrypt. **Risiko urutan atribut**: closure `set` cuma bisa baca `$attributes['key']` kalau `key` sudah ke-assign SEBELUM `value` di proses mass-assignment (urutan `$fillable`/array yang dikirim ke `create()`/`fill()`) — perlu dicek urutan di semua jalur tulis (`PayrollSettingsSeeder`, halaman admin Pengaturan) supaya `key` selalu keisi duluan, atau pola closure-nya diubah supaya baca `key` dari model instance (`$this->key`) bukan `$attributes` parameter kalau itu lebih reliable di Eloquent versi yang dipakai — perlu dicek versi Laravel.
- Pakai key encryption yang SAMA dengan payroll (`PayrollEncrypted` cast §5) atau key terpisah lagi khusus settings — belum diputuskan, defaultnya samain aja (1 key payroll buat semua nominal payroll-related, termasuk `bpjs_declared_value` karena secara fungsi ini basis BPJS payroll).

**Blocker tipe kolom**: `settings.value` = `string` (`database/migrations/2026_07_16_150600_create_settings_table.php:29`, varchar default 255). Output enkripsi Laravel (`Crypt::encryptString()`/`encrypted` cast) = JSON base64 berisi `iv`+`value`+`mac`(+`tag` kalau GCM) — buat plaintext pendek kayak `"5000000"` biasanya jadi ~200-300+ karakter, MEPET/berisiko kepotong di varchar(255) tergantung cipher. **Wajib migration ubah `value` jadi `text` dulu** sebelum enkripsi apa pun ditempel — riskan kalau kelewat, karena 64 baris lain (plaintext, jauh lebih pendek) gak bakal kelihatan masalahnya sampai baris `bpjs_declared_value` dicoba disimpan dan ke-truncate diam-diam.

**Belum settled**: strategi reencrypt 3 baris existing (sama pola §6.1 — reencrypt 3 baris itu doang, bukan 65 baris, tapi migration tipe kolom `string→text` di atas kena SEMUA 65 baris sekaligus meski isinya tetap plaintext buat 62 baris lain).

## 8. Ringkasan scope final (payroll inti, tanpa trainer)

**9 model, 13 kolom, 12 titik refactor SQL** (naik dari klaim awal karena ada `PayrollManualLine` yang ketinggalan total + beberapa titik `orderByDesc` di dashboard yang gak kehitung di dokumen lama):

- Aman langsung (5 model): `PositionAllowance`, `LegacyPositionAllowance`, `TransportAllowance`, `JasaUnitDetail`, `PayrollManualLine`
- Butuh refactor (6 model, 12 titik SQL): `Incentive` (3 titik), `UserKoperasi` (3 titik), `FeeItem` (1 titik), `OmsetDetail` (1 titik, jalur kritis generate payroll), `ExecutiveAllowance` (1 titik), `PayrollItem` (2 titik, 1 jalur kritis generate payroll)
- Kritikal terpisah: `Payroll` (2 titik SUM/orderBy + 1 blocker fundamental `increment()`/`decrement()`)
- Bukan nominal langsung tapi ikut: `AuditLog` (blanket, no refactor)

Nunggu perintah eksekusi. Kalau lanjut, urutan yang disarankan: (1) infra key + cast (§5), (2) beresin blocker kritikal `PayrollItemsRelationManager` dulu, (3) refactor 12 titik SQL sisanya, (4) putuskan & jalankan strategi reencrypt (§6.1), (5) baru tempel cast ke semua model, (6) verifikasi tiap titik pakai data real.
