# Approval `sub_module` — alur persetujuan berbeda per jenis Izin/Cuti/Lembur

> **STATUS 2026-09-10: BELUM DIEKSEKUSI.** Planning saja, akan dikerjakan menyusul.

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
| Mekanisme | Kolom baru **`sub_module`** (VARCHAR nullable) di `m_approval_flows`. `null` = berlaku seluruh modul (perilaku lama). | Perubahan schema minimal. Semua baris & company existing otomatis aman lewat fallback `null`. |
| Berlaku untuk siapa | **Semua company.** Company yang tidak bikin flow ber-`sub_module` selalu jatuh ke flow `null` = tidak ada perubahan perilaku. | Tidak ada alasan membatasi ke 1 company. Fallback bikin ini aman universal. |
| Granularitas "ember" | Per **kelompok izin** (`PermissionGroupEnum`) + `leave` + `overtime`. Bukan per-kategori. | Kebutuhan yang ada cukup di level kelompok. Terlambat-masuk vs pulang-cepat beda approver = YAGNI, tambah nanti kalau muncul. |
| Nilai `sub_module` untuk izin | Sama persis dengan `PermissionGroupEnum::value` (`meninggalkan_jam_kerja`, `tidak_masuk_seharian`, `sakit`, `khusus`). | Bisa pakai ulang `PermissionGroupEnum::fromCategory()` tanpa mapping tambahan. |
| Izin lama tanpa `category` | `sub_module = null` → fallback. | Data izin lama (sebelum enum kategori) tidak punya kelompok; tidak apa-apa ikut flow default. |
| Prioritas resolusi | `sub_module` cocok + `specific` (jabatan) → `sub_module` cocok + `default` → `sub_module IS NULL` + `specific` → `sub_module IS NULL` + `default`. | `specific` (jabatan) tetap menang atas `default` di tiap tingkat, konsisten aturan sekarang. Flow ber-`sub_module` menang atas fallback. |
| Snapshot | Tidak berubah. `sub_module` hanya dipakai saat **memilih** master flow; `steps_snapshot` tetap salinan beku step seperti biasa. | `sub_module` bukan properti pengajuan, cuma kunci routing. |
| Uniqueness flow | Jadi per (`company_id`, `module`, `sub_module`, `flow_type`) — sebelumnya per (`company_id`, `module`, `flow_type`). | Supaya boleh ada beberapa flow `default` untuk 1 modul, satu per `sub_module`. |

---

## 1. Migration

File: `database/migrations/2026_09_10_XXXXXX_add_sub_module_to_m_approval_flows_table.php`

```php
Schema::table('m_approval_flows', function (Blueprint $table) {
    $table->string('sub_module', 50)->nullable()->after('module');
});
```

`down()`: `dropColumn('sub_module')`.

Tidak perlu index khusus — tabel `m_approval_flows` kecil (segelintir baris per company), query resolusi sudah ter-filter `company_id` + `module`.

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

    /** value => label, plus opsi "Semua Absensi (default)" = '' untuk form Filament. */
    public static function optionsWithNull(): array
    {
        return ['' => 'Semua Absensi (default)']
            + collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
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

foreach ($candidates as $sm) {
    if ($jobPositionId) {
        $specific = MasterApprovalFlows::with('steps')
            ->where('module', $moduleType)
            ->where('company_id', $companyId)
            ->where('flow_type', 'specific')
            ->where('is_active', true)
            ->when($sm === null, fn ($q) => $q->whereNull('sub_module'))
            ->when($sm !== null, fn ($q) => $q->where('sub_module', $sm))
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
        ->when($sm === null, fn ($q) => $q->whereNull('sub_module'))
        ->when($sm !== null, fn ($q) => $q->where('sub_module', $sm))
        ->first();

    if ($default) {
        return $default;
    }
}

return null;
```

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
    ->helperText('Khusus modul Izin/Cuti/Lembur — batasi alur ini ke jenis pengajuan tertentu. "Semua Absensi" = berlaku untuk semua jenis yang tidak punya alur khusus.')
    ->options(\App\Enum\Approval\AttendanceApprovalBucketEnum::optionsWithNull())
    ->default('')
    ->visible(fn (callable $get) => $get('module') === \App\Enum\Approval\ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
    ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state)
    ->live(),
```

Catatan: `''` di form dipetakan ke `null` di DB (`dehydrateStateUsing`). Kalau `module` bukan `attendance_request`, field hidden & tidak ter-dehydrate → tersimpan `null`.

### 5b. Uniqueness — ikutkan `sub_module`

Di file yang sama, closure `->rule(...)` pada `Select::make('module')` (baris ~68–108). Kedua cek `$exists` (`flow_type === 'default'` dan `=== 'specific'`) tambah:

```php
->where('sub_module', $get('sub_module') ?: null)
```

Sehingga pesan "Alur Persetujuan Default untuk Modul ini sudah ada" hanya muncul kalau `sub_module`-nya juga sama.

### 5c. Tabel list (opsional, quality-of-life)

File: `app/Filament/Resources/ApprovalFlows/Tables/ApprovalFlowsTable.php` — tambah kolom "Jenis Pengajuan" (`sub_module`) dengan formatter ke label enum, `placeholder('Semua')`. Tidak wajib untuk fungsi, memudahkan admin membedakan beberapa flow satu modul.

---

## 6. Test

File baru: `tests/Feature/Presensi/AttendanceApprovalSubModuleTest.php`

Skenario minimal (1–2 test):

1. **Routing per sub_module.** Company punya:
   - Flow `default`, `sub_module = null`, step final → User A.
   - Flow `default`, `sub_module = meninggalkan_jam_kerja`, step final → User B.

   Lalu:
   - Ajukan izin `izin_terlambat_masuk` → `approval_snapshots.flow_id` = flow B.
   - Ajukan izin `izin_seharian` → snapshot pakai flow A (fallback).
   - Ajukan cuti → snapshot pakai flow A.

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

- Granularitas per-`PermissionTypeCategoryEnum` (mis. terlambat-masuk beda approver dari pulang-cepat). Tambah dengan mengganti nilai `sub_module` ke level kategori kalau kebutuhan itu muncul.
- UI "matriks kebijakan approval" terpisah. Admin cukup bikin beberapa baris flow.
- Migrasi data — tidak ada. Semua flow existing jadi `sub_module = NULL` otomatis dan itu memang perilaku yang diinginkan (fallback).
- `sub_module` untuk modul non-attendance.
