<?php

namespace App\Filament\Resources\SalaryComponents;

use App\Filament\Resources\SalaryComponents\Pages\ListSalaryComponents;
use App\Filament\Resources\SalaryComponents\Schemas\SalaryComponentForm;
use App\Filament\Resources\SalaryComponents\Tables\SalaryComponentsTable;
use App\Models\SalaryComponent\SalaryComponents;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalaryComponentResource extends Resource
{
    protected static ?string $model = SalaryComponents::class;

    protected static ?string $navigationLabel = "Komponen Gaji";

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function form(Schema $schema): Schema
    {
        return SalaryComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
    return SalaryComponentsTable::configure($table);
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
            'index' => ListSalaryComponents::route('/'),
        ];
    }
}
