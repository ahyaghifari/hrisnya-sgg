<?php

namespace App\Filament\Resources\Payroll\JobPositionSalaryComponents\Pages;

use App\Enum\Salary\SalaryComponentNameEnum;
use App\Filament\Resources\Payroll\JobPositionSalaryComponents\SalaryComponentsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListSalaryComponents extends ListRecords
{
    protected static string $resource = SalaryComponentsResource::class;
    protected static ?string $title = 'Daftar Komponen Gaji';

    public ?string $componentType = null;

    public function mount(): void
    {
        parent::mount();
        $this->componentType = request()->query('type');
    }

    public function getTitle(): string
    {
        return $this->componentType ?? 'Komponen Gaji';
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return static::getResource()::getModel()::query();
    }
}
