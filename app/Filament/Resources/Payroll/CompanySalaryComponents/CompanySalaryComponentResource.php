<?php

namespace App\Filament\Resources\Payroll\CompanySalaryComponents;

use App\Enum\Salary\SalaryComponentNameEnum;
use App\Filament\Clusters\MasterSalaryComponent\MasterSalaryComponentCluster;
use App\Filament\Resources\Payroll\CompanySalaryComponents\Pages\CreateCompanySalaryComponent;
use App\Filament\Resources\Payroll\CompanySalaryComponents\Pages\EditCompanySalaryComponent;
use App\Filament\Resources\Payroll\CompanySalaryComponents\Pages\ListCompanySalaryComponents;
use App\Filament\Resources\Payroll\CompanySalaryComponents\Schemas\CompanySalaryComponentForm;
use App\Filament\Resources\Payroll\CompanySalaryComponents\Tables\CompanySalaryComponentsTable;
use App\Models\Master\Companies;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompanySalaryComponentResource extends Resource
{
    protected static ?string $model = Companies::class;

    protected static ?string $navigationLabel = "Komponen Gaji";

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = MasterSalaryComponentCluster::class;

    public static function form(Schema $schema): Schema
    {
        return CompanySalaryComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanySalaryComponentsTable::configure($table);
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
            'index' => ListCompanySalaryComponents::route('/'),
            //'create' => CreateCompanySalaryComponent::route('/create'),
            //'edit' => EditCompanySalaryComponent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return collect(SalaryComponentNameEnum::companyComponents())
            ->map(function ($enum) {
                return NavigationItem::make($enum->label())
                    ->label($enum->label())
                    ->url(static::getUrl('index', ['type' => $enum->value]))
                    ->icon(static::getNavigationIcon())
                    ->isActiveWhen(fn () => request()->get('type') === $enum->value);
            })
            ->toArray();
    }
}
