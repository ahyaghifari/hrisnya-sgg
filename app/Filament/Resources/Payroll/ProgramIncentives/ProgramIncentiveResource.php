<?php

namespace App\Filament\Resources\Payroll\ProgramIncentives;

use App\Filament\Resources\Payroll\ProgramIncentives\Pages\CreateProgramIncentive;
use App\Filament\Resources\Payroll\ProgramIncentives\Pages\EditProgramIncentive;
use App\Filament\Resources\Payroll\ProgramIncentives\Pages\ListProgramIncentives;
use App\Filament\Resources\Payroll\ProgramIncentives\Pages\ViewProgramIncentive;
use App\Filament\Resources\Payroll\ProgramIncentives\Schemas\ProgramIncentiveForm;
use App\Filament\Resources\Payroll\ProgramIncentives\Schemas\ProgramIncentiveInfolist;
use App\Filament\Resources\Payroll\ProgramIncentives\Tables\ProgramIncentivesTable;
use App\Models\Incentive\ProgramIncentive;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProgramIncentiveResource extends Resource
{
    protected static ?string $model = ProgramIncentive::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    public static function form(Schema $schema): Schema
    {
        return ProgramIncentiveForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProgramIncentiveInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgramIncentivesTable::configure($table);
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
            'index' => ListProgramIncentives::route('/'),
            //'create' => CreateProgramIncentive::route('/create'),
            //'view' => ViewProgramIncentive::route('/{record}'),
            //'edit' => EditProgramIncentive::route('/{record}/edit'),
        ];
    }
}
