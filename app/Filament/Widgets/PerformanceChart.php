<?php

namespace App\Filament\Widgets;

use App\Enum\Task\TaskStatus;
use App\Models\Task\Task;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PerformanceChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Statistik Kinerja Bulanan';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '350px';

    protected function getData(): array
    {
        $currentYear = now()->year;
        $userId = Auth::id();

        // Get completed tasks count grouped by month
        $tasksPerMonth = Task::query()
            ->select(
                DB::raw('MONTH(completed_date) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('assigned_to', $userId)
            ->where('status', TaskStatus::COMPLETE->value)
            ->whereYear('completed_date', $currentYear)
            ->whereNotNull('completed_date')
            ->groupBy(DB::raw('MONTH(completed_date)'))
            ->pluck('count', 'month')
            ->toArray();

        // Prepare data for all 12 months
        $data = [];
        for ($month = 1; $month <= 12; $month++) {
            $data[] = $tasksPerMonth[$month] ?? 0;
        }

        $labels = [
            'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Task Selesai',
                    'data' => $data,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.2)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'min' => 0,
                    'stepSize' => 1,
                ],
            ],
        ];
    }
}
