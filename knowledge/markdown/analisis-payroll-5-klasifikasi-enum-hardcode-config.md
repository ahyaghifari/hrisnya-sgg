# Analisis Payroll #5 — Klasifikasi Enum vs Hardcode vs Config Table (2026-07-16)

Lanjutan dari [`analisis-payroll-3-skema-komponen-real.md`](analisis-payroll-3-skema-komponen-real.md) (arsitektur Registry+Strategy+Config) dan [`analisis-payroll-4-pembacaan-excel-skema-aktual.md`](analisis-payroll-4-pembacaan-excel-skema-aktual.md) (data nyata + jawaban user). Dokumen ini menjawab pertanyaan konkret user: **"mana yang ENUM, mana yang HARDCODE, mana yang tabel CONFIG?"** — plus permintaan baru: **1 tabel config generik, reusable lintas modul (payroll & bukan-payroll), dengan scope global + override per company.**

Status: keputusan desain, siap jadi basis Fase 0.

---

## 1. Temuan Kunci: Sudah Ada Prototipe yang Tepat — `attendance_policies`

Sebelum desain baru, ini fakta penting: **`attendance_policies`** (`company_id` nullable, `name`, `type`, `value`) **sudah punya bentuk & pola fallback yang persis diminta user**, dan **sudah dipakai** di [`PayrollService.php:528-534`](../../app/Services/Payroll/PayrollService.php):

```php
$policy = AttendancePolicies::query()
    ->where('type', 'min_total_working_hours_per_month')
    ->where(fn ($q) => $q->where('company_id', $period->company_id)->orWhereNull('company_id'))
    ->orderByRaw('(company_id IS NULL) ASC')   // company spesifik menang, baru fallback global
    ->first();
```

Ini **bukti hidup** pola "global + override per company" yang user minta sudah pernah dibangun dan terbukti jalan — cuma sekarang scope-nya sempit (nama tabel & 1 pemakaian `min_total_working_hours_per_month`). Rekomendasi: **generalisasi pola ini jadi 1 tabel config lintas modul**, bukan bikin tabel baru yang mirip-mirip (project ini sudah pernah kena masalah "2 tabel nyaris kembar" — `m_salary_components` vs `salary_components` — dan sengaja dirapikan di redesign 12 Juli. Jangan ulangi pola itu).

---

## 2. Keputusan: `attendance_policies` → generalisasi jadi `settings` (tabel config lintas modul)

**Bentuk akhir** (nama tabel diusulkan `settings`, model `App\Models\Setting`):

```
settings
  id             ulid, PK
  company_id     ulid, FK -> m_companies, NULLABLE (NULL = default global, dipakai
                 kalau tidak ada override company spesifik)
  module         string  (mis. 'payroll', 'attendance', 'kanban', dst — buat
                 pengelompokan tampilan admin & filter, BUKAN dipakai logic lookup)
  key            string  (mis. 'ump', 'bpjs_kesehatan_rate', 'keterlambatan_denda',
                 'cuti_tahunan_max_pengajuan_per_bulan')
  type           enum('integer','decimal','boolean','string')  — cara cast `value`
  value          string  (selalu disimpan sebagai string, di-cast sesuai `type` saat dibaca)
  label          string  (nama tampilan buat form admin, mis. "UMP (Upah Minimum Provinsi)")
  description    text, nullable (helper text form)
  timestamps

  unique(company_id, key)
```

**Cara pakai** (1 helper, dipakai semua modul):
```php
Setting::resolve('ump', $companyId);              // company spesifik, fallback global, cast sesuai type
Setting::resolve('cuti_tahunan_max_pengajuan_per_bulan', $companyId, default: null);
```

**Kenapa `module` cuma buat tampilan, bukan bagian lookup key**: supaya key tetap unik & stabil dipakai lintas konteks tanpa perlu tahu "modul mana yang manggil" — `PayrollService` dan `AttendaceReqService` bisa sama-sama baca key yang sama kalau memang perlu, tanpa duplikasi baris.

**Migrasi dari `attendance_policies`**: 1 baris data (`min_total_working_hours_per_month`) dipindah manual ke `settings` (bukan migration otomatis besar — cuma 1 baris). `attendance_policies` lama di-drop, `PayrollService::handleExtraHourAllowanceComponent()` diarahkan ke `Setting::resolve()`. Ini kerjaan kecil, aman dilakukan di Fase 0 sekalian.

### 2.1 Contoh isi `settings` untuk semua kebutuhan yang sudah diidentifikasi

| `key` | `module` | `type` | Contoh scope | Catatan |
|---|---|---|---|---|
| `ump` | payroll | decimal | per company (realistisnya SELALU diisi per company, tidak ada 1 UMP nasional yang masuk akal — tapi tetap boleh ada baris global sebagai fallback default kalau company baru belum di-setup) | Basis BPJS Pelaksana |
| `bpjs_kesehatan_rate` | payroll | decimal | global (1%), override per company kalau ada kontrak beda | |
| `bpjs_ketenagakerjaan_rate` | payroll | decimal | global (2%) | |
| `bpjs_declared_value` | payroll | decimal | **wajib per company** (tidak ada default global yang masuk akal) | Basis BPJS Supervisor/Manager |
| `keterlambatan_threshold_menit` | payroll | integer | global (5), override kalau ada company beda | |
| `keterlambatan_denda` | payroll | decimal | global (25000) | |
| `keterlambatan_grace_count` | payroll | integer | **per company** (Holding=1, company lain mungkin 0 = tidak ada grace) | |
| `izin_potongan_flat` | payroll | decimal | global (10000) | |
| `lembur_minimum_jam` | payroll | integer | global (2) | |
| `lembur_rate_tl` / `lembur_rate_tm` | payroll | decimal | company (Holding) | ⚠️ nunggu kejelasan arti TL/TM dulu |
| **`cuti_tahunan_max_pengajuan_per_bulan`** | **attendance** | integer | **per company** (Holding=5, company lain mungkin tidak dibatasi) | **Contoh baru dari user — bukti tabel ini memang harus lintas modul, bukan cuma payroll** |

Baris terakhir itu (`cuti_tahunan_max_pengajuan_per_bulan`) — permintaan baru yang baru muncul di sesi ini — **bukan aturan payroll**, itu **aturan pengajuan** (dicek pas `AttendaceReqService::storeLeaveRequest()`, bukan pas generate payroll). Ini justru bukti paling kuat kenapa tabel config-nya harus digeneralisasi lintas modul: kalau tetap dinamai/dianggap "attendance_policies" scoped ke payroll doang, aturan ini janggal ditaruh di situ; kalau dianggap tabel `settings` generik, pas.

---

## 3. Klasifikasi Lengkap: ENUM vs HARDCODE vs CONFIG TABLE vs TABEL RELASIONAL

### 3.1 ENUM (vocabulary tetap, dipakai branching kode — nambah nilai baru butuh developer & deploy)

Kriteria: kode **membaca nilainya untuk memutuskan jalur logika** (`match`/`if` berdasar nilai ini). Kalau cuma angka yang dipakai sebagai input rumus, itu BUKAN kandidat enum (itu masuk config table).

| Enum | Status | Kegunaan |
|---|---|---|
| `SalaryComponentCategoryEnum` | **Sudah ada**, perlu diperluas | Penentu kalkulator mana yang jalan (Layer 1→2 di Registry+Strategy). Tambah case baru: `bpjs_kesehatan`, `bpjs_ketenagakerjaan` (atau tetap 1 `bpjs` + parameter rate dari config — lihat §4), `tunjangan_masa_kerja`, dst sesuai komponen baru. |
| `PayrollItemSourceEnum` | **Sudah ada**, perlu diperluas | Provenance tiap baris payroll_items — tambah source per kalkulator baru. |
| **`BpjsBasisEnum`** (baru: `ump` \| `declared_value`) | **Baru — jawaban langsung buat pertanyaanmu soal pemetaan BPJS** | **Bukan baris di tabel `settings`** — ini kolom baru **langsung di `m_pangkats`**: `bpjs_basis` (enum). Karena `m_pangkats` **sudah scoped per company** (`Pangkats.php` company_id di-set otomatis lewat `boot()`), tiap company bisa punya pemetaan pangkat→basis BPJS sendiri lewat 1 dropdown di form Pangkat yang sudah ada — **tidak perlu tabel mapping baru**. Ini yang menjawab "mungkin bisa dipetakan saja di sistem" — admin tinggal pilih dropdown "Basis BPJS: UMP / Nilai Perusahaan" pas edit tiap Pangkat, bukan hardcode assumsi "Pelaksana+Staff+Koordinator vs sisanya" yang saya tulis sebagai asumsi sementara di dokumen #4. |
| `SalaryComponentTypeEnum` (earning/deduction) | Sudah ada | Tidak berubah. |
| `LeaveBasisEnum` (baru: `annual_balance` \| `event_based` \| `monthly_recurring`) | **Baru**, dari `analisis-payroll-3` §7.2 | Kolom baru di `m_leave_types` — nentuin cuti tahunan (saldo) vs melahirkan/keguguran (seputar event) vs haid (bulanan). |
| `SettingTypeEnum` (`integer`\|`decimal`\|`boolean`\|`string`) | **Baru**, kecil | Cast helper buat tabel `settings` itu sendiri (§2). |

### 3.2 HARDCODE (logika/struktur rumus — di dalam kelas Calculator, Layer 2)

Kriteria: **bentuk perhitungannya sendiri** (urutan operasi, rumus), bukan angkanya. Kalau berubah, itu perubahan LOGIKA BISNIS yang wajar butuh developer + code review — bukan hal yang finance mestinya bisa oprek sendiri dari UI.

| Yang di-hardcode | Di mana | Kenapa bukan config |
|---|---|---|
| Rumus Gaji Pokok: `base × (1 + rate × steps)` (additive, §7.3 dok #4) | `GajiPokokCalculator` | Bentuk rumusnya (additive, bukan compound) itu keputusan bisnis stabil. `rate` (8%) & `base` sendiri tetap dari config/tabel relasional (§3.4), cuma STRUKTUR `1 + rate×steps` yang hardcode. |
| Rumus Lembur Holding: `base_status / (hari×pembagi) × clamp_formula` (§3.6 dok #4) | `HoldingLemburCalculator` (khusus, dipilih Strategy per company) | Bentuk formula (pembagi hari×X, clamp 2-4, offset -0.5) itu spesifik & rumit, bukan sekadar 1 angka. Company lain pakai `ShiftOvertimeCostCalculator` yang beda total — makanya **beda company = beda KELAS Calculator**, bukan 1 formula digeneralisasi pakai config. |
| Logika grace keterlambatan (hitung kejadian ke berapa, skip kalau ≤ grace) | `KeterlambatanCalculator` | Alur logikanya (counting + conditional skip) hardcode; ANGKA grace count & denda dari `settings` (§2). |
| Logika pemilihan basis BPJS (baca `pangkat.bpjs_basis`, pilih UMP vs declared_value) | `BpjsCalculator` (1 kelas, param rate beda buat Kesehatan/Ketenagakerjaan) | Alur "kalau X pakai basis A, kalau Y pakai basis B" hardcode; rate % & nilai UMP/declared dari `settings`. |
| Registry pemetaan `category → Calculator class` | 1 array/service kecil (Layer 1→2 wiring) | Nambah kategori baru = nambah 1 baris array + 1 kelas baru, itu memang kerjaan developer, wajar. |
| Urutan eksekusi kalkulator (pendapatan dulu, baru potongan yang butuh cross-reference) | `PayrollService::generatePayroll()` (jadi orchestrator tipis) | Urutan ini aturan sistem, bukan sesuatu yang finance perlu ubah. |

### 3.3 CONFIG TABLE `settings` (angka tunggal, global+override company — Layer 3, generik)

Sudah didaftar lengkap di §2.1. Prinsip: **kalau nilainya cuma 1 angka/boolean/string yang dipakai langsung sebagai parameter rumus atau syarat**, dan **wajar diubah finance/HC tanpa nunggu developer**, taruh di sini.

### 3.4 TABEL RELASIONAL/MATRIKS (Layer 3, tapi bukan key-value — butuh multi-dimensi)

Kriteria: nilainya **tidak bisa direpresentasikan 1 baris key-value** — perlu kombinasi ≥2 dimensi (pangkat×kelas, company×tahun, dst). Ini **tetap "config"** secara filosofi (table-driven, editable tanpa deploy), tapi bentuk tabelnya beda dari `settings` generik.

| Tabel | Dimensi | Menggantikan/memperluas |
|---|---|---|
| `m_pangkats` + kolom baru | `start_kelas_level`, `max_kelas_level`, `class_increment_rate` (8%, per pangkat/company — bisa beda per company kalau nanti perlu), `base_salary` (per company — pangkat sudah company-scoped), **`bpjs_basis`** (enum, §3.1) | Extend tabel existing, bukan tabel baru |
| `m_kelas` + kolom baru | `level` (int 1-15) | Extend tabel existing |
| `position_allowances` (baru) | company × pangkat × kelas → amount | Tunjangan Jabatan matrix |
| `transport_allowances` (baru) | company × kelas → amount | Tunjangan Transportasi |
| `tenure_allowances` (baru) | company × tahun(bracket) → amount | Tunjangan Masa Kerja |

---

## 4. Ringkasan Aturan Keputusan (biar konsisten ke depan, dipakai tiap ada komponen baru)

Saat menambah aturan gaji/attendance baru, tanya berurutan:

1. **Apakah kode perlu BERCABANG berdasarkan nilai ini (pilih logika A vs B)?** → **ENUM**.
2. **Kalau bukan #1 — apakah nilainya butuh ≥2 dimensi (matriks/bracket) buat dicari?** → **Tabel relasional** (§3.4).
3. **Kalau bukan #1 dan #2 — apakah ini SATU angka/boolean/string yang jadi parameter, dan wajar diubah non-developer?** → **`settings`** (§2/§3.3).
4. **Kalau bukan semua di atas — ini bentuk/urutan operasi rumus itu sendiri** → **Hardcode di Calculator** (§3.2).

---

## 5. Dampak ke Rencana Fase 0 (`analisis-payroll-3` §9)

Tambahan konkret ke Fase 0:
- Bangun tabel `settings` (generalisasi `attendance_policies`) — **prioritas tinggi**, karena dipakai banyak kalkulator sekaligus & juga dipakai sisi attendance (`cuti_tahunan_max_pengajuan_per_bulan`).
- `m_pangkats` + `m_kelas` dapat kolom baru (§3.4) — termasuk **`bpjs_basis`** yang menjawab pertanyaan pemetaan BPJS tanpa perlu tabel mapping terpisah.
- Migrasi 1 baris data `attendance_policies` → `settings`, lalu drop tabel lama.
- Filament: form edit `settings` sebaiknya dikelompokkan per `module` (tab/section), dan per row bisa pilih "berlaku semua company" (company_id kosong) atau company spesifik — pola form-nya sama seperti yang sudah dipakai `SalaryComponentForm` (`company_id` Select nullable, helper text "Kosongkan = berlaku semua company").

## 6. Catatan Requirement Baru (dari contoh user, bukan dari HR sheet manapun)

**"Setiap bulan perusahaan Holding hanya bisa mengajukan cuti selama 5 hari"** — ini requirement BARU yang belum pernah muncul di analisis #1-#4. Perlu diklarifikasi sebelum dieksekusi (tapi strukturnya sudah jelas — cukup 1 baris `settings`):
- Apakah ini batas **per pengajuan** (1x ajukan max 5 hari cuti sekaligus) atau **akumulasi per bulan** (total semua pengajuan cuti dalam 1 bulan kalender tidak boleh lebih dari 5 hari)?
- Apakah berlaku per **user** (tiap karyawan max 5 hari/bulan) — hampir pasti ini maksudnya, bukan kuota bersama seluruh company?
- Dicek di titik mana: saat submit (`AttendaceReqService::storeLeaveRequest()`) atau cuma validasi UI?

Ditambahkan ke daftar pertanyaan terbuka `analisis-payroll-3` §8 sebagai **Q11**.

---

## 7. Addendum: 3 Temuan Baru (2026-07-16, lanjutan sesi yang sama)

Dicatat sesuai instruksi user ("catat dulu") — **belum diimplementasikan**, dan 1 di antaranya (§8.2) sengaja ditandai sebagai pertanyaan terbuka baru, bukan keputusan final, karena user sendiri menyatakan tidak yakin.

### 7.1 Izin — dibatasi 2× dalam 1 bulan, LINTAS SEMUA JENIS IZIN

**Pembacaan**: "jenis izin apapun itu" dibatasi total **2 kali per bulan** — bukan 2× per subtipe (Izin Terlambat Masuk, Pulang Cepat, Seharian, Meninggalkan Jam Kerja dihitung gabungan, bukan masing-masing dapat jatah 2). Kalau bacaan ini salah, tolong dikoreksi — soalnya konsekuensinya beda jauh: "2 gabungan" perlu 1 counter lintas subtipe per user per periode, "2 per subtipe" perlu 4 counter terpisah.

**Pemetaan ke klasifikasi §3-4**: nilai `2` = **`settings`** (config, key `izin_max_per_periode`, kemungkinan global dulu, override per company kalau nanti ada company beda kebijakan). Logika "hitung kejadian, tolak/tandai kalau sudah lebih dari batas" = **hardcode** di titik submit pengajuan izin (`AttendaceReqService`) — pola sama seperti grace keterlambatan (§3.2), cuma titiknya di pengajuan, bukan payroll.

**Belum jelas** (jangan diasumsikan): apakah pengajuan ke-3 dst di bulan itu **ditolak sistem** (hard block, tidak bisa submit), atau **tetap bisa diajukan tapi kena konsekuensi lain** (potongan tambahan / butuh approval berlapis)? Ini beda flow yang cukup signifikan.

### 7.2 ⚠️ PERTANYAAN TERBUKA BARU (Q12) — Periode Reset Izin (20→21) vs Periode Slip Gaji (akhir bulan)

User eksplisit bilang "aku ngga ngerti" soal ini — jadi **ditandai pertanyaan terbuka, BUKAN keputusan**, sama seperti Q1-Q11 di dokumen sebelumnya.

**Yang disampaikan**: periode reset kuota izin bukan kalender bulan biasa (1–31), tapi **siklus tanggal 20 ke tanggal 21 bulan berikutnya** (mis. 20 Januari → 21 Februari = 1 "periode"). Tapi slip gaji sendiri dihitung & terbit di **tanggal 30/akhir bulan kalender**.

**Kenapa ini penting & butuh klarifikasi, bukan tebakan**:
1. Sistem sekarang (`LeaveUserBalances.period_start/period_end`, `payroll_periods.start_date/end_date`) **semuanya berasumsi periode = kalender bulan/tahun** (`now()->startOfYear()`/`startOfMonth()`, dst). Kalau kuota izin benar-benar reset di siklus 20→21, itu **konsep periode yang berbeda** dari `payroll_periods` yang datanya sudah ada (Jan/Mar/Apr 2026, semua kelihatannya kalender biasa berdasar `analisis-payroll-2` §1) — perlu tabel/kolom periode terpisah, bukan reuse `payroll_periods`.
2. **Potensi konflik nyata**: kalau periode izin 20 Jan–20 Feb, tapi payroll periode Januari dihitung akhir Januari (tanggal 30/31) — izin yang terjadi tanggal 25-31 Januari itu masuk periode izin MANA (Jan 20-Feb 20), tapi payroll SIAPA (slip Januari yang sudah tutup di tanggal 30, atau slip Februari)? Ini **celah nyata** yang perlu jawaban eksplisit dari HR: apakah payroll bulan berjalan menghitung data absensi periode 20-20 yang mana persis (periode yang BARU SAJA tutup tanggal 20/21, ATAU periode yang sedang berjalan)?
3. **Kemungkinan penjelasan paling masuk akal** (dugaan, PERLU DIKONFIRMASI): payroll bulan-N dihitung berdasarkan data absensi periode 20 bulan-(N-1) sampai 20/21 bulan-N (cutoff sebelum tanggal terbit gaji), lalu slip TERBIT di tanggal 30 bulan-N tapi ISINYA mencerminkan periode kerja yang sudah tutup. Ini pola umum "payroll cutoff" yang lazim di perusahaan Indonesia — tapi **tetap harus dikonfirmasi persis**, jangan diasumsikan begitu saja karena bisa salah 1 periode penuh kalau tebakannya keliru.

**Rekomendasi**: jangan bangun logika periode izin sebelum ini jelas. Kalau nanti terkonfirmasi memang siklus 20→21, kemungkinan besar butuh 1 konsep periode baru (`attendance_periods` atau kolom `cutoff_day` di `settings`/company) yang **terpisah** dari `payroll_periods` (yang tetap kalender, karena itu yang dipakai slip/laporan resmi) — 2 kalender berjalan paralel, saling terhubung lewat aturan cutoff yang jelas.

### 7.3 Status Karyawan → jadi ENUM (bukan tabel master `m_employee_types`)

**Pembacaan**: user menilai "status karyawan" (Tetap/Kontrak) sebaiknya jadi **enum tetap** (2 nilai pasti), bukan tabel master `m_employee_types` yang sekarang bisa diisi bebas oleh admin.

**Ini SETUJU secara prinsip** dengan kriteria enum di §3.1 (vocabulary tetap + dipakai branching kode) — DAN sekaligus jadi **fix nyata** buat masalah data yang sudah ditemukan sebelumnya: dicek ulang barusan ke DB lokal, `m_employee_types` sekarang punya **6 baris nama berbeda**: `Karyawan Tetap`, `Tetap`, `Karyawan Kontrak`, `Kontrak`, `Freelance`, `Outsorcing` — jelas hasil entry tidak konsisten (harusnya cuma 2 konsep: Tetap & Kontrak), persis seperti yang ditemukan analisis lama (`planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md` §J catatan tambahan).

**⚠️ Ini BUKAN perubahan kecil** — `employee_type_id` dipakai sebagai FK nyata di 4 titik kode (dicek grep): `UserProfiles.employee_type_id` (data induk tiap karyawan), `SalaryComponents` (eligibility — `salary_component_employee_types` pivot many-to-many, dipakai `PayrollService::isEligibleForEmployeeType()`), `CandidateService` (kemungkinan proses rekrutmen → karyawan baru). Konversi ke enum literal berarti:
- `user_profiles.employee_type_id` (FK ulid) → jadi kolom `employee_status` (string/enum), ATAU tetap FK tapi ke tabel yang di-lock cuma 2 baris (opsi lebih aman, migrasi lebih kecil — lihat opsi B).
- Pivot `salary_component_employee_types` (relasi many-to-many ke tabel master) perlu bentuk baru kalau master-nya hilang — bisa jadi kolom array/JSON (`salary_components.eligible_employee_statuses`) berisi list enum values, bukan pivot table lagi.
- **Data 6 baris yang tidak konsisten itu harus di-cleanup/mapping dulu** (Karyawan Tetap+Tetap → 1 nilai, Karyawan Kontrak+Kontrak+Freelance+Outsorcing → perlu diputuskan apakah semua masuk "Kontrak" atau ada yang perlu jadi nilai enum ke-3).

**Pertanyaan buat mastiin cuma 2 nilai**: data yang ada sekarang ada **6 nama** termasuk "Freelance" dan "Outsorcing" yang kedengarannya beda konsep dari sekadar "Tetap/Kontrak" (freelance/outsourcing biasanya punya aturan payroll & BPJS yang beda dari karyawan kontrak biasa). **Konfirmasi**: apakah Freelance & Outsourcing itu data usang/salah-input yang aman dihapus/digabung ke Kontrak, atau itu memang kategori ke-3/ke-4 yang masih dipakai riil? Kalau ternyata lebih dari 2 kategori riil dipakai, enum-nya perlu lebih dari 2 case (tetap boleh enum, cuma jumlah case-nya nambah).

**Rekomendasi eksekusi** (nanti, bukan sekarang — "catat dulu" sesuai instruksi): opsi paling aman secara migrasi adalah **Opsi B** — TETAP pertahankan `m_employee_types` sebagai tabel (supaya FK & pivot existing tidak perlu dibongkar total), tapi:
1. Cleanup data jadi cuma 2 (atau N sesuai jawaban pertanyaan di atas) baris kanonis per company.
2. Tambah constraint/UI yang mencegah admin bikin baris baru bebas (mis. dropdown fixed di seed, form create disembunyikan/dibatasi, atau validasi nama harus dari whitelist enum).
Ini dapat manfaat "enum-like" (vocabulary tetap, tidak bisa di-nambah sembarangan) TANPA bongkar FK/pivot yang sudah dipakai di 4 titik kode. Kalau user tetap mau literal PHP enum (bukan tabel), itu migrasi lebih besar — didiskusikan lagi saat mau eksekusi.

---

## 8. Referensi
- [`app/Models/Attendance/AttendancePolicies.php`](../../app/Models/Attendance/AttendancePolicies.php) — prototipe pola yang digeneralisasi.
- [`app/Services/Payroll/PayrollService.php:520-545`](../../app/Services/Payroll/PayrollService.php) — bukti pola fallback company→global sudah pernah dipakai & jalan.
- [`analisis-payroll-3-skema-komponen-real.md`](analisis-payroll-3-skema-komponen-real.md) — arsitektur Registry+Strategy+Config.
- [`analisis-payroll-4-pembacaan-excel-skema-aktual.md`](analisis-payroll-4-pembacaan-excel-skema-aktual.md) — data & jawaban user yang jadi input klasifikasi ini.
