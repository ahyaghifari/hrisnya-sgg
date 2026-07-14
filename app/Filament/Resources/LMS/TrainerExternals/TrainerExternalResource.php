<?php

namespace App\Filament\Resources\LMS\TrainerExternals;

use App\Filament\Resources\LMS\TrainerExternals\Pages\CreateTrainerExternal;
use App\Filament\Resources\LMS\TrainerExternals\Pages\EditTrainerExternal;
use App\Filament\Resources\LMS\TrainerExternals\Pages\ListTrainerExternals;
use App\Filament\Resources\LMS\TrainerExternals\Schemas\TrainerExternalForm;
use App\Filament\Resources\LMS\TrainerExternals\Tables\TrainerExternalsTable;
use App\Models\LMS\Trainer\Trainers;
use App\Models\LMS\TrainerExternal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrainerExternalResource extends Resource
{
    protected static ?string $model = Trainers::class;

    protected static ?string $slug = 'lms/trainer-externals';

    protected static ?string $navigationLabel = "Trainer";

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TrainerExternalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainerExternalsTable::configure($table);
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
            'index' => ListTrainerExternals::route('/'),
            'create' => CreateTrainerExternal::route('/create'),
            'edit' => EditTrainerExternal::route('/{record}/edit'),
        ];
    }
}
