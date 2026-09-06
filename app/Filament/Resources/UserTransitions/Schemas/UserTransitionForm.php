<?php

namespace App\Filament\Resources\UserTransitions\Schemas;

use App\Enum\OrgStructurLevel;
use App\Enum\UserTransitionTypeEnum;
use App\Enum\EmployeeTypeCategory;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserTransitionForm
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

    protected static function createSchema(): array
    {
        return [
            Section::make('Informasi Umum')
                ->icon('heroicon-o-identification')
                ->schema([
                    Select::make('user_id')
                        ->label('Karyawan')
                        ->relationship('user', 'name', function ($query, Get $get) {
                            $query->where('company_id', auth()->user()->company_id);

                            // Pengangkatan Orientasi khusus buat karyawan Orientasi (naik
                            // jadi Kontrak/Tetap) - kebalikan dari jenis transisi lain yang
                            // khusus Tetap/Kontrak (Orientasi tidak relevan buat Promosi/dst).
                            $categories = $get('transition_type') === UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value
                                ? [EmployeeTypeCategory::ORIENTASI->value]
                                : [EmployeeTypeCategory::TETAP->value, EmployeeTypeCategory::KONTRAK->value];

                            $query->whereHas('profile.employeeType', fn ($q) => $q->whereIn('category', $categories));
                        })
                        ->searchable()
                        ->reactive()
                        ->required(),

                    Select::make('transition_type')
                        ->label('Jenis Perubahan')
                        ->options(collect(UserTransitionTypeEnum::assignable())
                            ->mapWithKeys(fn ($t) => [$t->value => $t->label()])
                            ->all())
                        ->default(fn () => request()->query('type'))
                        ->reactive()
                        ->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Perubahan Jabatan')
                ->icon('heroicon-o-briefcase')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->schema([
                    Select::make('transitionable_id')
                        ->label('Jabatan Baru')
                        ->options(function (callable $get) {
                            $userId = $get('user_id');

                            if (! $userId) {
                                return [];
                            }

                            $user = User::find($userId);

                            if (! $user) {
                                return [];
                            }

                            return JobPositions::where('company_id', $user->company_id)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->reactive()
                        ->required()
                        ->searchable()
                        ->afterStateUpdated(function (callable $set, $state) {
                            $set('role_id', JobPositions::find($state)?->role_id);
                        }),
                    DatePicker::make('effective_date')
                        ->label('Tanggal Efektif')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->closeOnDateSelection(),
                    Select::make('role_id')
                        ->label('Role Baru')
                        ->helperText('Otomatis disarankan dari Role Default jabatan yang dipilih. Kosongkan kalau tidak ingin mengubah role user.')
                        ->options(function (callable $get) {
                            $userId = $get('user_id');
                            $user = $userId ? User::find($userId) : null;

                            if (! $user) {
                                return [];
                            }

                            return \App\Models\Role::where('company_id', $user->company_id)
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Alasan Penilaian')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->schema([
                    Select::make('reason')
                        ->hiddenLabel()
                        ->options([
                            'poor_performance' => 'Kinerja tidak memenuhi standar',
                            'violation' => 'Pelanggaran kebijakan atau etika kerja',
                            'restructuring' => 'Restrukturisasi organisasi',
                            'personal_request' => 'Permintaan pribadi',
                            'other' => 'Lainnya',
                        ])
                        ->reactive()
                        ->required(),
                    Textarea::make('reason')
                        ->label('Alasan Lainnya')
                        ->placeholder('Tuliskan alasan lainnya...')
                        ->visible(fn ($get) => $get('reason_option') === 'other')
                        ->rows(2),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Evaluasi Kinerja')
                ->icon('heroicon-o-chart-bar')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->schema([
                    Fieldset::make('Pencapaian Target Kerja')
                        ->schema([
                            Select::make('detail.target_achievement')
                                ->hiddenLabel()
                                ->options([
                                    'Memenuhi target sepenuhnya' => 'Memenuhi target sepenuhnya',
                                    'Sebagian besar target terpenuhi' => 'Sebagian besar target terpenuhi',
                                    'Tidak memenuhi target' => 'Tidak memenuhi target',
                                ]),

                            Textarea::make('detail.target_comment')
                                ->label('Komentar')
                                ->rows(2),
                        ])
                        ->columns(1),
                    Fieldset::make('Kualitas Pekerjaan')
                        ->schema([
                            Select::make('detail.work_quality')
                                ->hiddenLabel()
                                ->options([
                                    'Sangat Baik' => 'Sangat Baik',
                                    'Cukup Baik' => 'Cukup Baik',
                                    'Tidak Memadai' => 'Tidak Memadai',
                                ]),

                            Textarea::make('detail.work_quality_comment')
                                ->label('Komentar')
                                ->rows(2),
                        ])
                        ->columns(1),
                    Fieldset::make('Produktivitas')
                        ->schema([
                            Select::make('detail.productivity_level')
                                ->hiddenLabel()
                                ->options([
                                    'Tinggi' => 'Tinggi',
                                    'Sedang' => 'Sedang',
                                    'Rendah' => 'Rendah',
                                ]),

                            Textarea::make('detail.productivity_comment')
                                ->label('Komentar')
                                ->rows(2),
                        ])
                        ->columns(1),

                ])
                ->columns(1),

            Section::make('Analisis Perilaku')
                ->icon('heroicon-o-user-group')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->schema([
                    Fieldset::make('Kepatuhan Tanggung Jawab')
                        ->schema([
                            Select::make('detail.responsibility_adherence')
                                ->hiddenLabel()
                                ->options([
                                    'Mematuhi semua tugas' => 'Mematuhi semua tugas',
                                    'Pelanggaran kecil' => 'Pelanggaran kecil',
                                    'Pelanggaran besar' => 'Pelanggaran besar',
                                ]),

                            Textarea::make('detail.responsibility_comment')
                                ->label('Komentar')
                                ->rows(2),
                        ])
                        ->columns(1),
                    Fieldset::make('Etika & Sikap Kerja')
                        ->schema([
                            Select::make('detail.work_ethic')
                                ->hiddenLabel()
                                ->options([
                                    'Sangat Baik' => 'Sangat Baik',
                                    'Cukup Baik' => 'Cukup Baik',
                                    'Bermasalah' => 'Bermasalah',
                                ]),

                            Textarea::make('detail.work_ethic_comment')
                                ->label('Komentar')
                                ->rows(2),
                        ])
                        ->columns(1),
                    Fieldset::make('Hubungan Dengan Rekan Kerja')
                        ->schema([
                            Select::make('detail.teamwork')
                                ->hiddenLabel()
                                ->options([
                                    'Harmonis' => 'Harmonis',
                                    'Cukup Baik' => 'Cukup Baik',
                                    'Terdapat konflik' => 'Terdapat konflik',
                                ]),

                            Textarea::make('detail.teamwork_comment')
                                ->label('Komentar')
                                ->rows(2),
                        ])
                        ->columns(1),

                ])
                ->columns(1),

            Section::make('Pertimbangan Tambahan')
                ->icon('heroicon-o-light-bulb')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->schema([
                    Textarea::make('detail.external_factors')
                        ->label('Faktor Eksternal (Restukturisasi, dll)')
                        ->rows(2),

                    Textarea::make('detail.employee_request')
                        ->label('Permintaan Karyawan (jika ada)')
                        ->rows(2),

                    Textarea::make('detail.other_comments')
                        ->label('Komentar Lainnya')
                        ->rows(2),
                ]),

            Section::make('Komentar Manager/Kepegawaian')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->schema([
                    Textarea::make('manager_comment')
                        ->hiddenLabel()
                        ->rows(3),
                ]),

            Section::make('Mutasi Perusahaan')
                ->icon('heroicon-o-building-office')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::MUTATION->value,
                ])
                )
                ->schema([
                    Select::make('transitionable_id')
                        ->label('Department Baru')
                        ->options(fn () => OrgStructure::where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)->pluck('name', 'id'))
                        ->searchable(),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Alasan')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::TERMINATION->value,
                ])
                )
                ->schema([
                    Textarea::make('reason')
                        ->hiddenLabel()
                        ->required(),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Perubahan Kelas')
                ->icon('heroicon-o-arrow-trending-up')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::KELAS_CHANGE->value,
                ])
                )
                ->schema([
                    Select::make('transitionable_id')
                        ->label('Kelas Baru')
                        ->options(function (callable $get) {
                            $userId = $get('user_id');
                            $user = $userId ? User::find($userId) : null;

                            if (! $user) {
                                return [];
                            }

                            return Kelas::where('company_id', $user->company_id)->pluck('name', 'id');
                        })
                        ->required()
                        ->searchable(),
                    DatePicker::make('effective_date')
                        ->label('Tanggal Efektif')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->closeOnDateSelection(),
                    Textarea::make('reason')
                        ->label('Alasan')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Pengangkatan Orientasi')
                ->icon('heroicon-o-identification')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value,
                ])
                )
                ->schema([
                    Select::make('transitionable_id')
                        ->label('Status Baru')
                        ->options(function (callable $get) {
                            $userId = $get('user_id');
                            $user = $userId ? User::find($userId) : null;

                            if (! $user) {
                                return [];
                            }

                            return EmployeeTypes::where('company_id', $user->company_id)
                                ->whereIn('category', [EmployeeTypeCategory::TETAP->value, EmployeeTypeCategory::KONTRAK->value])
                                ->pluck('name', 'id');
                        })
                        ->reactive()
                        ->required()
                        ->searchable(),
                    DatePicker::make('effective_date')
                        ->label('Tanggal Efektif')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->closeOnDateSelection()
                        ->reactive()
                        ->helperText('Kalau status barunya Kontrak, "Kontrak Berlaku Sampai" otomatis dihitung dari tanggal ini + durasi perpanjangan (Pengaturan Kepegawaian) - tidak diisi manual.'),
                    Text::make(function (Get $get) {
                        $newEmployeeType = $get('transitionable_id') ? EmployeeTypes::find($get('transitionable_id')) : null;
                        $user = $get('user_id') ? User::find($get('user_id')) : null;

                        if (! $user || ! $get('effective_date') || $newEmployeeType?->category !== EmployeeTypeCategory::KONTRAK) {
                            return null;
                        }

                        try {
                            $endDate = app(\App\Services\User\UserTransitionService::class)->resolveContractEndDate(
                                $user,
                                \Carbon\Carbon::parse($get('effective_date')),
                                $user->company_id,
                            );

                            return 'Kontrak akan berakhir pada ' . $endDate->translatedFormat('d F Y') . '.';
                        } catch (\Exception $e) {
                            return $e->getMessage();
                        }
                    })
                        ->weight('bold')
                        ->columnSpanFull(),
                    Textarea::make('reason')
                        ->label('Alasan')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Perpanjangan Kontrak')
                ->icon('heroicon-o-calendar-days')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::CONTRACT_EXTENSION->value,
                ])
                )
                ->schema([
                    Text::make('Tanggal kontrak baru dihitung otomatis (durasi perpanjangan dari Pengaturan Kepegawaian, disambung dari tanggal kontrak yang berjalan sekarang) begitu pengajuan disetujui - tidak ada tanggal yang perlu diisi manual di sini.'),
                    Textarea::make('reason')
                        ->label('Alasan')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Lampiran')
                ->icon('heroicon-o-paper-clip')
                ->visible(fn ($get) =>
                in_array($get('transition_type'), [
                    UserTransitionTypeEnum::MUTATION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                    UserTransitionTypeEnum::TERMINATION->value,
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::KELAS_CHANGE->value,
                    UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value,
                    UserTransitionTypeEnum::CONTRACT_EXTENSION->value,
                ])
                )
                ->schema([
                    FileUpload::make('attachments')
                        ->hiddenLabel()
                        ->multiple()
                        ->disk('public')
                        ->directory('user-transitions')
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull(),
        ];
    }

    protected static function editSchema(): array
    {
        return [
            Section::make('Informasi Umum')
                ->icon('heroicon-o-identification')
                ->columns(2)
                ->schema([
                    TextEntry::make('number')
                        ->label('Nomor Dokumen'),

                    TextEntry::make('transition_type')
                        ->label('Jenis Perubahan')
                        ->badge()
                        ->formatStateUsing(fn ($state) =>
                        UserTransitionTypeEnum::from($state)->label()
                        )
                        ->color('primary'),

                    TextEntry::make('user.name')
                        ->label('Karyawan'),

                    TextEntry::make('createdBy.name')
                        ->label('Dibuat Oleh'),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn ($state) => $state === 'approved' ? 'success' : 'warning'),
                ])
                ->columnSpanFull(),

            Section::make('Perubahan Jabatan')
                ->visible(fn ($record) =>
                in_array($record->transition_type, [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->icon('heroicon-o-briefcase')
                ->columns(2)
                ->schema([
                    TextEntry::make('transitionable.name')
                        ->label('Jabatan Baru'),
                    TextEntry::make('effective_date')
                        ->label('Tanggal Efektif'),
                    TextEntry::make('role.name')
                        ->label('Role Baru')
                        ->placeholder('Tidak diubah'),
                ])
                ->columnSpanFull(),

            Section::make('Alasan Penilaian Demosi')
                ->visible(fn ($record) =>
                    $record->transition_type === UserTransitionTypeEnum::DEMOTION->value
                )
                ->icon('heroicon-o-exclamation-triangle')
                ->schema([
                    TextEntry::make('detail.reason_option')
                        ->label('Alasan')
                        ->formatStateUsing(function ($state) {
                            return [
                                'poor_performance' => 'Kinerja tidak memenuhi standar',
                                'violation' => 'Pelanggaran kebijakan/etika',
                                'restructuring' => 'Restrukturisasi organisasi',
                                'personal_request' => 'Permintaan pribadi',
                                'other' => 'Lainnya',
                            ][$state] ?? $state;
                        }),

                    TextEntry::make('detail.reason_text')
                        ->label('Alasan Tambahan')
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Evaluasi Kinerja')
                ->visible(fn ($record) =>
                in_array($record->transition_type, [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    TextEntry::make('detail.target_achievement')
                        ->label('Pencapaian Target Kerja'),

                    TextEntry::make('detail.target_comment')
                        ->label('Komentar Target')
                        ->placeholder('-'),

                    TextEntry::make('detail.work_quality')
                        ->label('Kualitas Pekerjaan'),

                    TextEntry::make('detail.work_quality_comment')
                        ->label('Komentar Kualitas')
                        ->placeholder('-'),

                    TextEntry::make('detail.productivity_level')
                        ->label('Produktivitas'),

                    TextEntry::make('detail.productivity_comment')
                        ->label('Komentar Produktivitas')
                        ->placeholder('-'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Analisis Perilaku')
                ->visible(fn ($record) =>
                in_array($record->transition_type, [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->icon('heroicon-o-user-group')
                ->columns(2)
                ->schema([
                    TextEntry::make('detail.responsibility_adherence')
                        ->label('Kepatuhan Tanggung Jawab'),

                    TextEntry::make('detail.responsibility_comment')
                        ->label('Komentar Tanggung Jawab')
                        ->placeholder('-'),

                    TextEntry::make('detail.work_ethic')
                        ->label('Etika & Sikap Kerja'),

                    TextEntry::make('detail.work_ethic_comment')
                        ->label('Komentar Etika')
                        ->placeholder('-'),

                    TextEntry::make('detail.teamwork')
                        ->label('Hubungan Dengan Rekan Kerja'),

                    TextEntry::make('detail.teamwork_comment')
                        ->label('Komentar Teamwork')
                        ->placeholder('-'),
                ])
                ->columnSpanFull(),

            Section::make('Pertimbangan Tambahan')
                ->visible(fn ($record) =>
                in_array($record->transition_type, [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->icon('heroicon-o-light-bulb')
                ->schema([
                    TextEntry::make('detail.external_factors')
                        ->label('Faktor Eksternal')
                        ->placeholder('-'),
                    TextEntry::make('detail.employee_request')
                        ->label('Permintaan Karyawan')
                        ->placeholder('-'),
                    TextEntry::make('detail.other_comments')
                        ->label('Komentar Lainnya')
                        ->placeholder('-'),
                ])
                ->columnSpanFull(),

            Section::make('Komentar')
                ->visible(fn ($record) =>
                in_array($record->transition_type, [
                    UserTransitionTypeEnum::PROMOTION->value,
                    UserTransitionTypeEnum::DEMOTION->value,
                ])
                )
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    TextEntry::make('manager_comment')
                        ->hiddenLabel()
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Mutasi Perusahaan')
                ->visible(fn ($record) =>
                    $record->transition_type === UserTransitionTypeEnum::MUTATION->value
                )
                ->icon('heroicon-o-building-office')
                ->columns(1)
                ->schema([
                    TextEntry::make('transitionable.name')
                        ->label('Department Baru'),
                ])
                ->columnSpanFull(),

            Section::make('Perubahan Kelas')
                ->visible(fn ($record) =>
                    $record->transition_type === UserTransitionTypeEnum::KELAS_CHANGE->value
                )
                ->icon('heroicon-o-arrow-trending-up')
                ->columns(2)
                ->schema([
                    TextEntry::make('old_value')
                        ->label('Kelas Sebelumnya'),
                    TextEntry::make('transitionable.name')
                        ->label('Kelas Baru'),
                    TextEntry::make('effective_date')
                        ->label('Tanggal Efektif'),
                    TextEntry::make('reason')
                        ->label('Alasan')
                        ->placeholder('-'),
                ])
                ->columnSpanFull(),

            Section::make('Pengangkatan Orientasi')
                ->visible(fn ($record) =>
                    $record->transition_type === UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value
                )
                ->icon('heroicon-o-identification')
                ->columns(2)
                ->schema([
                    TextEntry::make('old_value')
                        ->label('Status Sebelumnya'),
                    TextEntry::make('transitionable.name')
                        ->label('Status Baru'),
                    TextEntry::make('effective_date')
                        ->label('Tanggal Efektif'),
                    TextEntry::make('contract_start_date')
                        ->label('Kontrak Berlaku Mulai')
                        ->placeholder('-'),
                    TextEntry::make('contract_end_date')
                        ->label('Kontrak Berlaku Sampai')
                        ->placeholder('-'),
                    TextEntry::make('reason')
                        ->label('Alasan')
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Perpanjangan Kontrak')
                ->visible(fn ($record) =>
                    $record->transition_type === UserTransitionTypeEnum::CONTRACT_EXTENSION->value
                )
                ->icon('heroicon-o-calendar-days')
                ->columns(2)
                ->schema([
                    TextEntry::make('old_value')
                        ->label('Kontrak Sebelumnya Sampai'),
                    TextEntry::make('contract_start_date')
                        ->label('Kontrak Baru Mulai'),
                    TextEntry::make('contract_end_date')
                        ->label('Kontrak Baru Sampai'),
                    TextEntry::make('reason')
                        ->label('Alasan')
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Lampiran')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    RepeatableEntry::make('attachments')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('file_path')
                                ->hiddenLabel()
                                ->formatStateUsing(fn ($state) => basename($state))
                                ->url(fn ($record) => asset('storage/' . $record->file_path), true)
                                ->color('primary')
                                ->icon('heroicon-o-link')
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }
}
