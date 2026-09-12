<?php

namespace App\Filament\Keycloak\Resources\IntegrationApps;

use App\Filament\Keycloak\Resources\IntegrationApps\Pages\ManageIntegrationApps;
use App\Models\Integrasi\IntegrationApp;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Kelola sistem eksternal yang boleh memanggil API integrasi HRIS (lihat
 * App\Models\Integrasi\IntegrationApp). Token dibuat/di-generate ulang lewat
 * halaman ini — bentuk plaintext hanya muncul sekali di notifikasi sesudah
 * aksi, tidak pernah disimpan ulang atau ditampilkan lagi setelahnya.
 */
class IntegrationAppsResource extends Resource
{
    protected static ?string $model = IntegrationApp::class;

    protected static ?string $navigationLabel = 'Aplikasi Terhubung';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Aplikasi Terhubung';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Aplikasi')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Nama sistem yang memakai token ini, misalnya "Sistem Keuangan".'),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->helperText('Nonaktifkan untuk memutus akses tanpa menghapus riwayat aplikasi ini.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Aplikasi')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_used_at')
                    ->label('Terakhir Dipakai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah dipakai')
                    ->sortable(),
                TextColumn::make('creator.name')->label('Dibuat Oleh')->placeholder('—'),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime('d M Y')->sortable(),
            ])
            ->recordActions([
                Action::make('regenerateToken')
                    ->label('Generate Ulang Token')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Token lama langsung tidak berlaku sesudah ini. Aplikasi yang masih memakai token lama akan gagal autentikasi sampai diperbarui.')
                    ->action(function (IntegrationApp $record): void {
                        $token = IntegrationApp::newPlainToken();

                        $record->update(['token_hash' => IntegrationApp::hashToken($token)]);

                        Notification::make()
                            ->title('Token baru dibuat untuk "' . $record->name . '"')
                            ->body('Salin sekarang, token tidak akan ditampilkan lagi: ' . $token)
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIntegrationApps::route('/'),
        ];
    }
}
