<?php

namespace App\Filament\Resources\Master\Pangkats;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\Pangkats\Pages\ManagePangkats;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Pangkat punya rentang Kelas (start_kelas_id..max_kelas_id, lihat KelasResource) - field ini
 * SEBELUMNYA ada di model tapi tidak muncul di form sama sekali, ditambahkan di sini. Opsi kedua
 * Select di-scope ke company yang sama (Super Admin: company yang dipilih di form; Admin:
 * company sendiri) - sama pola dengan departmentOptions() di Divisions/SubDivisions.
 */
class PangkatsResource extends BaseCompanyResource
{
    protected static ?string $model = Pangkats::class;

    protected static ?string $navigationLabel = 'Pangkat';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare2Stack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Pangkat';
    }

    protected static function kelasOptions(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return Kelas::where('company_id', $companyId)
            ->orderBy('level')
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField()->live(),

                TextInput::make('name')
                    ->label('Nama Pangkat')
                    ->required()
                    ->maxLength(255),

                TextInput::make('golongan')
                    ->label('Golongan')
                    ->placeholder('Contoh: III/a')
                    ->maxLength(255),

                TextInput::make('level')
                    ->label('Level (Urutan Pangkat)')
                    ->helperText('Makin tinggi angkanya, makin tinggi jenjang pangkat ini - dipakai buat sortir tertinggi ke terendah.')
                    ->numeric()
                    ->required(),

                Select::make('start_kelas_id')
                    ->label('Kelas Dasar')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn (Get $get) => static::kelasOptions(
                        static::isSuperAdmin() ? $get('company_id') : static::companyId()
                    )),

                Select::make('max_kelas_id')
                    ->label('Kelas Maksimal')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn (Get $get) => static::kelasOptions(
                        static::isSuperAdmin() ? $get('company_id') : static::companyId()
                    )),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Pangkat')->searchable()->sortable(),
                TextColumn::make('golongan')->label('Golongan')->searchable()->sortable()->placeholder('-'),
                TextColumn::make('level')->label('Level')->sortable()->alignCenter(),
                TextColumn::make('startKelas.name')->label('Kelas Dasar')->sortable(),
                TextColumn::make('maxKelas.name')->label('Kelas Maksimal')->sortable(),
                TextColumn::make('job_positions_count')->label('Jumlah Posisi')->alignCenter()->sortable(),
                TextColumn::make('user_profiles_count')->label('Jumlah Karyawan')->alignCenter()->sortable(),
            ])
            ->defaultSort('level', 'desc')
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                // Pangkat yang masih dipakai jabatan atau ada karyawannya tidak boleh dihapus -
                // keduanya merujuk pangkat_id, hapus pangkatnya bisa bikin data anak yatim.
                DeleteAction::make()
                    ->visible(fn (Pangkats $record) => static::canDelete($record))
                    ->before(function (DeleteAction $action, Pangkats $record) {
                        if ($record->jobPositions()->exists() || $record->userProfiles()->exists()) {
                            Notification::make()
                                ->title('Pangkat ini masih dipakai jabatan atau ada karyawannya, tidak bisa dihapus.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['jobPositions', 'userProfiles']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePangkats::route('/'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->jobPositions()->exists() && ! $record->userProfiles()->exists();
    }
}
