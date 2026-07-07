<?php

namespace App\Filament\Resources\Payroll\GeneralSalaryComponents\Pages;

use App\Filament\Resources\Payroll\GeneralSalaryComponents\GeneralSalaryComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListGeneralSalaryComponents extends ListRecords
{
    protected static string $resource = GeneralSalaryComponentResource::class;
    protected static ?string $title = 'Master Data Komponen Gaji';

    #[Url]
    public ?string $type = null;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function mount(): void
    {
        parent::mount();

        if (!$this->type) {
            $this->type = request()->get('type');
        }
    }
}
