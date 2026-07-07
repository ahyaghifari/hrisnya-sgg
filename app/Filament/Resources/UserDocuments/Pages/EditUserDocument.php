<?php

namespace App\Filament\Resources\UserDocuments\Pages;

use App\Filament\Resources\UserDocuments\UserDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserDocument extends EditRecord
{
    protected static string $resource = UserDocumentResource::class;

    public function getTitle(): string
    {
        $typeLabel = $this->record->document_type ?? '';
        return 'Edit Dokumen Karyawan - ' . $typeLabel;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['type' => $this->record->document_type]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
