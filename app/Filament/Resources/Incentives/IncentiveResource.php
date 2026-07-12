<?php

namespace App\Filament\Resources\Incentives;

use App\Filament\Resources\Incentives\Pages\ListIncentives;
use App\Filament\Resources\Incentives\Schemas\IncentiveForm;
use App\Filament\Resources\Incentives\Schemas\IncentiveInfolist;
use App\Filament\Resources\Incentives\Tables\IncentivesTable;
use App\Models\Incentive\Incentive;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IncentiveResource extends Resource
{
    protected static ?string $model = Incentive::class;

    protected static ?string $navigationLabel = "Insentif";

    protected static ?string $modelLabel = 'Insentif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return IncentiveForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IncentiveInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncentivesTable::configure($table);
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
            'index' => ListIncentives::route('/'),
        ];
    }
}
