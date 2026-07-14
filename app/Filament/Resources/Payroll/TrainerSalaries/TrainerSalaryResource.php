<?php

namespace App\Filament\Resources\Payroll\TrainerSalaries;

use App\Filament\Resources\Payroll\TrainerSalaries\Pages\CreateTrainerSalary;
use App\Filament\Resources\Payroll\TrainerSalaries\Pages\EditTrainerSalary;
use App\Filament\Resources\Payroll\TrainerSalaries\Pages\ListTrainerSalaries;
use App\Filament\Resources\Payroll\TrainerSalaries\Schemas\TrainerSalaryForm;
use App\Filament\Resources\Payroll\TrainerSalaries\Tables\TrainerSalariesTable;
use App\Models\LMS\Trainer\TrainerAssessmentResults;
use App\Models\Payroll\TrainerSalary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TrainerSalaryResource extends Resource
{
    protected static ?string $model = TrainerAssessmentResults::class;

    protected static ?string $navigationLabel = "Gaji Trainer";

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TrainerSalaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainerSalariesTable::configure($table);
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
            'index' => ListTrainerSalaries::route('/'),
            // 'create' => CreateTrainerSalary::route('/create'),
            // 'edit' => EditTrainerSalary::route('/{record}/edit'),
        ];
    }
}
