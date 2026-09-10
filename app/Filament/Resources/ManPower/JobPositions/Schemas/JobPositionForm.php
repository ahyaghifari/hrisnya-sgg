<?php

namespace App\Filament\Resources\ManPower\JobPositions\Schemas;

use App\Enum\JobPositionReqStatus;
use App\Enum\OrgStructurLevel;
use App\Models\Master\Companies;
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
                        ->icon('heroicon-o-briefcase')
                        ->completedIcon('heroicon-s-briefcase')
                        ->schema(self::PositionDetailSchema()),

                    Step::make('Job Evaluation')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->completedIcon('heroicon-s-clipboard-document-check')
                        ->schema(self::jobEvaluationSchema()),
                    
                    Step::make('Work Load Analysis')
                        ->icon('heroicon-o-scale')
                        ->completedIcon('heroicon-s-scale')
                        ->schema(self::workLoadAnalysisSchema()),
                ])
                ->columnSpanFull()
                ->skippable()
            ]);
    }

    private static function PositionDetailSchema()
    {
        return [
            Section::make('Struktur Organisasi')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('company_id')
                                ->label('Perusahaan')
                                ->options(function ($record) {
                                    $userCompanyId = Auth::user()?->company_id;
                                    $companyId = $record?->company_id ?? $userCompanyId;

                                    if ($companyId) {
                                        return Companies::where('id', $companyId)->pluck('name', 'id');
                                    }

                                    return Companies::pluck('name', 'id');
                                })
                                ->default(fn () => Auth::user()?->company_id)
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
                                    if ($org?->level === OrgStructurLevel::LEVEL_DEPARTMENT->value) $set('department_id', $org->id);
                                    if ($org?->level === OrgStructurLevel::LEVEL_DIVISION->value) $set('department_id', $org->parent?->id);
                                    if ($org?->level === OrgStructurLevel::LEVEL_SUBDIVISION->value) $set('department_id', $org->parent?->parent?->id);
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
                                    if ($org?->level === OrgStructurLevel::LEVEL_DIVISION->value) $set('division_id', $org->id);
                                    if ($org?->level === OrgStructurLevel::LEVEL_SUBDIVISION->value) $set('division_id', $org->parent?->id);
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
                                    if ($org?->level === OrgStructurLevel::LEVEL_SUBDIVISION->value) $set('sub_division_id', $org->id);
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
                            ->options(function (callable $get) {
                                $companyId = $get('company_id');
                                if (!$companyId) return [];
                                return JobPositions::where('company_id', $companyId)->pluck('name', 'id')->toArray();
                            })
                            ->preload()
                            ->searchable()
                            ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                            ->placeholder('Pilih atasan (opsional)'),

                        RichEditor::make('description')
                            ->label('Deskripsi Jabatan')
                            ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                            ->placeholder('Deskripsikan tugas dan tanggung jawab untuk jabatan ini (opsional)'),

                        RichEditor::make('qualification')
                            ->label('Kualifikasi Jabatan')
                            ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                            ->placeholder('Jelaskan kualifikasi yang dibutuhkan untuk jabatan ini (opsional)'),
                    ])
        ];
    }

    private static function jobEvaluationSchema()
    {
        return [
            Grid::make(2)
                ->schema([
                    Section::make('Pangkat, Kelas')
                        ->schema([
                            Select::make('pangkat_id')
                                ->label('Pangkat')
                                ->relationship('pangkat')
                                ->options(function ($record) {
                                    $userCompanyId = Auth::user()->company_id;
                                    $companyId = $record?->company_id ?? $userCompanyId;
                                    return Pangkats::where(
                                        'company_id', $companyId
                                    )->orderByDesc('level')->pluck('name', 'id');
                                })
                                ->preload()
                                ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                ->searchable(),

                            Select::make('kelas_id')
                                ->label('Kelas')
                                ->relationship('kelas')
                                ->options(function ($record) {
                                    $userCompanyId = Auth::user()->company_id;
                                    $companyId = $record?->company_id ?? $userCompanyId;
                                    return Kelas::where(
                                        'company_id', $companyId
                                    )->pluck('name', 'id');
                                })
                                ->disabled(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                ->preload(),
                        ]),

                    Section::make('Skor Penilaian Jabatan')
                        ->columns(1)
                        ->schema([
                            TextInput::make('job_evaluation_score')
                                ->label('Skor Penilaian Jabatan')
                                ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                ->numeric()
                                ->helperText('Masukkan skor penilaian jabatan berdasarkan evaluasi yang telah dilakukan')
                                ->minValue(0)
                        ])
                ])
        ];
    }


    private static function workLoadAnalysisSchema()
    {
        return [
            Grid::make(2)
                ->schema([
                    Section::make('Manpower Kuota')
                        ->columns(1)
                        ->schema([
                            TextInput::make('planned_manpower')
                                ->label('Request Kuota')
                                ->numeric()
                                ->columns(1)
                                ->readOnly(fn ($record) => $record && $record->status !== JobPositionReqStatus::DRAFT->value)
                                ->minValue(0)
                                ->required(),
        
                            TextInput::make('actual_manpower')
                                ->label('Aktual Kuota')
                                ->helperText(fn ($record) => 'Jumlah aktual kuota karyawan saat ini | Terisi ' . $record?->userAssignments()->count())
                                ->numeric()
                                ->columns(1)
                                ->disabled()
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
                ]),
        ];
    }
}
