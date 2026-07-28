<?php

namespace App\Models\Task;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTaskReport
 * @property string $id
 * @property string $company_id
 * @property string $user_id
 * @property int $week_number
 * @property int $year
 * @property string $week_start
 * @property string $week_end
 * @property string|null $quote
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\Task> $completedTasks
 * @property-read int|null $completed_tasks_count
 * @property-read array $chart_data
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\Task> $highRiskTasks
 * @property-read int|null $high_risk_tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task\Task> $nextWeekTasks
 * @property-read int|null $next_week_tasks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereQuote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereWeekEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereWeekNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereWeekStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskReport whereYear($value)
 * @mixin \Eloquent
 */
class TaskReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'user_id',
        'week_number',
        'year',
        'week_start',
        'week_end',
        'quote',
    ];

    public function completedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to', 'user_id')
            ->whereBetween('completed_date', [$this->week_start, $this->week_end])
            ->whereNotNull('completed_date')
            ->orderBy('completed_date', 'asc');
    }

    public function highRiskTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to', 'user_id')
            ->whereBetween('completed_date', [$this->week_start, $this->week_end])
            ->where(function ($q) {
                $q->where('risk_level', 'high')
                    ->orWhere('is_overdue', true);
            })
            ->orderBy('completed_date', 'asc');
    }

    public function nextWeekTasks(): HasMany
    {
        $nextWeekStart = Carbon::parse($this->week_end)->addDay()->startOfWeek();
        $nextWeekEnd = $nextWeekStart->copy()->endOfWeek();

        return $this->hasMany(Task::class, 'assigned_to', 'user_id')
            ->whereBetween('due_date', [$nextWeekStart, $nextWeekEnd])
            ->orderBy('due_date', 'asc');
    }

    public function getChartDataAttribute(): array
    {
        $chart = $this->completedTasks()
            ->reorder()
            ->selectRaw("
            DATE_FORMAT(completed_date, '%a') as day,
            COUNT(*) as count,
            MIN(completed_date) as min_date
        ")
            ->groupBy('day')
            ->orderBy('min_date')
            ->get()
            ->pluck('count', 'day');

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        return collect($days)
            ->map(fn($day) => [
                'day' => $day,
                'count' => (int) ($chart[$day] ?? 0),
            ])
            ->values()
            ->toArray();
    }
}
