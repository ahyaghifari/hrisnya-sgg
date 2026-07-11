<?php

namespace App\Filament\Resources\ScheduleShifts\Pages;

use App\Filament\Resources\ScheduleShifts\ScheduleShiftResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

class ListScheduleShifts extends ListRecords
{
    protected static string $resource = ScheduleShiftResource::class;

    protected static ?string $title = 'Jadwal Shift';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Jadwal Shift')->visible(fn () => ScheduleShiftResource::canCreate()),
        ];
    }

    /**
     * Override the base query to pivot the view: one row = one employee.
     * Only employees who have shift assignments in the selected period are shown.
     * The actual per-day shift data is resolved lazily in ScheduleShiftsTable column closures.
     */
    protected function getTableQuery(): Builder | Relation | null
    {
        $filters = $this->tableFilters['period'] ?? [];
        $month   = (int) ($filters['month'] ?? now()->month);
        $year    = (int) ($filters['year'] ?? now()->year);

        $orgIds = $this->tableFilters['divisi']['values'] ?? [];

        $query = User::query()
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('shiftScheduleAssignments', fn(Builder $q) =>
                $q->whereMonth('date', $month)->whereYear('date', $year)
            )
            ->orderBy('name');

        if (!empty($orgIds)) {
            $query->whereHas('profile', fn(Builder $q) =>
                $q->whereIn('org_structure_id', $orgIds)
            );
        }

        return $query;
    }
}
