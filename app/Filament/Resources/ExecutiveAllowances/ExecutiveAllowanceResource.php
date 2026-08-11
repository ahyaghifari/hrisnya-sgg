<?php

namespace App\Filament\Resources\ExecutiveAllowances;

use App\Filament\Resources\ExecutiveAllowances\Pages\ListExecutiveAllowances;
use App\Filament\Resources\ExecutiveAllowances\Schemas\ExecutiveAllowanceForm;
use App\Filament\Resources\ExecutiveAllowances\Schemas\ExecutiveAllowanceInfolist;
use App\Filament\Resources\ExecutiveAllowances\Tables\ExecutiveAllowancesTable;
use App\Models\Payroll\ExecutiveAllowance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExecutiveAllowanceResource extends Resource
{
    protected static ?string $model = ExecutiveAllowance::class;

    protected static ?string $navigationLabel = 'Tunjangan Eksekutif';

    protected static ?string $modelLabel = 'Tunjangan Eksekutif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return ExecutiveAllowanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExecutiveAllowanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExecutiveAllowancesTable::configure($table);
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
            'index' => ListExecutiveAllowances::route('/'),
        ];
    }
}
