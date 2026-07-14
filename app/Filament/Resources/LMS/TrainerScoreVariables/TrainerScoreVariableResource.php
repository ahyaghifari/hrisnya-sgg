<?php

namespace App\Filament\Resources\LMS\TrainerScoreVariables;

use App\Filament\Clusters\MasterSalaryComponent\MasterSalaryComponentCluster;
use App\Filament\Resources\LMS\TrainerScoreVariables\Pages\CreateTrainerScoreVariable;
use App\Filament\Resources\LMS\TrainerScoreVariables\Pages\EditTrainerScoreVariable;
use App\Filament\Resources\LMS\TrainerScoreVariables\Pages\ListTrainerScoreVariables;
use App\Filament\Resources\LMS\TrainerScoreVariables\Schemas\TrainerScoreVariableForm;
use App\Filament\Resources\LMS\TrainerScoreVariables\Tables\TrainerScoreVariablesTable;
use App\Models\LMS\Trainer\TrainerScoreVariables;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrainerScoreVariableResource extends Resource
{
    protected static ?string $model = TrainerScoreVariables::class;

    protected static ?string $cluster = MasterSalaryComponentCluster::class;

    protected static ?string $slug = 'lms/trainer-score-variables';

    protected static ?string $navigationLabel = "Variabel Gaji Trainer";

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public static function form(Schema $schema): Schema
    {
        return TrainerScoreVariableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainerScoreVariablesTable::configure($table);
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
            'index' => ListTrainerScoreVariables::route('/'),
            'create' => CreateTrainerScoreVariable::route('/create'),
            'edit' => EditTrainerScoreVariable::route('/{record}/edit'),
        ];
    }
}
