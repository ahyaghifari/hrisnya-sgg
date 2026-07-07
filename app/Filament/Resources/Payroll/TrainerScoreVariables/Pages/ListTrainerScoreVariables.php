<?php

namespace App\Filament\Resources\Payroll\TrainerScoreVariables\Pages;

use App\Filament\Resources\Payroll\TrainerScoreVariables\TrainerScoreVariableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTrainerScoreVariables extends ListRecords
{
    protected static string $resource = TrainerScoreVariableResource::class;
    protected static ?string $title = 'Daftar Variabel Gaji Trainer';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Variabel Gaji Trainer'),
        ];
    }

    public function getTabs(): array
    {
        return [
            Tab::make('honor')
                ->label('Honor')
                ->query(fn ($query) => $query->where('type', 'honor')),
            Tab::make('validity')
                ->label('Plafon')
                ->query(fn ($query) => $query->where('type', 'validity')),
        ];
    }
}
