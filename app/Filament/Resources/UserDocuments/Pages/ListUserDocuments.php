<?php

namespace App\Filament\Resources\UserDocuments\Pages;

use App\Enum\UserDocumentType;
use App\Filament\Resources\UserDocuments\UserDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListUserDocuments extends ListRecords
{
    public $type;
    protected static string $resource = UserDocumentResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->type = request()->query('type');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat ' . $this->getTitle())
                ->url(fn (): Htmlable|string => UserDocumentResource::getUrl('create', [
                    'type' => $this->type,
                ])),
        ];
    }

    public function getTitle(): string
    {
        $type = $this->type;

        if ($type === UserDocumentType::RESIGNATION->value) {
            return 'Pengunduran Diri';
        } elseif ($type === UserDocumentType::RETIREMENT->value) {
            return 'Surat Pensiun';
        } elseif ($type === UserDocumentType::ASSIGNMENT->value) {
            return 'Surat Tugas';
        } elseif ($type === UserDocumentType::WORK_CERTIFICATE->value) {
            return 'Surat Keterangan Kerja';
        } elseif ($type === UserDocumentType::EMPLOYMENT_CONTRACT->value) {
            return 'Surat Kontrak Kerja';
        }

        return 'Dokumen Pengguna';
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        $query = UserDocumentResource::getEloquentQuery();

        $type = $this->type;

        if ($type === UserDocumentType::RESIGNATION->value) {
            return $query->where('document_type', UserDocumentType::RESIGNATION->value);
        } elseif ($type === UserDocumentType::RETIREMENT->value) {
            return $query->where('document_type', UserDocumentType::RETIREMENT->value);
        } elseif ($type === UserDocumentType::ASSIGNMENT->value) {
            return $query->where('document_type', UserDocumentType::ASSIGNMENT->value);
        } elseif ($type === UserDocumentType::WORK_CERTIFICATE->value) {
            return $query->where('document_type', UserDocumentType::WORK_CERTIFICATE->value);
        } elseif ($type === UserDocumentType::EMPLOYMENT_CONTRACT->value) {
            return $query->where('document_type', UserDocumentType::EMPLOYMENT_CONTRACT->value);
        }

        return $query;
    }
}
