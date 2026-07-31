<?php

namespace App\Filament\Resources\Master\JobPositions;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\JobPositions\Pages\CreateJobPosition;
use App\Filament\Resources\Master\JobPositions\Pages\EditJobPosition;
use App\Filament\Resources\Master\JobPositions\Pages\ListJobPositions;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Master\Pangkats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;


class JobPositionResource extends BaseCompanyResource
{
    protected static ?string $model = JobPositions::class;

    protected static ?string $navigationLabel = 'Jabatan';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 8;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Jabatan';
    }

    /** Field terkunci begitu status bukan lagi draft (sudah diajukan/disetujui/ditolak) -
     * pengajuan yang sudah jalan tidak boleh diam-diam diubah lewat form ini. */
    protected static function lockedAfterDraft($record): bool
    {
        return $record && $record->status !== \App\Enum\JobPositionReqStatus::DRAFT->value;
    }

    protected static function kelasOptions(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return Kelas::where('company_id', $companyId)->orderBy('level')->pluck('name', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Struktur Organisasi')
                    ->columns(2)
                    ->schema([
                        static::companyFormField()
                            ->live()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),

                        Select::make('department_id')
                            ->label('Departemen')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(function (Get $get) {
                                $companyId = static::isSuperAdmin() ? $get('company_id') : static::companyId();

                                if (! $companyId) {
                                    return [];
                                }

                                return OrgStructure::where('company_id', $companyId)
                                    ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                    ->pluck('name', 'id');
                            })
                            ->disabled(fn ($record) => static::lockedAfterDraft($record))
                            ->afterStateHydrated(function ($set, $record) {
                                if (! $record?->org_structure_id) {
                                    return;
                                }

                                $org = OrgStructure::with('parent.parent')->find($record->org_structure_id);
                                match ($org?->level) {
                                    1 => $set('department_id', $org->id),
                                    2 => $set('department_id', $org->parent_id),
                                    3 => $set('department_id', $org->parent?->parent_id),
                                    default => null,
                                };
                            })
                            ->afterStateUpdated(function ($set) {
                                $set('division_id', null);
                                $set('sub_division_id', null);
                            })
                            ->dehydrated(false),

                        Select::make('division_id')
                            ->label('Divisi')
                            ->helperText('Opsional - isi kalau jabatan ini di dalam divisi tertentu.')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn (Get $get) => $get('department_id')
                                ? OrgStructure::where('parent_id', $get('department_id'))
                                    ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                                    ->pluck('name', 'id')
                                : [])
                            ->disabled(fn (Get $get, $record) => ! $get('department_id') || static::lockedAfterDraft($record))
                            ->afterStateHydrated(function ($set, $record) {
                                if (! $record?->org_structure_id) {
                                    return;
                                }

                                $org = OrgStructure::find($record->org_structure_id);
                                match ($org?->level) {
                                    2 => $set('division_id', $org->id),
                                    3 => $set('division_id', $org->parent_id),
                                    default => null,
                                };
                            })
                            ->afterStateUpdated(fn ($set) => $set('sub_division_id', null))
                            ->dehydrated(false),

                        Select::make('sub_division_id')
                            ->label('Sub Divisi')
                            ->helperText('Opsional - isi kalau jabatan ini di dalam sub divisi tertentu.')
                            ->searchable()
                            ->preload()
                            ->options(fn (Get $get) => $get('division_id')
                                ? OrgStructure::where('parent_id', $get('division_id'))
                                    ->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value)
                                    ->pluck('name', 'id')
                                : [])
                            ->disabled(fn (Get $get, $record) => ! $get('division_id') || static::lockedAfterDraft($record))
                            ->afterStateHydrated(function ($set, $record) {
                                $org = $record?->org_structure_id ? OrgStructure::find($record->org_structure_id) : null;
                                if ($org?->level === 3) {
                                    $set('sub_division_id', $org->id);
                                }
                            })
                            ->dehydrated(false),

                        // org_structure_id final = yang paling dalam terisi (sub divisi > divisi > departemen).
                        Hidden::make('org_structure_id')
                            ->dehydrateStateUsing(fn (Get $get) => $get('sub_division_id') ?? $get('division_id') ?? $get('department_id')),
                    ]),

                Section::make('Detail Jabatan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Jabatan')
                            ->placeholder('Contoh: HR Officer, Finance Manager')
                            ->required()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),

                        Select::make('parent_id')
                            ->label('Atasan Langsung')
                            ->helperText('Opsional.')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),

                        Textarea::make('description')
                            ->label('Deskripsi Jabatan')
                            ->columnSpanFull()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),

                        RichEditor::make('qualification')
                            ->label('Kualifikasi Jabatan')
                            ->columnSpanFull()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),
                    ]),

                Section::make('Job Evaluation')
                    ->columns(3)
                    ->schema([
                        Select::make('pangkat_id')
                            ->label('Pangkat')
                            ->relationship('pangkat', 'name', fn ($query) => $query->orderByDesc('level'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),

                        Placeholder::make('pangkat_golongan')
                            ->label('Golongan')
                            ->content(fn (Get $get) => Pangkats::find($get('pangkat_id'))?->golongan ?? '-'),

                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->searchable()
                            ->preload()
                            ->options(fn (Get $get) => static::kelasOptions(
                                static::isSuperAdmin() ? $get('company_id') : static::companyId()
                            ))
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),

                        TextInput::make('job_evaluation_score')
                            ->label('Skor Penilaian Jabatan')
                            ->numeric()
                            ->minValue(0)
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),

                        Select::make('role_id')
                            ->label('Role Default')
                            ->helperText('Role yang disarankan otomatis saat user dipromosikan/dimutasi ke jabatan ini.')
                            ->relationship('defaultRole', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),
                    ]),

                Section::make('Analisis Beban Kerja')
                    ->columns(2)
                    ->schema([
                        TextInput::make('planned_manpower')
                            ->label('Kuota Karyawan')
                            ->helperText('Cuma bisa diisi saat pengajuan baru. Perubahan kuota setelahnya lewat pengajuan Penambahan/Penggantian Karyawan.')
                            ->numeric()
                            ->minValue(0)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->disabledOn('edit'),

                        TextInput::make('actual_manpower')
                            ->label('Realisasi Karyawan')
                            ->numeric()
                            ->disabled()
                            ->visible(fn (string $operation) => $operation === 'edit'),

                        FileUpload::make('file_workload_analysis')
                            ->label('File Analisis Beban Kerja')
                            ->helperText('Opsional.')
                            ->disk('public')
                            ->openable()
                            ->directory('workload-analysis')
                            ->visibility('public')
                            ->columnSpanFull()
                            ->disabled(fn ($record) => static::lockedAfterDraft($record)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Jabatan')->searchable()->sortable(),
                TextColumn::make('planned_manpower')
                    ->label('Kuota')
                    ->formatStateUsing(fn ($state) => $state ?? 0)
                    ->alignCenter(),
                TextColumn::make('jumlah_karyawan')
                    ->label('Jumlah Karyawan')
                    ->alignCenter()
                    ->getStateUsing(fn ($record) => $record->userAssignments()->count()),
                TextColumn::make('status')
                    ->label('Status Persetujuan')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'requested' => 'warning',
                        default => 'gray',
                    })
                    ->alignCenter(),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('history_action')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Riwayat Pengajuan Jabatan')
                    ->schema([
                        RepeatableEntry::make('approvalHistories')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('actor_name')->hiddenLabel()->alignCenter(),
                                TextEntry::make('actor_id')
                                    ->hiddenLabel()
                                    ->alignCenter()
                                    ->formatStateUsing(function ($state) {
                                        if (! $state) {
                                            return null;
                                        }

                                        $user = \App\Models\User::find($state);

                                        return $user ? ($user->roles()->first()?->name ?? '-') : null;
                                    }),
                                TextEntry::make('note')->hiddenLabel()->alignCenter(),
                                TextEntry::make('created_at')
                                    ->hiddenLabel()
                                    ->alignCenter()
                                    ->dateTime()
                                    ->formatStateUsing(fn ($state) => $state?->translatedFormat('d F Y H:i')),
                                TextEntry::make('action')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'requested' => 'Diajukan',
                                        'approved' => 'Disetujui',
                                        'rejected' => 'Ditolak',
                                        'completed' => 'Selesai',
                                        default => $state,
                                    })
                                    ->color(fn (string $state): string => match ($state) {
                                        'requested' => 'gray',
                                        'approve' => 'warning',
                                        'reject' => 'danger',
                                        'completed' => 'success',
                                        default => 'gray',
                                    })
                                    ->alignCenter(),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobPositions::route('/'),
            'create' => CreateJobPosition::route('/create'),
            'edit' => EditJobPosition::route('/{record}/edit'),
        ];
    }
}
