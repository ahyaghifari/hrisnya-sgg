<?php

namespace App\Filament\Resources\Payroll\Payrolls;

use App\Filament\Resources\Payroll\Payrolls\Pages\CreatePayroll;
use App\Filament\Resources\Payroll\Payrolls\Pages\EditPayroll;
use App\Filament\Resources\Payroll\Payrolls\Pages\ListPayrolls;
use App\Filament\Resources\Payroll\Payrolls\Pages\ViewPayroll;
use App\Filament\Resources\Payroll\Payrolls\RelationManagers\PayrollItemsRelationManager;
use App\Filament\Resources\Payroll\Payrolls\Schemas\PayrollForm;
use App\Filament\Resources\Payroll\Payrolls\Schemas\PayrollInfolist;
use App\Filament\Resources\Payroll\Payrolls\Tables\PayrollsTable;
use App\Helpers\PermissionHelper;
use App\Models\Payroll\Payroll;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    public static function form(Schema $schema): Schema
    {
        return PayrollForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayrollInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PayrollItemsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrolls::route('/'),
            //'create' => CreatePayroll::route('/create'),
            'view' => ViewPayroll::route('/{record}'),
            //'edit' => EditPayroll::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest();

        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            $query->whereHas('payrollPeriod', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_payroll');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_payroll');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_payroll');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_payroll');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_payroll');
    }
}
