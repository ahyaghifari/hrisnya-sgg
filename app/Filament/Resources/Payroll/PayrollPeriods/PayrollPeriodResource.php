<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods;

use App\Filament\Resources\Payroll\PayrollPeriods\Pages\CreatePayrollPeriod;
use App\Filament\Resources\Payroll\PayrollPeriods\Pages\EditPayrollPeriod;
use App\Filament\Resources\Payroll\PayrollPeriods\Pages\ListPayrollPeriods;
use App\Filament\Resources\Payroll\PayrollPeriods\Pages\ViewPayrollPeriod;
use App\Filament\Resources\Payroll\PayrollPeriods\RelationManagers\PayrollsRelationManager;
use App\Filament\Resources\Payroll\PayrollPeriods\Schemas\PayrollPeriodForm;
use App\Filament\Resources\Payroll\PayrollPeriods\Schemas\PayrollPeriodInfolist;
use App\Filament\Resources\Payroll\PayrollPeriods\Tables\PayrollPeriodsTable;
use App\Models\Payroll\PayrollPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PayrollPeriodResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;

    protected static ?string $navigationLabel = "Periode Penggajian";

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return PayrollPeriodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayrollPeriodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollPeriodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PayrollsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPeriods::route('/'),
            'create' => CreatePayrollPeriod::route('/create'),
            'view' => ViewPayrollPeriod::route('/{record}'),
            'edit' => EditPayrollPeriod::route('/{record}/edit'),
        ];
    }
}
