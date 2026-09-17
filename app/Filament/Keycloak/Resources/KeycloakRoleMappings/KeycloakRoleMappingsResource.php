<?php

namespace App\Filament\Keycloak\Resources\KeycloakRoleMappings;

use App\Filament\Keycloak\Resources\KeycloakRoleMappings\Pages\ManageKeycloakRoleMappings;
use App\Models\Integrasi\KeycloakRoleMapping;
use App\Models\Master\JobPositions;
use App\Services\Keycloak\KeycloakAdmin;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Konfigurasi "jabatan X otomatis dapat role Y di client Z" — langkah 1->2 dari
 * rantai role (lihat KeycloakRoles). Tabel INI CUMA KONFIGURASI, belum nge-push apa
 * pun ke Keycloak — assign/lepas role ke user sungguhan masih langkah terpisah
 * (App\Services\Keycloak\KeycloakAdmin::assignClientRoleToUser()).
 */
class KeycloakRoleMappingsResource extends Resource
{
    protected static ?string $model = KeycloakRoleMapping::class;

    protected static ?string $navigationLabel = 'Role per Jabatan';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $recordTitleAttribute = 'keycloak_role_name';

    public static function getModelLabel(): string
    {
        return 'Role per Jabatan';
    }

    protected static function kc(): KeycloakAdmin
    {
        return app(KeycloakAdmin::class);
    }

    /** @return array<string, string> uuid client => label */
    protected static function clientOptions(): array
    {
        return collect(static::kc()->clients(null, 0, 100))
            ->mapWithKeys(fn (array $c): array => [$c['id'] => $c['clientId']])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('job_position_id')
                    ->label('Jabatan')
                    ->relationship('jobPosition', 'name')
                    ->getOptionLabelFromRecordUsing(fn (JobPositions $r): string => $r->name . ' — ' . ($r->company?->name ?? '—'))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('keycloak_client_uuid')
                    ->label('Client Keycloak')
                    ->options(fn () => static::clientOptions())
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $set('keycloak_client_label', static::clientOptions()[$state] ?? null);
                        $set('keycloak_role_name', null);
                    }),

                Hidden::make('keycloak_client_label'),

                Select::make('keycloak_role_name')
                    ->label('Role')
                    ->required()
                    ->helperText('Pilih client dulu di atas.')
                    ->options(function (Get $get): array {
                        $clientUuid = $get('keycloak_client_uuid');

                        if (! $clientUuid) {
                            return [];
                        }

                        return collect(static::kc()->clientRoles($clientUuid))
                            ->pluck('name', 'name')
                            ->all();
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobPosition.name')->label('Jabatan')->searchable()->sortable(),
                TextColumn::make('jobPosition.company.name')->label('Perusahaan')->placeholder('—'),
                TextColumn::make('keycloak_client_label')->label('Client')->badge()->color('gray'),
                TextColumn::make('keycloak_role_name')->label('Role')->badge()->color('primary'),
                TextColumn::make('creator.name')->label('Dibuat Oleh')->placeholder('—'),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('keycloak_client_uuid')
                    ->label('Client')
                    ->options(fn () => static::clientOptions()),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKeycloakRoleMappings::route('/'),
        ];
    }
}
