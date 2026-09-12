<?php

namespace App\Filament\Keycloak\Resources\IntegrationAppLogs\Pages;

use App\Filament\Keycloak\Resources\IntegrationAppLogs\IntegrationAppLogsResource;
use Filament\Resources\Pages\ManageRecords;

class ManageIntegrationAppLogs extends ManageRecords
{
    protected static string $resource = IntegrationAppLogsResource::class;

    protected static ?string $title = 'Log Integrasi';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
