# Analisis Payroll #6 — Enkripsi Nominal (Payroll Items, Manual Lines, Eksekutif) — 2026-08-12

Diskusi dipicu permintaan direktur/atasan: nominal payroll (`payroll_items`, `payroll_manual_lines`, `executive_allowances`, agregat di `payrolls`) mau dienkripsi biar tidak bocor. Sampai titik ini BARU DISKUSI/ANALISIS, belum ada eksekusi kode/migration.

## 1. Threat Model (diklarifikasi eksplisit ke user)

Dua ancaman berbeda, butuh solusi berbeda — jangan dicampur:

1. **DB/backup dicuri dari luar** (server kena hack, backup ke-copy, disk dicuri).
2. **Orang dalam yang punya akses query DB langsung** (DBA/developer bisa `SELECT` tabel payroll langsung, lihat nominal siapa saja).

User pilih: **keduanya sekaligus**.

## 2. Kenapa field-level encryption polos (Eloquent `encrypted` cast) TIDAK bisa langsung tempel

`PayrollDashboardPage.php` pakai SQL-level `SUM()` langsung di kolom nominal:
- `:89` — `Payroll::periodPayrolls()->sum('net_salary')`
- `:139` — `selectRaw('source_type, type, sum(amount) as total')` (breakdown per komponen)
- `:186` — `scopedIncentives()->sum('amount')`
- `:209` — `scopedKoperasi()->sum('amount')`

Kolom yang di-cast `encrypted` (Laravel) ciphertext-nya acak per-value (AES-CBC via `APP_KEY`) — `SUM()`/`WHERE`/sort SQL langsung mati kalau kolom itu dienkripsi. Ke-4 titik ini WAJIB direfactor jadi ambil row lalu jumlah di PHP (`->get()->sum(fn($r) => $r->kolom)`) kalau field-level encryption jadi dipakai. Cek juga: `PayrollsTable.php`/`PayrollItemsRelationManager.php` — kolom nominal di situ **tidak** ada yang `->sortable()`/`->searchable()`, jadi UI Filament aman, cuma 4 titik SUM di atas yang kena.

Timing bagus buat perubahan tipe kolom (decimal→text buat nampung ciphertext): `payrolls` masih **0 baris** di semua environment (README, dikonfirmasi ulang 2026-08-11) — belum ada migrasi data lama yang perlu di-reencrypt.

## 3. Opsi yang dibahas (ranking, bukan kronologis)

| # | Opsi | Nutup ancaman | Trade-off |
|---|---|---|---|
| A | **MySQL InnoDB tablespace encryption** (infra, `keyring_file` plugin, `ALTER TABLE ... ENCRYPTION='Y'`) | #1 (DB/backup dicuri) | Transparan, nol kode app, SUM/query tetap normal. TIDAK nutup #2 (app/DBA tetap lihat plaintext lewat query biasa). |
| B | **App-level, `encrypted` cast + `APP_KEY` tunggal** | #1 + #2 | Refactor 4 titik SUM (§2). `APP_KEY` gak boleh hilang/berubah tanpa proses reencrypt eksplisit (Laravel gak punya tool bawaan buat rotasi). |
| C | **Envelope encryption per periode** — tiap `payroll_periods` punya DEK random sendiri (kolom baru `encryption_key`, di-wrap pakai `APP_KEY`), dipakai encrypt nominal periode itu | #1 + #2, PLUS rotasi `APP_KEY` jadi murah (cuma re-wrap DEK kecil per periode, bukan reencrypt semua `payroll_items`) + blast radius kebatasi per periode | Custom cast class, kolom `payroll_period_id` didenormalisasi ke `payroll_items` (biar cast baca periode tanpa N+1 query), cache DEK di memory per request. |
| D | **Envelope + KMS/Vault eksternal** (GCP KMS/AWS KMS/HashiCorp Vault) sebagai pemegang master key | A+B/C, plus hilangnya "1 file `.env` = kunci semua data" sebagai single point of failure di server sendiri | Dependency jaringan ke KMS (KMS down = payroll gak bisa dibuka), Vault self-hosted butuh unseal & maintenance sendiri, GCP/AWS KMS murah (~$0.06/bulan/key + $0.03/10rb request) tapi butuh akun cloud baru — project ini sekarang full self-hosted VPS (deploy Bitbucket Pipelines SSH), belum ada bukti pakai cloud aktif (`AWS_*` di `.env.example` cuma placeholder skeleton Laravel, belum dipakai). |

**Rekomendasi**: **A + B** dulu. Proporsional ke skala (HRIS internal, payroll 0 baris, tim ops kecil, deploy manual). C didokumentasikan sebagai upgrade path kalau rotasi key jadi kebutuhan nyata. D baru relevan kalau ada mandate compliance/audit eksternal eksplisit.

## 4. Skema "password per periode" yang diajukan user — dianalisis, ditolak sebagai mekanisme enkripsi utama

User mengajukan: saat generate payroll 1 periode, user wajib bikin password (mis. "GAJI062026"), disimpan di kolom periode, key enkripsi = kombinasi `password + user_id + payroll_id + nominal`.

**Iterasi 1 — password disimpan mentah di kolom `payroll_periods`**: FATAL. Key ingredient (`password`, `user_id`, `payroll_id`) semua ada di database yang sama — orang dengan akses `SELECT` (persis ancaman #2) tinggal join 2 tabel, langsung reconstruct key. Enkripsi jadi teater, sama seperti backup Excel/PDF rekap "buat jaga-jaga lupa password" — itu sendiri jadi salinan plaintext yang malah lebih gampang bocor (attachment WA/email) dibanding DB-nya.

**Iterasi 2 — password TIDAK disimpan mentah, cuma hash-nya (buat verifikasi), key derivation dari password asli**: Fatal flaw #1 (key nempel ciphertext) hilang. Tapi masalah lebih besar muncul: **siapa saja yang butuh baca nominal SETELAH digenerate, di luar sesi generator, gak bisa decrypt** —
- `SendPayrollSlipJob` (queued, `App\Jobs\SendPayrollSlipJob`, dipicu `PayrollDetailPage.php:196` & `PayrollsPage.php:224` lewat klik manusia, TAPI eksekusi aktualnya di worker background terpisah) — gak ada manusia hadir buat masukin password saat job jalan.
- Karyawan buka slip sendiri (`MyPayrollSlipPage`) — gak punya password itu (dan sengaja gak dikasih tau).
- Dashboard/tabel payroll dibuka orang lain (bukan generator) hari/minggu berikutnya.

User lalu setuju: **self-view karyawan boleh hilang** — tapi email slip (`SendPayrollSlipJob`) tetap harus jalan. Karena dispatch-nya berasal dari klik manusia (bukan job terjadwal murni otomatis), skema BISA dipakai KHUSUS buat alur "Kirim Slip" — DENGAN SYARAT:
- Decrypt + build PDF **wajib sinkron di request yang sama** saat password divalidasi — TIDAK BOLEH masukkan password mentah sebagai parameter job (`SendPayrollSlipJob::dispatch($id, $password)`), karena queue driver project ini `database` (README) — payload job (termasuk password) akan nampang di tabel `jobs`, dan kalau job gagal, **nyangkut permanen** di `failed_jobs` sampai dibersihkan manual. Balik ke masalah "key di database" lagi.
- Job yang di-queue cuma boleh pegang PDF bytes yang sudah jadi (sudah dienkripsi password per-karyawan lewat `PayrollService::encryptedSlip()`, mekanisme yang sudah ada — DOB+3 digit NIP), bukan raw amount/key.
- Konsekuensi UX: kirim massal (`PayrollsPage.php:224`, loop banyak karyawan) jadi kerja sinkron berat di 1 request, bukan lagi instant-klik-lalu-background — perlu progress indicator/pembatasan jumlah per klik kalau jadi dieksekusi.

**Soal friksi "password tiap aksi" (isi Bon, isi Omset, dst di periode yang sama)**: gak perlu tanya tiap aksi. Solusi: unlock sekali per sesi kerja, bukan per aksi — password/key di-cache di **session server**, scoped `user_id + payroll_period_id`, TTL (mis. 30–60 menit), reuse untuk semua aksi periode itu selama TTL belum habis. Laravel sudah punya pola serupa built-in: `Illuminate\Auth\Middleware\RequirePassword` (`password.confirm`, default timeout 3 jam) — tinggal adaptasi, jangan bangun dari nol.

**Tapi caching password di session balik ke masalah yang sama lagi**: session driver project ini `database` (README) — payload session (termasuk password yang di-cache) jadi baris di tabel `sessions`, tabel yang sama juga di DB yang sama. Penutupnya: aktifkan `SESSION_ENCRYPT=true` (opsi Laravel, belum dicek statusnya sekarang) — session payload otomatis dienkripsi pakai `APP_KEY` sebelum disimpan. **Artinya skema password-per-periode, sejauh apa pun dirapikan, ujungnya tetap butuh `APP_KEY` sebagai root of trust** — bukan pengganti opsi B/C, melainkan lapisan TAMBAHAN di atasnya.

## 5. Verdict akhir soal skema password

- **Ancaman #1 (DB/backup dicuri)**: password gate TIDAK menutup ini sama sekali — jalur curi-DB-langsung skip semua UI/session Laravel. Yang kerja di jalur ini cuma opsi A/B/C (enkripsi kolom + `APP_KEY`).
- **Ancaman #2 (insider dengan akses query)**: password gate menutup **insider yang TIDAK punya password**, tapi siapa pun yang legit butuh kerja di modul payroll (isi Bon/Omset/generate/kirim) HARUS dikasih tau password itu juga — jadi gak menutup insider yang justru paling berbahaya (yang legit punya akses & tau password, lalu nyalahgunain).
- **Manfaat nyata yang tersisa**: bukan enkripsi tambahan yang berarti, melainkan **lapisan otorisasi eksplisit / jejak "siapa sengaja buka data ini kapan"** (mirip sudo-mode/password confirmation) — valid kalau tujuannya audit trail perilaku, BUKAN kalau tujuannya murni "nominal tidak bocor lewat DB".
- **Kesimpulan**: skema password, setelah dirapikan penuh, teknis konsisten dan bisa jalan — tapi kompleksitas & friksi operasional (password harus dikoordinasikan tim finance tiap periode, prosedur kalau lupa/pegang orang salah, refactor `SendPayrollSlipJob` jadi sinkron) lebih besar dari manfaat keamanan tambahan dibanding opsi A+B polos, KECUALI ada alasan spesifik (requirement compliance, kebiasaan approval berlapis perusahaan) yang belum diungkap user. Pertanyaan ini masih terbuka.

## 6. Belum dijawab / langkah berikutnya

- Apakah ada requirement compliance/audit formal (ISO27001, dll) di balik permintaan direktur, atau proaktif murni? — nentuin apakah opsi C/D/password-gate worth ditambah di atas opsi A+B dasar.
- Status `SESSION_ENCRYPT` di `.env` sekarang — belum dicek.
- Kalau lanjut opsi A+B: perlu grep menyeluruh sekali lagi (belum tuntas) buat pastikan ke-4 titik SUM di §2 itu SATU-SATUNYA tempat query SQL langsung ke kolom nominal — jangan sampai ada yang kelewat pas migration jalan.

## 7. Klarifikasi lanjutan (2026-08-12, sesi berikutnya) — hash cuma buat verifikasi, key dari password mentah

Diklarifikasi ulang: skema Iterasi 2 (§4) yang dimaksud user PERSIS skenario "hash cuma buat verifikasi, key enkripsi diturunkan dari password ASLI (bukan dari hash-nya)" — bukan hash-nya sendiri yang dipakai/diturunkan jadi key. Ini penting dibedakan dari kemungkinan lain (hash itu sendiri jadi key material) yang FATAL — kalau hash tersimpan di tabel yang sama dengan data terenkripsi, insider dengan akses `SELECT` (ancaman #2) tinggal ambil hash itu langsung, setara nyimpen key mentah (sama fatal flaw-nya dengan Iterasi 1, cuma ganti nama kolom).

Dengan key = password asli (tidak pernah tersimpan, cuma ada di kepala orang yang ngetik saat itu), sudah dipetakan 3 skenario nyata yang butuh decrypt TANPA manusia yang tahu password hadir saat itu, dan sekarang ketiganya sudah ada jawaban:

1. **`SendPayrollSlipJob`** — decrypt+build PDF sinkron di request yang sama saat password divalidasi (bukan parameter job), job cuma bawa PDF jadi (pakai enkripsi DOB+NIP yang sudah ada). Sudah ada jalan keluarnya, lihat §4.
2. **Karyawan lihat slip sendiri** — dihapus dari requirement (disepakati §4).
3. **Dashboard/tabel payroll dibuka orang lain, hari/minggu berikutnya (bukan yang generate)** — **DIPUTUSKAN**: minta password periode itu lagi tiap kali mau buka (di-cache session TTL 30–60 menit biar tidak tanya berulang tiap klik dalam sesi kerja yang sama, pola `password.confirm` Laravel yang sudah disebut §4). Berlaku juga buat orang yang generate sendiri kalau sesi/TTL-nya sudah habis — tidak ada pengecualian "generator selalu bisa lihat tanpa password lagi".

**Implikasi operasional yang perlu disadari** (belum dibahas eksplisit, perlu dikonfirmasi sebelum implementasi): karena SEMUA viewer dashboard/tabel (bukan cuma generator) butuh tahu password periode itu, tim finance/siapa pun yang rutin cek dashboard payroll butuh cara terima password itu tiap periode - balik ke masalah distribusi yang sama seperti password WiFi kantor (dikirim ke grup WA/email = jadi salinan plaintext yang lebih gampang bocor dibanding DB-nya, persis kekhawatiran §4). Perlu diputuskan mekanisme distribusi password periode ke tim yang legit butuh akses, di luar generator awal.

## 8. Keputusan simplifikasi (2026-08-12) — balik ke Opsi B polos, skema password didrop

Setelah eksplorasi ISO 27001 (kontrol A.8.24/A.5.12/A.8.3/A.5.14) dan feasibility teknis skema password-per-periode (custom cast + KDF + resolver session + denormalisasi `payroll_period_id`, semua dimungkinkan tapi kompleks), user memutuskan **simplifikasi**: pakai **1 key/`APP_KEY` saja** (Opsi B), skema password-per-periode dan Opsi C/D didrop dari rencana eksekusi.

Trade-off yang diterima secara eksplisit: siapa pun yang punya akses `.env` (`APP_KEY`) SEKALIGUS akses query DB bisa decrypt manual — ini diterima karena levelnya sudah "punya kunci semua sistem" (developer/ops), beda dari ancaman #2 (orang yang CUMA punya `SELECT` tanpa `APP_KEY`, yang tetap tertutup). User pribadi (lewat aplikasi, permission Shield biasa) tetap lihat nominal transparan seperti sekarang, tanpa gerbang password/session tambahan.

Opsi A (MySQL tablespace encryption) tidak disebut ulang saat keputusan ini - diasumsikan didrop juga (butuh akses server yang tidak tersedia dari sesi kerja ini). Kalau nanti mau ditambah lagi sebagai defense-in-depth, itu kerjaan infra terpisah, tidak menghalangi Opsi B jalan duluan.

## 9. Audit menyeluruh kolom nominal — perluasan scope (2026-08-12)

User minta scope diperluas ke SEMUA data master/config yang menyimpan nominal (Insentif, Koperasi, Tunjangan Jabatan, Tunjangan Transportasi, UMP/UMK), bukan cuma `payroll_items` dkk. Hasil audit menyeluruh (baca kode langsung, bukan asumsi):

### 9.1 Tabel/kolom nominal ditemukan, per kategori

| Kategori | Tabel.kolom | Status |
|---|---|---|
| Insentif | `incentives.amount` | **Perlu refactor** |
| Koperasi | `user_koperasi.amount` | **Perlu refactor** |
| Tunjangan Jabatan | `position_allowances.amount` | Aman langsung |
| Tunjangan Jabatan (lama) | `legacy_position_allowances.amount` | Aman langsung |
| Tunjangan Transportasi | `transport_allowances.amount` | Aman langsung |
| UMP | `settings.value` (key=`ump`) | Kasus khusus, lihat §9.2 |
| UMK | — | **Tidak ada di kode sama sekali**, tidak perlu ditangani |
| Fee Klaim/Jasa Dokter | `fee_items.amount` | **Perlu refactor** |
| Omset (basis Jasa Pelayanan) | `omset_details.amount` | **Perlu refactor** (dipanggil tiap Generate Payroll, bukan cuma dashboard) |
| Jasa Unit | `jasa_unit_details.amount` | Aman langsung |
| Gaji Pokok | `pangkat_salaries.base_salary` | Aman langsung (dikonfirmasi sebelumnya) |
| Lembur | `pangkat_lembur_rates.amount_per_hour` | Aman langsung (dikonfirmasi sebelumnya) |
| Tunjangan Masa Kerja | `tenure_allowances.amount` | Aman langsung (dikonfirmasi sebelumnya) |
| Eksekutif | `executive_allowances.amount` | **Perlu refactor** (`sortable()` di tabel admin) |
| Payroll (transaksional) | `payroll_items.amount`/`unit_amount` | **Perlu refactor** (2 titik SUM baru ditemukan, lihat §9.3) |
| Payroll (agregat) | `payrolls.gross_salary`/`total_deductions`/`net_salary` | **Perlu refactor KRITIKAL** (lihat §9.3, `increment()`/`decrement()`) |
| Setting nominal lain | `settings.value` (key=`keterlambatan_denda`, `izin_potongan_flat`, `bpjs_declared_value`) | Kasus khusus, lihat §9.2 |

"Aman langsung" = dibaca via `->first()`/`->value()`/relasi FK/Collection setelah `->get()`, tidak pernah kena SQL `WHERE`/`ORDER BY`/`SUM` langsung pada kolom nominal itu sendiri — bisa langsung dikasih cast `encrypted` tanpa refactor apa pun. Pola ini persis seperti `pangkat_salaries.base_salary` yang sudah dikonfirmasi lebih dulu.

### 9.2 Kasus khusus: `settings.value`

Kolom generik yang nyimpen campuran tipe (string/integer/decimal/boolean, dibedakan lewat kolom `type`, di-cast manual lewat `Setting::resolve()`). Cuma 4 key yang benar-benar nominal rupiah: `ump`, `bpjs_declared_value`, `keterlambatan_denda`, `izin_potongan_flat`. Sisanya (persentase, threshold, count, string metode) HARUS TETAP PLAINTEXT karena dibaca langsung buat logic (`gaji_pokok_kenaikan_per_kelas`, `bon_metode`, dst).

**Tidak bisa enkripsi blanket 1 kolom itu** — butuh keputusan desain sendiri: enkripsi selektif per-baris berdasarkan `key` (cast kondisional di accessor/mutator model), atau kolom baru `value_encrypted` terpisah khusus baris nominal. Belum diputuskan.

### 9.3 Temuan kritikal pada kolom yang SUDAH dikonfirmasi sebelumnya

Dua pola baru ditemukan yang akan MERUSAK kolom yang sudah masuk daftar confirmed kalau dienkripsi tanpa refactor dulu:

1. **`payroll_items.amount`** kena SQL `SUM` di 2 titik BARU (selain 4 titik dashboard yang sudah diketahui):
   - `IzinSeharianGajiPokokCalculator.php:37-40` — `PayrollItem::query()->where(...)->sum('amount')`, dipanggil tiap Generate Payroll (bukan cuma dashboard).
   - `PayrollDashboardPage.php:139` — `selectRaw('...sum(amount)...')->orderByDesc('total')` (chart breakdown komponen).

2. **`payrolls.gross_salary/net_salary/total_deductions`** dipakai `Model::increment()`/`decrement()` di `PayrollItemsRelationManager.php:218-222` (fitur Add/Edit Adjustment manual pada slip). **Ini fundamental tidak kompatibel** dengan `encrypted` cast Laravel — `increment()` selalu generate SQL `UPDATE col = col + ?` mentah di level DB, cast Laravel tidak berlaku untuk ekspresi SQL. WAJIB direfactor jadi baca-tambah-simpan di PHP (bukan cuma "nice to have", ini blocker kalau tidak dibereskan).

### 9.4 Temuan tambahan (di luar permintaan awal, tapi krusial) — kebocoran lewat `audit_logs`

7 model yang nyimpen nominal (`PangkatSalary`, `TenureAllowance`, `PositionAllowance`, `TransportAllowance`, `PangkatLemburRate`, `LegacyPositionAllowance`, `Setting`) SEMUA pakai trait `App\Models\Concerns\LogsAuditChanges` — otomatis catat `old_value`/`new_value` ke tabel `audit_logs` tiap kali field yang di-watch (persis kolom nominalnya - `base_salary`, `amount`, `amount_per_hour`, `value`) berubah.

**Masalahnya**: `LogsAuditChanges::recordAuditLog()` baca nilai lewat `getOriginal()` dan magic-getter (`$this->{$attr}`) — DUA-DUANYA otomatis lewat Eloquent cast. Jadi walau kolom sumber dienkripsi, kode ini tetap dapat nilai SUDAH TERDEKRIPSI (plaintext), lalu ditulis `(string) $old`/`(string) $new` ke `audit_logs.old_value`/`new_value` — kolom yang TIDAK PUNYA cast apa pun. Hasilnya: tiap kali admin EDIT nominal di tabel master mana pun, angka rupiahnya tetap kecatat polos di `audit_logs`, membatalkan enkripsi kolom sumbernya.

**Perbaikan** (lebih sederhana dari §9.2): tambah cast `encrypted` di model `AuditLog` untuk `old_value` dan `new_value`, BLANKET (tidak perlu selektif per-key seperti `settings.value`) - dikonfirmasi tidak ada satu pun tempat yang `sortable()`/`sum()`/filter SQL berdasarkan isi kedua kolom itu (cuma difilter by `key`/`module`, diurut by `created_at`). Kolom sudah bertipe `text` dari awal, aman menampung ciphertext tanpa migration tipe kolom.

### 9.5 Ringkasan total titik yang perlu ditangani

**12 titik refactor SQL** (naik dari 4 titik awal) tersebar di 6 file: `IncentivesTable.php`, `UserKoperasiResource.php`, `ExecutiveAllowancesTable.php`, `PayrollDashboardPage.php` (6 titik: baris net_salary x2, incentives x2, koperasi x2, breakdown komponen x1 - total 7 sebenarnya di file ini saja), `FeeSubmission.php`, `JasaPelayananCalculator.php`, `IzinSeharianGajiPokokCalculator.php`, `PayrollItemsRelationManager.php` (kritikal, `increment()`/`decrement()`).

**Plus** 2 keputusan desain yang belum diambil: strategi `settings.value` (§9.2), dan `AuditLog.old_value`/`new_value` (§9.4, sudah ada solusinya, tinggal eksekusi).

Model master rate yang AMAN LANGSUNG tanpa refactor: `position_allowances`, `transport_allowances`, `legacy_position_allowances`, `jasa_unit_details`, `pangkat_salaries`, `pangkat_lembur_rates`, `tenure_allowances`.
