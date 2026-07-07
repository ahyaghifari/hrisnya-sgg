<?php

namespace App\Filament\Widgets;

use App\Models\Shift\ShiftScheduleAssignments;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\DatesSetInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ShiftCalendarWidget extends CalendarWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.clusters.shift-user.widgets.shift-calendar-widget';

    protected CalendarViewType $calendarView = CalendarViewType::DayGridMonth;

    protected bool $datesSetEnabled = true;

    protected ?string $locale = 'id';

    protected string | HtmlString | null | bool $heading = 'Kalender Jadwal Shift';

    /** Stored calendar date range, updated on every prev/next navigation */
    public ?string $calendarStart = null;

    public ?string $calendarEnd = null;

    protected function onDatesSet(DatesSetInfo $info): void
    {
        $this->calendarStart = $info->start->toDateString();
        $this->calendarEnd   = $info->end->toDateString();

        $this->refreshRecords();
    }

    public function getEvents(FetchInfo $info): Collection|array
    {
        // Use dates stored from onDatesSet (explicit startStr/endStr from EventCalendar).
        // Fallback to a wide window around today for the very first render
        // (before datesSet has fired to populate calendarStart/calendarEnd).
        $start = $this->calendarStart ?? Carbon::now()->startOfMonth()->subWeeks(2)->toDateString();
        $end   = $this->calendarEnd   ?? Carbon::now()->endOfMonth()->addWeeks(2)->toDateString();

        return ShiftScheduleAssignments::query()
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$start, $end])
            ->with('shift')
            ->get()
            ->map(function ($assignment) {
                $shift    = $assignment->shift;
                $color    = $shift?->color ?? '#042c6c';
                $date     = Carbon::parse($assignment->date);

                $startTime = $shift?->start_time
                    ? Carbon::parse($shift->start_time)->format('H:i')
                    : null;
                $endTime = $shift?->end_time
                    ? Carbon::parse($shift->end_time)->format('H:i')
                    : null;

                $title = new HtmlString(
                    collect([
                        $shift?->code ? '<span style="font-weight:700">' . e($shift->code) . '</span>' : null,
                        $shift?->name ? '<span>' . e($shift->name) . '</span>' : null,
                        ($startTime && $endTime) ? '<span style="opacity:.85">' . e($startTime) . '–' . e($endTime) . '</span>' : null,
                    ])
                    ->filter()
                    ->implode('<br>')
                );

                return CalendarEvent::make()
                    ->title($title)
                    ->start($date->copy()->startOfDay())
                    ->end($date->copy()->endOfDay())
                    ->allDay(true)
                    ->backgroundColor($color)
                    ->textColor('#ffffff')
                    ->extendedProps([
                        'code'       => $shift?->code,
                        'shift_name' => $shift?->name,
                        'start_time' => $startTime,
                        'end_time'   => $endTime,
                        'notes'      => $assignment->notes,
                    ]);
            });
    }

    public function getOptions(): array
    {
        return [
            'editable'         => false,
            'selectable'       => false,
            'dayMaxEvents'     => 3,
            'headerToolbar'    => [
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => 'dayGridMonth,listMonth',
            ],
            'buttonText'       => [
                'today'   => 'Hari ini',
                'month'   => 'Bulan',
                'list'    => 'Daftar',
            ],
        ];
    }
}


