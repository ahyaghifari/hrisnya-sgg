<?php

namespace App\Livewire\MyStaff;

use App\Enum\AttendanceStatus;
use App\Livewire\MyStaff\Concerns\LazyLoadsOnExpand;
use App\Models\Attendance\AttendanceRecords;
use App\Services\Attendance\AttendancePeriodResolver;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget "Presensi Tim" di halaman MyStaff - toggle Harian/Periode, pola sama
 * App\Livewire\Presensi\RekapPresensiPage (instruksi user 2026-09-18: "bisa filter juga
 * berdasarkan periode kaya di rekap presensi"), default periode ikut cutoff company
 * (AttendancePeriodResolver, bukan tanggal 1-akhir bulan kalender polos) - bedanya scope di
 * sini SELALU staff (OrgHierarchyService::subordinates()), tanpa filter company/departemen
 * (RekapPresensiPage company-wide, ini cuma tim sendiri).
 *
 * Data di-query pas tab "Dashboard" diklik pertama kali - lihat LazyLoadsOnExpand.
 */
class PresensiTimWidget extends Component
{
    use LazyLoadsOnExpand;

    public string $mode = 'harian';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $activeModal = null;

    /** Key tile -> nilai kolom status attendance_records (harian & periode pakai status yang sama). */
    private const STATUS_BY_KEY = [
        'present' => 'present', 'present_days' => 'present',
        'late' => 'late', 'late_days' => 'late',
        'leave' => 'leave', 'leave_days' => 'leave',
        'permit' => 'permit', 'permit_days' => 'permit',
    ];

    public function setMode(string $mode): void
    {
        $this->mode = $mode;

        if ($mode === 'periode' && ! $this->dateFrom) {
            [$start, $end] = AttendancePeriodResolver::resolve(now(), Auth::user()->company_id);
            $this->dateFrom = $start->toDateString();
            $this->dateTo = $end->toDateString();
        }
    }

    private function subordinateIds()
    {
        return app(OrgHierarchyService::class)->subordinates(Auth::user())->pluck('id');
    }

    public function getTotalTeamProperty(): int
    {
        return $this->subordinateIds()->count();
    }

    /** Stat hari ini - dihitung dari status kolom attendance_records (bukan resolve jadwal per
     * user, terlalu berat) - sama pola agregat TodayAttendanceSummaryWidget punya Dashboard
     * Anda, cuma scope staff. */
    public function getTodayStatsProperty(): array
    {
        if ($this->mode !== 'harian') {
            return [];
        }

        $userIds = $this->subordinateIds();
        $total = $userIds->count();

        $counts = AttendanceRecords::whereIn('user_id', $userIds)
            ->where('date', now()->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recorded = (int) $counts->sum();

        return [
            'total' => $total,
            'present' => (int) ($counts[AttendanceStatus::PRESENT->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::LATE->value] ?? 0),
            'permit' => (int) ($counts[AttendanceStatus::PERMIT->value] ?? 0),
            'leave' => (int) ($counts[AttendanceStatus::LEAVE->value] ?? 0),
            'not_recorded' => max(0, $total - $recorded),
        ];
    }

    /** Stat 1 periode (rentang dateFrom-dateTo) - agregat, bukan per-baris per orang (widget
     * ringkas, bukan tabel rekap penuh - itu tetap tugasnya RekapPresensiPage). */
    public function getPeriodStatsProperty(): array
    {
        if ($this->mode !== 'periode' || ! $this->dateFrom || ! $this->dateTo) {
            return [];
        }

        $userIds = $this->subordinateIds();

        $records = AttendanceRecords::whereIn('user_id', $userIds)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo]);

        return [
            'present_days' => (clone $records)->where('status', AttendanceStatus::PRESENT->value)->count(),
            'late_days' => (clone $records)->where('status', AttendanceStatus::LATE->value)->count(),
            'leave_days' => (clone $records)->where('status', AttendanceStatus::LEAVE->value)->count(),
            'permit_days' => (clone $records)->where('status', AttendanceStatus::PERMIT->value)->count(),
            'total_late_minutes' => (int) (clone $records)->where('status', AttendanceStatus::LATE->value)->sum('late_minutes'),
        ];
    }

    public function openModal(string $key): void
    {
        $this->activeModal = $key;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
    }

    /** Judul modal = label tile yang diklik. */
    public function getModalTitleProperty(): string
    {
        return [
            'present' => 'Hadir', 'late' => 'Terlambat', 'leave' => 'Cuti', 'permit' => 'Izin',
            'not_recorded' => 'Belum Presensi',
            'present_days' => 'Hari Hadir', 'late_days' => 'Hari Telat', 'leave_days' => 'Hari Cuti',
            'permit_days' => 'Hari Izin', 'late_minutes' => 'Menit Telat',
        ][$this->activeModal] ?? '';
    }

    /** Warna keterangan di modal - palet sama dengan badge status RekapPresensiPage. */
    public function getModalStyleProperty(): array
    {
        return [
            'present' => ['#047C54', 'rgba(4,124,84,0.1)'],
            'late' => ['#b45309', 'rgba(180,83,9,0.1)'],
            'permit' => ['#1d4ed8', 'rgba(29,78,216,0.1)'],
            'leave' => ['#0f766e', 'rgba(15,118,110,0.1)'],
        ][str_replace('_days', '', $this->activeModal ?? '')] ?? ['#94a3b8', 'rgba(148,163,184,0.1)'];
    }

    /**
     * Daftar staff di balik angka tile yang diklik. Harian: status hari ini (not_recorded = tidak
     * punya record hari ini). Periode: staff yang punya >=1 hari status itu di rentang, 'detail'
     * = jumlah hari (atau total menit untuk late_minutes).
     *
     * @return \Illuminate\Support\Collection<int, array{user: \App\Models\User, detail: ?string}>
     */
    public function getModalEmployeesProperty()
    {
        if (! $this->activeModal) {
            return collect();
        }

        $ids = $this->subordinateIds();
        $users = fn ($filterIds) => \App\Models\User::whereIn('id', $filterIds)->with('profile.jobPosition')->orderByName()->get();

        if ($this->mode === 'harian') {
            $recorded = AttendanceRecords::whereIn('user_id', $ids)->where('date', now()->toDateString());

            $matched = $this->activeModal === 'not_recorded'
                ? $ids->diff((clone $recorded)->pluck('user_id'))
                : (clone $recorded)->where('status', self::STATUS_BY_KEY[$this->activeModal])->pluck('user_id');

            // Hadir: jam masuk. Terlambat: jam masuk + lama terlambat.
            $records = in_array($this->activeModal, ['present', 'late'], true)
                ? (clone $recorded)->whereIn('user_id', $matched)->get()->keyBy('user_id')
                : collect();

            return $users($matched)->map(function ($u) use ($records) {
                $r = $records[$u->id] ?? null;
                $in = $r?->check_in_time ? 'Masuk ' . substr($r->check_in_time, 0, 5) : null;
                $late = $r && $r->late_minutes > 0 ? 'terlambat ' . $this->formatMinutes((int) $r->late_minutes) : null;

                return ['user' => $u, 'detail' => implode(' · ', array_filter([$in, $late])) ?: null];
            });
        }

        $isMinutes = $this->activeModal === 'late_minutes';
        $rows = AttendanceRecords::whereIn('user_id', $ids)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->where('status', $isMinutes ? AttendanceStatus::LATE->value : self::STATUS_BY_KEY[$this->activeModal])
            ->selectRaw('user_id, count(*) as days, sum(late_minutes) as minutes')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $users($rows->keys())->map(fn ($u) => [
            'user' => $u,
            'detail' => $isMinutes
                ? $this->formatMinutes((int) $rows[$u->id]->minutes)
                : $rows[$u->id]->days . ' hari' . ($this->activeModal === 'late_days' ? ' · terlambat ' . $this->formatMinutes((int) $rows[$u->id]->minutes) : ''),
        ]);
    }

    private function formatMinutes(int $minutes): string
    {
        return $minutes >= 60
            ? intdiv($minutes, 60) . ' jam' . ($minutes % 60 ? ' ' . $minutes % 60 . ' menit' : '')
            : $minutes . ' menit';
    }

    /** Config Chart.js utuh (type/data/options) - dikirim ke blade sebagai JSON, diparse &
     * di-instantiate oleh initCharts() di resources/js/app.js. Pola sama
     * TodayAttendanceSummaryWidget punya Dashboard Anda (instruksi user 2026-09-18:
     * "diperbagus kaya dashboard hc manager"). */
    public function getChartConfigProperty(): array
    {
        if ($this->mode === 'harian') {
            $stats = $this->todayStats;
            $labels = ['Hadir', 'Terlambat', 'Cuti', 'Izin', 'Belum Presensi'];
            $data = [$stats['present'] ?? 0, $stats['late'] ?? 0, $stats['leave'] ?? 0, $stats['permit'] ?? 0, $stats['not_recorded'] ?? 0];
        } else {
            $stats = $this->periodStats;
            $labels = ['Hari Hadir', 'Hari Telat', 'Hari Cuti', 'Hari Izin'];
            $data = [$stats['present_days'] ?? 0, $stats['late_days'] ?? 0, $stats['leave_days'] ?? 0, $stats['permit_days'] ?? 0];
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $data,
                    'backgroundColor' => ['#047C54', '#d97706', '#1a56c4', '#a16207', '#94a3b8'],
                    'borderRadius' => 6,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => [
                    'x' => ['ticks' => ['font' => ['size' => 10]]],
                    'y' => ['beginAtZero' => true, 'ticks' => ['font' => ['size' => 10], 'precision' => 0]],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.my-staff.presensi-tim-widget');
    }
}
