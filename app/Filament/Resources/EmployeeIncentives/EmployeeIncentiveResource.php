<?php

namespace App\Filament\Resources\EmployeeIncentives;

use App\Filament\Resources\EmployeeIncentives\Pages\ListEmployeeIncentives;
use App\Filament\Resources\EmployeeIncentives\Schemas\EmployeeIncentiveForm;
use App\Filament\Resources\EmployeeIncentives\Schemas\EmployeeIncentiveInfolist;
use App\Filament\Resources\EmployeeIncentives\Tables\EmployeeIncentivesTable;
use App\Models\Employee\EmployeeIncentive;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmployeeIncentiveResource extends Resource
{
    protected static ?string $model = EmployeeIncentive::class;

    protected static ?string $navigationLabel = "Insentif";

    protected static ?string $modelLabel = 'Insentif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return EmployeeIncentiveForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeIncentiveInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeIncentivesTable::configure($table);
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
            'index' => ListEmployeeIncentives::route('/'),
        ];
    }
}
