<?php

namespace App\Filament\Resources\EmployeeExecutiveAllowances;

use App\Filament\Resources\EmployeeExecutiveAllowances\Pages\ListEmployeeExecutiveAllowances;
use App\Filament\Resources\EmployeeExecutiveAllowances\Schemas\EmployeeExecutiveAllowanceForm;
use App\Filament\Resources\EmployeeExecutiveAllowances\Schemas\EmployeeExecutiveAllowanceInfolist;
use App\Filament\Resources\EmployeeExecutiveAllowances\Tables\EmployeeExecutiveAllowancesTable;
use App\Models\Employee\EmployeeExecutiveAllowance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmployeeExecutiveAllowanceResource extends Resource
{
    protected static ?string $model = EmployeeExecutiveAllowance::class;

    protected static ?string $navigationLabel = 'Eksekutif';

    protected static ?string $modelLabel = 'Eksekutif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return EmployeeExecutiveAllowanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeExecutiveAllowanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeExecutiveAllowancesTable::configure($table);
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
            'index' => ListEmployeeExecutiveAllowances::route('/'),
        ];
    }
}
