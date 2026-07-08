<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods;

use App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages\CreateDoctorPayrollPeriod;
use App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages\EditDoctorPayrollPeriod;
use App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages\ListDoctorPayrollPeriods;
use App\Filament\Resources\Payroll\DoctorPayrollPeriods\Pages\ViewDoctorPayrollPeriod;
use App\Filament\Resources\Payroll\DoctorPayrollPeriods\RelationManagers\PayrollsRelationManager;
use App\Filament\Resources\Payroll\DoctorPayrollPeriods\Schemas\DoctorPayrollPeriodForm;
use App\Filament\Resources\Payroll\DoctorPayrollPeriods\Schemas\DoctorPayrollPeriodInfolist;
use App\Filament\Resources\Payroll\DoctorPayrollPeriods\Tables\DoctorPayrollPeriodsTable;
use App\Helpers\PermissionHelper;
use App\Models\Payroll\DoctorPayrollPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DoctorPayrollPeriodResource extends Resource
{
    protected static ?string $model = DoctorPayrollPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = "Doctor Payroll";

    public static function form(Schema $schema): Schema
    {
        return DoctorPayrollPeriodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DoctorPayrollPeriodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorPayrollPeriodsTable::configure($table);
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
            'index' => ListDoctorPayrollPeriods::route('/'),
            'create' => CreateDoctorPayrollPeriod::route('/create'),
            'view' => ViewDoctorPayrollPeriod::route('/{record}'),
            'edit' => EditDoctorPayrollPeriod::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest();

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
