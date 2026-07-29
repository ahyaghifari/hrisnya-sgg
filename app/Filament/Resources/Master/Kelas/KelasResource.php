<?php

namespace App\Filament\Resources\Master\Kelas;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\Kelas\Pages\ManageKelas;
use App\Models\Master\Kelas;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Kelas adalah induk dari Pangkat (Pangkat punya rentang start_kelas_id..max_kelas_id, bukan
 * 1 kelas tetap) - makanya diletakkan DI ATAS Pangkat di nav Master (navigationSort=5, Pangkat
 * digeser ke 6). Field `level` (urutan kelas, dipakai buat bandingkan rentang Pangkat) SEBELUMNYA
 * ada di model tapi tidak muncul di form sama sekali - ditambahkan di sini.
 *
 * "Jumlah Pangkat" = Pangkat yang rentangnya MENCAKUP kelas ini (lihat Kelas::pangkatsInRange()),
 * bukan relasi FK langsung, jadi dihitung manual (bukan withCount) di getStateUsing().
 * "Jumlah Karyawan" = UserProfiles yang kelas_id-nya langsung kelas ini (withCount biasa).
 */
class KelasResource extends BaseCompanyResource
{
    protected static ?string $model = Kelas::class;

    protected static ?string $navigationLabel = 'Kelas';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Kelas';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                TextInput::make('name')
                    ->label('Nama Kelas')
                    ->required()
                    ->maxLength(255),

                TextInput::make('level')
                    ->label('Level (Urutan Kelas)')
                    ->helperText('Urutan kelas dari terendah ke tertinggi, dipakai buat bandingkan rentang Pangkat.')
                    ->numeric()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Kelas')->searchable()->sortable(),
                TextColumn::make('level')->label('Level')->sortable()->alignCenter(),
                TextColumn::make('pangkats_count')
                    ->label('Jumlah Pangkat')
                    ->alignCenter()
                    ->getStateUsing(fn (Kelas $record) => $record->pangkatsInRange()->count()),
                TextColumn::make('user_profiles_count')->label('Jumlah Karyawan')->alignCenter(),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                // Kelas yang masih dipakai Pangkat (dalam rentang) atau ada karyawannya langsung
                // tidak boleh dihapus - keduanya cuma "referensi longgar" (bukan FK constraint DB
                // yang cascade), jadi harus dijaga manual di sini.
                DeleteAction::make()
                    ->visible(fn (Kelas $record) => static::canDelete($record))
                    ->before(function (DeleteAction $action, Kelas $record) {
                        if ($record->pangkatsInRange()->exists() || $record->userProfiles()->exists()) {
                            Notification::make()
                                ->title('Kelas ini masih dipakai Pangkat atau ada karyawannya, tidak bisa dihapus.')
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
            ->withCount('userProfiles');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKelas::route('/'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->pangkatsInRange()->exists() && ! $record->userProfiles()->exists();
    }
}
