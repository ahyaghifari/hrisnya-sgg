<?php

namespace App\Filament\Resources\AttendanceRequests\Schemas;

use App\Enum\AttendanceReqStatus;
use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
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

    /**
     * Field admin buat pilih perusahaan (Super Admin doang, Admin biasa auto company sendiri),
     * karyawan target (di-filter dari company itu), toggle Perlu Approval, dan Catatan (wajib
     * kalau tanpa approval) - dipakai bareng oleh Cuti/Izin/Lembur, lihat storeLeaveRequest()/
     * storePermissionRequest()/storeOvertimeRequest() param $skipApproval/$note/$createdBy.
     */
    private static function adminAssignmentFields(): array
    {
        return [
            Select::make('target_company_id')
                ->label('Perusahaan')
                ->options(fn () => \App\Models\Master\Companies::pluck('name', 'id'))
                ->default(fn () => AttendanceRequestResource::canBrowseAllCompanies() ? null : AttendanceRequestResource::companyId())
                ->visible(fn () => AttendanceRequestResource::canBrowseAllCompanies())
                ->required(fn () => AttendanceRequestResource::canBrowseAllCompanies())
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(fn (callable $set) => $set('target_user_id', null)),

            Select::make('target_user_id')
                ->label('Karyawan')
                ->options(function (callable $get) {
                    $companyId = $get('target_company_id');

                    if (! $companyId) {
                        return [];
                    }

                    return \App\Models\User::where('company_id', $companyId)->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->reactive()
                ->required(),

            Toggle::make('need_approval')
                ->label('Perlu Approval')
                ->default(true)
                ->reactive()
                ->inline(false),

            Textarea::make('note')
                ->label('Catatan')
                ->helperText('Wajib diisi kalau pengajuan ini langsung disetujui tanpa approval — alasan kenapa dibuat langsung.')
                ->rows(2)
                ->visible(fn (callable $get) => ! $get('need_approval'))
                ->required(fn (callable $get) => ! $get('need_approval')),
        ];
    }

    public static function leaveFormSchema(): array
    {
        return [
            \Filament\Schemas\Components\Group::make()
                ->visible(fn (callable $get) => $get('type') === 'leave')
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    ...self::adminAssignmentFields(),

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
            \Filament\Schemas\Components\Group::make()
                ->visible(fn (callable $get) => $get('type') === 'overtime')
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    ...self::adminAssignmentFields(),

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

    /** Mirror App\Enum\Attendance\PermissionGroupEnum::from(), null-safe buat closure form. */
    private static function groupFrom(?string $value): ?\App\Enum\Attendance\PermissionGroupEnum
    {
        return $value ? \App\Enum\Attendance\PermissionGroupEnum::from($value) : null;
    }

    /** Mirror SubmitPermissionRequest::totalDays() - dipakai validasi wajib-lampiran Sakit >1 hari. */
    private static function totalDays(callable $get): int
    {
        $date = $get('date');

        if (! $date) {
            return 0;
        }

        if (! $get('end_date')) {
            return 1;
        }

        return \Carbon\Carbon::parse($date)->diffInDays(\Carbon\Carbon::parse($get('end_date'))) + 1;
    }

    /**
     * Sama persis dengan form pengajuan Izin di portal karyawan (App\Livewire\SubmitPermissionRequest
     * + resources/views/livewire/submit-permission-request.blade.php) - 2 level pilihan kelompok/jenis,
     * field kondisional per kelompok (lihat knowledge/markdown/rencana-izin-empat-kategori.md). Beda
     * cuma tambahan "Buat Untuk & Persetujuan" di atas (khusus admin) dan 1 kolom penuh (boleh scroll
     * panjang, bukan 2 kolom seperti Cuti/Lembur).
     */
    public static function permissionFormSchema(): array
    {
        return [
            \Filament\Schemas\Components\Group::make()
                ->visible(fn (callable $get) => $get('type') === 'permission')
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    ...self::adminAssignmentFields(),

                    Select::make('group')
                        ->label('Kelompok Izin')
                        ->options(fn () => collect(\App\Enum\Attendance\PermissionGroupEnum::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all())
                        ->reactive()
                        ->required()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $set('permission_type_id', null);
                            $set('category', null);
                            $set('end_date', null);
                            $set('start_time', null);
                            $set('end_time', null);
                            $set('attachments', null);

                            $group = self::groupFrom($state);

                            // Kelompok flat (Tidak Masuk Seharian, Sakit) tidak lewat master sama
                            // sekali - category langsung dari kelompoknya (lihat updatedGroup()
                            // di SubmitPermissionRequest, versi portalnya).
                            if ($group && ! $group->hasSubKategori()) {
                                $set('category', $group->categories()[0]->value);
                            }
                        }),

                    Select::make('permission_type_id')
                        ->label('Jenis Izin')
                        ->visible(fn (callable $get) => self::groupFrom($get('group'))?->hasSubKategori() ?? false)
                        ->required(fn (callable $get) => self::groupFrom($get('group'))?->hasSubKategori() ?? false)
                        ->options(function (callable $get) {
                            $group = self::groupFrom($get('group'));

                            if (! $group || ! $group->hasSubKategori()) {
                                return [];
                            }

                            $categoryValues = array_map(fn ($c) => $c->value, $group->categories());
                            $companyId = $get('target_company_id');

                            return \App\Models\Master\Attendance\PermissionTypes::where('company_id', $companyId)
                                ->whereIn('category', $categoryValues)
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $type = $state ? \App\Models\Master\Attendance\PermissionTypes::find($state) : null;
                            $set('category', $type?->category);

                            // Prefill end_date dari default_days Izin Khusus - cuma prefill, tetap
                            // bebas diubah manual (rencana-izin-empat-kategori.md §2).
                            if (self::groupFrom($get('group')) === \App\Enum\Attendance\PermissionGroupEnum::KHUSUS && $type?->default_days && $get('date')) {
                                $set('end_date', \Carbon\Carbon::parse($get('date'))->addDays($type->default_days - 1)->toDateString());
                            }
                        }),

                    Hidden::make('category'),

                    DatePicker::make('date')
                        ->label(fn (callable $get) => self::groupFrom($get('group'))?->isRentangTanggal() ? 'Tanggal Mulai' : 'Tanggal')
                        ->minDate(now()->subDay())
                        ->reactive()
                        ->required(),

                    DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->visible(fn (callable $get) => self::groupFrom($get('group'))?->isRentangTanggal() ?? false)
                        ->required(fn (callable $get) => self::groupFrom($get('group'))?->isRentangTanggal() ?? false)
                        ->minDate(fn (callable $get) => $get('date')),

                    TimePicker::make('start_time')
                        ->label('Waktu Mulai')
                        ->visible(fn (callable $get) => self::groupFrom($get('group')) === \App\Enum\Attendance\PermissionGroupEnum::MENINGGALKAN_JAM_KERJA)
                        ->required(fn (callable $get) => self::groupFrom($get('group')) === \App\Enum\Attendance\PermissionGroupEnum::MENINGGALKAN_JAM_KERJA),

                    TimePicker::make('end_time')
                        ->label('Waktu Selesai')
                        ->visible(fn (callable $get) => self::groupFrom($get('group')) === \App\Enum\Attendance\PermissionGroupEnum::MENINGGALKAN_JAM_KERJA)
                        ->required(fn (callable $get) => self::groupFrom($get('group')) === \App\Enum\Attendance\PermissionGroupEnum::MENINGGALKAN_JAM_KERJA),

                    Textarea::make('purpose')
                        ->label('Keperluan Izin')
                        ->rows(3),

                    TextInput::make('location')
                        ->label('Lokasi Izin'),

                    Toggle::make('with_substitute')
                        ->label('Ada Pengganti Tugas?')
                        ->default(false)
                        ->reactive()
                        ->inline(false),

                    TextInput::make('substitute_user_name')
                        ->label('Nama Pengganti')
                        ->visible(fn (callable $get) => (bool) $get('with_substitute')),

                    Textarea::make('substitute_task')
                        ->label('Tugas Pengganti')
                        ->rows(3)
                        ->visible(fn (callable $get) => (bool) $get('with_substitute')),

                    FileUpload::make('attachments')
                        ->label('Lampiran')
                        ->multiple()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)
                        ->helperText('PDF/JPG/PNG, maksimal 10MB per file.')
                        ->visible(fn (callable $get) => self::groupFrom($get('group'))?->bolehLampiran() ?? false)
                        ->required(fn (callable $get) => self::groupFrom($get('group')) === \App\Enum\Attendance\PermissionGroupEnum::SAKIT && self::totalDays($get) > 1),
                ]),
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
