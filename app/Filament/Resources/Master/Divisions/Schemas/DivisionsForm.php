<?php

namespace App\Filament\Resources\Master\Divisions\Schemas;

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

class DivisionsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Informasi Divisi")
                    ->description("Masukkan informasi divisi di bawah ini.")
                    ->columnSpanFull()
                    ->schema([
                        Hidden::make('level')
                            ->default(OrgStructurLevel::LEVEL_DIVISION->value),
                            
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
                            })
                            ->validationMessages([
                                'required' => 'Perusahaan wajib diisi.',
                            ]),

                        Select::make("parent_id")
                            ->label("Departemen")
                            ->relationship("parent", "name")
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
                                    ->pluck('name', 'id');
                            })
                            ->validationMessages([
                                'required' => 'Departemen wajib diisi.',
                            ]),

                        TextInput::make('name')
                            ->label('Nama Divisi')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Masukkan nama divisi')
                            ->validationMessages([
                                'required' => 'Nama divisi wajib diisi.',
                                'max' => 'Panjang maksimal nama divisi adalah 255 karakter.',
                            ]),

                        TextInput::make('capacity')
                            ->label('Kapasitas')
                            ->helperText('Otomatis terisi berdasarkan jumlah karyawan yang ditugaskan ke departemen ini.')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Masukkan kapasitas') 
                            ->disabled()
                    ]),
            ]);
    }
}
