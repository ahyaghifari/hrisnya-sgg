<?php

namespace App\Filament\Resources\Payroll\GeneralSalaryComponents;

use App\Enum\Salary\SalaryComponentNameEnum;
use App\Filament\Clusters\MasterSalaryComponent\MasterSalaryComponentCluster;
use App\Filament\Resources\Payroll\GeneralSalaryComponents\Pages\CreateGeneralSalaryComponent;
use App\Filament\Resources\Payroll\GeneralSalaryComponents\Pages\EditGeneralSalaryComponent;
use App\Filament\Resources\Payroll\GeneralSalaryComponents\Pages\ListGeneralSalaryComponents;
use App\Filament\Resources\Payroll\GeneralSalaryComponents\Schemas\GeneralSalaryComponentForm;
use App\Filament\Resources\Payroll\GeneralSalaryComponents\Tables\GeneralSalaryComponentsTable;
use App\Filament\Resources\Payroll\MasterSalaryComponents\Pages\ListMasterSalaryComponents;
use App\Filament\Resources\Payroll\MasterSalaryComponents\Tables\MasterSalaryComponentsTable;
use App\Models\Payroll\MasterSalaryComponents;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GeneralSalaryComponentResource extends Resource
{
    protected static ?string $model = MasterSalaryComponents::class;

    protected static ?string $navigationLabel = "Komponen Gaji Jabatan";

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $cluster = MasterSalaryComponentCluster::class;

    public static function form(Schema $schema): Schema
    {
        return GeneralSalaryComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralSalaryComponentsTable::configure($table)
            ->modifyQueryUsing(function (Builder $query, ListGeneralSalaryComponents $livewire) {
                if ($livewire->type) {
                    $query->where('name', $livewire->type);
                }
            });
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
            'index' => ListGeneralSalaryComponents::route('/'),
            //'create' => CreateGeneralSalaryComponent::route('/create'),
            //'edit' => EditGeneralSalaryComponent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return collect(SalaryComponentNameEnum::generalComponents())
            ->map(function ($enum) {
                return NavigationItem::make($enum->label())
                    ->label($enum->label())
                    ->sort(2)
                    ->url(static::getUrl('index', ['type' => $enum->value]))
                    ->icon(static::getNavigationIcon())
                    ->isActiveWhen(fn () => request()->get('type') === $enum->value);
            })
            ->toArray();
    }
}
