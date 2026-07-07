<?php

namespace App\Filament\Resources\Master\JobPositions\Pages;

use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobPositions extends ListRecords
{
    protected static string $resource = JobPositionResource::class;
    protected static ?string $title = 'Daftar Jabatan';

    // protected function getHeaderActions(): array
    // {
    //     $type = request()->get('type');
    //     return [
    //         CreateAction::make()->label('Buat Jabatan Baru')
    //             ->visible(fn () => JobPositionResource::canCreate() && $type !== 'master_data'),
    //     ];
    // }
}
