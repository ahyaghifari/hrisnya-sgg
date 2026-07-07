<?php

namespace App\Filament\Resources\Payroll\TrainerScoreVariables;

use App\Filament\Clusters\MasterSalaryComponent\MasterSalaryComponentCluster;
use App\Filament\Resources\Payroll\TrainerScoreVariables\Pages\CreateTrainerScoreVariable;
use App\Filament\Resources\Payroll\TrainerScoreVariables\Pages\EditTrainerScoreVariable;
use App\Filament\Resources\Payroll\TrainerScoreVariables\Pages\ListTrainerScoreVariables;
use App\Filament\Resources\Payroll\TrainerScoreVariables\Schemas\TrainerScoreVariableForm;
use App\Filament\Resources\Payroll\TrainerScoreVariables\Tables\TrainerScoreVariablesTable;
use App\Models\LMS\Trainer\TrainerScoreVariables;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrainerScoreVariableResource extends Resource
{
    protected static ?string $model = TrainerScoreVariables::class;

    protected static ?string $navigationLabel = "Variabel Gaji Trainer";

    protected static ?string $cluster = MasterSalaryComponentCluster::class;

    protected static ?int $navigationSort = 3;

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
