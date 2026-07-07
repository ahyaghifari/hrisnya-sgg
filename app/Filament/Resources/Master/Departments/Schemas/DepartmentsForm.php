<?php

namespace App\Filament\Resources\Master\Departments\Schemas;

use App\Enum\OrgStructurLevel;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DepartmentsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Informasi Departemen")
                    ->description("Lengkapi informasi departemen di bawah ini.")
                    ->columnSpanFull()
                    ->schema([
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Pilih Perusahaan')
                            ->columnSpanFull()
                            ->options(function () {
                                $user = Auth::user();
                                if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
                                    return Companies::where('id', $user->company_id)
                                        ->pluck('name', 'id');
                                }
                                return Companies::pluck('name', 'id');
                            })
                            ->visible(function () {
                                $user = Auth::user();
                                return PermissionHelper::hasGlobalRole($user);
                            }),

                        Hidden::make('level')
                            ->default(OrgStructurLevel::LEVEL_DEPARTMENT->value),

                        TextInput::make('name')
                            ->label('Nama Departemen')
                            ->required()
                            ->validationMessages([
                                'required' => 'Nama Departemen wajib diisi.',
                            ])
                            ->placeholder('Masukkan Nama Departemen')
                            ->columnSpanFull(),

                        // Select::make('head_id')
                        //     ->label('Kepala Departemen')
                        //     ->relationship('head', 'name')
                        //     ->searchable()
                        //     ->preload()
                        //     ->placeholder('Pilih Kepala Departemen')
                        //     ->columnSpanFull()
                        //     ->options(function (callable $get) {
                        //         $user = Auth::user();
                        //         $companyId = $user && $user->company_id ? $user->company_id : $get('company_id');
                                
                        //         if ($companyId) {
                        //             return \App\Models\User::where('company_id', $companyId)
                        //                 ->pluck('name', 'id');
                        //         }
                        //         return \App\Models\User::pluck('name', 'id');
                        //     }),

                        TextInput::make('capacity')
                            ->label('Kapasitas')
                            ->helperText('Otomatis terisi berdasarkan jumlah karyawan yang ditugaskan ke departemen ini.')
                            ->disabled()
                            ->placeholder('Masukkan Kapasitas Departemen')
                            ->columnSpanFull()
                            ->numeric(),
                    ]),
            ]);
    }
}
