<?php

namespace App\Filament\Keycloak\Resources\KeycloakRoleMappings\Pages;

use App\Filament\Keycloak\Resources\KeycloakRoleMappings\KeycloakRoleMappingsResource;
use App\Models\Integrasi\KeycloakRoleMapping;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageKeycloakRoleMappings extends ManageRecords
{
    protected static string $resource = KeycloakRoleMappingsResource::class;

    protected static ?string $title = 'Role per Jabatan';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Mapping')
                ->using(function (array $data): KeycloakRoleMapping {
                    // Dicek dulu di sini (bukan andalkan constraint unik + tangkap exception) —
                    // lebih jelas pesannya buat admin, dan aman kalau di-submit dobel.
                    $existing = KeycloakRoleMapping::where('job_position_id', $data['job_position_id'])
                        ->where('keycloak_client_uuid', $data['keycloak_client_uuid'])
                        ->where('keycloak_role_name', $data['keycloak_role_name'])
                        ->first();

                    if ($existing) {
                        Notification::make()
                            ->title('Mapping ini sudah ada')
                            ->body('Jabatan tersebut sudah punya mapping role yang sama di client ini.')
                            ->warning()
                            ->send();

                        return $existing;
                    }

                    $data['created_by'] = Auth::id();

                    return KeycloakRoleMapping::create($data);
                }),
        ];
    }
}
