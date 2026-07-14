<?php

namespace App\Filament\Resources\LMS\TrainerScoreRanges;

use App\Filament\Resources\LMS\TrainerScoreRanges\Pages\CreateTrainerScoreRanges;
use App\Filament\Resources\LMS\TrainerScoreRanges\Pages\EditTrainerScoreRanges;
use App\Filament\Resources\LMS\TrainerScoreRanges\Pages\ListTrainerScoreRanges;
use App\Filament\Resources\LMS\TrainerScoreRanges\Schemas\TrainerScoreRangesForm;
use App\Filament\Resources\LMS\TrainerScoreRanges\Tables\TrainerScoreRangesTable;
use App\Models\LMS\Trainer\TrainerScoreRanges;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrainerScoreRangesResource extends Resource
{
    protected static ?string $model = TrainerScoreRanges::class;

    protected static ?string $slug = 'lms/trainer-score-ranges';

    protected static ?string $navigationLabel = "Skala Nilai Trainer";

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 6;

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
