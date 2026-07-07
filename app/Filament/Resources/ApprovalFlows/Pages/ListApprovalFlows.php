<?php

namespace App\Filament\Resources\ApprovalFlows\Pages;

use App\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalFlows extends ListRecords
{
    protected static string $resource = ApprovalFlowResource::class;
    protected static ?string $title = 'Daftar Alur Persetujuan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Alur Persetujuan Baru'),
        ];
    }
}
