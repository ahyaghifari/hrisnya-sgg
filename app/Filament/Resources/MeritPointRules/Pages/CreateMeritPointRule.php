<?php

namespace App\Filament\Resources\MeritPointRules\Pages;

use App\Filament\Resources\MeritPointRules\MeritPointRuleResource;
use App\Helpers\PermissionHelper;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMeritPointRule extends CreateRecord
{
    protected static string $resource = MeritPointRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        if (! PermissionHelper::hasGlobalRole($user)) {
            $data['company_id'] = $user->company_id;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
