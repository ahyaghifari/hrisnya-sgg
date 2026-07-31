<?php

namespace App\Filament\Resources\AttendanceRequests\Schemas;

use App\Enum\AttendanceReqStatus;
use App\Services\Attendance\AttendaceReqService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class AttendanceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                $schema->getLivewire() instanceof CreateRecord
                    ? self::createSchema()
                    : self::editSchema()
            )
            ->columns(2);
    }

    public static function createSchema(): array
    {
        return [
            Hidden::make('type')
                ->default(fn () => request()->query('type')),
            
            Hidden::make('user_id')
                ->default(Auth::id()),

            Hidden::make('status')
                ->default(AttendanceReqStatus::SUBMITTED->value),

            ...self::leaveFormSchema(),
            ...self::permissionFormSchema(),
            ...self::overtimeFormSchema(),
        ];
    }

    public static function leaveFormSchema(): array
    {
        return [
            Section::make('Form Pengajuan Cuti')
                ->visible(fn (callable $get) => $get('type') === 'leave')
                ->columnSpanFull()
                ->icon(Heroicon::OutlinedCalendarDays)
                ->columns(2)
                ->schema([
                    Select::make('leave_type_id')
                        ->label('Jenis Cuti')
                        ->options(fn () => \App\Models\Master\Attendance\LeaveTypes::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Toggle::make('is_one_day')
                        ->label('Cuti 1 Hari')
                        ->default(true)
                        ->reactive(),

                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->required()
                        ->minDate(now()->subDay()),

                    DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->required(fn (callable $get) => ! $get('is_one_day'))
                        ->disabled(fn (callable $get) => $get('is_one_day'))
                        ->minDate(fn (callable $get) => $get('start_date')),

                    Textarea::make('reason')
                        ->label('Keterangan')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function overtimeFormSchema(): array
    {
        return [
            Section::make('Form Pengajuan Lembur')
                ->visible(fn (callable $get) => $get('type') === 'overtime')
                ->columnSpanFull()
                ->icon(Heroicon::OutlinedClock)
                ->columns(2)
                ->schema([
                    Select::make('overtime_type_id')
                        ->label('Jenis Lembur')
                        ->options(fn () => \App\Models\Master\Attendance\OvertimeTypes::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    DatePicker::make('date')
                        ->label('Tanggal Lembur')
                        ->required()
                        ->minDate(now()->subDay()),

                    Repeater::make('tasks')
                        ->label('Rincian Tugas')
                        ->columnSpanFull()
                        ->columns(2)
                        ->addActionLabel('Tambah Tugas')
                        ->minItems(1)
                        ->schema([
                            Textarea::make('task_description')
                                ->label('Deskripsi Tugas')
                                ->rows(2)
                                ->columnSpanFull(),

                            TimePicker::make('start_time')
                                ->label('Waktu Mulai'),

                            TimePicker::make('end_time')
                                ->label('Waktu Selesai'),
                        ]),

                    FileUpload::make('attachments')
                        ->label('Lampiran')
                        ->columnSpanFull()
                        ->multiple()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)
                        ->helperText('PDF/JPG/PNG, maksimal 10MB per file.'),
                ]),
        ];
    }

    public static function permissionFormSchema(): array
    {
        return [
            Section::make('Form Pengajuan Izin')
                ->visible(fn (callable $get) => $get('type') === 'permission')
                ->columnSpanFull()
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->columns(2)
                ->schema([
                    Fieldset::make('Detail Izin')
                        ->columns(1)
                        ->schema([
                            Select::make('permission_type_id')
                                ->label('Tipe Izin')
                                ->options(function () {
                                    return \App\Models\Master\Attendance\PermissionTypes::pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->required()
                                ->afterStateUpdated(fn ($state, callable $set) => $set('permission_type', $state ? \App\Models\Master\Attendance\PermissionTypes::find($state)?->name : null)),

                            Hidden::make('permission_type')
                                ->label('Tipe Izin')
                                ->live()
                                ->reactive()
                                ->default(fn (callable $get) => \App\Models\Master\Attendance\PermissionTypes::find($get('permission_type_id'))?->name)
                                ->disabled(),

                            Textarea::make('purpose')
                                ->label('Keperluan Izin')
                                ->rows(3)
                                ->required(),

                            TextInput::make('location')
                                ->label('Lokasi Izin')
                                ->required(),
                        ]),

                    Fieldset::make('Waktu Izin')
                        ->schema([
                            DatePicker::make('date')
                                ->label('Tanggal')
                                ->columnSpanFull()
                                ->minDate(now()->subDay())
                                ->required(),

                            TimePicker::make('start_time')
                                ->label('Waktu Mulai')
                                ->required(),

                            TimePicker::make('end_time')
                                ->label('Waktu Selesai')
                                ->required(),
                        ]),

                    Fieldset::make('Pengganti Tugas')
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('substitute_user_name')
                                ->label('Nama Pengganti')
                                ->required(),

                            Textarea::make('substitute_task')
                                ->label('Tugas Pengganti')
                                ->rows(3)
                                ->required(),
                        ])
                ])
        ];
    }

    public static function editSchema(): array
    {
        return [
            Tabs::make('Detail Pengajuan')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('Detail Request')
                        ->schema(self::getAtendanceRequest()),

                    Tabs\Tab::make('Riwayat Approval')
                        ->schema([
                            Repeater::make('approvalHistories')
                                ->hiddenLabel()
                                ->relationship('approvalHistories')
                                ->addable(false)
                                ->deletable(false)
                                ->table([
                                    TableColumn::make('Nama'),
                                    TableColumn::make('Role'),
                                    TableColumn::make('Catatan'),
                                    TableColumn::make('Tanggal'),
                                    TableColumn::make('Status'),
                                ])
                                ->schema(self::getHistorySchema()),
                            ],
                        ),
                ]),
        ];
    }

    private static function getAtendanceRequest(): array
    {
        return [
            Section::make('Informasi Karyawan')
                ->columnSpanFull()
                ->schema([
                    Grid::make()
                        ->columns(2)
                        ->schema([
                            TextEntry::make('user.name')
                                ->label('Nama Karyawan'),

                            TextEntry::make('user.nip')
                                ->label('NIP Karyawan'),
    
                            TextEntry::make('user.profile.department.name')
                                ->label('Departemen'),

                            TextEntry::make('user.profile.employeeType.name')
                                ->label('Tipe Karyawan'),
                        ]),
                ]),

            Section::make('Informasi Pengajuan')
                ->description('Untuk manajemen approval jika ada perubahan step terbaru maka akan mengikuti step approval yang lama / step pada saat pengajuan dibuat.')
                ->columnSpanFull()
                ->schema([
                    Grid::make()
                        ->columns(2)
                        ->schema([
                            TextEntry::make('type')
                                ->formatStateUsing(fn ($state) => [
                                    'overtime' => 'Lembur',
                                    'leave' => 'Cuti',
                                    'permission' => 'Izin',
                                ][$state] ?? $state)
                                ->label('Tipe Request'),

                            TextEntry::make('status')
                                ->label('Status')
                                ->formatStateUsing(function ($state, $record) {
                                    $statusLabel = AttendanceReqStatus::from($state)->getLabel();
                                    
                                    // Only show reviewer info for in_review status
                                    if ($state === 'in_review') {
                                        $reviewerInfo = self::getReviewerInfo($record);
                                        if ($reviewerInfo) {
                                            return $statusLabel . ' - ' . $reviewerInfo;
                                        }
                                    }
                                    
                                    return $statusLabel;
                                })
                                ->badge()
                                ->color(fn (string $state): string => AttendanceReqStatus::from($state)->getColor())
                                ->icon(fn (string $state): string => AttendanceReqStatus::from($state)->getIcon()),
                        ]),
                ]),

            Section::make('Detail Lembur')
                ->columnSpanFull()
                ->visible(fn ($record) => $record->type === 'overtime')
                ->schema([
                    RepeatableEntry::make('overtimeDetails')
                        ->hiddenLabel()
                        ->schema(self::getOvertimeDetailSchema()),
                ]),

            Section::make('Detail Cuti')
                ->columnSpanFull()
                ->visible(fn ($record) => $record->type === 'leave')
                ->schema([
                    Grid::make()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('leaveDetails.0.leave_type')
                                ->label('Tipe Cuti')
                                ->columnSpanFull(),

                            TextEntry::make('leaveDetails.0.start_date')
                                ->label('Tanggal Mulai')
                                ->date(),

                            TextEntry::make('leaveDetails.0.end_date')
                                ->label('Tanggal Selesai')
                                ->date(),

                            TextEntry::make('leaveDetails.0.total_days')
                                ->label('Total Hari')
                                ->numeric(decimalPlaces: 0)
                                ->suffix(' hari'),
                        ]),
                ]),

            Section::make('Detail Izin')
                ->columnSpanFull()
                ->visible(fn ($record) => $record->type === 'permission')
                ->schema([
                    Grid::make()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('permissionDetails.0.permission_type')
                                ->label('Tipe Izin'),

                            TextEntry::make('permissionDetails.0.date')
                                ->label('Tanggal')
                                ->date(),

                            TextEntry::make('permissionDetails.0.location')
                                ->label('Lokasi'),

                            TextEntry::make('permissionDetails.0.start_time')
                                ->label('Waktu Mulai'),

                            TextEntry::make('permissionDetails.0.end_time')
                                ->label('Waktu Selesai'),

                            TextEntry::make('permissionDetails.0.purpose')
                                ->label('Tujuan')
                                ->columnSpan(3),

                            TextEntry::make('permissionDetails.0.substitute_user_name')
                                ->label('Nama Pengganti'),

                            TextEntry::make('permissionDetails.0.substitute_task')
                                ->label('Tugas Pengganti')
                                ->columnSpan(2),
                        ]),
                ]),

            // Info kuota pemohon — approver bisa lihat sisa jatah Cuti/Izin pemohon SAAT
            // memutuskan approve, bukan widget terpisah. Lihat
            // planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md §8.
            Section::make('Kuota Pemohon')
                ->columnSpanFull()
                ->visible(fn ($record) => in_array($record->type, ['leave', 'permission']))
                ->schema([
                    Grid::make()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('quota_total')
                                ->label('Kuota')
                                ->formatStateUsing(fn ($record) => self::getQuotaSummary($record)['quota'] ?? '-'),

                            TextEntry::make('quota_used')
                                ->label('Digunakan (Disetujui)')
                                ->formatStateUsing(fn ($record) => self::getQuotaSummary($record)['used'] ?? '-'),

                            TextEntry::make('quota_available')
                                ->label('Sisa')
                                ->formatStateUsing(fn ($record) => self::getQuotaSummary($record)['available'] ?? '-'),
                        ]),
                ]),
        ];
    }

    /** @return array{quota: string, used: string, available: string} */
    private static function getQuotaSummary($record): array
    {
        $service = app(AttendaceReqService::class);

        if ($record->type === 'leave' && $record->leaveDetails->isNotEmpty()) {
            $leave = $record->leaveDetails->first();
            $balance = $service->getLeaveUserBalance($record->user_id, $leave->leave_type_id);

            return [
                'quota' => "{$balance['quota']} hari",
                'used' => "{$balance['used']} hari (Cuti Bersama: {$balance['collective_leave_days']} hari)",
                'available' => "{$balance['available']} hari",
            ];
        }

        if ($record->type === 'permission' && $record->permissionDetails->isNotEmpty()) {
            $permission = $record->permissionDetails->first();
            $category = $permission->category ? \App\Enum\Attendance\PermissionTypeCategoryEnum::tryFrom($permission->category) : null;
            $group = $category ? \App\Enum\Attendance\PermissionGroupEnum::fromCategory($category) : null;
            $usage = $group ? $service->getIzinPeriodeUsage($record->user, $group, $permission->date) : null;

            // Tanpa category, atau kelompok yang memang tidak ada limit (Sakit/Khusus) - tidak
            // ada info kuota yang relevan untuk ditampilkan.
            if (! $usage) {
                return [];
            }

            return [
                'quota' => $usage['max'] > 0 ? "{$usage['max']} hari" : 'Tidak dibatasi',
                'used' => "{$usage['used']} hari",
                'available' => $usage['available'] !== null ? "{$usage['available']} hari" : '-',
            ];
        }

        return [];
    }

    private static function getOvertimeDetailSchema(): array
    {
        return [
            Grid::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('overtime_type')
                        ->label('Tipe Lembur'),

                    TextEntry::make('date')
                        ->label('Tanggal Lembur')
                        ->date(),

                    TextEntry::make('start_time')
                        ->label('Waktu Mulai'),

                    TextEntry::make('end_time')
                        ->label('Waktu Selesai'),

                    TextEntry::make('task_description')
                        ->label('Deskripsi Tugas')
                        ->columnSpan(2)
                ]),
        ];
    }

    private static function getHistorySchema(): array
    {
        return [
            TextEntry::make('actor_name')
                ->hiddenLabel()
                ->alignCenter(),
            TextEntry::make('actor_id')
                ->hiddenLabel()
                ->alignCenter()
                ->formatStateUsing(function ($state) {
                    if (!$state) {
                        return null;
                    }
                    $user = \App\Models\User::find($state);
                    if (!$user) {
                        return null;
                    }
                    $roleName = $user->roles()->first()?->name ?? '-';
                    return "{$roleName}";
                }),
            TextEntry::make('note')
                ->hiddenLabel()
                ->alignCenter(),
            TextEntry::make('created_at')
                ->hiddenLabel()
                ->alignCenter()
                ->dateTime()
                ->formatStateUsing(fn ($state) => $state?->translatedFormat('d F Y H:i')),
            TextEntry::make('action')
                ->hiddenLabel()
                ->badge()
                ->formatStateUsing(function ($state) {
                    $actionLabels = [
                        'requested' => 'Diajukan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        'completed' => 'Selesai',
                    ];
                    return $actionLabels[$state] ?? $state;
                })
                ->color(fn (string $state): string => match ($state) {
                    'requested' => 'gray',
                    'approved' => 'warning',
                    'rejected' => 'danger',
                    'completed' => 'success',
                    default => 'gray',
                })
                ->alignCenter(),
        ];
    }

    private static function getReviewerInfo($record): ?string
    {
        try {
            $snapshot = $record->approvalSnapshot;
            if (!$snapshot || !$snapshot->activity) {
                return null;
            }
            
            $activity = $snapshot->activity;
            
            if ($activity->status !== 'pending') {
                return null;
            }
            
            switch ($activity->approver_type) {
                case 'user':
                    $user = \App\Models\User::find($activity->approver_ref_id);
                    return $user ? "Review oleh: {$user->name}" : null;
                    
                case 'role':
                    $role = \App\Models\Role::find($activity->approver_ref_id);
                    if ($role) {
                        if ($activity->scope_type === 'specific_department') {
                            $department = \App\Models\Master\OrgStructure::find($activity->scope_ref_id);
                            $departmentName = $department ? $department->name : 'Departemen tidak diketahui';
                            return "Review oleh: {$role->name} - {$departmentName}";
                        } else {
                            return "Review oleh: {$role->name}";
                        }
                    }
                    return null;
                    
                case 'job_position':
                    $jobPosition = \App\Models\Master\JobPositions::find($activity->approver_ref_id);
                    return $jobPosition ? "Review oleh: {$jobPosition->name}" : null;
                    
                default:
                    return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
