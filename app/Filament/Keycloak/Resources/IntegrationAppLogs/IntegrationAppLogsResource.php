<?php

namespace App\Filament\Keycloak\Resources\IntegrationAppLogs;

use App\Filament\Keycloak\Resources\IntegrationAppLogs\Pages\ManageIntegrationAppLogs;
use App\Models\Integrasi\IntegrationApp;
use App\Models\Integrasi\IntegrationAppLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only — riwayat tiap panggilan ke GET /api/integrasi/karyawan/{nip}, termasuk yang
 * gagal auth (integration_app_id kosong berarti token tidak valid, jadi belum ketahuan
 * aplikasi mana). Dipakai buat pastikan sistem client BENERAN menjangkau HRIS, bukan cuma
 * asumsi — lihat App\Http\Controllers\Api\Integrasi\EmployeeDirectoryController.
 */
class IntegrationAppLogsResource extends Resource
{
    protected static ?string $model = IntegrationAppLog::class;

    protected static ?string $navigationLabel = 'Log Integrasi';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    public static function getModelLabel(): string
    {
        return 'Log Integrasi';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('app.name')->label('Aplikasi')->placeholder('— token tidak valid —')->color(fn ($record) => $record->app ? null : 'danger'),
                TextColumn::make('nip')->label('NIP Diminta')->placeholder('—'),
                TextColumn::make('status')
                    ->label('Hasil')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'not_found' => 'warning',
                        'unauthorized' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Berhasil',
                        'not_found' => 'NIP tidak ditemukan',
                        'unauthorized' => 'Token tidak valid',
                        default => $state,
                    }),
                TextColumn::make('ip')->label('IP')->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Hasil')
                    ->options([
                        'success' => 'Berhasil',
                        'not_found' => 'NIP tidak ditemukan',
                        'unauthorized' => 'Token tidak valid',
                    ]),
                SelectFilter::make('integration_app_id')
                    ->label('Aplikasi')
                    ->options(fn () => IntegrationApp::pluck('name', 'id')->all()),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIntegrationAppLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
