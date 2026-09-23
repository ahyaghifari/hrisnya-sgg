<?php

namespace App\Filament\Resources\MeritPointTransactions\Pages;

use App\Filament\Resources\MeritPointTransactions\MeritPointTransactionResource;
use App\Models\MeritPoint\MeritPointTransaction;
use App\Services\MeritPoint\MeritPointService;
use Filament\Resources\Pages\CreateRecord;

class CreateMeritPointTransaction extends CreateRecord
{
    protected static string $resource = MeritPointTransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['mode']);

        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): MeritPointTransaction
    {
        return app(MeritPointService::class)->addTransaction($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
