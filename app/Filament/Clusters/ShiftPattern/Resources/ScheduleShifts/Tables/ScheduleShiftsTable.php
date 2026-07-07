<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\Tables;

use App\Enum\OrgStructurLevel;
use App\Filament\Clusters\ShiftPattern\Resources\ScheduleShifts\ScheduleShiftResource;
use App\Helpers\PermissionHelper;
use App\Models\Master\OrgStructure;
use App\Models\Shift\MasterShift;
use App\Models\Shift\ShiftScheduleAssignments;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ScheduleShiftsTable
{
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();

        // Shared cache: populated once per employee per request (keyed by user id)
        $cache = [];

        /**
         * Load all shift assignments for the given employee in current month/year.
         * Uses $cache by reference to avoid repeated DB queries within the same render.
         */
        $loadAssignments = function (int $month, int $year, string $uid) use (&$cache): Collection {
            if (!array_key_exists($uid, $cache)) {
                $cache[$uid] = ShiftScheduleAssignments::query()
                    ->with('shift:id,code,name,color,start_time,end_time')
                    ->where('user_id', $uid)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->get()
                    ->keyBy(fn($assignment) => Carbon::parse($assignment->date)->day);
            }

            return $cache[$uid];
        };

        // Build day columns 1–31; days 29–31 use visible() to respect month length
        $dayColumns = [];

        for ($day = 1; $day <= 31; $day++) {
            $d = $day; // capture by value for closure

            $dayColumns[] = TextColumn::make("shift_day_{$d}")
                ->label((string) $d)
                ->visible(function () use ($livewire, $d): bool {
                    $filters = $livewire->tableFilters['period'] ?? [];
                    $month   = (int) ($filters['month'] ?? now()->month);
                    $year    = (int) ($filters['year'] ?? now()->year);

                    return $d <= Carbon::create($year, $month, 1)->daysInMonth;
                })
                ->getStateUsing(function (Model $record) use ($livewire, $d, $loadAssignments): string {
                    $filters = $livewire->tableFilters['period'] ?? [];
                    $month   = (int) ($filters['month'] ?? now()->month);
                    $year    = (int) ($filters['year'] ?? now()->year);

                    $assignments = $loadAssignments($month, $year, (string) $record->getKey());

                    // Always return a non-null value so the cell is always clickable
                    return $assignments->get($d)?->shift?->code ?? 'L';
                })
                ->color(function (Model $record) use ($livewire, $d, $loadAssignments): string {
                    $filters = $livewire->tableFilters['period'] ?? [];
                    $month   = (int) ($filters['month'] ?? now()->month);
                    $year    = (int) ($filters['year'] ?? now()->year);

                    $assignments = $loadAssignments($month, $year, (string) $record->getKey());
                    $shift       = $assignments->get($d)?->shift;

                    // Gray for empty cells, shift color (or primary) for assigned ones
                    return $shift ? ($shift->color ?? 'primary') : 'danger';
                })
                ->tooltip(function (Model $record) use ($livewire, $d, $loadAssignments): ?string {
                    $filters = $livewire->tableFilters['period'] ?? [];
                    $month   = (int) ($filters['month'] ?? now()->month);
                    $year    = (int) ($filters['year'] ?? now()->year);

                    $assignments = $loadAssignments($month, $year, (string) $record->getKey());
                    $assignment  = $assignments->get($d);

                    if ($assignment?->shift) {
                        return "{$assignment->shift->name} (" . Carbon::parse($assignment->shift->start_time)->format('H:i') . " – " . Carbon::parse($assignment->shift->end_time)->format('H:i') . ")";
                    }

                    return null;
                })
                ->badge()
                ->size(TextSize::ExtraSmall)
                ->alignCenter()
                ->wrapHeader()
                ->action(
                    Action::make("assign_day_{$d}")
                        ->modalHeading(function (Model $record) use ($d, $livewire): string {
                            $filters = $livewire->tableFilters['period'] ?? [];
                            $month   = (int) ($filters['month'] ?? now()->month);
                            $year    = (int) ($filters['year'] ?? now()->year);
                            $date    = Carbon::create($year, $month, $d)->translatedFormat('l, d F Y');

                            return "Jadwal: {$record->name} – {$date}";
                        })
                        ->modalSubmitActionLabel('Simpan Jadwal')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->fillForm(function (Model $record) use ($d, $livewire): array {
                            $filters = $livewire->tableFilters['period'] ?? [];
                            $month   = (int) ($filters['month'] ?? now()->month);
                            $year    = (int) ($filters['year'] ?? now()->year);
                            $date    = Carbon::create($year, $month, $d)->format('Y-m-d');

                            $assignment = ShiftScheduleAssignments::where('user_id', $record->getKey())
                                ->where('date', $date)
                                ->first();

                            return [
                                'shift_id' => $assignment?->shift_id,
                                'notes'    => $assignment?->notes,
                            ];
                        })
                        ->disabled(fn ($record) => !ScheduleShiftResource::canEdit($record))
                        ->schema([
                            Select::make('shift_id')
                                ->label('Shift')
                                ->options(fn () => MasterShift::where('company_id', Auth::user()->company_id)
                                    ->get()
                                    ->mapWithKeys(fn ($s) => [
                                        $s->id => "{$s->code}  ·  {$s->name}  ({$s->start_time} – {$s->end_time})",
                                    ])
                                    ->toArray()
                                )
                                ->required()
                                ->searchable()
                                ->native(false),

                            Textarea::make('notes')
                                ->label('Catatan')
                                ->rows(2)
                                ->nullable(),
                        ])
                        ->action(function (array $data, Model $record) use ($d, $livewire): void {
                            $filters = $livewire->tableFilters['period'] ?? [];
                            $month   = (int) ($filters['month'] ?? now()->month);
                            $year    = (int) ($filters['year'] ?? now()->year);
                            $date    = Carbon::create($year, $month, $d)->format('Y-m-d');

                            ShiftScheduleAssignments::updateOrCreate(
                                ['user_id' => $record->getKey(), 'date' => $date],
                                [
                                    'shift_id'   => $data['shift_id'],
                                    'notes'      => $data['notes'] ?? null,
                                    'company_id' => Auth::user()->company_id,
                                    'created_by' => Auth::id(),
                                ]
                            );

                            Notification::make()
                                ->success()
                                ->title('Jadwal disimpan')
                                ->send();
                        })
                        ->extraModalFooterActions([
                            Action::make("delete_day_{$d}")
                                ->label('Hapus Jadwal')
                                ->color('danger')
                                ->icon(Heroicon::OutlinedTrash)
                                ->requiresConfirmation()
                                ->modalHeading('Hapus Jadwal Ini?')
                                ->modalDescription('Assignment shift pada hari ini untuk karyawan ini akan dihapus permanen.')
                                ->visible(fn ($record) => ScheduleShiftResource::canDelete($record))
                                ->action(function (Model $record) use ($d, $livewire): void {
                                    $filters = $livewire->tableFilters['period'] ?? [];
                                    $month   = (int) ($filters['month'] ?? now()->month);
                                    $year    = (int) ($filters['year'] ?? now()->year);
                                    $date    = Carbon::create($year, $month, $d)->format('Y-m-d');

                                    $deleted = ShiftScheduleAssignments::where('user_id', $record->getKey())
                                        ->where('date', $date)
                                        ->delete();

                                    Notification::make()
                                        ->title($deleted ? 'Jadwal dihapus' : 'Tidak ada jadwal untuk dihapus')
                                        ->{$deleted ? 'success' : 'warning'}()
                                        ->send();
                                })
                                ->closeModalByClickingAway(false),
                        ])
                );
        }

        // Summary column: count each shift type in the selected month for each employee
        $summaryColumn = TextColumn::make('shift_summary')
            ->label('Rekap Shift')
            ->getStateUsing(function (Model $record) use ($livewire, $loadAssignments): array {
                $filters = $livewire->tableFilters['period'] ?? [];
                $month   = (int) ($filters['month'] ?? now()->month);
                $year    = (int) ($filters['year'] ?? now()->year);

                $assignments = $loadAssignments($month, $year, (string) $record->getKey());

                return $assignments
                    ->filter(fn ($a) => $a->shift !== null)
                    ->groupBy(fn ($a) => $a->shift->name)
                    ->map(fn ($group, $name) => "{$name}: {$group->count()}x")
                    ->values()
                    ->toArray();
            })
            ->listWithLineBreaks()
            ->size(TextSize::ExtraSmall)
            ->wrap()
            ->alignStart();

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->description(fn(Model $record) => "NIP : $record->nip" ?? 'Tanpa NIP')
                    ->searchable()
                    ->wrap(),
                ...$dayColumns,
                $summaryColumn,
            ])
            ->defaultGroup(
                Group::make('profile.orgStructure.name')->label('Unit')
            )
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
                Filter::make('period')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('month')
                            ->label('Bulan')
                            ->options(
                                collect(range(1, 12))
                                    ->mapWithKeys(fn($m) => [$m => Carbon::create(null, $m)->translatedFormat('F')])
                                    ->toArray()
                            )
                            ->default(now()->month)
                            ->required()
                            ->native(false),
                        Select::make('year')
                            ->label('Tahun')
                            ->options(
                                collect(range(now()->year - 1, now()->year + 2))
                                    ->mapWithKeys(fn($y) => [$y => (string) $y])
                                    ->toArray()
                            )
                            ->default(now()->year)
                            ->required()
                            ->native(false),
                    ])
                    // Real filtering happens in ListScheduleShifts::getTableQuery()
                    ->query(fn($query) => $query)
                    ->indicateUsing(function (array $data): ?string {
                        $month = $data['month'] ?? now()->month;
                        $year  = $data['year'] ?? now()->year;

                        return Carbon::create($year, $month, 1)->translatedFormat('F Y');
                    }),

                SelectFilter::make('divisi')
                    ->label('Divisi')
                    ->options(function () {
                        return OrgStructure::where('company_id', Auth::user()->company_id)
                            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($o) => [$o->id => $o->name])
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple()
                    ->native(false)
                    // Real filtering happens in ListScheduleShifts::getTableQuery()
                    ->query(fn($query) => $query),
            ])
            ->recordActions([
                // 
            ])
            ->toolbarActions([])
            ->paginated([25, 50, 100])
            ->striped();
    }
}

