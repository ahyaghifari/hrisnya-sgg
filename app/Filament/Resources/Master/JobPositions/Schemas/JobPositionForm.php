<?php

namespace App\Filament\Resources\Master\JobPositions\Schemas;

use App\Enum\JobPositionReqStatus;
use App\Enum\OrgStructurLevel;
use App\Models\Master\Companies;
use App\Models\Master\Golongans;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\OrgStructure;
use App\Models\Master\Pangkats;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class JobPositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Struktur Organisasi')
                        ->schema([
                            Section::make('Detail Jabatan')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Select::make('company_id')
                                                ->label('Perusahaan')
                                                ->options(function () {
                                                    $user = Auth::user();
                                                    if ($user && $user->company_id) {
                                                        return Companies::where('id', $user->company_id)
                                                            ->pluck('name', 'id');
                                                    }
                                                    return Companies::pluck('name', 'id');
                                                })
                                                ->default(function () {
                                                    $user = Auth::user();
                                                    return $user && $user->company_id ? $user->company_id : null;
                                                })
                                                ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                                ->required()
                                                ->searchable()
                                                ->live(),

                                            // 🔹 Department (Level 1)
                                            Select::make('department_id')
                                                ->label('Departemen')
                                                ->options(function (callable $get) {
                                                    $companyId = $get('company_id');
                                                    if (!$companyId) return [];
                                                    return OrgStructure::query()
                                                        ->where('company_id', $companyId)
                                                        ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                                        ->pluck('name', 'id')
                                                        ->toArray();
                                                })
                                                ->live()
                                                ->searchable()
                                                ->disabled(fn (callable $get, $record) => !$get('company_id') || $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                                ->afterStateHydrated(function ($set, $record) {
                                                    if (!$record?->org_structure_id) return;

                                                    $org = OrgStructure::with('parent.parent')->find($record->org_structure_id);
                                                    if ($org?->level === 1) $set('department_id', $org->id);
                                                    if ($org?->level === 2) $set('department_id', $org->parent?->id);
                                                    if ($org?->level === 3) $set('department_id', $org->parent?->parent?->id);
                                                })
                                                ->afterStateUpdated(function (callable $set, $state) {
                                                    $set('division_id', null);
                                                    $set('sub_division_id', null);
                                                    // Update org_structure_id when department changes
                                                    $set('org_structure_id', $state);
                                                })
                                                ->dehydrated(false),

                                            // 🔹 Division (Level 2)
                                            Select::make('division_id')
                                                ->label('Divisi')
                                                ->helperText('Pilih divisi jika jabatan ini berada di dalam divisi tertentu')
                                                ->options(function (callable $get) {
                                                    $departmentId = $get('department_id');
                                                    if (!$departmentId) return [];
                                                    return OrgStructure::query()
                                                        ->where('parent_id', $departmentId)
                                                        ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                                                        ->pluck('name', 'id')
                                                        ->toArray();
                                                })
                                                ->live()
                                                ->searchable()
                                                ->disabled(fn (callable $get, $record) => !$get('department_id') || $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                                ->afterStateHydrated(function ($set, $record) {
                                                    if (!$record?->org_structure_id) return;

                                                    $org = OrgStructure::with('parent.parent')->find($record->org_structure_id);
                                                    if ($org?->level === 2) $set('division_id', $org->id);
                                                    if ($org?->level === 3) $set('division_id', $org->parent?->id);
                                                })
                                                ->afterStateUpdated(function (callable $set, $state) {
                                                    $set('sub_division_id', null);
                                                    // Update org_structure_id when division changes
                                                    $set('org_structure_id', $state);
                                                })
                                                ->dehydrated(false),

                                            // 🔹 Sub-Division (Level 3)
                                            Select::make('sub_division_id')
                                                ->label('Sub Divisi')
                                                ->helperText('Pilih sub divisi jika jabatan ini berada di dalam sub divisi tertentu')
                                                ->options(function (callable $get) {
                                                    $divisionId = $get('division_id');
                                                    if (!$divisionId) return [];
                                                    return OrgStructure::query()
                                                        ->where('parent_id', $divisionId)
                                                        ->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value)
                                                        ->pluck('name', 'id')
                                                        ->toArray();
                                                })
                                                ->live()
                                                ->searchable()
                                                ->disabled(fn (callable $get, $record) => !$get('division_id') || $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                                ->afterStateHydrated(function ($set, $record) {
                                                    if (!$record?->org_structure_id) return;

                                                    $org = OrgStructure::find($record->org_structure_id);
                                                    if ($org?->level === 3) $set('sub_division_id', $org->id);
                                                })
                                                ->afterStateUpdated(function (callable $set, $state) {
                                                    // Update org_structure_id when sub_division changes
                                                    $set('org_structure_id', $state);
                                                })
                                                ->dehydrated(false),

                                            // 🔹 Hidden field untuk org_structure_id final
                                            Hidden::make('org_structure_id')
                                                ->dehydrated(true)
                                                ->afterStateHydrated(function ($state, $set, $record) {
                                                    // Set default value from existing record if available
                                                    if ($record?->org_structure_id) {
                                                        $set('org_structure_id', $record->org_structure_id);
                                                    }
                                                })
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, $get, $set) {
                                                    // Update org_structure_id based on the last filled field
                                                    $orgStructureId = $get('sub_division_id')
                                                        ?? $get('division_id')
                                                        ?? $get('department_id');

                                                    if ($orgStructureId) {
                                                        $set('org_structure_id', $orgStructureId);
                                                    }
                                                })
                                                ->live(true),
                                        ]),

                                    ]),
                                Section::make('Detail Jabatan')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Jabatan')
                                            ->placeholder('Contoh: HR Officer, Finance Manager')
                                            ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                            ->required(),

                                        Select::make('parent_id')
                                            ->label('Atasan Langsung')
                                            ->helperText('Opsional: Pilih atasan langsung untuk jabatan ini')
                                            ->relationship('parent', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                            ->placeholder('Pilih atasan (opsional)'),

                                        Textarea::make('description')
                                            ->label('Deskripsi Jabatan')
                                            ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                            ->placeholder('Deskripsikan tugas dan tanggung jawab untuk jabatan ini (opsional)'),

                                        RichEditor::make('qualifications')
                                            ->label('Kualifikasi Jabatan')
                                            ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                            ->placeholder('Jelaskan kualifikasi yang dibutuhkan untuk jabatan ini (opsional)'),
                                    ])
                        ]),

                    Step::make('Job Evaluation')
                        ->schema([
                            Section::make('Job Evaluation')
                                ->columns(3)
                                ->schema([
                                    Select::make('pangkat_id')
                                        ->label('Pangkat')
                                        ->relationship('pangkat')
                                        ->options(fn () => Pangkats::where(
                                                'company_id', Auth::user()->company_id
                                            )->pluck('name', 'id'))
                                        ->preload()
                                        ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                        ->searchable(),

                                    Select::make('golongan_id')
                                        ->label('Golongan')
                                        ->relationship('golongan')
                                        ->options(fn () => Golongans::where(
                                                'company_id', Auth::user()->company_id
                                            )->pluck('name', 'id'))
                                        ->preload()
                                        ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                        ->searchable(),

                                    Select::make('kelas_id')
                                        ->label('Kelas')
                                        ->relationship('kelas')
                                        ->options(fn () => Kelas::where(
                                                'company_id', Auth::user()->company_id
                                            )->pluck('name', 'id'))
                                        ->searchable()
                                        ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                        ->preload(),

                                    TextInput::make('job_evaluation_score')
                                        ->label('Skor Penilaian Jabatan')
                                        ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                        ->numeric()
                                        ->minValue(0),

                                    Select::make('role_id')
                                        ->label('Role Default')
                                        ->helperText('Role yang disarankan otomatis saat user dipromosikan/dimutasi ke jabatan ini. Opsional, tetap bisa diganti manual saat itu terjadi.')
                                        ->relationship('defaultRole', 'name')
                                        ->options(fn () => \App\Models\Role::where(
                                                'company_id', Auth::user()->company_id
                                            )->pluck('name', 'id'))
                                        ->preload()
                                        ->searchable()
                                        ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                ])
                        ]),

//                    Step::make('Komponen Gaji')
//                        ->schema([
//                            Repeater::make('salaryComponentPivots')
//                                ->label('Komponen Gaji')
//                                ->relationship('salaryComponentPivots')
//                                ->minItems(1)
//                                ->addable(fn ($record) => $record && $record->status === JobPositionReqStatus::DRAFT->value)
//                                ->schema([
//                                    Select::make('salary_component_id')
//                                        ->label('Komponen Gaji')
//                                        ->options(function () {
//                                            return SalaryComponents::where('is_active', true)
//                                                ->pluck('name', 'id')
//                                                ->toArray();
//                                        })
//                                        ->searchable()
//                                        ->preload()
//                                        ->required()
//                                        ->reactive()
//                                        ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
//                                        ->afterStateUpdated(function ($state, callable $set) {
//                                            // Reset amount dan percentage saat komponen gaji berubah
//                                            $set('amount', null);
//                                            $set('percentage', null);
//
//                                            // Set default value jika ada
//                                            if ($state) {
//                                                $component = SalaryComponents::find($state);
//                                                if ($component && $component->default_value) {
//                                                    if ($component->calculation_type === 'fixed') {
//                                                        $set('amount', $component->default_value);
//                                                    } elseif ($component->calculation_type === 'percentage') {
//                                                        $set('percentage', $component->default_value);
//                                                    }
//                                                }
//                                            }
//                                        }),
//
//                                    TextInput::make('amount')
//                                        ->label('Jumlah Nominal')
//                                        ->helperText('Masukkan nominal tetap untuk komponen gaji ini')
//                                        ->numeric()
//                                        ->prefix('Rp')
//                                        ->step(0.01)
//                                        ->minValue(0)
//                                        ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
//                                        ->visible(function (callable $get) {
//                                            $salaryComponentId = $get('salary_component_id');
//                                            if (!$salaryComponentId) return false;
//
//                                            $component = SalaryComponents::find($salaryComponentId);
//                                            return $component && $component->calculation_type === 'fixed';
//                                        })
//                                        ->required(function (callable $get) {
//                                            $salaryComponentId = $get('salary_component_id');
//                                            if (!$salaryComponentId) return false;
//
//                                            $component = SalaryComponents::find($salaryComponentId);
//                                            return $component && $component->calculation_type === 'fixed';
//                                        }),
//
//                                    TextInput::make('percentage')
//                                        ->label('Persentase (%)')
//                                        ->helperText('Masukkan persentase yang akan dikalikan dengan gaji pokok')
//                                        ->numeric()
//                                        ->suffix('%')
//                                        ->step(0.01)
//                                        ->minValue(0)
//                                        ->maxValue(100)
//                                        ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
//                                        ->visible(function (callable $get) {
//                                            $salaryComponentId = $get('salary_component_id');
//                                            if (!$salaryComponentId) return false;
//
//                                            $component = SalaryComponents::find($salaryComponentId);
//                                            return $component && $component->calculation_type === 'percentage';
//                                        })
//                                        ->required(function (callable $get) {
//                                            $salaryComponentId = $get('salary_component_id');
//                                            if (!$salaryComponentId) return false;
//
//                                            $component = SalaryComponents::find($salaryComponentId);
//                                            return $component && $component->calculation_type === 'percentage';
//                                        }),
//
//                                    TextInput::make('formula_info')
//                                        ->label('Informasi Formula')
//                                        ->disabled()
//                                        ->visible(function (callable $get) {
//                                            $salaryComponentId = $get('salary_component_id');
//                                            if (!$salaryComponentId) return false;
//
//                                            $component = SalaryComponents::find($salaryComponentId);
//                                            return $component && $component->calculation_type === 'formula';
//                                        })
//                                        ->afterStateHydrated(function (callable $get, callable $set) {
//                                            $salaryComponentId = $get('salary_component_id');
//                                            if ($salaryComponentId) {
//                                                $component = SalaryComponents::find($salaryComponentId);
//                                                if ($component && $component->calculation_type === 'formula') {
//                                                    $set('formula_info', 'Komponen ini menggunakan formula otomatis: ' . ($component->formula_key ?? 'Belum ditentukan'));
//                                                }
//                                            }
//                                        })
//                                        ->dehydrated(false),
//
//                                    TextInput::make('is_overrideable')
//                                        ->label('Dapat Diubah')
//                                        ->default(true)
//                                        ->hidden(),
//                                ])
//                                ->columnSpanFull()
//                                ->minItems(1)
//                                ->required()
//                                ->addActionLabel('Tambah Komponen Gaji'),
//                        ]),

                    Step::make('Work Load Analysis')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('planned_manpower')
                                        ->label('Kuota Karyawan')
                                        ->numeric()
                                        ->columns(1)
                                        ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                        ->minValue(0)
                                        ->required(),

                                    TextInput::make('actual_manpower')
                                        ->label('Realisasi Karyawan')
                                        ->numeric()
                                        ->columns(1)
                                        ->readOnly()
                                        ->minValue(0)
                                ]),

                            FileUpload::make('file_workload_analysis')
                                ->label('File Analisis Beban Kerja')
                                ->helperText('Unggah file analisis beban kerja untuk jabatan ini (opsional)')
                                ->disk('public')
                                ->openable()
                                ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                ->directory('workload-analysis')
                                ->visibility('public'),
                        ])
                ])
                ->columnSpanFull()
                ->skippable()
            ]);
    }
}
