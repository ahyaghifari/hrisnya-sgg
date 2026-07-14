<?php

namespace App\Filament\Resources\Payroll\ExtraHourAllowances;

use App\Filament\Resources\Payroll\ExtraHourAllowances\Pages\CreateExtraHourAllowances;
use App\Filament\Resources\Payroll\ExtraHourAllowances\Pages\EditExtraHourAllowances;
use App\Filament\Resources\Payroll\ExtraHourAllowances\Pages\ListExtraHourAllowances;
use App\Filament\Resources\Payroll\ExtraHourAllowances\Schemas\ExtraHourAllowancesForm;
use App\Filament\Resources\Payroll\ExtraHourAllowances\Tables\ExtraHourAllowancesTable;
use App\Filament\Clusters\MasterSalaryComponent\MasterSalaryComponentCluster;
use App\Models\Shift\ExtraHourAllowanceRules;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExtraHourAllowancesResource extends Resource
{
    protected static ?string $model = ExtraHourAllowanceRules::class;

    protected static ?string $cluster = MasterSalaryComponentCluster::class;

    protected static ?string $navigationLabel = "Tunjangan Kelebihan Jam";

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;


    public static function form(Schema $schema): Schema
    {
        return ExtraHourAllowancesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExtraHourAllowancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtraHourAllowances::route('/'),
            'create' => CreateExtraHourAllowances::route('/create'),
            'edit' => EditExtraHourAllowances::route('/{record}/edit'),
        ];
    }
}
