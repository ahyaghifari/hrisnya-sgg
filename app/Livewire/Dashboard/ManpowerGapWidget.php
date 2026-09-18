<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Dashboard\Concerns\FiltersByOrgStructure;
use App\Models\Master\JobPositions;
use Livewire\Component;

/**
 * Widget "Manpower Planning" di Dashboard (Beranda) - gap aktual vs kuota. Default: diagregat
 * per Departemen. Pilih Departemen -> per Divisi di dalamnya. Pilih Divisi juga -> per Jabatan
 * di dalam divisi itu (instruksi user 2026-09-18) - beda dari tile "Jabatan Kurang Orang" di
 * KepegawaianDashboardPage yang per-jabatan individual company-wide tanpa drill-down,
 * dua-duanya boleh hidup berdampingan.
 *
 * Namespace App\Livewire\Dashboard - widget ini murni milik halaman Beranda/Dashboard Anda
 * (instruksi user 2026-09-18), bukan bagian modul Kepegawaian.
 *
 * Gate: sama seperti widget lain di dashboard ini, lihat
 * Dashboard::getCanDashboardHcManpowerProperty().
 */
class ManpowerGapWidget extends Component
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

    /** Label level yang lagi ditampilkan - dipakai judul & pesan kosong di blade. */
    public function getGapLevelLabelProperty(): string
    {
        if ($this->selectedDivisionId) {
            return 'Jabatan';
        }

        if ($this->selectedDepartmentId) {
            return 'Divisi';
        }

        return 'Departemen';
    }

    public function getGapRowsProperty()
    {
        $positions = JobPositions::query()
            ->where('company_id', $this->companyId)
            ->withCount('userAssignments')
            ->with('orgStructure.parent.parent')
            ->get();

        // Level 3: Divisi sudah dipilih -> breakdown per JABATAN di dalam divisi itu.
        if ($this->selectedDivisionId) {
            return $this->toRows(
                $positions
                    ->filter(fn (JobPositions $jp) => $this->divisionOf($jp->orgStructure)?->id === $this->selectedDivisionId)
                    ->groupBy('name')
            );
        }

        // Level 2: Departemen sudah dipilih -> breakdown per DIVISI di dalam departemen itu.
        if ($this->selectedDepartmentId) {
            return $this->toRows(
                $positions
                    ->filter(fn (JobPositions $jp) => $this->departmentOf($jp->orgStructure)?->id === $this->selectedDepartmentId)
                    ->groupBy(fn (JobPositions $jp) => $this->divisionOf($jp->orgStructure)?->name ?? 'Tanpa Divisi')
            );
        }

        // Level 1 (default): breakdown per DEPARTEMEN.
        return $this->toRows(
            $positions->groupBy(fn (JobPositions $jp) => $this->departmentOf($jp->orgStructure)?->name ?? '-')
        );
    }

    private function toRows($grouped)
    {
        return $grouped
            ->map(fn ($group, $name) => [
                'name' => $name,
                'actual' => $group->sum('user_assignments_count'),
                'quota' => (int) $group->sum('actual_manpower'),
            ])
            ->map(fn (array $row) => $row + ['gap' => $row['actual'] - $row['quota']])
            ->sortBy('gap')
            ->values();
    }

    /** Config Chart.js utuh (type/data/options) - dikirim ke blade sebagai JSON, diparse &
     * di-instantiate oleh initCharts() di resources/js/app.js. */
    public function getChartConfigProperty(): array
    {
        $rows = $this->gapRows;

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $rows->pluck('name')->all(),
                'datasets' => [
                    ['label' => 'Aktual', 'data' => $rows->pluck('actual')->all(), 'backgroundColor' => '#042c6c', 'borderRadius' => 5],
                    ['label' => 'Kebutuhan', 'data' => $rows->pluck('quota')->all(), 'backgroundColor' => '#c7d2e8', 'borderRadius' => 5],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 8, 'font' => ['size' => 10]]]],
                'scales' => [
                    'x' => ['ticks' => ['font' => ['size' => 10]]],
                    'y' => ['beginAtZero' => true, 'ticks' => ['font' => ['size' => 10], 'precision' => 0]],
                ],
            ],
        ];
    }

    /** Baris yang kekurangan tenaga kerja signifikan (gap negatif > 5 orang) - dipakai baris
     * catatan di bawah widget, pola sama planning/dashboard/dashboard-hc-manager.html. */
    public function getSignificantShortageCountProperty(): int
    {
        return $this->gapRows->filter(fn (array $row) => $row['gap'] < -5)->count();
    }

    public function render()
    {
        return view('livewire.dashboard.manpower-gap-widget');
    }
}
