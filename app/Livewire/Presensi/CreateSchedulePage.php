<?php

namespace App\Livewire\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleFixedDay;
use App\Models\Shift\ScheduleFlexibleDay;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman terpisah (bukan modal, atas permintaan user) buat bikin 1 `schedules` baru. Tipe
 * Fixed/Flexible langsung isi tabel 7 hari (niru CONTOH JADWAL FIXED/FLEXIBEL.png di
 * knowledge/absensi) dalam 1 halaman yang sama — bukan menyusul terpisah lagi. Tipe Shift
 * SENGAJA belum ada tabel kode shift-nya di sini ("shift nanti dulu", diminta eksplisit),
 * cuma isi header dulu.
 */
#[Layout('components.layout', ['title' => 'Tambah Jadwal'])]
class CreateSchedulePage extends Component
{
    /** 0=Minggu..6=Sabtu (Carbon::dayOfWeek), tapi ditampilkan Senin->Minggu di tabel. */
    private const DISPLAY_ORDER = [1, 2, 3, 4, 5, 6, 0];
    private const DAY_LABELS = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    /** 1 = pilih tipe jadwal (kartu ikon), 2 = isi detail + tabel hari (Fixed/Flexible). */
    public int $step = 1;

    public ?string $companyId = null;
    public string $name = '';
    public string $scheduleType = '';

    // Flexible-only, level jadwal (bukan per hari) — lihat schedules.flexible_checkin_from/until.
    public string $flexibleCheckinFrom = '06:00';
    public string $flexibleCheckinUntil = '23:59';

    /** @var array<int, array<string, mixed>> keyed 0..6, isinya beda tergantung scheduleType */
    public array $days = [];

    /** Hari yang lagi dibuka modal "Pengaturan Lanjutan"-nya (0-6), null = tertutup. */
    public ?int $advancedDay = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_ABSENSI->value), 403);

        $this->companyId = Auth::user()->company_id ?? Companies::orderBy('code')->value('id');
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getScheduleTypesProperty(): array
    {
        return ScheduleType::cases();
    }

    public function getDisplayDaysProperty(): array
    {
        return self::DISPLAY_ORDER;
    }

    public function dayLabel(int $dayOfWeek): string
    {
        return self::DAY_LABELS[$dayOfWeek];
    }

    /** Nama kolom "Maksimal Durasi Kerja" beda per tabel — flexible sudah punya max_work_minutes
     * sendiri sebagai field JAM KERJA biasa, jadi field lanjutannya dikasih nama beda. */
    public function maxDurationField(): string
    {
        return $this->scheduleType === ScheduleType::FLEXIBLE->value ? 'max_work_minutes_cap' : 'max_work_minutes';
    }

    public function openAdvanced(int $dayOfWeek): void
    {
        $this->advancedDay = $dayOfWeek;
    }

    public function closeAdvanced(): void
    {
        $this->advancedDay = null;
    }

    /** Hari itu libur? Tooltip "Rincian" tampilkan pesan libur polos kalau iya, bukan rincian. */
    public function isDayOff(int $dayOfWeek): bool
    {
        return $this->days[$dayOfWeek]['is_off_day'] ?? false;
    }

    /**
     * Baris ikon+teks buat tooltip "Rincian" — niru persis contoh fingerspot.io (jam dihitung
     * dari start_time/end_time + buffer/toleransi, bukan cuma angka menit mentah). Line lembur
     * awal/akhir cuma muncul kalau memang dikonfigurasi (max > 0), sama seperti contohnya.
     *
     * @return array<int, array{icon: string, html: string}>
     */
    public function dayDetailLines(int $dayOfWeek): array
    {
        $row = $this->days[$dayOfWeek] ?? [];

        if ($this->scheduleType === ScheduleType::FLEXIBLE->value) {
            return [
                ['icon' => 'fa-tower-broadcast', 'html' => "Jendela absensi <strong>{$this->flexibleCheckinFrom}</strong> &ndash; <strong>{$this->flexibleCheckinUntil}</strong>"],
                ['icon' => 'fa-business-time', 'html' => sprintf('Total jam kerja <strong>%d&ndash;%d menit</strong>', (int) ($row['min_work_minutes'] ?? 0), (int) ($row['max_work_minutes'] ?? 0))],
                ['icon' => 'fa-utensils', 'html' => sprintf('Istirahat <strong>%d menit</strong>', (int) ($row['break_minutes'] ?? 0))],
                ['icon' => 'fa-hourglass-half', 'html' => sprintf('Lembur dihitung <strong>%d&ndash;%d menit</strong> (toleransi %d menit)', (int) ($row['overtime_min_minutes'] ?? 0), (int) ($row['overtime_max_minutes'] ?? 0), (int) ($row['overtime_break_minutes'] ?? 0))],
            ];
        }

        $lines = [
            ['icon' => 'fa-tower-broadcast', 'html' => 'Batas mulai bisa scan masuk <strong>' . $this->shiftTime($row['start_time'] ?? null, -(int) ($row['checkin_buffer_minutes'] ?? 0)) . '</strong>'],
            ['icon' => 'fa-clock', 'html' => sprintf('Jam Masuk <strong>%s</strong> (toleransi terlambat %d menit)', $row['start_time'] ?? '-', (int) ($row['late_tolerance_minutes'] ?? 0))],
            ['icon' => 'fa-utensils', 'html' => sprintf('Istirahat <strong>%d menit</strong>', (int) ($row['break_minutes'] ?? 0))],
            ['icon' => 'fa-clock', 'html' => 'Jam Pulang <strong>' . ($row['end_time'] ?? '-') . '</strong>'],
        ];

        if ((int) ($row['overtime_before_max'] ?? 0) > 0) {
            $from = $this->shiftTime($row['start_time'] ?? null, -(int) ($row['overtime_before_max'] ?? 0));
            $to = $this->shiftTime($row['start_time'] ?? null, -(int) ($row['overtime_before_min'] ?? 0));
            $lines[] = ['icon' => 'fa-hourglass-half', 'html' => "Dianggap lembur awal jika ada absensi lembur awal dari jam <strong>{$from}</strong> sampai <strong>{$to}</strong>"];
        }

        if ((int) ($row['overtime_after_max'] ?? 0) > 0) {
            $from = $this->shiftTime($row['end_time'] ?? null, (int) ($row['overtime_after_min'] ?? 0));
            $to = $this->shiftTime($row['end_time'] ?? null, (int) ($row['overtime_after_max'] ?? 0));
            $lines[] = ['icon' => 'fa-hourglass-half', 'html' => "Dianggap lembur akhir jika ada absensi lembur akhir dari jam <strong>{$from}</strong> sampai <strong>{$to}</strong>"];
        }

        $lines[] = ['icon' => 'fa-tower-broadcast', 'html' => 'Batas akhir bisa scan pulang <strong>' . $this->shiftTime($row['end_time'] ?? null, (int) ($row['checkout_buffer_minutes'] ?? 0)) . '</strong>'];

        return $lines;
    }

    /** Bullet list bawah tooltip — sama buat Fixed & Flexible, niru contoh fingerspot.io. */
    public function dayDetailBullets(int $dayOfWeek): array
    {
        $row = $this->days[$dayOfWeek] ?? [];

        return [
            sprintf('Dihitung kerja : <strong>%d</strong>', (int) ($row['counted_work_days'] ?? 1)),
            sprintf('Maksimal Durasi Kerja : <strong>%d (Menit)</strong>', (int) ($row[$this->maxDurationField()] ?? 0)),
            sprintf('Hadir setengah hari, jika durasi kerja kurang dari : <strong>%d (Menit)</strong>', (int) ($row['half_day_threshold_minutes'] ?? 0)),
            sprintf('Dihitung tidak hadir, jika durasi kerja kurang dari : <strong>%d (Menit)</strong>', (int) ($row['absent_threshold_minutes'] ?? 0)),
        ];
    }

    /** Geser "H:i" sejumlah menit (boleh negatif) — dipakai hitung batas scan/jendela lembur di tooltip Rincian. */
    private function shiftTime(?string $time, int $minutes): string
    {
        if (! $time) {
            return '-';
        }

        return Carbon::createFromFormat('H:i', $time)->addMinutes($minutes)->format('H:i');
    }

    public function selectType(string $type): void
    {
        $this->scheduleType = $type;
    }

    public function nextStep(): void
    {
        $this->validate([
            'scheduleType' => 'required|in:' . implode(',', array_column(ScheduleType::cases(), 'value')),
        ], [], ['scheduleType' => 'tipe jadwal']);

        if ($this->scheduleType !== ScheduleType::SHIFT->value) {
            $this->initDays();
        }

        $this->step = 2;
    }

    public function backStep(): void
    {
        $this->step = 1;
    }

    /**
     * Default Minggu (day_of_week=0) libur — niru CONTOH JADWAL FIXED/FLEXIBEL.png (baris
     * Minggu tercentang Libur & di-highlight merah bawaan). Nilai default lain (jam kerja
     * 08:00-16:00 dst) cuma titik awal yang wajar, semua tetap bisa diedit user.
     */
    private function initDays(): void
    {
        $this->days = [];

        // 8 field "Pengaturan Lanjutan" — default niru KONFIGURASI JADWAL FIXED/FLEXIBLE.png
        // di knowledge/absensi (batas absensi 150/210 menit, toleransi 5 menit, dst).
        $advanced = [
            'checkin_buffer_minutes' => 150,
            'checkout_buffer_minutes' => 210,
            'late_tolerance_minutes' => 5,
            'early_leave_tolerance_minutes' => 0,
            'counted_work_days' => 1,
            'half_day_threshold_minutes' => 0,
            'absent_threshold_minutes' => 0,
        ];

        foreach (range(0, 6) as $dow) {
            $isOff = $dow === 0;

            $this->days[$dow] = $this->scheduleType === ScheduleType::FIXED->value
                ? [
                    'is_off_day' => $isOff,
                    'start_time' => '08:00',
                    'break_minutes' => 60,
                    'end_time' => '16:00',
                    'overtime_before_min' => 0,
                    'overtime_before_break' => 0,
                    'overtime_before_max' => 0,
                    'overtime_after_min' => 120,
                    'overtime_after_break' => 0,
                    'overtime_after_max' => 240,
                    ...$advanced,
                    'max_work_minutes' => 480,
                ]
                : [
                    'is_off_day' => $isOff,
                    'break_minutes' => 60,
                    'min_work_minutes' => 240,
                    'max_work_minutes' => 480,
                    'overtime_break_minutes' => 0,
                    'overtime_min_minutes' => 0,
                    'overtime_max_minutes' => 0,
                    ...$advanced,
                    'max_work_minutes_cap' => 480,
                ];
        }
    }

    public function save(): void
    {
        $this->validate([
            'companyId' => 'required',
            'name' => 'required|string|max:255',
            'scheduleType' => 'required|in:' . implode(',', array_column(ScheduleType::cases(), 'value')),
        ], [], [
            'companyId' => 'perusahaan',
            'name' => 'nama jadwal',
            'scheduleType' => 'tipe jadwal',
        ]);

        DB::transaction(function () {
            // org_structure_id (unit/divisi) sengaja tidak diisi di sini — dipindah ke step
            // assignment terpisah nanti (schedule_assignments, lihat planning §2.6), bukan lagi
            // ditentukan pas bikin header jadwal.
            $schedule = Schedule::create([
                'company_id' => $this->companyId,
                'name' => $this->name,
                'schedule_type' => $this->scheduleType,
                'flexible_checkin_from' => $this->scheduleType === ScheduleType::FLEXIBLE->value ? $this->flexibleCheckinFrom : null,
                'flexible_checkin_until' => $this->scheduleType === ScheduleType::FLEXIBLE->value ? $this->flexibleCheckinUntil : null,
            ]);

            foreach ($this->days as $dow => $row) {
                if ($this->scheduleType === ScheduleType::FIXED->value) {
                    ScheduleFixedDay::create(['schedule_id' => $schedule->id, 'day_of_week' => $dow, ...$row]);
                } elseif ($this->scheduleType === ScheduleType::FLEXIBLE->value) {
                    ScheduleFlexibleDay::create(['schedule_id' => $schedule->id, 'day_of_week' => $dow, ...$row]);
                }
            }
        });

        Notification::make()
            ->title('Jadwal berhasil dibuat')
            ->success()
            ->send();

        $this->redirect(route('presensi.jadwal'), navigate: true);
    }

    public function render()
    {
        return view('livewire.presensi.create-schedule-page');
    }
}
