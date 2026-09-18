<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Dashboard\Concerns\FiltersByOrgStructure;
use App\Models\Attendance\AttendanceRecords;
use App\Models\User;
use Carbon\CarbonPeriod;
use Livewire\Component;

/**
 * Widget "Tren Kehadiran 30 Hari" di Dashboard (Beranda) - persentase Hadir/Terlambat/Absen per
 * hari, 30 hari terakhir. Bisa di-scope ke Departemen, lanjut Divisi (instruksi user
 * 2026-09-18, pola filter sama dengan ManpowerGapWidget lewat FiltersByOrgStructure) - default
 * tanpa filter = seluruh company. Ditumpuk di bawah TodayAttendanceSummaryWidget (pola sama
 * planning/dashboard/dashboard-hc-manager.html).
 *
 * "Absen" di sini disederhanakan jadi (100% - Hadir%) - tidak memecah lagi ke Cuti/Izin/Alpa
 * terpisah kayak widget "Attendance Hari Ini" (itu presisi buat HARI INI, tren 30 hari cukup
 * granularitas kasar). ponytail: upgrade ke breakdown penuh kalau ada kebutuhan nyata.
 *
 * Query 1x agregat per tanggal (group by date), bukan loop per hari x per user - aman buat
 * company besar.
 *
 * Gate: sama seperti widget lain di dashboard ini, lihat
 * Dashboard::getCanKepegawaianWidgetsProperty().
 */
class AttendanceTrendWidget extends Component
{
    use FiltersByOrgStructure;

    public string $companyId;

    public function getSelectedDepartmentNameProperty(): ?string
    {
        return $this->selectedDepartmentId ? $this->departmentOptions->get($this->selectedDepartmentId) : null;
    }

    public function getSelectedDivisionNameProperty(): ?string
    {
        return $this->selectedDivisionId ? $this->divisionOptions->get($this->selectedDivisionId) : null;
    }

    /** Config Chart.js utuh (type/data/options) - dikirim ke blade sebagai JSON, diparse &
     * di-instantiate oleh initCharts() di resources/js/app.js. */
    public function getChartConfigProperty(): array
    {
        $jobPositionIds = $this->scopedJobPositionIds();

        $userQuery = User::query()->where('company_id', $this->companyId);
        if ($jobPositionIds !== null) {
            $userQuery->whereHas('profile', fn ($q) => $q->whereIn('job_position_id', $jobPositionIds));
        }
        $userIds = $userQuery->pluck('id');
        $totalUsers = $userIds->count();

        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $perDay = AttendanceRecords::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('date, count(*) as hadir, sum(case when late_minutes > 0 then 1 else 0 end) as terlambat')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date instanceof \Carbon\Carbon ? $row->date->toDateString() : (string) $row->date);

        $labels = [];
        $hadirSeries = [];
        $terlambatSeries = [];
        $absenSeries = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dateStr = $date->toDateString();
            $row = $perDay->get($dateStr);
            $hadir = $row->hadir ?? 0;
            $terlambat = $row->terlambat ?? 0;

            $labels[] = $date->format('d');
            $hadirSeries[] = $totalUsers > 0 ? round($hadir / $totalUsers * 100, 1) : 0;
            $terlambatSeries[] = $totalUsers > 0 ? round($terlambat / $totalUsers * 100, 1) : 0;
            $absenSeries[] = $totalUsers > 0 ? round(max($totalUsers - $hadir, 0) / $totalUsers * 100, 1) : 0;
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Hadir %', 'data' => $hadirSeries, 'borderColor' => '#047C54', 'backgroundColor' => 'rgba(4,124,84,0.08)', 'tension' => 0.35, 'fill' => true, 'pointRadius' => 0],
                    ['label' => 'Terlambat %', 'data' => $terlambatSeries, 'borderColor' => '#f59e0b', 'backgroundColor' => 'transparent', 'tension' => 0.35, 'pointRadius' => 0],
                    ['label' => 'Absen %', 'data' => $absenSeries, 'borderColor' => '#b91c1c', 'backgroundColor' => 'transparent', 'tension' => 0.35, 'pointRadius' => 0],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 8, 'font' => ['size' => 10]]]],
                'scales' => [
                    'y' => ['beginAtZero' => false, 'ticks' => ['font' => ['size' => 10]]],
                    'x' => ['ticks' => ['font' => ['size' => 10]]],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.attendance-trend-widget');
    }
}
