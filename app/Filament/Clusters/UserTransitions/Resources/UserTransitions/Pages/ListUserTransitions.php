<?php

namespace App\Filament\Clusters\UserTransitions\Resources\UserTransitions\Pages;

use App\Enum\UserDocumentType;
use App\Filament\Clusters\UserTransitions\Resources\UserTransitions\UserTransitionResource;
use App\Filament\Resources\UserDocuments\UserDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListUserTransitions extends ListRecords
{
    protected static string $resource = UserTransitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat ' . $this->getTitle())
                ->url(fn (): Htmlable|string => UserTransitionResource::getUrl('create', [
                    'type' => request()->query('type'),
                ])),
        ];
    }

    public function getTitle(): string
    {
        $type = request()->query('type');

        if (! $type || ! UserDocumentType::tryFrom($type)) {
            return 'Dokumen Pengguna';
        }

        return UserDocumentType::from($type)->label();
    }

    protected function getTableQuery(): null|Builder|Relation
    {
        $query = UserTransitionResource::getEloquentQuery();

        $type = request()->query('type');

        if ($type) {
            return $query->where('transition_type', $type);
        }

        return $query->latest();
    }
}
