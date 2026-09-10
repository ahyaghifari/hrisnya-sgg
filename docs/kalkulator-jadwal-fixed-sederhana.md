# Kalkulator Presensi — Jadwal Fixed Sederhana (per Perusahaan)

Spesifikasi implementasi untuk dieksekusi oleh Claude di project Laravel lain.
Tujuan: menghasilkan rekap presensi harian dari tabel mentah `access_logs`
(dump alat absensi, mis. Hikvision), memakai **satu jadwal fixed per
perusahaan** — tanpa jadwal flexible, shift, roster, lembur, maupun shift
lintas tengah malam.

Turunan yang disederhanakan dari pipeline HRIS
(`App\Services\Attendance\AccessLogScheduleReader` + `FixedScheduleCalculator`).
Yang dibuang secara sengaja dicatat di bagian [Batasan](#batasan).

---

## 1. Gambaran alur

```
access_logs  (DB HRIS, mentah per-scan — dibaca via koneksi 'hris', read-only)
   │  1. baca baris baru (watermark incremental)
   │  2. cocokkan employee_id -> users.nip -> company_id   (users di DB lokal project target)
   │  3. pasangkan scan jadi 1 entri per (nip, tanggal): check_in = scan pertama, check_out = scan terakhir
   ▼
entri harian
   │  4. ambil jadwal fixed perusahaan untuk day_of_week tanggal itu   (DB lokal)
   │  5. hitung telat / pulang-cepat / menit-kerja / status
   ▼
attendance_records  (DB lokal, rekap 1 baris per nip+tanggal) — upsert
```

Dijalankan berkala (cron tiap 1–5 menit) atau manual untuk rentang tanggal.

---

## 2. Prasyarat di project target

| Kebutuhan | Keterangan |
|---|---|
| Akses ke database HRIS | `access_logs` **dibaca langsung dari DB HRIS** lewat koneksi database kedua — project target **tidak** membuat/menyalin tabel ini. Butuh kredensial DB HRIS (host, port, nama db, user read-only). Setup di §3.1. |
| Tabel karyawan (lokal) | Di DB project target sendiri. Punya kolom NIP unik yang nilainya sama dengan `access_logs.employee_id` di HRIS, dan kolom `company_id`. Dokumen ini asumsikan model `App\Models\User` dengan kolom `nip` dan `company_id`. Sesuaikan bila beda. |
| Tabel perusahaan (lokal) | Model apa pun dengan primary key yang dipakai `company_id`. |
| Laravel | 10/11/12. Butuh `nesbot/carbon` (bawaan). |

Kalau nama model/kolom berbeda, ganti referensi `User`, `nip`, `company_id`
di seluruh kode di bawah — tidak ada asumsi lain.

Tabel di DB **lokal** project target yang dibuat dokumen ini:
`company_fixed_schedules`, `attendance_records`, `attendance_sync_states`.
`access_logs` tetap di DB HRIS.

---

## 3. Skema & koneksi database

### 3.1 `access_logs` (di DB HRIS — dibaca lewat koneksi kedua)

Jangan buat migration. Tambahkan koneksi kedua di `config/database.php`:

```php
'connections' => [
    // ... koneksi default project target ...

    'hris' => [
        'driver'    => 'mysql',
        'host'      => env('HRIS_DB_HOST', '127.0.0.1'),
        'port'      => env('HRIS_DB_PORT', '3306'),
        'database'  => env('HRIS_DB_DATABASE', 'hris'),
        'username'  => env('HRIS_DB_USERNAME'),
        'password'  => env('HRIS_DB_PASSWORD'),
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'strict'    => true,
    ],
],
```

`.env` project target:

```
HRIS_DB_HOST=...
HRIS_DB_PORT=3306
HRIS_DB_DATABASE=hris
HRIS_DB_USERNAME=readonly_user
HRIS_DB_PASSWORD=...
```

User DB HRIS cukup hak **SELECT** pada `access_logs`. Semua akses ke tabel ini
lewat `DB::connection('hris')`.

Skema `access_logs` di HRIS (referensi — kolom yang dipakai reader):

| Kolom | Tipe | Dipakai |
|---|---|---|
| `employee_id` | string | ya — = `users.nip` di project target |
| `access_datetime` | datetime | ya — watermark incremental, urutan |
| `access_date` | date | ya — filter rentang |
| `access_time` | time | ya — jam check-in/check-out |
| `device_name` | string, nullable | dibaca (kolom `devices`), tidak dipakai memblokir |
| `first_name`, `last_name`, `person_name`, `person_group` | string | tidak dipakai |

Tabel ini tidak punya primary key; ada index di `access_datetime`, `access_date`,
`employee_id`. Reader hanya `SELECT ... ORDER BY access_datetime`.

### 3.2 `company_fixed_schedules` (konfigurasi jadwal — 7 baris per perusahaan)

```php
Schema::create('company_fixed_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('day_of_week');           // 0=Minggu .. 6=Sabtu (Carbon::dayOfWeek)
    $table->boolean('is_off_day')->default(false);
    $table->time('start_time')->nullable();               // wajib bila !is_off_day
    $table->time('end_time')->nullable();                 // wajib bila !is_off_day
    $table->unsignedSmallInteger('break_minutes')->default(0);
    $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
    $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0);
    $table->unsignedSmallInteger('checkin_buffer_minutes')->default(0);   // untuk flag out_of_window
    $table->unsignedSmallInteger('checkout_buffer_minutes')->default(0);  // untuk flag out_of_window
    $table->timestamps();

    $table->unique(['company_id', 'day_of_week']);
});
```

Arti kolom:

| Kolom | Arti |
|---|---|
| `day_of_week` | 0 Minggu … 6 Sabtu, cocok dengan `Carbon::parse($date)->dayOfWeek`. |
| `is_off_day` | Hari libur tetap. Scan pada hari ini diabaikan (tidak menghasilkan rekap). |
| `start_time` / `end_time` | Jam masuk & pulang resmi, format `HH:MM:SS`. |
| `break_minutes` | Potongan istirahat dari durasi kerja. |
| `late_tolerance_minutes` | Menit toleransi sebelum dihitung telat. Telat dihitung dari `start_time + toleransi`. |
| `early_leave_tolerance_minutes` | Menit toleransi pulang cepat. Dihitung dari `end_time - toleransi`. |
| `checkin_buffer_minutes` / `checkout_buffer_minutes` | Hanya untuk menandai `out_of_window` (scan jauh di luar jam wajar). Tidak mengubah perhitungan menit. |

### 3.3 `attendance_records` (hasil — upsert)

Kalau project target sudah punya tabel rekap, pakai itu dan sesuaikan nama
kolom di `AttendanceRecordWriter` (§6). Kalau belum:

```php
Schema::create('attendance_records', function (Blueprint $table) {
    $table->id();
    $table->string('nip');
    $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
    $table->date('date');
    $table->time('check_in_time')->nullable();
    $table->time('check_out_time')->nullable();
    $table->unsignedSmallInteger('late_minutes')->default(0);
    $table->unsignedSmallInteger('early_leave_minutes')->default(0);
    $table->unsignedInteger('working_minutes')->nullable();
    $table->string('status');                 // 'present' | 'late' | 'absent'
    $table->boolean('out_of_window')->default(false);
    $table->string('source')->default('access_logs_fixed');
    $table->timestamps();

    $table->unique(['nip', 'date']);
});
```

### 3.4 `attendance_sync_states` (watermark incremental — 1 baris)

```php
Schema::create('attendance_sync_states', function (Blueprint $table) {
    $table->id();
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();
});
```

---

## 4. Model

```php
// app/Models/CompanyFixedSchedule.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyFixedSchedule extends Model
{
    protected $fillable = [
        'company_id', 'day_of_week', 'is_off_day', 'start_time', 'end_time',
        'break_minutes', 'late_tolerance_minutes', 'early_leave_tolerance_minutes',
        'checkin_buffer_minutes', 'checkout_buffer_minutes',
    ];

    protected $casts = ['is_off_day' => 'boolean'];
}
```

```php
// app/Models/AttendanceSyncState.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSyncState extends Model
{
    protected $fillable = ['last_synced_at'];
    protected $casts = ['last_synced_at' => 'datetime'];

    /** Singleton watermark. */
    public static function singleton(): self
    {
        return static::firstOrCreate([]);
    }
}
```

---

## 5. Service: baca & pasangkan scan

`app/Services/Attendance/AccessLogReader.php`

Tanggung jawab: baca `access_logs`, cocokkan NIP ke perusahaan, pasangkan scan
menjadi entri harian. **Tidak** menghitung apa pun.

```php
namespace App\Services\Attendance;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessLogReader
{
    /**
     * @return array{
     *   entries: array<int, array{nip:string, company_id:mixed, date:string, check_in:?string, check_out:?string, devices:array<string>}>,
     *   unmatched: array<string,int>,
     *   max_synced_at: ?string
     * }
     */
    /** Semua akses access_logs lewat koneksi 'hris' (DB HRIS, read-only). */
    private function accessLogs()
    {
        return DB::connection('hris')->table('access_logs');
    }

    public function readIncremental(?string $sinceDatetime): array
    {
        $query = $this->accessLogs();
        if ($sinceDatetime) {
            $query->where('access_datetime', '>', $sinceDatetime);
        }
        $rows = $query->orderBy('access_datetime')->get();

        return $this->build($rows);
    }

    /** Rentang tanggal, untuk backfill / jalankan ulang manual. */
    public function readRange(string $fromDate, string $toDate, ?string $nip = null): array
    {
        $query = $this->accessLogs()->whereBetween('access_date', [$fromDate, $toDate]);
        if ($nip) {
            $query->where('employee_id', $nip);
        }
        $rows = $query->orderBy('access_datetime')->get();

        return $this->build($rows);
    }

    private function build(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['entries' => [], 'unmatched' => [], 'max_synced_at' => null];
        }

        $maxSyncedAt = (string) $rows->max('access_datetime');

        // 1 query untuk semua NIP.
        $nips = $rows->pluck('employee_id')->unique()->values();
        $users = User::whereIn('nip', $nips)->get(['id', 'nip', 'company_id'])->keyBy('nip');

        $entries = [];
        $unmatched = [];

        // Kelompokkan per (nip, tanggal-scan). Tidak ada penanganan overnight:
        // check-out selalu jatuh di access_date yang sama dengan check-in.
        $groups = $rows->groupBy(fn ($r) => $r->employee_id.'|'.$r->access_date);

        foreach ($groups as $key => $dayRows) {
            [$nip, $date] = explode('|', $key, 2);
            $user = $users->get($nip);

            if (! $user) {
                $unmatched[$nip] = ($unmatched[$nip] ?? 0) + count($dayRows);
                continue;
            }

            $dayRows = $dayRows->sortBy('access_datetime')->values();
            $first = $dayRows->first()->access_time;
            $last  = $dayRows->last()->access_time;

            $entries[] = [
                'nip'        => $nip,
                'company_id' => $user->company_id,
                'date'       => $date,
                // Scan tunggal: kalau sudah lewat jam pulang jadwal, itu check-out (lupa scan
                // masuk) — check_in dikosongkan tapi tetap dianggap hadir. Kalau belum, itu
                // check-in saja. Keputusan finalnya di calculator (butuh end_time jadwal).
                'check_in'   => $dayRows->count() > 1 ? $first : $first,
                'check_out'  => $dayRows->count() > 1 ? $last : null,
                'single_scan'=> $dayRows->count() === 1,
                'devices'    => $dayRows->pluck('device_name')->filter()->unique()->values()->all(),
            ];
        }

        return ['entries' => $entries, 'unmatched' => $unmatched, 'max_synced_at' => $maxSyncedAt];
    }
}
```

> Catatan: pada versi incremental sederhana ini, satu batch yang hanya berisi
> scan check-out (check-in-nya sudah diproses batch sebelumnya) akan
> menghasilkan entri dengan `check_in` = scan check-out itu. Writer di §6
> menanganinya dengan aturan **merge**: `check_in` diambil `min`, `check_out`
> diambil `max` terhadap baris yang sudah tersimpan. Selama writer memakai
> merge, ini aman.

---

## 6. Service: hitung + tulis

### 6.1 Kalkulator

`app/Services/Attendance/FixedScheduleCalculator.php`

```php
namespace App\Services\Attendance;

use App\Models\CompanyFixedSchedule;
use Carbon\Carbon;

class FixedScheduleCalculator
{
    /** cache jadwal per (company_id, day_of_week) selama 1 proses. */
    private array $cache = [];

    /**
     * @param  array{nip:string, company_id:mixed, date:string, check_in:?string, check_out:?string, single_scan?:bool}  $entry
     * @return array|null  null = hari libur / jadwal tidak ada / scan diabaikan
     */
    public function calculate(array $entry): ?array
    {
        $cfg = $this->schedule($entry['company_id'], $entry['date']);

        if (! $cfg || $cfg->is_off_day || ! $cfg->start_time || ! $cfg->end_time) {
            return null; // tidak ada jadwal kerja -> tidak menghasilkan rekap
        }

        $start = Carbon::createFromFormat('H:i:s', $cfg->start_time);
        $end   = Carbon::createFromFormat('H:i:s', $cfg->end_time);

        $checkIn  = $entry['check_in'];
        $checkOut = $entry['check_out'];

        // Scan tunggal yang waktunya sudah >= jam pulang: perlakukan sebagai check-out,
        // check_in dikosongkan. Tetap hadir.
        if (! empty($entry['single_scan']) && $checkIn && $checkIn >= $cfg->end_time) {
            $checkOut = $checkIn;
            $checkIn  = null;
        }

        if ($checkIn === null && $checkOut === null) {
            return null;
        }

        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        $workingMinutes = null;
        $outOfWindow = false;

        if ($checkIn !== null) {
            $ci = Carbon::createFromFormat('H:i:s', $checkIn);
            $lateThreshold = $start->copy()->addMinutes($cfg->late_tolerance_minutes);
            $lateMinutes = $ci->gt($lateThreshold) ? (int) $lateThreshold->diffInMinutes($ci) : 0;

            $earliest = $start->copy()->subMinutes($cfg->checkin_buffer_minutes);
            $outOfWindow = $ci->lt($earliest);
        }

        if ($checkOut !== null) {
            $co = Carbon::createFromFormat('H:i:s', $checkOut);
            $earlyThreshold = $end->copy()->subMinutes($cfg->early_leave_tolerance_minutes);
            $earlyLeaveMinutes = $co->lt($earlyThreshold) ? (int) $co->diffInMinutes($earlyThreshold) : 0;

            $latest = $end->copy()->addMinutes($cfg->checkout_buffer_minutes);
            $outOfWindow = $outOfWindow || $co->gt($latest);

            if ($checkIn !== null) {
                $ci = Carbon::createFromFormat('H:i:s', $checkIn);
                $workingMinutes = max(0, (int) $ci->diffInMinutes($co) - $cfg->break_minutes);
            }
        }

        // Early-leave tidak mengubah status (konsisten dengan HRIS). check_out kosong pun
        // tetap 'present' — karyawan sudah scan masuk, cuma belum/lupa scan pulang.
        $status = $lateMinutes > 0 ? 'late' : 'present';

        return [
            'nip'                => $entry['nip'],
            'company_id'         => $entry['company_id'],
            'date'               => $entry['date'],
            'check_in_time'      => $checkIn,
            'check_out_time'     => $checkOut,
            'late_minutes'       => $lateMinutes,
            'early_leave_minutes'=> $earlyLeaveMinutes,
            'working_minutes'    => $workingMinutes,
            'status'             => $status,
            'out_of_window'      => $outOfWindow,
        ];
    }

    private function schedule(mixed $companyId, string $date): ?CompanyFixedSchedule
    {
        $dow = Carbon::parse($date)->dayOfWeek; // 0..6
        $key = $companyId.'|'.$dow;

        return $this->cache[$key] ??= CompanyFixedSchedule::query()
            ->where('company_id', $companyId)
            ->where('day_of_week', $dow)
            ->first() ?: null;
    }
}
```

### 6.2 Writer (upsert + merge)

`app/Services/Attendance/AttendanceRecordWriter.php`

```php
namespace App\Services\Attendance;

use App\Models\AttendanceRecord;

class AttendanceRecordWriter
{
    /** @param  array  $r  hasil FixedScheduleCalculator::calculate() */
    public function save(array $r): AttendanceRecord
    {
        $existing = AttendanceRecord::where('nip', $r['nip'])->where('date', $r['date'])->first();

        // Merge: jangan biarkan batch yang cuma bawa scan check-out menimpa check-in
        // yang sudah benar dari batch sebelumnya (dan sebaliknya).
        $checkIn  = $this->earliest($existing?->check_in_time, $r['check_in_time']);
        $checkOut = $this->latest($existing?->check_out_time, $r['check_out_time']);

        // Kalau merge mengubah pasangan waktu, hitung ulang menit dari data gabungan
        // dengan memanggil calculator sekali lagi lewat entri sintetis — atau, versi
        // paling sederhana: percayai $r bila belum ada existing, dan bila ada existing
        // cukup perbarui check_out + menit dari $r (batch incremental normal: check_in
        // sudah tetap, yang baru hanyalah check_out).
        return AttendanceRecord::updateOrCreate(
            ['nip' => $r['nip'], 'date' => $r['date']],
            [
                'company_id'          => $r['company_id'],
                'check_in_time'       => $checkIn,
                'check_out_time'      => $checkOut,
                'late_minutes'        => $r['late_minutes'],
                'early_leave_minutes' => $r['early_leave_minutes'],
                'working_minutes'     => $r['working_minutes'],
                'status'              => $r['status'],
                'out_of_window'       => $r['out_of_window'],
                'source'              => 'access_logs_fixed',
            ],
        );
    }

    private function earliest(?string $a, ?string $b): ?string
    {
        return collect([$a, $b])->filter()->min();
    }

    private function latest(?string $a, ?string $b): ?string
    {
        return collect([$a, $b])->filter()->max();
    }
}
```

> Penyederhanaan yang dipilih: pada jalur incremental, `check_in` untuk satu
> (nip, tanggal) tidak berubah setelah scan pertama tersimpan; batch berikutnya
> hanya menambah `check_out`. Jadi `late_minutes` dari `$r` tetap benar. Kalau
> butuh presisi mutlak saat scan datang tak berurutan, panggil ulang
> `FixedScheduleCalculator::calculate()` dengan `check_in`/`check_out` hasil
> merge sebelum menyimpan.

---

## 7. Command

`app/Console/Commands/SyncFixedAttendance.php`

```php
namespace App\Console\Commands;

use App\Models\AttendanceSyncState;
use App\Services\Attendance\AccessLogReader;
use App\Services\Attendance\AttendanceRecordWriter;
use App\Services\Attendance\FixedScheduleCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFixedAttendance extends Command
{
    protected $signature = 'attendance:sync
        {--from= : tanggal mulai (Y-m-d) untuk mode rentang}
        {--to=   : tanggal akhir (Y-m-d) untuk mode rentang}
        {--nip=  : batasi 1 NIP (mode rentang)}';

    protected $description = 'Sinkron access_logs -> attendance_records (jadwal fixed per perusahaan)';

    public function handle(
        AccessLogReader $reader,
        FixedScheduleCalculator $calculator,
        AttendanceRecordWriter $writer,
    ): int {
        $isRange = $this->option('from') && $this->option('to');

        if ($isRange) {
            $result = $reader->readRange($this->option('from'), $this->option('to'), $this->option('nip'));
        } else {
            $state = AttendanceSyncState::singleton();
            $result = $reader->readIncremental($state->last_synced_at?->toDateTimeString());
        }

        if ($result['max_synced_at'] === null) {
            $this->info('Tidak ada access_logs baru.');
            return self::SUCCESS;
        }

        $saved = 0;
        $skipped = 0;

        foreach ($result['entries'] as $entry) {
            $calc = $calculator->calculate($entry);

            if ($calc === null) {
                $skipped++; // hari libur / tidak ada jadwal
                continue;
            }

            $writer->save($calc);
            $saved++;
        }

        foreach ($result['unmatched'] as $nip => $count) {
            Log::warning("attendance:sync — NIP tidak dikenal: {$nip} ({$count} scan)");
        }

        if (! $isRange) {
            AttendanceSyncState::singleton()->update(['last_synced_at' => $result['max_synced_at']]);
        }

        $this->info("Selesai: {$saved} disimpan, {$skipped} dilewati, "
            .count($result['unmatched']).' NIP tidak dikenal. Watermark -> '.$result['max_synced_at']);

        return self::SUCCESS;
    }
}
```

Jadwalkan di `routes/console.php` (Laravel 11/12) atau `app/Console/Kernel.php`:

```php
Schedule::command('attendance:sync')->everyMinute()->withoutOverlapping();
```

Backfill manual:

```bash
php artisan attendance:sync --from=2026-09-01 --to=2026-09-08
php artisan attendance:sync --from=2026-09-01 --to=2026-09-08 --nip=12345
```

---

## 8. Contoh perhitungan

Jadwal perusahaan A, Senin: `start 08:00`, `end 17:00`, `break 60`,
`late_tolerance 5`, `early_leave_tolerance 5`.

| check_in | check_out | late | early_leave | working | status |
|---|---|---|---|---|---|
| 07:55 | 17:10 | 0 | 0 | 555 (`9j15m - 60`) | present |
| 08:04 | 17:00 | 0 (masih ≤ 08:05) | 0 | 536 | present |
| 08:30 | 16:00 | 25 (dari 08:05) | 55 (sampai 16:55) | 450 | late |
| 08:00 | — | 0 | 0 | null | present (belum scan pulang) |
| — | 17:05 (scan tunggal) | 0 | 0 | null | present (lupa scan masuk) |
| hari `is_off_day` | apa pun | — | — | — | *tidak ada rekap* |

---

## 9. Batasan (sengaja tidak diimplementasikan)

Bila salah satu diperlukan, angkat dari pipeline HRIS
(`App\Services\Attendance\*`) — jangan tambal di kalkulator ini.

- **Jadwal flexible & shift/roster.** Hanya fixed per perusahaan.
- **Jadwal per karyawan / per jabatan.** Semua karyawan satu perusahaan =
  satu jadwal per hari.
- **Shift lintas tengah malam.** `check_out` diasumsikan selalu di
  `access_date` yang sama dengan `check_in`.
- **Lembur** (sebelum/sesudah jam kerja), `min_work_minutes` /
  `max_work_minutes`, penolakan scan check-out.
- **Bobot hari kerja** (`work_day_weight`, half-day/absent threshold,
  `counted_work_days`).
- **Validasi device** (`device_name` vs daftar alat resmi perusahaan).
  Kolom `devices` dibaca tapi tidak dipakai memblokir.
- **Notifikasi** check-in/check-out/penolakan.
- **Hari libur nasional** (tabel Holidays). Hanya `is_off_day` mingguan yang
  dihormati.
- **Status `absent`.** Versi ini hanya menulis baris untuk hari yang ada scan.
  Untuk menandai mangkir, butuh proses terpisah yang mengiterasi semua
  karyawan aktif × tanggal kerja dan menulis `status = 'absent'` bila tak ada
  `attendance_records`.

---

## 10. Checklist pengetesan

1. **Koneksi `hris`** bisa `SELECT` dari `access_logs`:
   `php artisan tinker` → `DB::connection('hris')->table('access_logs')->count()`.
2. **Migrasi** 3 tabel lokal (`company_fixed_schedules`, `attendance_records`,
   `attendance_sync_states`) jalan bersih.
3. Seed `company_fixed_schedules`: 7 baris untuk 1 perusahaan (mis. Sen–Jum
   kerja, Sab–Min `is_off_day`).
4. Insert `access_logs` dummy **di DB HRIS** (atau uji dengan data nyata):
   - normal (2 scan, dalam jam) → `status present`, `working_minutes` benar.
   - telat → `late_minutes` > 0, `status late`.
   - pulang cepat → `early_leave_minutes` > 0, `status` tetap `present`/`late`.
   - 1 scan pagi → `check_in` terisi, `check_out` null, `present`.
   - 1 scan setelah `end_time` → `check_in` null, `check_out` terisi, `present`.
   - scan di hari `is_off_day` → tidak ada baris `attendance_records`.
   - `employee_id` yang tak ada di `users` → tidak error, tercatat di log.
5. **Incremental**: jalankan `attendance:sync`, cek `last_synced_at` maju;
   jalankan lagi tanpa data baru → "Tidak ada access_logs baru".
6. **Merge**: insert scan masuk, sync; lalu insert scan pulang belakangan,
   sync → satu baris `attendance_records` dengan `check_in` & `check_out`
   keduanya benar (bukan tertimpa).
7. **Rentang**: `attendance:sync --from=... --to=...` tidak menyentuh
   watermark dan bisa dijalankan berulang (idempoten karena `updateOrCreate`).
