<?php

namespace App\Filament\Resources\Payroll\TrainerScoreRanges;

use App\Filament\Resources\Payroll\TrainerScoreRanges\Pages\CreateTrainerScoreRanges;
use App\Filament\Resources\Payroll\TrainerScoreRanges\Pages\EditTrainerScoreRanges;
use App\Filament\Resources\Payroll\TrainerScoreRanges\Pages\ListTrainerScoreRanges;
use App\Filament\Resources\Payroll\TrainerScoreRanges\Schemas\TrainerScoreRangesForm;
use App\Filament\Resources\Payroll\TrainerScoreRanges\Tables\TrainerScoreRangesTable;
use App\Models\LMS\Trainer\TrainerScoreRanges;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrainerScoreRangesResource extends Resource
{
    protected static ?string $model = TrainerScoreRanges::class;

    protected static ?string $navigationLabel = "Skala Nilai Trainer";


    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public static function form(Schema $schema): Schema
    {
        return TrainerScoreRangesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainerScoreRangesTable::configure($table);
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
            'index' => ListTrainerScoreRanges::route('/'),
            'create' => CreateTrainerScoreRanges::route('/create'),
            'edit' => EditTrainerScoreRanges::route('/{record}/edit'),
        ];
    }
}
