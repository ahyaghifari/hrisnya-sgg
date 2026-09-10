# Approval `sub_module` — alur persetujuan berbeda per jenis Izin/Cuti/Lembur

> **STATUS 2026-09-10: SUDAH DIEKSEKUSI.** Migration jalan, enum + repo + service + Filament + test hijau (5 test baru).
> Deviasi dari planning: resolusi ember **tidak pakai `FIND_IN_SET`** (tidak ada di SQLite test) —
> diganti fetch baris (tabel kecil) lalu `in_array(explode(','))` di PHP. Sama untuk validasi overlap Filament.

> Dibuat 2026-09-10.
>
> **Masalah.** Resolusi alur persetujuan (`ApprovalSnapshotRepository::resolveApprovalFlowForUser`)
> sekarang cuma kenal 3 dimensi: `module` (`attendance_request`) + `company_id` + `job_position`
> (via `m_approval_flow_assignments`). Tidak ada dimensi jenis pengajuan. Akibatnya dalam 1
> perusahaan, **izin meninggalkan jam kerja, izin tidak masuk, cuti, dan lembur semua lewat
> flow yang sama** dan berakhir di approver final yang sama.
>
> Perusahaan tipe rumah sakit butuh beda: izin meninggalkan jam kerja (terlambat masuk /
> pulang cepat) approver finalnya beda dengan izin tidak masuk; sedangkan izin tidak masuk
> approver-nya sama dengan cuti.
>
> **Scope: HANYA modul `attendance_request` (Izin, Cuti, Lembur).** Modul lain
> (`task_request`, `jobposition_request`, `user_transition`, `swap_shift_request`,
> `training_request`, `additional_employee_request`, dst) TIDAK disentuh — pemanggilnya kirim
> `sub_module = null`, resolusi jatuh ke jalur lama persis.

---

## 0. Keputusan

| Topik | Keputusan | Alasan |
|---|---|---|
| Mekanisme | Kolom baru **`sub_module`** (VARCHAR(255) nullable) di `m_approval_flows`, isi **daftar ember dipisah koma** (`leave,tidak_masuk_seharian`). `null`/kosong = berlaku seluruh modul (perilaku lama). | Perubahan schema minimal, tanpa pivot table. 1 flow bisa melayani beberapa jenis pengajuan sekaligus. Baris & company existing otomatis `null` = aman lewat fallback. |
| Berlaku untuk siapa | **Semua company.** Company yang tidak bikin flow ber-`sub_module` selalu jatuh ke flow `null` = tidak ada perubahan perilaku. | Tidak ada alasan membatasi ke 1 company. Fallback bikin ini aman universal. |
| Granularitas "ember" | Per **kelompok izin** (`PermissionGroupEnum`) + `leave` + `overtime`. Bukan per-kategori. 1 flow boleh menampung >1 ember. | Kebutuhan yang ada cukup di level kelompok. Terlambat-masuk vs pulang-cepat beda approver = YAGNI, tambah nanti kalau muncul. |
| Nilai ember untuk izin | Sama persis dengan `PermissionGroupEnum::value` (`meninggalkan_jam_kerja`, `tidak_masuk_seharian`, `sakit`, `khusus`). | Bisa pakai ulang `PermissionGroupEnum::fromCategory()` tanpa mapping tambahan. |
| Izin lama tanpa `category` | ember = `null` → fallback. | Data izin lama (sebelum enum kategori) tidak punya kelompok; tidak apa-apa ikut flow default. |
| Prioritas resolusi | ember pengajuan **ada di daftar** flow + `specific` (jabatan) → ada di daftar + `default` → `sub_module IS NULL` + `specific` → `sub_module IS NULL` + `default`. | `specific` (jabatan) tetap menang atas `default` di tiap tingkat, konsisten aturan sekarang. Flow ber-`sub_module` menang atas fallback. |
| Snapshot | Tidak berubah. `sub_module` hanya dipakai saat **memilih** master flow; `steps_snapshot` tetap salinan beku step seperti biasa. | `sub_module` bukan properti pengajuan, cuma kunci routing. |
| Uniqueness flow | Dua flow **aktif** dengan (`company_id`, `module`, `flow_type`) sama tidak boleh **berbagi ember** — termasuk dua-duanya `null`. | Kalau 2 flow sama-sama klaim `leave`, resolusi jadi ambigu. Cek overlap saat simpan. |

---

## 1. Migration

File: `database/migrations/2026_09_10_XXXXXX_add_sub_module_to_m_approval_flows_table.php`

```php
Schema::table('m_approval_flows', function (Blueprint $table) {
    // Daftar ember dipisah koma, mis. "leave,tidak_masuk_seharian". null = semua jenis.
    $table->string('sub_module', 255)->nullable()->after('module');
});
```

`down()`: `dropColumn('sub_module')`.

Tidak perlu index khusus — tabel `m_approval_flows` kecil (segelintir baris per company), query resolusi sudah ter-filter `company_id` + `module`. `FIND_IN_SET` di step 3 memang tidak bisa pakai index, tapi tidak masalah di tabel sekecil ini.

---

## 2. Enum ember: `App\Enum\Approval\AttendanceApprovalBucketEnum`

File baru: `app/Enum/Approval/AttendanceApprovalBucketEnum.php`

```php
enum AttendanceApprovalBucketEnum: string
{
    case LEAVE               = 'leave';
    case OVERTIME            = 'overtime';
    case IZIN_MENINGGALKAN   = 'meninggalkan_jam_kerja'; // == PermissionGroupEnum::MENINGGALKAN_JAM_KERJA
    case IZIN_TIDAK_MASUK    = 'tidak_masuk_seharian';   // == PermissionGroupEnum::TIDAK_MASUK_SEHARIAN
    case IZIN_SAKIT          = 'sakit';                  // == PermissionGroupEnum::SAKIT
    case IZIN_KHUSUS         = 'khusus';                 // == PermissionGroupEnum::KHUSUS

    public function label(): string
    {
        return match ($this) {
            self::LEAVE             => 'Cuti',
            self::OVERTIME          => 'Lembur',
            self::IZIN_MENINGGALKAN => 'Izin Meninggalkan Jam Kerja',
            self::IZIN_TIDAK_MASUK  => 'Izin Tidak Masuk',
            self::IZIN_SAKIT        => 'Izin Sakit',
            self::IZIN_KHUSUS       => 'Izin Khusus',
        };
    }

    /** value => label. Dipakai form Filament multi-select (step 5a) & filter tabel. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
```

Catatan: value izin sengaja identik dengan `PermissionGroupEnum`. Tidak menaruh helper konversi di sini — cukup `PermissionGroupEnum::fromCategory($cat)->value` di pemanggil (lihat step 4).

---

## 3. `ApprovalSnapshotRepository::resolveApprovalFlowForUser`

File: `app/Repositories/Approval/ApprovalSnapshotRepository.php`

Tambah parameter opsional **di akhir** (biar pemanggil lain tidak berubah):

```php
public function resolveApprovalFlowForUser(
    string $moduleType,
    string $companyId,
    string $userId,
    ?string $subModule = null,
): ?MasterApprovalFlows {
```

Logika baru — coba `sub_module` dulu, lalu fallback `null`:

```php
$jobPositionId = UserProfiles::where('user_id', $userId)->value('job_position_id');

// Urutan: [subModule, specific] > [subModule, default] > [null, specific] > [null, default]
$candidates = $subModule !== null ? [$subModule, null] : [null];

// sub_module = daftar dipisah koma. Cocok kalau ember ada di dalamnya (FIND_IN_SET, MySQL).
$matchSubModule = function ($q, ?string $sm) {
    return $sm === null
        ? $q->whereNull('sub_module')
        : $q->whereRaw('FIND_IN_SET(?, sub_module)', [$sm]);
};

foreach ($candidates as $sm) {
    if ($jobPositionId) {
        $specific = MasterApprovalFlows::with('steps')
            ->where('module', $moduleType)
            ->where('company_id', $companyId)
            ->where('flow_type', 'specific')
            ->where('is_active', true)
            ->tap(fn ($q) => $matchSubModule($q, $sm))
            ->whereHas('assignments', function ($q) use ($jobPositionId) {
                $q->where('assignable_type', JobPositions::class)
                  ->where('assignable_id', $jobPositionId);
            })
            ->first();

        if ($specific) {
            return $specific;
        }
    }

    $default = MasterApprovalFlows::with('steps')
        ->where('module', $moduleType)
        ->where('company_id', $companyId)
        ->where('flow_type', 'default')
        ->where('is_active', true)
        ->tap(fn ($q) => $matchSubModule($q, $sm))
        ->first();

    if ($default) {
        return $default;
    }
}

return null;
```

> Catatan: `FIND_IN_SET('leave', 'leave,tidak_masuk_seharian')` = 2 (truthy), `FIND_IN_SET('leave', 'overtime')` = 0. Aman untuk daftar tanpa spasi setelah koma — pastikan penyimpanan pakai `implode(',', ...)` (step 5a), bukan `', '`.

**Regresi yang harus dijaga:** pemanggil tanpa `$subModule` (training, shift-swap, user-transition, dst) → `$candidates = [null]` → query `whereNull('sub_module')` → identik hasil lama (baris lama semua `sub_module` = NULL setelah migration).

---

## 4. `AttendaceReqService` — kirim ember di 3 titik

File: `app/Services/Attendance/AttendaceReqService.php`

Ketiga method sudah memanggil `resolveApprovalFlowForUser(...)` di dalam blok `if (! $skipApproval)`. Tambah argumen `subModule`:

| Method | Baris (per 2026-09-10) | Argumen baru |
|---|---|---|
| `storeOvertimeRequest` | ~79 | `subModule: AttendanceApprovalBucketEnum::OVERTIME->value` |
| `storeLeaveRequest` | ~172 | `subModule: AttendanceApprovalBucketEnum::LEAVE->value` |
| `storePermissionRequest` | ~630 | `subModule: $this->resolvePermissionBucket($data)` |

Untuk permission, `category` baru diresolusi **setelah** blok flow (baris ~644–646). Dua pilihan:

- **Pindahkan** resolusi `$permissionType` / `$category` ke atas blok `if (! $skipApproval)` supaya bisa dipakai untuk menentukan bucket. (disarankan — tidak ada efek samping, cuma lookup)
- Lalu:

```php
private function permissionBucket(?string $category): ?string
{
    if (! $category) {
        return null; // izin lama tanpa kategori → flow default
    }

    $cat = PermissionTypeCategoryEnum::tryFrom($category);

    return $cat ? PermissionGroupEnum::fromCategory($cat)->value : null;
}
```

`PermissionGroupEnum::fromCategory()` melempar `ValueError` kalau kategori tak dikenal — sudah dijaga `tryFrom` di atas (null → fallback), jadi aman.

**`skipApproval` (input admin manual) tidak terpengaruh** — tidak resolve flow sama sekali.

---

## 5. Filament `ApprovalFlowResource`

### 5a. Form — field `sub_module`

File: `app/Filament/Resources/ApprovalFlows/Schemas/ApprovalFlowForm.php`

Tambah `Select` setelah field `module` (baris ~111):

```php
Select::make('sub_module')
    ->label('Jenis Pengajuan')
    ->multiple()
    ->helperText('Khusus modul Izin/Cuti/Lembur — batasi alur ini ke satu atau beberapa jenis pengajuan. Kosongkan = berlaku untuk semua jenis yang tidak punya alur khusus.')
    ->options(\App\Enum\Approval\AttendanceApprovalBucketEnum::options())
    ->visible(fn (callable $get) => $get('module') === \App\Enum\Approval\ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
    ->formatStateUsing(fn ($state) => filled($state) ? explode(',', $state) : [])
    ->dehydrateStateUsing(fn ($state) => filled($state) ? implode(',', $state) : null)
    ->live(),
```

Catatan:
- DB simpan string koma (`implode`), form pegang array (`explode`). Kosong → `null`.
- **Jangan** `', '` — `FIND_IN_SET` di step 3 tidak trim spasi.
- Kalau `module` bukan `attendance_request`, field hidden & tidak ter-dehydrate → tersimpan `null`.

### 5b. Uniqueness — cek overlap ember

Di file yang sama, closure `->rule(...)` pada `Select::make('module')` (baris ~68–108). Sekarang bukan lagi cek string-equal `sub_module`, tapi cek **irisan ember**: tolak simpan kalau ada flow aktif lain dengan (`company_id`, `module`, `flow_type`) sama yang mengklaim salah satu ember yang sama (atau dua-duanya `null`).

```php
$selected = $get('sub_module') ?: []; // array; [] = flow "semua jenis"

$query = MasterApprovalFlows::query()
    ->where('company_id', $companyId)
    ->where('module', $get('module'))
    ->where('flow_type', $flowType) // 'default' atau 'specific' sesuai cabang existing
    ->where('is_active', true)
    ->when($record, fn ($q) => $q->whereKeyNot($record->getKey())); // exclude diri saat edit

if ($selected === []) {
    $query->whereNull('sub_module'); // dua flow "semua jenis" bentrok
} else {
    $query->where(function ($q) use ($selected) {
        foreach ($selected as $sm) {
            $q->orWhereRaw('FIND_IN_SET(?, sub_module)', [$sm]);
        }
    });
}

if ($query->exists()) {
    $fail('Sudah ada alur untuk modul & jenis pengajuan ini.');
}
```

Untuk cabang `specific`, tambahkan juga filter jabatan yang sudah dipakai closure existing (overlap hanya masalah kalau jabatannya sama).

### 5c. Tabel list (opsional, quality-of-life)

File: `app/Filament/Resources/ApprovalFlows/Tables/ApprovalFlowsTable.php` — tambah kolom "Jenis Pengajuan" (`sub_module`), formatter: pecah koma, map tiap ember ke `AttendanceApprovalBucketEnum::from($x)->label()`, gabung dengan `, `. `placeholder('Semua')` untuk `null`. Tidak wajib, memudahkan admin membedakan beberapa flow satu modul.

---

## 6. Test

File baru: `tests/Feature/Presensi/AttendanceApprovalSubModuleTest.php`

Skenario minimal (1–2 test):

1. **Routing per sub_module.** Company punya:
   - Flow `default`, `sub_module = null`, step final → User A.
   - Flow `default`, `sub_module = meninggalkan_jam_kerja`, step final → User B.
   - Flow `default`, `sub_module = leave,tidak_masuk_seharian`, step final → User C.

   Lalu:
   - Ajukan izin `izin_terlambat_masuk` → `approval_snapshots.flow_id` = flow B.
   - Ajukan izin `izin_seharian` → flow C (ember `tidak_masuk_seharian` ada di daftarnya).
   - Ajukan cuti → flow C (ember `leave` ada di daftarnya).
   - Ajukan izin `sakit` → flow A (fallback, tidak ada flow yang klaim `sakit`).

2. **Regresi modul lain.** Buat `training_request` flow `default` (`sub_module` NULL) → ajukan training → snapshot tetap resolve flow itu (memastikan param opsional tidak merusak pemanggil lama). *(boleh digabung ke test modul training yang sudah ada kalau ada.)*

Pakai helper/seeder flow yang sudah dipakai test approval existing (cari `MasterApprovalFlows::factory` atau seeder manual di `tests/Feature/...` approval).

---

## 7. Urutan eksekusi

1. Migration (step 1) + `php artisan migrate`.
2. Enum `AttendanceApprovalBucketEnum` (step 2).
3. `resolveApprovalFlowForUser` (step 3) — jalankan test approval existing, pastikan hijau (regresi).
4. `AttendaceReqService` 3 titik (step 4).
5. Filament form + uniqueness (step 5a, 5b). 5c belakangan.
6. Test baru (step 6).
7. Commit.

## 8. Yang sengaja TIDAK dikerjakan

- Granularitas per-`PermissionTypeCategoryEnum` (mis. terlambat-masuk beda approver dari pulang-cepat). Tambah dengan memasukkan nilai kategori sebagai ember baru kalau kebutuhan itu muncul.
- Pivot table `m_approval_flow_sub_modules`. Daftar koma di 1 kolom cukup — jumlah ember kecil (6), tidak perlu query balik "flow mana yang punya ember X" selain resolusi yang sudah pakai `FIND_IN_SET`.
- UI "matriks kebijakan approval" terpisah. Admin cukup bikin beberapa baris flow, tiap baris boleh multi-ember.
- Normalisasi/validasi ejaan ember di DB selain lewat form Filament (multi-select sudah membatasi ke value enum).
- Migrasi data — tidak ada. Semua flow existing jadi `sub_module = NULL` otomatis dan itu memang perilaku yang diinginkan (fallback).
- `sub_module` untuk modul non-attendance.
