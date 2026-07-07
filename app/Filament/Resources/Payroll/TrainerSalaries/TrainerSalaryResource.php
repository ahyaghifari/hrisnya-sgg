<?php

namespace App\Filament\Resources\Payroll\TrainerSalaries;

use App\Filament\Resources\Payroll\TrainerSalaries\Pages\CreateTrainerSalary;
use App\Filament\Resources\Payroll\TrainerSalaries\Pages\EditTrainerSalary;
use App\Filament\Resources\Payroll\TrainerSalaries\Pages\ListTrainerSalaries;
use App\Filament\Resources\Payroll\TrainerSalaries\Schemas\TrainerSalaryForm;
use App\Filament\Resources\Payroll\TrainerSalaries\Tables\TrainerSalariesTable;
use App\Models\LMS\Trainer\TrainerAssesmentsResults;
use App\Models\Payroll\TrainerSalary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class TrainerSalaryResource extends Resource
{
    protected static ?string $model = TrainerAssesmentsResults::class;

    protected static ?string $navigationLabel = "Gaji Trainer";

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

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

    public static function canAccess(): bool
    {
        return Gate::allows('view_gaji_trainner');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_gaji_trainner');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_gaji_trainner');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_gaji_trainner');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_gaji_trainner');
    }
}
