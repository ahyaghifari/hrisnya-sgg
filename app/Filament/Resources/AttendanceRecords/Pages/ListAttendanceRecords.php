<?php

namespace App\Filament\Resources\AttendanceRecords\Pages;

use App\Filament\Resources\AttendanceRecords\AttendanceRecordResource;
use App\Models\Attendance\AttendanceRecords;
use App\Models\User;
use App\Services\Attendance\AttendanceImportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListAttendanceRecords extends ListRecords
{
    protected static string $resource = AttendanceRecordResource::class;

    protected static ?string $title = 'Detail Absensi Karyawan';
    
    public $selectedUserId;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_requests')
                ->label('Sync Data')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn () => (bool) $this->selectedUserId)
                ->modalHeading('Sync Data Overtime, Cuti & Izin')
                ->modalDescription('Sinkronkan record absensi karyawan ini dengan data request overtime, cuti, dan izin yang telah disetujui.')
                ->modalSubmitActionLabel('Mulai Sync')
                ->schema([
                    DatePicker::make('from')
                        ->label('Dari Tanggal')
                        ->default(Carbon::now()->startOfMonth())
                        ->required(),
                    DatePicker::make('to')
                        ->label('Sampai Tanggal')
                        ->default(Carbon::now()->endOfMonth())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $syncService = app(AttendanceImportService::class);

                    $result = $syncService->syncRequestsForRecords(
                        Carbon::parse($data['from'])->toDateString(),
                        Carbon::parse($data['to'])->toDateString(),
                        $this->selectedUserId
                    );

                    if ($result['success']) {
                        Notification::make()
                            ->title('Sync Selesai')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Sync Gagal')
                            ->body($result['message'])
                            ->danger()
                            ->send();
                    }

                    $this->resetTable();
                }),
        ];
    }

    public function mount(): void
    {
        parent::mount();
        
        // Store selected user ID from URL parameter
        $this->selectedUserId = request()->get('user');
        
        if ($this->selectedUserId) {
            // Set initial filter to show only selected user
            $this->tableFilters['user_filter']['value'] = $this->selectedUserId;
        }

        // Carry over date range from attendance report if provided
        $fromDate = request()->get('from_date');
        $toDate = request()->get('to_date');

        if ($fromDate || $toDate) {
            $this->tableFilters['date_range']['from'] = $fromDate ?? Carbon::now()->startOfMonth()->toDateString();
            $this->tableFilters['date_range']['until'] = $toDate ?? Carbon::now()->endOfMonth()->toDateString();
        }
    }

    protected function getTableQuery(): Builder
    {
        // Get date range from filters
        $dateRange = $this->getTableFilterState('date_range') ?? [];
        $fromDate = $dateRange['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $toDate = $dateRange['until'] ?? Carbon::now()->endOfMonth()->toDateString();
        
        // Get all dates in range
        $period = CarbonPeriod::create($fromDate, $toDate);
        $allDates = [];
        foreach ($period as $date) {
            $allDates[] = $date->format('Y-m-d');
        }
        
        // Get existing records
        $query = static::getResource()::getEloquentQuery();
        
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }
        
        $existingRecords = $query->whereBetween('date', [$fromDate, $toDate])
            ->get()
            ->keyBy('date');
        
        // Create placeholder records for missing dates
        $allRecords = collect();
        
        foreach ($allDates as $date) {
            if (isset($existingRecords[$date])) {
                $allRecords->push($existingRecords[$date]);
            } else {
                // Create a placeholder record for dates without data
                $placeholder = new AttendanceRecords();
                $placeholder->id = 'placeholder_' . $date; // Unique ID for placeholder
                $placeholder->date = $date;
                $placeholder->user_id = $this->selectedUserId;
                $placeholder->nip = User::find($this->selectedUserId)?->nip ?? '';
                $placeholder->check_in_time = null;
                $placeholder->check_out_time = null;
                $placeholder->working_minutes = null;
                $placeholder->status = null;
                $placeholder->exists = false; // Mark as non-existent record
                
                $allRecords->push($placeholder);
            }
        }
        
        // Store in session to be retrieved later
        session(['attendance_all_records' => $allRecords]);
        
        // Return empty query builder since we'll use custom pagination
        return $query->whereRaw('1 = 0');
    }

    protected function paginateTableQuery(Builder $query): \Illuminate\Contracts\Pagination\Paginator
    {
        $records = session('attendance_all_records', collect());
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $records->sortBy('date')->values(),
            $records->count(),
            $records->count(), // Show all records, no pagination
            1,
            ['path' => request()->url()]
        );
    }

    protected function applyFiltersToTableQuery(Builder $query): Builder
    {
        // Override to prevent standard filter application
        // since we handle date range manually in getTableQuery
        return $query;
    }

    public function getTitle(): string
    {
        if ($this->selectedUserId) {
            $user = User::find($this->selectedUserId);
            return $user ? "Detail Absensi - {$user->name}" : 'Detail Absensi';
        }
        
        return 'Data Absensi';
    }

    public function getTableRecordKey($record): string
    {
        return (string) ($record->exists ? $record->getKey() : $record->id);
    }

    protected function resolveTableRecord(?string $key): ?\Illuminate\Database\Eloquent\Model
    {
        if ($key === null) {
            return null;
        }

        // Look up from session collection first (handles both real and placeholder records)
        $records = session('attendance_all_records', collect());
        $record = $records->first(fn ($r) => (string) ($r->exists ? $r->getKey() : $r->id) === $key);

        if ($record) {
            return $record;
        }

        // Fallback: resolve real records directly from the database
        return AttendanceRecords::find($key);
    }
}
