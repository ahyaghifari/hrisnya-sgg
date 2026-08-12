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
- Belum diputuskan: dashboard/tabel payroll (`PayrollDashboardPage`, `PayrollsTable`) ikut digate password session juga, atau cukup dilindungi permission Shield yang sudah ada (`ViewAny:Payroll`, dll) tanpa password tambahan?
