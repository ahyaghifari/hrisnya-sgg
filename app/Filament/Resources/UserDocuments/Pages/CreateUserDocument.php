<?php

namespace App\Filament\Resources\UserDocuments\Pages;

use App\Enum\UserDocumentType;
use App\Filament\Resources\UserDocuments\UserDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserDocument extends CreateRecord
{
    public $type;
    protected static string $resource = UserDocumentResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->type = request()->query('type');
    }

    public function getTitle(): string
    {
        $typeLabel = '';
        $type = UserDocumentType::tryFrom($this->type);
        if ($type) {
            $typeLabel = $type->label();
        }

        return 'Buat Dokumen Karyawan - ' . $typeLabel;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['type' => $this->record->document_type]);
    }
}
