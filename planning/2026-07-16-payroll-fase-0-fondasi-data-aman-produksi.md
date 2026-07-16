# Planning: Payroll Fase 0 — Fondasi Data, Aman untuk Production Live (2026-07-16)

Status: **dieksekusi penuh ke database LOKAL (2026-07-17).** Semua migration jalan bersih, backfill terverifikasi manual. **BELUM di-commit ke git, BELUM di-deploy ke production** — lihat `planning/next/2026-07-17-rekap-sesi-dan-lanjutan.md` untuk status lengkap & langkah lanjutan.

Lanjutan dari rangkaian analisis payroll: [`analisis-payroll-3-skema-komponen-real.md`](../knowledge/markdown/analisis-payroll-3-skema-komponen-real.md) (arsitektur Registry+Strategy+Config), [`analisis-payroll-4-pembacaan-excel-skema-aktual.md`](../knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md) (data & jawaban user), [`analisis-payroll-5-klasifikasi-enum-hardcode-config.md`](../knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md) (klasifikasi Enum/Hardcode/Config + tabel `settings`). Dokumen ini = **eksekusi Fase 0** dari rencana bertahap di `analisis-payroll-3` §9, dengan penekanan khusus: **database local ini adalah salinan production, dan production sedang berjalan (walau payroll sendiri jarang dipakai) — jadi setiap langkah harus aman dijalankan tanpa mengganggu apa yang sudah ada.**

## Kenapa Perlu Penekanan Khusus "Aman Production"

Ini bukan proyek greenfield. Sudah dicek langsung ke data (salinan production):
- **76 `payrolls` sudah ada, 6 di antaranya `is_paid = true`** — data final, sudah "dibayar", tidak boleh berubah nilainya.
- **`salary_components` katalog sudah berisi 11 baris riil** (Gaji Pokok, Tunjangan Jabatan, Tunjangan Transportasi, Tunjangan Masa Kerja, BPJS Kesehatan, BPJS Ketenagakerjaan, Cuti, Izin, Keterlambatan, Jasa Pelayanan, Pajak Jasa Dokter) — **semuanya masih `company_id = NULL` (GLOBAL)**, dipakai lintas company walau nilainya seharusnya beda per company (terbukti dari Excel: Gaji Pokok Holding Rp200rb vs RS Rp100rb).
- **`m_pangkats` (18 baris) & `m_kelas` (39 baris) sudah ada dan strukturnya SUDAH cocok** dengan skema Excel — nama pangkat persis 6 tingkat (Pelaksana/Staff/Koordinator/Supervisor/Manager/Direktur, Holding pakai "President Direktur"), dan `m_kelas.name` **sudah berupa angka** (`"1 (SGG)"`, `"2 (SGG)"`, dst) — tinggal di-parse jadi kolom `level` numerik, bukan data baru dari nol.
- **170 `attendance_requests`, 496 `attendance_records`, 509 `users`** — sistem attendance aktif dipakai rutin (beda dari payroll yang jarang).

**Konsekuensi**: Fase 0 **tidak boleh** mengubah perilaku yang sudah berjalan (attendance request submit/approve, generate payroll manual yang sudah ada) sama sekali. Semua perubahan Fase 0 harus **murni aditif** (tabel baru, kolom baru nullable/default aman) — **tidak ada rename, tidak ada drop, tidak ada logic baru yang otomatis aktif**.

---

## Prinsip Keamanan (non-negotiable, berlaku semua langkah di bawah)

1. **Additive-only.** Migration Fase 0 cuma `Schema::create` (tabel baru) dan `Schema::table(...)->addColumn(nullable/default)`. Tidak ada `renameColumn`/`dropColumn`/`renameTable` terhadap tabel yang sudah dipakai kode aktif (`m_pangkats`, `m_kelas`, `salary_components`, dll). **Pengecualian satu-satunya**: `attendance_policies` → `settings` (§4.5), dan itu pun cuma 1 baris data + 1 titik kode yang kena, ditangani hati-hati (create baru → pindah data → update 1 pemanggil → baru drop lama, bukan drop duluan).
2. **Kolom baru = nullable atau punya default yang tidak mengubah perilaku lama.** Kalau kolom baru dibaca kode lama (harusnya tidak, karena Fase 0 belum mengubah `PayrollService`), nilainya harus `NULL`/default yang aman ditelan tanpa efek samping.
3. **Tidak menyentuh `PayrollService::generatePayroll()` sama sekali di Fase 0.** Refactor jadi Registry+Strategy itu **Fase 1** (dokumen terpisah, nanti) — sengaja dipisah supaya risiko "logic berubah" dan risiko "skema berubah" tidak bercampur dalam 1 deploy. Fase 0 murni nyiapkan tanah, belum ada yang "dinyalakan".
4. **`payrolls.is_paid = true` adalah batas suci** — sudah otomatis dilindungi `regeneratePayroll()` (cuma hapus & bangun ulang payroll yang `is_paid=false`). Fase 0 tidak melakukan apa pun yang bisa membongkar payroll `is_paid=true`, dan langkah manapun ke depan (Fase 1+) WAJIB mempertahankan invarian ini.
5. **Data backfill (isi kolom baru dari data lama) dipisah dari migration schema, dan idempotent.** Backfill ditulis sebagai `Seeder`/`Command` artisan terpisah yang aman dijalankan berkali-kali (pakai `updateOrCreate`/cek-dulu-baru-isi), bukan di dalam method `up()` migration — supaya kalau backfill perlu diulang/diperbaiki, tidak perlu bikin migration baru tiap kali.
6. **Staged rollout per company** (relevan mulai Fase 2, dicatat di sini biar konsisten sejak awal): karena `salary_components`/`salary_component_assignments` sudah bersifat "opt-in per company" (Registry), komponen BARU yang dibangun di fase-fase depan otomatis TIDAK memengaruhi company yang belum di-assign — tidak perlu feature flag terpisah, Registry-nya sendiri sudah jadi gate.
7. **Migrasi dijalankan di local dulu (branch `dev`), diverifikasi, baru ke production** — ikuti `GIT-WORKFLOW.md` (push ke `dev` dulu, tidak trigger deploy) dan `deployment/CHECKLIST.md` (`migrate --force` di production perlu konfirmasi eksplisit + backup dulu untuk migration yang bukan cuma "create table baru murni").
8. **Tidak ada perubahan ke Filament Resource/portal yang sudah dipakai user** di Fase 0 — form Pangkat/Kelas yang ada tetap jalan seperti biasa, kolom baru dulu ditambahkan tanpa expose ke form (atau expose sebagai field opsional yang defaultnya kosong) supaya user existing tidak tiba-tiba lihat field asing wajib diisi.

---

## 1. Cek Kesiapan Data Existing (sudah diverifikasi, dicatat sebagai bukti risiko rendah)

| Tabel | Kondisi sekarang | Kesiapan buat Fase 0 |
|---|---|---|
| `m_pangkats` | 18 baris, nama sudah persis 6 tingkat skema Excel per company (Holding: President Direktur/Manager/Supervisor/Staff/Koordinator/Pelaksana — RS ×2 company: Direktur/Manager/Supervisor/Koordinator/Staff/Pelaksana) | ✅ Tidak perlu bikin baris baru, tinggal isi kolom baru |
| `m_kelas` | 39 baris, `name` sudah angka literal (`"1 (SGG)"` s.d. `"15 (SGG)"` untuk Holding, `"1 (RS)"` s.d `"12 (RS)"` untuk tiap RS) | ✅ `level` bisa di-backfill otomatis dari parsing `name` (regex ambil angka di depan) |
| `salary_components` | 11 baris relevan, semua `company_id = NULL` (GLOBAL), `default_value` generic | ⚠️ Perlu diputuskan: apakah baris GLOBAL ini didup jadi per-company (Gaji Pokok Holding & Gaji Pokok RS jadi 2 baris beda `company_id`), atau `default_value`-nya tetap generic dan nilai riil per-company diambil dari tabel relasional baru (`position_allowances`, dst) — **direkomendasikan opsi kedua** (§2), karena kalau di-dup per company jadi N baris katalog dengan `category` sama, `PayrollService` lookup by category jadi ambigu (dapat baris yang mana?). Katalog tetap 1 baris per kategori (penanda "komponen ini eksis & aktif"), NILAI aktualnya dari tabel relasional. |
| `payrolls` | 76 baris, 6 `is_paid=true` | ✅ Fase 0 tidak menyentuh tabel ini sama sekali |
| `attendance_policies` | 1 baris (`min_total_working_hours_per_month`, global) | ✅ Kecil, aman dipindah (§4.5) |
| `m_employee_types` | 6 baris nama tidak konsisten (temuan `analisis-payroll-5` §7.3) | ⛔ **Di luar scope Fase 0** — cleanup data ini butuh keputusan user dulu (Freelance/Outsourcing itu data usang atau kategori riil), dicatat sebagai item terpisah, JANGAN diutak-atik di Fase 0 |

**Kesimpulan §1**: fondasi struktural (pangkat & kelas) ternyata **sudah sangat siap** — bukan bikin dari nol, murni nambah kolom & backfill dari data yang sudah konsisten. Ini menurunkan risiko Fase 0 secara signifikan dibanding kalau datanya berantakan.

---

## 2. Skema Migration (semua additive)

### 2.1 `m_kelas` — tambah `level`
```php
Schema::table('m_kelas', function (Blueprint $table) {
    $table->unsignedInteger('level')->nullable()->after('name');
});
```
Nullable dulu (bukan `default(0)` — biar kelihatan jelas mana yang belum di-backfill, hindari 0 dikira level valid). Backfill lewat command terpisah (§3.1).

### 2.2 `m_pangkats` — tambah kolom klasifikasi & rentang kelas
```php
Schema::table('m_pangkats', function (Blueprint $table) {
    $table->unsignedInteger('start_kelas_level')->nullable()->after('company_id');
    $table->unsignedInteger('max_kelas_level')->nullable()->after('start_kelas_level');
    $table->decimal('class_increment_rate', 5, 4)->nullable()->after('max_kelas_level'); // 0.08 = 8%
    $table->string('bpjs_basis')->nullable()->after('class_increment_rate'); // 'ump' | 'declared_value' — string dulu (§2.5 kenapa bukan enum DB)
});
```
Semua nullable — pangkat existing tidak "rusak" kalau kolom ini kosong (kalkulator baru, kalau dibangun nanti di Fase 2, WAJIB skip/fallback aman kalau nilainya NULL, bukan asumsi 0).

### 2.3 Tabel relasional baru (matriks/bracket)

```php
Schema::create('pangkat_salaries', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('pangkat_id')->constrained('m_pangkats')->cascadeOnDelete();
    $table->decimal('base_salary', 15, 2);
    $table->timestamps();
    // pangkat sudah company-scoped (m_pangkats.company_id), jadi 1 baris per pangkat cukup
    // — tidak perlu company_id di sini, ikut company milik pangkat_id.
    $table->unique('pangkat_id');
});

Schema::create('position_allowances', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('pangkat_id')->constrained('m_pangkats')->cascadeOnDelete();
    $table->foreignUlid('kelas_id')->constrained('m_kelas')->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->timestamps();
    $table->unique(['pangkat_id', 'kelas_id']);
});

Schema::create('transport_allowances', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('kelas_id')->constrained('m_kelas')->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->timestamps();
    $table->unique('kelas_id');
});

Schema::create('tenure_allowances', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->unsignedInteger('min_years');
    $table->unsignedInteger('max_years')->nullable(); // null = tidak terbatas ke atas
    $table->decimal('amount', 15, 2);
    $table->timestamps();
});
```

**Kenapa `position_allowances`/`pangkat_salaries`/`transport_allowances` tidak butuh `company_id` eksplisit**: `pangkat_id` dan `kelas_id` **sudah** company-scoped (`m_pangkats.company_id`, `m_kelas.company_id` — dikonfirmasi §1). Nambah `company_id` lagi di tabel anak jadi redundan & berisiko drift (company_id di baris anak beda dari company_id pangkat/kelas-nya). `tenure_allowances` beda karena bracket-nya bukan turunan dari entitas yang sudah company-scoped (tahun kerja itu independen dari pangkat/kelas), jadi butuh `company_id` sendiri.

Tabel-tabel ini **kosong saat dibuat** (belum ada baris) — pengisian nilai riil (dari Excel/HR) itu kerjaan admin lewat Filament setelah Fase 0 selesai, bukan bagian migration.

### 2.4 Tabel `settings` (generalisasi `attendance_policies`)
```php
Schema::create('settings', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
    $table->string('module'); // 'payroll' | 'attendance' | dst — tampilan doang, bukan lookup key
    $table->string('key');
    $table->string('type'); // 'integer' | 'decimal' | 'boolean' | 'string'
    $table->string('value');
    $table->string('label');
    $table->text('description')->nullable();
    $table->timestamps();
    $table->unique(['company_id', 'key']);
});
```
(Detail lengkap & daftar `key` yang direncanakan sudah di `analisis-payroll-5` §2.)

### 2.5 Kenapa `bpjs_basis`/`type` di atas STRING, bukan MySQL native `ENUM` kolom
Konsisten pola yang **sudah dipakai** di project ini (`SalaryComponentCategoryEnum`, `PayrollItemSourceEnum` — semua kolom DB-nya `string`, validasi/vocabulary-nya di PHP Enum, BUKAN `ENUM(...)` native MySQL). Alasan yang sama berlaku di sini: native `ENUM` MySQL susah di-ALTER kalau nambah value baru (butuh migration ubah tipe kolom), sedangkan PHP enum tinggal tambah `case`. Klasifikasi "ini ENUM" di `analisis-payroll-5` §3.1 itu soal **level aplikasi** (PHP enum class yang dipakai validasi & branching kode), bukan soal tipe kolom database.

**Enum PHP baru yang dibuat di Fase 0** (definisi doang, belum dipakai branching kode manapun — itu Fase 1+):
- `App\Enum\Payroll\BpjsBasisEnum` (`UMP = 'ump'`, `DECLARED_VALUE = 'declared_value'`)
- `App\Enum\SettingTypeEnum` (`INTEGER`, `DECIMAL`, `BOOLEAN`, `STRING`)

---

## 3. Backfill (command artisan, idempotent, terpisah dari migration)

### 3.1 `php artisan payroll:backfill-kelas-level`
Parse `m_kelas.name` (regex `/^(\d+)/`) → isi `level`. Skip baris yang `level` sudah terisi (idempotent). Log baris yang GAGAL di-parse (nama tidak diawali angka) — jangan asumsikan, biar admin cek manual.

### 3.2 `php artisan payroll:backfill-pangkat-range`
Isi `start_kelas_level`/`max_kelas_level` **HANYA untuk pasangan pangkat+company yang datanya sudah dikonfirmasi dari Excel** (§2 dokumen `analisis-payroll-4`, tabel "6 Pangkat berjenjang"). **Tidak menebak untuk company selain Holding & RS Banjarbaru** — kolom dibiarkan `NULL` untuk pangkat di company lain sampai ada konfirmasi data mereka juga (Barabai, Lerina Inn, dst belum ada di Excel sama sekali).

**Eksplisit ditandai TODO/PERLU KONFIRMASI di command ini**: rate `class_increment_rate` (0.08) — Excel cuma bilang "ada kenaikan 8%" tanpa jelas apa ini berlaku SEMUA company atau Holding-only (dicatat sebagai open question di `analisis-payroll-4` §7.3 baris terakhir). Backfill **cuma isi untuk Holding & RS Banjarbaru** (2 company yang datanya jelas di Excel), company lain tetap `NULL` sampai dikonfirmasi.

Tidak mengisi `bpjs_basis` — itu **eksplisit ditunda** (butuh keputusan manual per pangkat lewat UI setelah Fase 0, bukan tebakan otomatis — lihat `analisis-payroll-5` §3.1 soal ini).

### 3.3 Data `pangkat_salaries`/`position_allowances`/`transport_allowances`/`tenure_allowances`
**TIDAK diisi otomatis di Fase 0.** Angka-angka di Excel adalah **data dummy** (sudah dikonfirmasi user di sesi sebelumnya) — mengisi tabel production dengan angka dummy itu berbahaya (kalau lupa diganti, payroll beneran pakai angka fiktif). Tabel dibuat kosong, diisi manual oleh admin/finance lewat Filament (dibangun di Fase 2) pakai angka riil.

---

## 4. Detail Tambahan

### 4.1 Model baru
`App\Models\Payroll\PangkatSalary`, `PositionAllowance`, `TransportAllowance`, `TenureAllowance`, `App\Models\Setting`. Semua `HasUlids`, relasi `belongsTo` ke `Pangkats`/`Kelas`/`Companies` sesuai kolom.

### 4.2 Model existing — tambah relasi, TIDAK ubah yang sudah ada
`Pangkats.php`: tambah `pangkatSalary(): HasOne`, `positionAllowances(): HasMany`. `Kelas.php`: tambah `transportAllowance(): HasOne`, `positionAllowances(): HasMany`. Method/relasi lama (`salaryComponents()`, `company()`, dst) **tidak disentuh**.

### 4.3 `Setting` model — helper `resolve()`
```php
class Setting extends Model
{
    public static function resolve(string $key, ?string $companyId, mixed $default = null): mixed
    {
        $row = static::query()
            ->where('key', $key)
            ->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderByRaw('(company_id IS NULL) ASC')
            ->first();

        if (!$row) return $default;

        return match ($row->type) {
            'integer' => (int) $row->value,
            'decimal' => (float) $row->value,
            'boolean' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
            default => $row->value,
        };
    }
}
```
Pola persis dicontek dari `PayrollService.php:528-534` yang sudah terbukti jalan (§1 `analisis-payroll-5`) — bukan pola baru yang belum teruji.

### 4.4 Filament — Fase 0 TIDAK bikin Resource baru
Sesuai prinsip #8 — Resource buat kelola `settings`/`position_allowances`/dst dibangun di **Fase 2** (setelah fondasi data stabil & dites). Fase 0 murni migration + model + backfill command, tidak ada perubahan yang user-facing.

### 4.5 Migrasi `attendance_policies` → `settings` (satu-satunya migrasi "mindah data lama")
Langkah **berurutan, bukan sekaligus**, supaya tiap tahap bisa diverifikasi:
1. Migration create `settings` (§2.4) — sudah bagian dari Fase 0.
2. **Seeder/command kecil**: copy 1 baris `attendance_policies` (`min_total_working_hours_per_month`, global) ke `settings` (`module='payroll'`, `key='min_total_working_hours_per_month'`). `attendance_policies` LAMA **tidak dihapus dulu**.
3. Update `PayrollService::handleExtraHourAllowanceComponent()` (baris ~528) ganti query `AttendancePolicies::query()->...` jadi `Setting::resolve('min_total_working_hours_per_month', $period->company_id, default: 0)`.
4. **Verifikasi**: generate/regenerate 1 payroll periode yang punya data extra-hour, pastikan hasilnya identik sebelum & sesudah perubahan (angka `excessHours`/tunjangan kelebihan jam sama persis).
5. **Baru setelah langkah 4 lolos**, di migration/PR terpisah (bukan bagian Fase 0 ini): drop `attendance_policies`.

**Ini SATU-SATUNYA bagian Fase 0 yang menyentuh logic `PayrollService` yang sudah jalan** — sengaja dipisah kecil & 1 titik saja (bukan refactor besar) supaya risikonya terisolasi dan gampang di-rollback (`git revert` 1 commit kecil) kalau ada yang salah.

---

## 5. Urutan Eksekusi & Verifikasi

1. **Kerja di branch `dev`** (sesuai `GIT-WORKFLOW.md` — push ke `dev` tidak memicu deploy).
2. Tulis semua migration (§2) — jalankan `php artisan migrate` di **DB lokal** (yang notabene salinan production, jadi ini uji paling realistis yang bisa dilakukan tanpa menyentuh production beneran).
3. Tulis & jalankan backfill command (§3.1, §3.2) di lokal — cek manual hasil `m_kelas.level` & `m_pangkats.start_kelas_level/max_kelas_level` masuk akal (bandingkan ke tabel §2 dokumen `analisis-payroll-4`).
4. Kerjakan migrasi `attendance_policies→settings` (§4.5) — jalankan test/generate payroll manual di lokal, bandingkan angka sebelum/sesudah.
5. `php artisan test` — pastikan **tidak ada test yang tadinya lulus jadi gagal** (baseline sudah diketahui dari sesi sebelumnya: 146 passed, 6 failed pre-existing & tidak terkait — pastikan angka itu tidak memburuk).
6. Review manual: buka `/admin` di lokal, pastikan halaman Pangkat/Kelas/Payroll yang sudah ada **masih terlihat & berfungsi sama seperti sebelumnya** (belum ada UI baru, tapi pastikan tidak ada yang KETIBAN error karena kolom baru).
7. Push ke `dev`, biarkan beberapa saat / minta review kalau perlu.
8. Merge `dev` → `main` (deploy) — sesuai `deployment/CHECKLIST.md`: **backup database dulu** (migrasi #4.5 menyentuh data, walau kecil), `php artisan migrate --force`, jalankan backfill command yang sama di production, verifikasi hasil backfill (spot check beberapa baris `m_kelas`/`m_pangkats` dari 2 company yang datanya dikonfirmasi), monitor `storage/logs/laravel.log` beberapa saat setelah deploy.
9. **Tidak perlu** `shield:generate` di Fase 0 (belum ada Resource/Policy baru — itu Fase 2).

## 6. Yang SENGAJA Tidak Dikerjakan di Fase 0 (biar scope tetap kecil & aman)

- Refactor `PayrollService::generatePayroll()` jadi Registry+Strategy (Fase 1 — dokumen terpisah nanti, wajib ada regression test angka-per-angka sebelum menggantikan handler lama).
- Kalkulator komponen baru apa pun (Gaji Pokok additive, Tunjangan Jabatan matrix, dst) — itu Fase 2, BARU setelah fondasi data (dokumen ini) stabil di production.
- Cleanup `m_employee_types` (perlu keputusan user dulu, `analisis-payroll-5` §7.3).
- Perubahan ke `m_leave_types` (cuti melahirkan/keguguran/haid) & logic reversal saldo cuti — item terpisah, di luar scope "fondasi payroll".
- Jawaban pertanyaan terbuka Q1-Q12 yang belum terjawab — komponen terkait tidak dibangun sampai jawabannya ada (lihat `analisis-payroll-3` §8, `analisis-payroll-5` §7.2).
- UI Filament apa pun untuk tabel-tabel baru ini.

## 7. Addendum: Registry Diulang Total — Enum + Toggle, Bukan Katalog Generik (2026-07-16, lanjutan sesi yang sama)

**Keputusan user, mengubah bagian §1-§2 di atas soal `salary_components`**: khusus untuk Payroll, Registry (Layer 1) TIDAK mempertahankan `salary_components`/`salary_component_assignments`/`salary_component_employee_types` — 3 tabel itu **diganti total**, bukan diperluas. Alasan: dunia komponen gaji perusahaan ini **tertutup/dikenal** (~16 komponen, terdaftar lengkap di `analisis-payroll-3` §2), bukan sesuatu yang admin akan bikin bebas kapan saja — jadi "katalog generik yang bisa diisi admin" itu abstraksi yang tidak pernah benar-benar terpakai fleksibilitasnya (persis argumen §3 `analisis-payroll-3` kenapa `calculation_type=formula` mati).

### 7.1 Skema baru

```php
enum PayrollComponentEnum: string
{
    // PENDAPATAN
    case GAJI_POKOK = 'gaji_pokok';
    case TUNJANGAN_JABATAN = 'tunjangan_jabatan';
    case TUNJANGAN_TRANSPORTASI = 'tunjangan_transportasi';
    case TUNJANGAN_MASA_KERJA = 'tunjangan_masa_kerja';
    case INSENTIF = 'insentif';
    case LEMBUR = 'lembur';
    case JASA_PELAYANAN = 'jasa_pelayanan';

    // POTONGAN
    case BPJS_KESEHATAN = 'bpjs_kesehatan';
    case BPJS_KETENAGAKERJAAN = 'bpjs_ketenagakerjaan';
    case KETERLAMBATAN = 'keterlambatan';
    case IZIN_TERLAMBAT_MASUK = 'izin_terlambat_masuk';
    case IZIN_PULANG_CEPAT = 'izin_pulang_cepat';
    case IZIN_SEHARIAN = 'izin_seharian';
    case IZIN_MENINGGALKAN_JAM_KERJA = 'izin_meninggalkan_jam_kerja';
    case CUTI = 'cuti'; // selalu Rp0 (§2.2 "Cuti tidak ada potongan"), tapi tetap dikelompokkan
                         // "potongan" biar konsisten baris slip — bukan bagian gross pendapatan.
    case KOPERASI = 'koperasi';
    case BON = 'bon';

    /**
     * earning/deduction ditentukan sepenuhnya oleh komponennya sendiri — pola sama persis
     * SalaryComponentCategoryEnum::type() (yang sudah ada) — supaya tidak mungkin ada
     * kombinasi kontradiktif (mis. company nyalain toggle Lembur tapi salah taruh di baris
     * potongan slip). Registry (`company_payroll_components`) TIDAK punya kolom `type`
     * terpisah yang bisa disetel salah — selalu diturunkan dari sini.
     */
    public function type(): PayrollComponentTypeEnum
    {
        return match ($this) {
            self::GAJI_POKOK,
            self::TUNJANGAN_JABATAN,
            self::TUNJANGAN_TRANSPORTASI,
            self::TUNJANGAN_MASA_KERJA,
            self::INSENTIF,
            self::LEMBUR,
            self::JASA_PELAYANAN => PayrollComponentTypeEnum::EARNING,

            self::BPJS_KESEHATAN,
            self::BPJS_KETENAGAKERJAAN,
            self::KETERLAMBATAN,
            self::IZIN_TERLAMBAT_MASUK,
            self::IZIN_PULANG_CEPAT,
            self::IZIN_SEHARIAN,
            self::IZIN_MENINGGALKAN_JAM_KERJA,
            self::CUTI,
            self::KOPERASI,
            self::BON => PayrollComponentTypeEnum::DEDUCTION,
        };
    }
}
```

`PayrollComponentTypeEnum` cukup 2 case (`EARNING`, `DEDUCTION`) — **menggantikan** `SalaryComponentTypeEnum` lama (nama beda dikit tapi isinya sama, sekalian dirapikan biar jelas ini punya `PayrollComponentEnum`, bukan sisa dari Registry lama).

**Dampak ke `payroll_items.type`**: kolom ini (sudah ada, `earning`/`deduction`) sekarang **selalu diisi dari `$component->type()->value`**, bukan input manual per Calculator — 1 sumber kebenaran, tidak ada Calculator yang bisa salah taruh `type` sendiri-sendiri.

```php
Schema::create('company_payroll_components', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->string('component'); // nilai dari PayrollComponentEnum
    $table->boolean('is_active')->default(true);
    $table->json('eligible_employee_statuses')->nullable(); // null = semua status, isi = whitelist
    $table->timestamps();
    $table->unique(['company_id', 'component']);
});
```

Ini menggantikan **total** `salary_components` (katalog) + `salary_component_assignments` (assignment polymorphic) + `salary_component_employee_types` (pivot eligibility) — jadi 1 tabel "saklar per company". Angka/logika aktualnya TETAP di tabel Config terpisah (`pangkat_salaries`, `position_allowances`, `transport_allowances`, `tenure_allowances`, `settings` — **tidak berubah** dari §2 di atas), dibaca masing-masing Calculator (Layer 2), bukan disimpan di tabel Registry ini.

Pemetaan lengkap enum → Calculator → sumber data ada di ringkasan chat sesi ini (akan dipindah ke `analisis-payroll-3` §6 saat dokumen itu diupdate berikutnya).

### 7.2 Data lama `salary_components`/`salary_component_assignments` — dihapus, bukan dimigrasi

Dikonfirmasi user: 11 baris katalog + 22 baris assignment yang ada sekarang **boleh langsung hilang** (konsisten temuan `analisis-payroll-2` §1 — semuanya dari periode payroll lama pra-redesign 12 Juli, bukan data yang harus diselamatkan). Migration drop 3 tabel ini masuk Fase 0 (bukan ditunda), karena begitu Registry baru ada, 3 tabel lama itu jadi dead weight yang membingungkan (2 "sistem komponen gaji" hidup bersamaan).

### 7.3 Simplifikasi tambahan yang ditemukan (jawaban langsung pertanyaan user: "ada kolom/resource yang memperbanyak?")

Ditemukan dengan grep ke kode aktual, bukan tebakan — semua ini jadi bagian scope Fase 0/Registry-rebuild:

1. **4 UI surface → 1.** `SalaryComponentResource` + `SalaryComponentAssignmentResource` (admin, drilldown 2 halaman) + `SalaryComponentsPage` + `SalaryComponentMappingPage` (portal Keuangan) **dihapus semua**, diganti **1 Resource baru** (mis. `CompanyPayrollComponentResource`) — bentuknya cukup tabel/checklist per company: kolom komponen (dari enum), toggle aktif, opsional pilih tipe karyawan eligible. Jauh lebih simpel dari desain drilldown lama.
2. **`PayrollItemSourceEnum` (7 case, `app/Enum/Salary/PayrollItemSourceEnum.php`) jadi berlebih.** Enum ini dibuat karena Registry lama tidak bisa presisi (5 komponen beda dilebur jadi 1 case `ATTENDANCE`). Dengan `PayrollComponentEnum` yang granular, `payroll_items.source_type` bisa langsung diisi **nilai `PayrollComponentEnum`** — lebih presisi (slip tahu persis "Izin Terlambat Masuk", bukan cuma "Kehadiran") DAN 1 enum lebih sedikit buat dirawat. **`PayrollItemSourceEnum` dihapus.**
3. **`payroll_items.calculation_type`** — dicek (`grep`): satu-satunya tempat yang BACA nilai ini (bukan cuma nulis) adalah `SalaryComponentAssignments.php:42`, yang ikut hilang di rebuild ini. Semua penulisannya di `PayrollService.php` sudah hardcode literal (`CalculationTypeEnum::FIXED->value`, dst) — kolom ini sudah jadi metadata tampilan doang, tidak ada logic yang bergantung padanya. **Keputusan user: kolom dihapus.**
4. **`CalculationTypeEnum` (`fixed`/`percentage`/`formula`) jadi 100% tidak terpakai** setelah §8.3.1 dan §8.3.3 di atas — dicek, semua 7 titik pemakaiannya ada di file yang dihapus (Resource/Form/Table lama) atau di penulisan `calculation_type` yang kolomnya sudah dihapus. Kasus `formula` sendiri sudah dikonfirmasi 0 baris terpakai sejak awal (`analisis-payroll-2` §TL;DR#4). **Enum ini dihapus total.**
5. **`Companies::selectedSalaryComponent()`** (helper method, `app/Models/Master/Companies.php`) — jadi dead code, dipakai cuma buat lookup assignment lama. **Dihapus.**
6. **Temuan baru — "Total Jaspel" (pool Jasa Pelayanan) butuh rumah baru.** Ditelusuri ke `PayrollService::handleServiceFeeComponent()` (baris ~644-690): angka pool "Total Jaspel" perusahaan (dipakai buat rasio JE) sekarang disimpan lewat `Companies::selectedSalaryComponent()->amount` — itu artinya angka yang **berubah tiap bulan** (total pendapatan jaspel bulan itu) dipaksa masuk ke tabel assignment yang harusnya buat config stabil jangka panjang (kaya Tunjangan Jabatan yang nilainya sama berbulan-bulan). Ini **konflik desain nyata**, bukan cuma soal rename. Begitu `salary_component_assignments` dihapus, pool ini butuh tabel baru sendiri, mis.:
   ```php
   Schema::create('service_fee_pools', function (Blueprint $table) {
       $table->ulid('id')->primary();
       $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
       $table->foreignUlid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
       $table->decimal('total_amount', 15, 2);
       $table->timestamps();
       $table->unique(['company_id', 'payroll_period_id']);
   });
   ```
   Diisi manual oleh finance tiap mau generate payroll company hospital (mirip cara `job_evaluation_companies` diisi tiap bulan) — **bukan** `settings` (yang buat nilai stabil jangka panjang, bukan angka yang ganti tiap periode).

### 7.4 Akses test — sudah dieksekusi

Permission `sistem_keuangan` **sudah diberikan langsung** ke user `ahyaghifari288@gmail.com` (lokal, lewat `$user->givePermissionTo()`, bukan lewat role — supaya tidak memengaruhi user lain yang berbagi role "Karyawan / Staff"). Begitu Resource baru (§8.1) selesai dibangun & dipasang gate `sistem_keuangan` yang sama, akun ini otomatis bisa langsung tes tanpa langkah tambahan.

### 7.5 Dampak ke Urutan Eksekusi (§5) & Cakupan (§6)

- §2 (Skema Migration) dan §6 (Yang Tidak Dikerjakan) di dokumen ini **perlu direvisi** saat eksekusi sungguhan: migration drop 3 tabel lama (§8.2) + create `company_payroll_components` + `service_fee_pools` (§8.1, §8.3.6) masuk ke Fase 0 juga, bukan cuma tabel Config murni seperti draft awal. Fase 1 (refactor `generatePayroll()` jadi orchestrator Registry+Strategy) **cakupannya bertambah** dari sekadar "pindahkan handler ke Calculator" jadi "pindahkan handler + baca Registry baru", tapi prinsip keamanannya (§Prinsip Keamanan di atas) tetap sama — additive dulu, tidak sentuh `is_paid=true`, regression test sebelum ganti logic lama.
- Karena Registry lama (`salary_components` dkk) di-drop sepenuhnya, `PayrollService::generatePayroll()` versi SEKARANG (yang masih baca `salary_components`) **tidak akan jalan lagi begitu migration Fase 0 dijalankan** kalau kodenya belum diupdate barengan. Ini beda dari asumsi awal dokumen ini ("Fase 0 tidak menyentuh `generatePayroll()` sama sekali") — **koreksi**: Fase 0 + Fase 1 kemungkinan perlu di-deploy **sebagai 1 paket** (bukan 2 deploy terpisah), supaya tidak ada window waktu di mana Registry lama sudah hilang tapi kode masih mengharapkannya ada. Detail teknis ini didetailkan ulang saat dokumen Fase 1 ditulis.

---

## 8. Referensi
- [`analisis-payroll-3-skema-komponen-real.md`](../knowledge/markdown/analisis-payroll-3-skema-komponen-real.md) §9 — rencana bertahap asli (Fase 0-4).
- [`analisis-payroll-4-pembacaan-excel-skema-aktual.md`](../knowledge/markdown/analisis-payroll-4-pembacaan-excel-skema-aktual.md) §2, §7.3 — data pangkat/kelas & rumus 8% yang jadi basis backfill.
- [`analisis-payroll-5-klasifikasi-enum-hardcode-config.md`](../knowledge/markdown/analisis-payroll-5-klasifikasi-enum-hardcode-config.md) — klasifikasi & skema `settings`.
- [`deployment/CHECKLIST.md`](../deployment/CHECKLIST.md) — prosedur deploy aman yang diikuti (§5 di atas).
- [`GIT-WORKFLOW.md`](../GIT-WORKFLOW.md) — alur branch `dev`/`main`.
- `app/Services/Payroll/PayrollService.php:520-545` — pola fallback company→global yang dicontek buat `Setting::resolve()`.
