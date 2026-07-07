<?php

namespace App\Filament\Resources\Payroll\TrainerScoreRanges\Pages;

use App\Filament\Resources\Payroll\TrainerScoreRanges\TrainerScoreRangesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTrainerScoreRanges extends ListRecords
{
    protected static string $resource = TrainerScoreRangesResource::class;
    protected static ?string $title = 'Daftar Skala Nilai Trainer';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Skala Nilai Trainer'),
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
