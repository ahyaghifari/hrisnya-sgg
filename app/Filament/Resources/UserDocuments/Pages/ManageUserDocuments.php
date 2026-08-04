<?php

namespace App\Filament\Resources\UserDocuments\Pages;

use App\Filament\Resources\UserDocuments\UserDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUserDocuments extends ManageRecords
{
    protected static string $resource = UserDocumentResource::class;

    protected static ?string $title = 'Dokumen Kepegawaian';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Dokumen'),
        ];
    }
}
