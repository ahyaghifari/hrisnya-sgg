<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances\Schemas;

use App\Enum\OrgStructurLevel;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ExtraHourAllowancesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Form Tunjangan Kelebihan Jam Kerja')
                    ->description('Atur tunjangan kelebihan jam kerja berdasarkan jabatan atau unit organisasi.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('company_id')
                            ->default(fn (): mixed => Auth::user()->company_id)
                            ->dehydrated(),

                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->options(Companies::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => Auth::user()->company_id)
                            ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user()))
                            ->reactive()
                            ->columnSpanFull(),

                        Select::make('allowanceable_type')
                            ->label('Tipe Tunjangan')
                            ->options([
                                JobPositions::class => 'Jabatan',
                                OrgStructure::class => 'Divisi',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('allowanceable_id', null)),

                        Select::make('allowanceable_id')
                            ->label('Target Tunjangan')
                            ->required()
                            ->searchable()
                            ->options(function (callable $get) {
                                $type = $get('allowanceable_type');
                                $companyId = $get('company_id') ?? Auth::user()->company_id;

                                if ($type === JobPositions::class) {
                                    return JobPositions::where('company_id', $companyId)
                                        ->pluck('name', 'id');
                                }

                                if ($type === OrgStructure::class) {
                                    return OrgStructure::where('company_id', $companyId)
                                        ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                                        ->pluck('name', 'id');
                                }

                                return [];
                            }),

                        TextInput::make('amount_per_hour')
                            ->label('Tunjangan Per Jam (Rp)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
