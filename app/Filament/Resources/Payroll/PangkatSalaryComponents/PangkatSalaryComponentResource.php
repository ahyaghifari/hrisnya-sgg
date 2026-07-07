<?php

namespace App\Filament\Resources\Payroll\PangkatSalaryComponents;

use App\Enum\Salary\SalaryComponentNameEnum;
use App\Filament\Clusters\MasterSalaryComponent\MasterSalaryComponentCluster;
use App\Filament\Resources\Payroll\PangkatSalaryComponents\Pages\CreatePangkatSalaryComponent;
use App\Filament\Resources\Payroll\PangkatSalaryComponents\Pages\EditPangkatSalaryComponent;
use App\Filament\Resources\Payroll\PangkatSalaryComponents\Pages\ListPangkatSalaryComponents;
use App\Filament\Resources\Payroll\PangkatSalaryComponents\Schemas\PangkatSalaryComponentForm;
use App\Filament\Resources\Payroll\PangkatSalaryComponents\Tables\PangkatSalaryComponentsTable;
use App\Models\Master\JobPositions;
use App\Models\Master\Pangkats;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PangkatSalaryComponentResource extends Resource
{
    protected static ?string $model = Pangkats::class;

    protected static ?string $navigationLabel = "Komponen Gaji";

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = MasterSalaryComponentCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function form(Schema $schema): Schema
    {
        return PangkatSalaryComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PangkatSalaryComponentsTable::configure($table);
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
            'index' => ListPangkatSalaryComponents::route('/'),
            //'create' => CreatePangkatSalaryComponent::route('/create'),
            //'edit' => EditPangkatSalaryComponent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return collect(SalaryComponentNameEnum::pangkatComponents())
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
