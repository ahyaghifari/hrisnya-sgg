<?php

namespace App\Filament\Resources\AttendanceRecords\Pages;

use App\Filament\Resources\AttendanceRecords\AttendanceRecordResource;
use App\Filament\Imports\AttendanceImporter;
use App\Helpers\PermissionHelper;
use App\Models\User;
use App\Services\Attendance\AttendanceImportService;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;

class AttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = AttendanceRecordResource::class;

    protected static ?string $title = 'Rekapan Absensi';

    protected static ?string $navigationLabel = 'Rekapan Absensi';

    public function getTitle(): string
    {
        $viewType = $this->getViewType();
        
        if ($viewType === 'records') {
            return 'Riwayat Absensi Saya';
        }
        
        return 'Rekapan Absensi';
    }

    public $fromDate;
    public $toDate;
    public $viewType;

    public function mount(): void
    {
        $this->fromDate = Carbon::now()->startOfMonth()->toDateString();
        $this->toDate = Carbon::now()->endOfMonth()->toDateString();
        $this->viewType = request()->get('type', 'report');
    }

    protected function getViewType(): string
    {
        return $this->viewType ?? 'report';
    }

    public function updatedTableFilters()
    {
        $this->resetTable();
    }

    protected function getViewData(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $viewType = $this->getViewType();
        
        // Hanya tampilkan import action untuk tipe report (admin)
        if ($viewType === 'report') {
            return [
                Action::make('sync_requests')
                    ->label('Sync Data')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->modalHeading('Sync Data Overtime, Cuti & Izin')
                    ->modalDescription('Pilih rentang tanggal untuk menyinkronkan data record absensi dengan request overtime, cuti, dan izin yang telah disetujui.')
                    ->modalSubmitActionLabel('Mulai Sync')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->default(Carbon::now()->startOfMonth())
                            ->required()
                            ->columns(1),
                        DatePicker::make('to')
                            ->label('Sampai Tanggal')
                            ->default(Carbon::now()->endOfMonth())
                            ->required()
                            ->columns(1),
                    ])
                    ->action(function (array $data) {
                        $syncService = app(AttendanceImportService::class);
                        $user = Auth::user();

                        $companyId = null;
                        if ($user && !PermissionHelper::hasGlobalRole($user)) {
                            $companyId = $user->company_id;
                        }

                        $result = $syncService->syncRequestsForRecords(
                            Carbon::parse($data['from'])->toDateString(),
                            Carbon::parse($data['to'])->toDateString(),
                            null,
                            $companyId
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
                    }),

                Action::make('import')
                    ->label('Import Data Absensi')
                    ->icon('heroicon-o-document')
                    ->color('primary')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('File Excel')
                            ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('local')
                            ->directory('uploads/attendance')
                            ->helperText('Unggah file Excel (.xlsx, .xls) atau CSV berisi data absensi. Maksimal ukuran file 10MB.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        $filePath = $data['file_path'];
                        $importService = app(\App\Services\Attendance\AttendanceImportService::class);
                        $upload = new \App\Models\Attendance\AttendanceUploads();
                        $upload->file_path = $filePath;
                        $upload->uploaded_by = Auth::id();
                        $upload->save();

                        $result = $importService->import($upload);

                        if (!empty($result)) {
                            Notification::make()
                                ->title('Import Selesai')
                                ->body("Proses import selesai. Berhasil: {$result['processed']}, Gagal: {$result['errors']}.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Import Gagal')
                                ->body("Terjadi kesalahan saat mengimpor data. Silakan coba lagi.")
                                ->danger()
                                ->send();
                        }
                    }),
            ];
        }
        
        // Untuk tipe records (karyawan melihat absensi sendiri), tampilkan sync only
        return [
            Action::make('sync_requests_employee')
                ->label('Sync Request')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->modalHeading('Sync Data Overtime, Cuti & Izin')
                ->modalDescription('Sinkronkan data absensi Anda dengan request overtime, cuti, dan izin yang telah disetujui.')
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
                        Auth::id()
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
                }),
        ];
    }

    public function getView(): string
    {
        return 'filament.resources.attendance-records.pages.attendance-report';
    }

    protected function getBaseQuery(): Builder
    {
        return $this->applyAttendanceMetrics(User::query(), $this->fromDate, $this->toDate);
    }

    protected function getTableQuery(): Builder
    {
        $dateRange = $this->resolveActiveDateRange();
        $fromDate = $dateRange['from'];
        $toDate = $dateRange['until'];

        $this->fromDate = $fromDate;
        $this->toDate = $toDate;

        return $this->applyAttendanceMetrics(User::query(), $fromDate, $toDate);
    }

    public function table(Table $table): Table
    {
        $dateRange = $this->resolveActiveDateRange();
        $fromDate = $dateRange['from'];
        $toDate = $dateRange['until'];

        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        
        return $table
            ->query($this->applyCompanyScope(User::query()))
            ->modifyQueryUsing(function (Builder $query) use ($fromDate, $toDate) {
                return $this->applyAttendanceMetrics($query, $fromDate, $toDate);
            })
            ->columns($this->getTableColumns())
            ->recordActions([
                EditAction::make('view_details')
                    ->hiddenLabel()
                    ->icon('heroicon-s-eye')
                ->url(fn ($record) => AttendanceRecordResource::getUrl('records', [
                    'user' => $record->id,
                    'from' => 'kepegawaian',
                    'from_date' => $this->fromDate,
                    'to_date' => $this->toDate,
                ]))
                ->openUrlInNewTab(true)
            ])
            ->recordUrl(fn ($record) => AttendanceRecordResource::getUrl('records', [
                'user' => $record->id,
                'from' => 'kepegawaian',
                'from_date' => $this->fromDate,
                'to_date' => $this->toDate,
            ]), true)
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $companyId) => $q->whereCompany($companyId),
                    ))
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),

                Filter::make('date_range')
                    ->label('Periode Absensi')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->default(Carbon::now()->startOfMonth())
                            ->live()
                            ->reactive()
                            ->required(),
                        DatePicker::make('until')
                            ->label('Sampai Tanggal')
                            ->default(Carbon::now()->endOfMonth())
                            ->live()
                            ->reactive()
                            ->required(),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from']) {
                            $indicators['from'] = 'Dari: ' . Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until']) {
                            $indicators['until'] = 'Sampai: ' . Carbon::parse($data['until'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->defaultSort(fn (Builder $query, string $direction) => $query->orderByName($direction));
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('Nama Karyawan')
                ->weight(FontWeight::Bold)
                ->description(fn ($record) => "NIP: {$record->nip}")
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->searchName($search);
                }, condition: $this->getViewType() === 'report')
                ->sortable(query: fn (Builder $query, string $direction) => $query->orderByName($direction)),

            TextColumn::make('total_days_in_period')
                ->label('Total Hari')
                ->alignCenter()
                ->numeric()
                ->sortable()
                ->suffix(' hari')
                ->tooltip('Total hari dalam periode'),

            TextColumn::make('present_count')
                ->label('Hadir')
                ->alignCenter()
                ->numeric()
                ->sortable()
                ->color('success')
                ->suffix(' hari')
                ->tooltip('Jumlah hari hadir'),

            TextColumn::make('late_count')
                ->label('Terlambat')
                ->alignCenter()
                ->numeric()
                ->sortable()
                ->color('danger')
                ->suffix(' kali')
                ->tooltip('Jumlah hari masuk terlambat'),

            TextColumn::make('total_late_minutes')
                ->label('Total Terlambat')
                ->alignCenter()
                ->numeric()
                ->sortable()
                ->color('danger')
                ->suffix(' mnt')
                ->tooltip('Total menit keterlambatan'),

            TextColumn::make('total_early_leave_minutes')
                ->label('Pulang Awal')
                ->alignCenter()
                ->numeric()
                ->sortable()
                ->color('warning')
                ->suffix(' mnt')
                ->tooltip('Total menit pulang lebih awal dari shift')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('absent_count')
                ->label('Tidak Hadir')
                ->alignCenter()
                ->numeric()
                ->sortable()
                ->suffix(' hari')
                ->color('danger')
                ->tooltip('Jumlah hari tidak hadir'),

            TextColumn::make('permission_count')
                ->label('Izin')
                ->alignCenter()
                ->numeric(decimalPlaces: 1)
                ->sortable()
                ->color('warning')
                ->suffix(' jam')
                ->tooltip('Total jam izin yang disetujui'),

            TextColumn::make('leave_count')
                ->label('Cuti')
                ->alignCenter()
                ->numeric()
                ->sortable()
                ->color('warning')
                ->suffix(' hari')
                ->tooltip('Total hari cuti yang disetujui'),

            TextColumn::make('overtime_approved_hours')
                ->label('Lembur (Disetujui)')
                ->alignCenter()
                ->numeric(decimalPlaces: 1)
                ->sortable()
                ->color('primary')
                ->suffix(' jam')
                ->tooltip('Total jam lembur dari pengajuan yang disetujui'),

            TextColumn::make('total_overtime_minutes')
                ->label('Lembur (Aktual)')
                ->alignCenter()
                ->formatStateUsing(fn ($state) => $state > 0 ? round($state / 60, 1) . ' jam' : '0 jam')
                ->sortable()
                ->color('primary')
                ->tooltip('Total lembur aktual dari data absensi (melewati jam selesai shift)')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('total_working_minutes')
                ->label('Total Jam Kerja')
                ->alignCenter()
                ->formatStateUsing(fn ($state) => $state > 0 ? round($state / 60, 1) . ' jam' : '0 jam')
                ->sortable()
                ->color('success')
                ->tooltip('Total jam kerja aktual dari data absensi'),

            TextColumn::make('avg_working_hours')
                ->label('Rata-rata Jam Kerja')
                ->alignCenter()
                ->numeric(decimalPlaces: 1)
                ->sortable()
                ->suffix(' jam'),
        ];
    }

    protected function resolveActiveDateRange(): array
    {
        $state = $this->getTableFilterState('date_range') ?? [];

        $from = $state['from'] ?? $this->fromDate;
        $until = $state['until'] ?? $this->toDate;

        $fromDate = $from ? Carbon::parse($from)->toDateString() : $this->fromDate;
        $toDate = $until ? Carbon::parse($until)->toDateString() : $this->toDate;

        return [
            'from' => $fromDate,
            'until' => $toDate,
        ];
    }

    protected function applyAttendanceMetrics(Builder $query, string $fromDate, string $toDate): Builder
    {
        $query = $this->applyCompanyScope($query);

        // Calculate total days in period
        $start = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        $totalDaysInPeriod = $start->diffInDays($end) + 1;

        return $query
            ->select([
                'users.id',
                'emp.nama_lengkap as name',
                'emp.nip as nip',
                DB::raw("{$totalDaysInPeriod} as total_days_in_period"),
                DB::raw('COUNT(DISTINCT attendance_records.date) as present_count'),
                DB::raw('(' . $totalDaysInPeriod . ' - COUNT(DISTINCT attendance_records.date)) as absent_count'),
                DB::raw('ROUND(AVG(attendance_records.working_minutes) / 60, 1) as avg_working_hours'),
                DB::raw('COALESCE(SUM(attendance_records.working_minutes), 0) as total_working_minutes'),

                // Late: number of days with status = 'late'
                DB::raw('COUNT(CASE WHEN attendance_records.status = "late" THEN 1 END) as late_count'),

                // Total late minutes from attendance records
                DB::raw('COALESCE(SUM(attendance_records.late_minutes), 0) as total_late_minutes'),

                // Total early-leave minutes from attendance records
                DB::raw('COALESCE(SUM(attendance_records.early_leave_minutes), 0) as total_early_leave_minutes'),

                // Total overtime minutes from attendance records (actual)
                DB::raw('COALESCE(SUM(attendance_records.overtime_minutes), 0) as total_overtime_minutes'),

                // Permission count - calculated using subquery to avoid duplication
                DB::raw('COALESCE((
                    SELECT ROUND(SUM(
                        TIMESTAMPDIFF(MINUTE,
                            CONCAT(pd.date, " ", pd.start_time),
                            CONCAT(pd.date, " ", pd.end_time)
                        ) / 60.0
                    ), 1)
                    FROM attendance_requests ar
                    INNER JOIN attendance_req_permission_details pd ON ar.id = pd.request_id
                    WHERE ar.user_id = users.id
                    AND ar.type = "permission"
                    AND ar.status = "approved"
                    AND pd.date BETWEEN "' . $fromDate . '" AND "' . $toDate . '"
                ), 0) as permission_count'),

                // Leave count - calculated using subquery
                DB::raw('COALESCE((
                    SELECT SUM(
                        DATEDIFF(
                            LEAST(ld.end_date, "' . $toDate . '"),
                            GREATEST(ld.start_date, "' . $fromDate . '")
                        ) + 1
                    )
                    FROM attendance_requests ar
                    INNER JOIN attendance_req_leave_details ld ON ar.id = ld.request_id
                    WHERE ar.user_id = users.id
                    AND ar.type = "leave"
                    AND ar.status = "approved"
                    AND ld.start_date <= "' . $toDate . '"
                    AND ld.end_date >= "' . $fromDate . '"
                ), 0) as leave_count'),

                // Overtime approved hours - from approved overtime requests
                DB::raw('COALESCE((
                    SELECT ROUND(SUM(od.total_hours), 1)
                    FROM attendance_requests ar
                    INNER JOIN attendance_req_overtime_details od ON ar.id = od.request_id
                    WHERE ar.user_id = users.id
                    AND ar.type = "overtime"
                    AND ar.status = "approved"
                    AND od.date BETWEEN "' . $fromDate . '" AND "' . $toDate . '"
                ), 0) as overtime_approved_hours'),
            ])
            ->leftJoin('employees as emp', 'emp.user_id', '=', 'users.id')
            ->leftJoin('attendance_records', function ($join) use ($fromDate, $toDate) {
                $join->on('users.id', '=', 'attendance_records.user_id')
                    ->whereBetween('attendance_records.date', [$fromDate, $toDate]);
            })
            ->groupBy('users.id', 'emp.nama_lengkap', 'emp.nip')
            ->orderBy('emp.nama_lengkap');
    }

    protected function applyCompanyScope(Builder $query): Builder
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $viewType = $this->getViewType();

        // Jika type = records, hanya tampilkan data user yang sedang login
        if ($viewType === 'records') {
            if ($user) {
                return $query->where('users.id', $user->id);
            }
            // Jika tidak ada user yang login, return empty query
            return $query->whereRaw('1 = 0');
        }

        // Jika type = report, tampilkan sesuai company scope seperti sebelumnya
        if (! $user || $user->hasRole(['Admin', 'Super Admin'])) {
            return $query;
        }

        return $query->whereCompany($user->company_id);
    }
}
