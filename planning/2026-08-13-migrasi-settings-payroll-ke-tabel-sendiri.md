# Planning: Migrasi Setting Payroll ke Tabel `company_payroll_settings` Sendiri (2026-08-13)

Status: **DI-SKIP (2026-08-13).** Setelah riset §1-5, direkomendasikan gak worth — masalah asli (accessor selektif rapuh kena `Builder::pluck()`) udah kefix di kode (selalu `get()->pluck()`, bukan `Builder::pluck()` langsung, buat kolom yang punya accessor kondisional). Bikin tabel baru cuma buat mindahin 4 kolom nominal effort-nya gak sepadan (15 titik baca + 2 jalur tulis paralel + fitur "Pengaturan Lainnya" kena refactor). User setuju skip. Dokumen ini disimpan sebagai referensi kalau nanti ada alasan baru yang lebih kuat (mis. kebutuhan query SQL langsung ke nominal payroll settings). Lanjutan `2026-08-13-payroll-enkripsi-nominal-key-terpisah.md` (§7 dokumen itu — `bpjs_declared_value` sekarang dienkripsi selektif per-key di kolom generik `settings.value`, sempat ketemu bug `Builder::pluck()` kehilangan konteks `key`). User tanya: worth dipisah ke tabel khusus payroll, biar gak perlu trik selektif itu lagi. Modul LAIN (attendance/task/LMS/kepegawaian) TETAP di `settings` — cuma payroll yang dipindah.

## 1. Kenapa ide ini muncul

Tabel `settings` generik: 1 kolom `value` (`text`, campur tipe: string/integer/decimal/boolean, dibedakan kolom `type`, di-cast manual `Setting::resolve()`). Karena campur tipe, enkripsi kolom nominal (`bpjs_declared_value`) harus SELEKTIF per-baris (accessor/mutator kondisional cek `key`), bukan cast biasa — lebih rapuh (ketahuan kemarin: `Builder::pluck()` bikin model sintetis yang kehilangan attribute `key` sibling, accessor gak tau harus decrypt, nilai balik ciphertext mentah ke UI).

Tabel dedicated `company_payroll_settings` (kolom native per-key, atau minimal pisah `value_nominal`/`value_text`) menghilangkan masalah ini — kolom nominal tinggal `PayrollEncrypted` cast polos, sama seperti 12 model lain yang sudah dikerjakan.

## 2. Inventori lengkap — apa isi `settings` module=payroll sekarang

Dicek langsung ke DB: **41 dari 65 baris** `settings` ber-`module='payroll'`. 16 key unik:

| Key | Type | Baris | Company-scoped? | Ditulis dari `PayrollSettingsSeeder`? |
|---|---|---|---|---|
| `gaji_pokok_kenaikan_per_kelas` | decimal | 3 | Ya | Ya |
| `prorate_metode` | string | 0* | Ya | Ya (belum ke-seed di DB dev ini) |
| `ump` | decimal | 3 | Ya | Ya |
| `bpjs_kesehatan_rate` | decimal | 1 | **Tidak, selalu global** (`GlobalPayrollSettingEnum`) | Ya |
| `bpjs_ketenagakerjaan_rate` | decimal | 1 | **Tidak, selalu global** | Ya |
| `bpjs_declared_value` | decimal | 3 | Ya | Ya — **sudah dienkripsi selektif** |
| `keterlambatan_threshold_menit` | integer | 3 | Ya | Ya |
| `keterlambatan_denda` | decimal | 3 | Ya | Ya |
| `keterlambatan_grace_count` | integer | 3 | Ya | Ya |
| `izin_potongan_flat` | decimal | 3 | Ya | Ya |
| `pajak_jasa_dokter_rate` | decimal | 3 | Ya | Ya |
| `insentif_metode` | string | 3 | Ya | Ya |
| `izin_seharian_metode` | string | 2 | Ya | Ya |
| `izin_seharian_persen_gaji_pokok` | decimal | 2 | Ya | Ya |
| `bon_metode` | string | 2 | Ya | Ya |
| `lembur_minimum_jam` | integer | 3 | Ya | Ya |
| `min_total_working_hours_per_month` | integer | 3 | Ya | Kepegawaian, BUKAN payroll — cek ulang, mungkin salah module atau dipakai lintas modul |

**Kolom nominal Rupiah beneran** (kandidat `PayrollEncrypted`, bukan integer/string): `ump`, `bpjs_declared_value`, `keterlambatan_denda`, `izin_potongan_flat`. Sisanya (rate %, threshold menit/jam, count, metode string) TETAP plaintext — sama keputusan lama (§9.2 dokumen enkripsi awal), gak berubah cuma karena pindah tabel.

## 3. Semua titik yang kena kalau pindah tabel

### Baca (`Setting::resolve('key', $companyId)`, khusus module payroll) — 15 titik

`AbstractBpjsCalculator.php` (×3: `ump`, `bpjs_declared_value`, rate), `AbstractIzinFlatCalculator.php` (`izin_potongan_flat`), `ResolvesProrateDivisor.php` (×3: `prorate_metode` dipanggil 2x + 1 turunan), `FeeClaimCalculator.php` (`pajak_jasa_dokter_rate`), `GajiPokokCalculator.php` (`gaji_pokok_kenaikan_per_kelas`), `IncentiveCalculator.php` (`insentif_metode`), `IzinSeharianCalculator.php` (`izin_seharian_metode`), `IzinSeharianGajiPokokCalculator.php` (`izin_seharian_persen_gaji_pokok`), `KeterlambatanCalculator.php` (×2: `keterlambatan_denda`, `keterlambatan_grace_count`), `LemburCalculator.php` (`lembur_minimum_jam`), `PayrollComponentEnum.php` (×4: BPJS rate label, `keterlambatan_threshold_menit`, `lembur_minimum_jam` — buat label UI, bukan kalkulasi), `BonImport.php`/`BonDetailImport.php` (`bon_metode`), `IncentivesPage.php`/`PayrollDashboardPage.php` (`insentif_metode`, cuma buat display), `PayrollDetailPage.php`/`PayrollsPage.php` (`izin_seharian_metode`, `bon_metode`).

### Tulis — **2 UI terpisah, keduanya harus dimigrasi bareng**

1. **`SalaryComponentsPage.php`** (Livewire, `/keuangan/...`) — halaman utama "Komponen Gaji", 3 lapis (global/required-per-komponen/flexible "Pengaturan Lainnya"). Method `upsertSetting()` (line 256) generik, dipanggil dari `save()` buat semua key sekaligus.
2. **`PayrollComponentDetail.php`** (Filament admin page, `app/Filament/Pages/PayrollComponents/`) — **DUPLIKAT jalur tulis** yang independen, `saveComponentForm()` (line 199) langsung `Setting::updateOrCreate()` juga. Kedua halaman ini nulis ke baris `settings` YANG SAMA — kalau tabel dipindah, DUA-DUANYA harus diupdate konsisten atau salah satu jadi orphan/berantakan.

### Elemen desain yang bikin migrasi ini BUKAN cuma "copy 16 kolom"

- **Layer "Pengaturan Lainnya"/flexible** (`SalaryComponentsPage.php` §3, "sisa setting module=payroll yang belum diklaim") — memungkinkan admin nambah setting payroll BARU tanpa migration kolom, lewat UI generik. Kalau pindah ke kolom native per-key, fitur ekstensibilitas ini HILANG kecuali disediakan mekanisme pengganti (kolom JSON `extra` di tabel baru? tetap simpan sisa di `settings` lama, cuma yang "dikenal" pindah?). **Belum ada jawaban** — perlu diputuskan sebelum desain skema final.
- **Global vs company-scoped** — `bpjs_kesehatan_rate`/`bpjs_ketenagakerjaan_rate` SELALU `company_id NULL`, sisanya SELALU company-scoped (fallback ke global kalau baris company kosong). Tabel baru perlu tetap punya `company_id` nullable + fallback query yang sama, atau pisah 2 tabel (global vs per-company) — trade-off desain.
- **Metadata deskriptif** (`label`, `description`, `type`) dipakai buat render form otomatis (`PayrollComponentDetail.php` baca `$global->label`/`$global->type` buat generate field). Kalau kolom jadi native per-key, metadata ini (label/help text) harus pindah ke mana — hardcode di enum (`PayrollComponentEnum`/key baru) kemungkinan, bukan data-driven lagi.

## 4. Rekomendasi desain awal (draf, belum final)

Opsi tengah yang kemungkinan paling proporsional — BUKAN "16 kolom native semua", tapi:
- Tabel baru `company_payroll_settings`: kolom native cuma buat 4 key nominal (`ump`, `bpjs_declared_value`, `keterlambatan_denda`, `izin_potongan_flat`) + `company_id` + timestamps, masing-masing kolom decimal asli lalu di-cast `PayrollEncrypted` (pola sama 12 model lain).
- 12 key non-nominal (rate/threshold/metode) **TETAP di `settings`** apa adanya — gak ada alasan kuat mindahin yang bukan nominal, resiko regresi flexible-layer/metadata cuma buat masalah yang gak eksis di situ (bukan nominal, gak butuh enkripsi).
- Konsekuensi: `Setting::resolve()` tetap dipakai buat 12 key lama, 4 key nominal pindah ke method baru (mis. `PayrollSetting::resolve()` atau field akses langsung by company). SEMUA 15 titik baca + 2 titik tulis kena refactor SATU-SATU (bukan bisa disamaratakan), karena sebagian key pindah sebagian nggak.

**Alternatif lebih murah** (worth dipertimbangkan sebelum commit ke rencana di atas): TETAP di `settings.value`, tapi ganti pendekatan encryption dari "accessor selektif berbasis sibling attribute" (rapuh terhadap `pluck()`, `select()` parsial, dsb — kelas bug yang baru ketemu) ke pendekatan yang gak bergantung context row lain — misalnya kolom BARU `value_encrypted` (nullable, dipakai KHUSUS 4 key nominal, `value` lama dikosongkan/diisi placeholder buat baris itu). Lebih kecil scope-nya dari bikin tabel+model+migrasi 2 UI, tapi tetap menghilangkan kerapuhan `pluck()`.

## 5. Belum settled — keputusan yang perlu diambil sebelum eksekusi

1. **Scope migrasi**: SEMUA 16 key pindah tabel, atau cuma 4 key nominal (rekomendasi §4)? Menentukan besar kecilnya refactor drastis (16 titik baca+2 titik tulis penuh vs cuma sebagian).
2. **Nasib fitur "Pengaturan Lainnya"/flexible** — kalau sebagian/semua key pindah ke kolom native, gimana caranya admin masih bisa nambah setting payroll baru tanpa minta migration tiap kali?
3. **PayrollComponentDetail.php (Filament) vs SalaryComponentsPage.php (Livewire)** — 2 jalur tulis paralel yang HARUS tetap konsisten. Salah satu dianggap sumber kebenaran / digabung, atau tetap dua-duanya dipertahankan tapi disinkronkan ke tabel baru?
4. **Company_id fallback** (`bpjs_kesehatan_rate`/`bpjs_ketenagakerjaan_rate` global vs sisanya company-scoped-with-fallback) — direplikasi persis di tabel baru, atau didesain ulang?
5. **Metadata (label/description)** — kalau pindah ke kolom native, dari mana UI ambil label buat ditampilkan? Hardcode di kode (kurang fleksibel tapi lebih sederhana) vs tetap data-driven (butuh tabel metadata terpisah)?
6. **Alternatif §4 lebih murah (kolom `value_encrypted` tambahan) belum dibandingkan cost/benefit-nya secara eksplisit sama user** — perlu diputuskan sebelum masuk desain skema final, biar gak kerja dobel.

Belum ada kode yang ditulis buat bagian ini. Tunggu keputusan di atas + perintah eksekusi.
