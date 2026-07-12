<?php

namespace App\Filament\Resources\Payroll\OvertimeRates;

use App\Filament\Resources\Payroll\OvertimeRates\Pages\CreateOvertimeRates;
use App\Filament\Resources\Payroll\OvertimeRates\Pages\EditOvertimeRates;
use App\Filament\Resources\Payroll\OvertimeRates\Pages\ListOvertimeRates;
use App\Filament\Resources\Payroll\OvertimeRates\Schemas\OvertimeRatesForm;
use App\Filament\Resources\Payroll\OvertimeRates\Tables\OvertimeRatesTable;
use App\Models\Shift\ShiftOvertimeCosts;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OvertimeRatesResource extends Resource
{
    protected static ?string $model = ShiftOvertimeCosts::class;

    protected static ?string $navigationLabel = "Tarif Lembur";

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Organisasi';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;


    public static function form(Schema $schema): Schema
    {
        return OvertimeRatesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OvertimeRatesTable::configure($table);
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
            'index' => ListOvertimeRates::route('/'),
            'create' => CreateOvertimeRates::route('/create'),
            'edit' => EditOvertimeRates::route('/{record}/edit'),
        ];
    }
}
