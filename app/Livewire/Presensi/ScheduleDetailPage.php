<?php

namespace App\Livewire\Presensi;

use App\Enum\ScheduleType;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleChangeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Lihat" jadwal — niru halaman detail fingerspot.io (knowledge/absensi, screenshot "JAM KERJA
 * NORMAL"): tab Jam Kerja (tabel 7 hari read-only, logic tooltip "Rincian" digandakan dari
 * CreateSchedulePage tapi baca dari baris DB asli, bukan array sesi form), Pengguna (siapa yang
 * pakai jadwal ini lewat schedule_assignments — key tab internal tetap 'kantor'), Riwayat.
 *
 * Tab Riwayat baca `schedule_change_logs` (diisi CreateSchedulePage::save() jalur edit, snapshot
 * kondisi SEBELUM tiap perubahan) — klik 1 baris buka popup nampilin kondisi lama, niru "Riwayat
 * Perubahan Jadwal" fingerspot.io. Popup-nya versi ringkas (tabel polos, TANPA tooltip
 * "Rincian" hover) — snapshot historis tidak perlu interaktivitas selengkap tab Jam Kerja aktif.
 *
 * Tab "Extra Shift" (2026-08-19, shift-only, di sebelah Template Shift) — komponen nested
 * `ScheduleExtraShiftTab`, daftar + tombol tambah Extra Shift SCOPED ke Schedule ini saja
 * (beda dari ExtraShiftPage yang company-wide dan ExtraShiftRequestModal yang scoped ke tim
 * atasan) - reuse ResolvesExtraShiftOptions yang sama.
 */
#[Layout('components.layout', ['title' => 'Lihat Jadwal'])]
class ScheduleDetailPage extends Component
{
    private const DISPLAY_ORDER = [1, 2, 3, 4, 5, 6, 0];
    private const DAY_LABELS = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    public Schedule $schedule;

    // #[Url] biar widget "Jadwal Shift Saya" di Dashboard bisa deep-link langsung ke tab
    // "Detail Penjadwalan" (?tab=penjadwalan) tanpa user klik tab manual (2026-08-15).
    #[Url]
    public string $tab = 'jam-kerja';

    /** @var array<int, array<string, mixed>> keyed 0..6, isi tergantung schedule_type */
    public array $days = [];

    /** Baris Riwayat yang lagi dibuka popup-nya, null = tertutup. */
    public ?string $viewingLogId = null;

    public function mount(Schedule $schedule): void
    {
        abort_unless(Auth::user()->can('view', $schedule), 403);

        $this->schedule = $schedule;

        $rows = $schedule->schedule_type === ScheduleType::FLEXIBLE->value
            ? $schedule->flexibleDays
            : $schedule->fixedDays;

        $this->days = $rows->keyBy('day_of_week')->map(fn ($row) => $row->toArray())->all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function getDisplayDaysProperty(): array
    {
        return self::DISPLAY_ORDER;
    }

    public function dayLabel(int $dayOfWeek): string
    {
        return self::DAY_LABELS[$dayOfWeek];
    }

    public function isDayOff(int $dayOfWeek): bool
    {
        return $this->days[$dayOfWeek]['is_off_day'] ?? false;
    }

    /** "08:30:00" (MySQL) atau "08:30" (SQLite, tidak menormalkan TIME) -> selalu "08:30". */
    public function formatTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '-';
    }

    /** @return array<int, array{icon: string, html: string}> */
    public function dayDetailLines(int $dayOfWeek): array
    {
        $row = $this->days[$dayOfWeek] ?? [];

        if ($this->schedule->schedule_type === ScheduleType::FLEXIBLE->value) {
            $lines = [
                ['icon' => 'fa-tower-broadcast', 'html' => 'Jendela absensi <strong>' . $this->formatTime($this->schedule->flexible_checkin_from) . '</strong> &ndash; <strong>' . $this->formatTime($this->schedule->flexible_checkin_until) . '</strong>'],
                ['icon' => 'fa-business-time', 'html' => sprintf('Total jam kerja <strong>%d&ndash;%d menit</strong>', (int) ($row['min_work_minutes'] ?? 0), (int) ($row['max_work_minutes'] ?? 0))],
                ['icon' => 'fa-utensils', 'html' => sprintf('Istirahat <strong>%d menit</strong>', (int) ($row['break_minutes'] ?? 0))],
                ['icon' => 'fa-hourglass-half', 'html' => sprintf('Lembur dihitung <strong>%d&ndash;%d menit</strong> (toleransi %d menit)', (int) ($row['overtime_min_minutes'] ?? 0), (int) ($row['overtime_max_minutes'] ?? 0), (int) ($row['overtime_break_minutes'] ?? 0))],
            ];

            if (! empty($row['late_checkin_deadline'])) {
                $lines[] = ['icon' => 'fa-clock', 'html' => 'Batas jam masuk <strong>' . $this->formatTime($row['late_checkin_deadline']) . '</strong>, lewat dari itu dianggap telat'];
            }

            return $lines;
        }

        $lines = [
            ['icon' => 'fa-tower-broadcast', 'html' => 'Batas mulai bisa scan masuk <strong>' . $this->shiftTime($row['start_time'] ?? null, -(int) ($row['checkin_buffer_minutes'] ?? 0)) . '</strong>'],
            ['icon' => 'fa-clock', 'html' => sprintf('Jam Masuk <strong>%s</strong> (toleransi terlambat %d menit)', $this->formatTime($row['start_time'] ?? null), (int) ($row['late_tolerance_minutes'] ?? 0))],
            ['icon' => 'fa-utensils', 'html' => sprintf('Istirahat <strong>%d menit</strong>', (int) ($row['break_minutes'] ?? 0))],
            ['icon' => 'fa-clock', 'html' => 'Jam Pulang <strong>' . $this->formatTime($row['end_time'] ?? null) . '</strong>'],
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

    /** @return array<int, string> */
    public function dayDetailBullets(int $dayOfWeek): array
    {
        $row = $this->days[$dayOfWeek] ?? [];

        if ($this->schedule->schedule_type === ScheduleType::FLEXIBLE->value) {
            return [
                sprintf('Dihitung kerja : <strong>%d</strong>', (int) ($row['counted_work_days'] ?? 1)),
                sprintf('Hadir setengah hari, jika durasi kerja kurang dari : <strong>%d (Menit)</strong>', (int) ($row['half_day_threshold_minutes'] ?? 0)),
            ];
        }

        return [
            sprintf('Dihitung kerja : <strong>%d</strong>', (int) ($row['counted_work_days'] ?? 1)),
            sprintf('Maksimal Durasi Kerja : <strong>%d (Menit)</strong>', (int) ($row['max_work_minutes'] ?? 0)),
            sprintf('Hadir setengah hari, jika durasi kerja kurang dari : <strong>%d (Menit)</strong>', (int) ($row['half_day_threshold_minutes'] ?? 0)),
            sprintf('Dihitung tidak hadir, jika durasi kerja kurang dari : <strong>%d (Menit)</strong>', (int) ($row['absent_threshold_minutes'] ?? 0)),
        ];
    }

    private function shiftTime(?string $time, int $minutes): string
    {
        if (! $time) {
            return '-';
        }

        return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time . ':00' : $time)->addMinutes($minutes)->format('H:i');
    }

    public function getAssignmentsProperty()
    {
        return ScheduleAssignment::where('schedule_id', $this->schedule->id)
            ->with('jobPosition')
            ->get();
    }

    /** Shift-only: baris kode shift (Pagi/Malam/dst), niru tabel di CreateSchedulePage tapi
     * baca dari DB asli, read-only. */
    public function getShiftTypesProperty()
    {
        return $this->schedule->shiftTypes;
    }

    /** Shift-only: karyawan yang dicentang di form Create/Edit (user_id, BUKAN job_position_id
     * seperti tab Pengguna Fixed/Flexible) — lihat Concerns\HandlesShiftScheduleForm::saveShiftSchedule(). */
    public function getShiftEmployeesProperty()
    {
        return ScheduleAssignment::where('schedule_id', $this->schedule->id)
            ->whereNotNull('user_id')
            ->with('user.activeJobPosition.jobPosition.orgStructure')
            ->get();
    }

    public function getChangeLogsProperty()
    {
        return ScheduleChangeLog::where('schedule_id', $this->schedule->id)
            ->with('changedBy')
            ->orderByDesc('created_at')
            ->get();
    }

    public function openLog(string $id): void
    {
        $this->viewingLogId = $id;
    }

    public function closeLog(): void
    {
        $this->viewingLogId = null;
    }

    public function getViewingLogProperty(): ?ScheduleChangeLog
    {
        return $this->viewingLogId ? $this->changeLogs->firstWhere('id', $this->viewingLogId) : null;
    }

    public function render()
    {
        return view('livewire.presensi.schedule-detail-page');
    }
}
