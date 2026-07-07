<?php

namespace App\Filament\Resources\Payroll\CompanySalaryComponents\Pages;

use App\Filament\Resources\Payroll\CompanySalaryComponents\CompanySalaryComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListCompanySalaryComponents extends ListRecords
{
    protected static string $resource = CompanySalaryComponentResource::class;

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

    protected function getTableQuery(): Relation|null|Builder
    {
        return static::getResource()::getModel()::query();
    }
}
