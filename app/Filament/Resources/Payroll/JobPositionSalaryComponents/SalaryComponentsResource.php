<?php

namespace App\Filament\Resources\Payroll\JobPositionSalaryComponents;

use App\Enum\Salary\SalaryComponentNameEnum;
use App\Filament\Resources\Payroll\JobPositionSalaryComponents\Pages\ListSalaryComponents;
use App\Filament\Resources\Payroll\JobPositionSalaryComponents\Schemas\SalaryComponentsForm;
use App\Filament\Resources\Payroll\JobPositionSalaryComponents\Tables\SalaryComponentsTable;
use App\Models\Master\JobPositions;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SalaryComponentsResource extends Resource
{
    protected static ?string $model = JobPositions::class;

    protected static ?string $navigationLabel = "Komponen Gaji";

    protected static ?int $navigationSort = 3;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function form(Schema $schema): Schema
    {
        return SalaryComponentsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalaryComponentsTable::configure($table);
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
            'index' => ListSalaryComponents::route('/'),
            // 'create' => CreateSalaryComponents::route('/create'),
            // 'edit' => EditSalaryComponents::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return collect(SalaryComponentNameEnum::jobPositionComponents())
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
