<?php

namespace App\Filament\Resources\Master\SubDivisions\Schemas;

use App\Enum\OrgStructurLevel;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Master\OrgStructure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SubDivisionsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Detail Sub Divisi")
                    ->description("Masukkan informasi detail mengenai sub divisi.")
                    ->columnSpanFull()
                    ->schema([
                        Hidden::make('level')
                            ->default(OrgStructurLevel::LEVEL_SUBDIVISION->value),

                        Select::make("company_id")
                            ->label("Perusahaan")
                            ->relationship("company", "name")
                            ->searchable()
                            ->required()
                            ->preload()
                            ->placeholder("Pilih perusahaan")
                            ->options(function () {
                                $user = Auth::user();
                                if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
                                    return Companies::where('id', $user->company_id)
                                        ->pluck('name', 'id');
                                }
                                return Companies::pluck('name', 'id');
                            })
                            ->default(function () {
                                $user = Auth::user();
                                return $user && $user->company_id ? $user->company_id : null;
                            })
                            ->disabled(function () {
                                $user = Auth::user();
                                return $user && $user->company_id && !PermissionHelper::hasGlobalRole($user);
                            }),

                        Select::make("department_id")
                            ->label("Departemen")
                            ->searchable()
                            ->required()
                            ->preload()
                            ->placeholder("Pilih departemen")
                            ->options(function ($get) {
                                $companyId = $get('company_id');
                                if (!$companyId) {
                                    return [];
                                }
                                return OrgStructure::where('company_id', $companyId)
                                    ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            }),

                        Select::make("parent_id")
                            ->label("Divisi")
                            ->relationship("parent", "name")
                            ->searchable()
                            ->required()
                            ->preload()
                            ->placeholder("Pilih divisi")
                            ->options(function ($get) {
                                $departmentId = $get('department_id');
                                if (!$departmentId) {
                                    return [];
                                }
                                return OrgStructure::where('parent_id', $departmentId)
                                    ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            }),

                        TextInput::make('name')
                            ->label('Nama Sub Divisi')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Masukkan nama sub divisi'),

                        TextInput::make('capacity')
                            ->label('Kapasitas')
                            ->helperText('Otomatis terisi berdasarkan jumlah karyawan yang ditugaskan ke sub divisi ini.')
                            ->numeric()
                            ->disabled()
                    ])
            ]);
    }
}
