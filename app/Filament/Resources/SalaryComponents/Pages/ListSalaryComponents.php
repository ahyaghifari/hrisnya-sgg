<?php

namespace App\Filament\Resources\SalaryComponents\Pages;

use App\Enum\Salary\SalaryComponentCategoryEnum;
use App\Enum\Salary\SalaryComponentTypeEnum;
use App\Filament\Resources\SalaryComponents\SalaryComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ListSalaryComponents extends ListRecords
{
    protected static string $resource = SalaryComponentResource::class;
    protected static ?string $title = 'Komponen Gaji';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            ...$schema->getComponents(),
            View::make('filament.salary-components.category-legend')
                ->viewData([
                    'earningCategories' => collect(SalaryComponentCategoryEnum::cases())
                        ->filter(fn ($category) => $category->type() === SalaryComponentTypeEnum::EARNING)
                        ->values(),
                    'deductionCategories' => collect(SalaryComponentCategoryEnum::cases())
                        ->filter(fn ($category) => $category->type() === SalaryComponentTypeEnum::DEDUCTION)
                        ->values(),
                ]),
        ]);
    }
}
