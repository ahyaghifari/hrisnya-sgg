<?php

namespace App\Filament\Keycloak\Resources\IntegrationApps\Pages;

use App\Filament\Keycloak\Resources\IntegrationApps\IntegrationAppsResource;
use App\Models\Integrasi\IntegrationApp;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageIntegrationApps extends ManageRecords
{
    protected static string $resource = IntegrationAppsResource::class;

    protected static ?string $title = 'Aplikasi Terhubung';

    protected function getHeaderActions(): array
    {
        return [
            // Skema dipersempit (cuma nama) — token dibuat otomatis, bukan diisi manual.
            CreateAction::make()
                ->label('Tambah Aplikasi')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Aplikasi')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Nama sistem yang akan memakai token ini, misalnya "Sistem Keuangan".'),
                ])
                ->using(function (array $data): IntegrationApp {
                    $token = IntegrationApp::newPlainToken();

                    $app = IntegrationApp::create([
                        'name' => $data['name'],
                        'token_hash' => IntegrationApp::hashToken($token),
                        'is_active' => true,
                        'created_by' => Auth::id(),
                    ]);

                    Notification::make()
                        ->title('Aplikasi "' . $app->name . '" dibuat')
                        ->body('Salin token sekarang, tidak akan ditampilkan lagi: ' . $token)
                        ->success()
                        ->persistent()
                        ->send();

                    return $app;
                })
                // Notifikasi sukses default Filament diganti notifikasi berisi token di atas.
                ->successNotification(null),
        ];
    }
}
