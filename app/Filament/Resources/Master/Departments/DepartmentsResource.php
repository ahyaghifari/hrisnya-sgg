<?php

namespace App\Filament\Resources\Master\Departments;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\Departments\Pages\ManageDepartments;
use App\Models\Master\OrgStructure;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Simple resource (form + table di-embed, 1 halaman ManageRecords dengan modal create/edit) -
 * pola sama dengan CompaniesResource. Company_id di-scope lewat BaseCompanyResource
 * (getEloquentQuery + companyFormField/companyTableColumn/companyTableFilter) - lihat komentar
 * di sana.
 */
class DepartmentsResource extends BaseCompanyResource
{
    protected static ?string $model = OrgStructure::class;

    protected static ?string $navigationLabel = 'Departemen';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Departemen';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                Hidden::make('level')
                    ->default(OrgStructurLevel::LEVEL_DEPARTMENT->value),

                TextInput::make('name')
                    ->label('Nama Departemen')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Kode Integrasi')
                    ->helperText('Kode teknis stabil dipakai sistem lain buat sinkronisasi (Keycloak dkk) — sekali diisi jangan diubah lagi. Boleh kosong kalau belum perlu.')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Departemen')->searchable()->sortable(),
                TextColumn::make('code')->label('Kode')->searchable()->copyable()->placeholder('—'),
                TextColumn::make('divisions_count')->label('Jumlah Divisi')->alignCenter()->sortable(),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                // Departemen yang masih punya divisi tidak boleh dihapus - divisi merujuk
                // parent_id ke departemen ini, hapus departemennya bisa bikin divisi yatim.
                DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record))
                    ->before(function (DeleteAction $action, $record) {
                        if ($record->divisions()->exists()) {
                            Notification::make()
                                ->title('Departemen ini masih punya divisi, tidak bisa dihapus.')
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
            ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->withCount('divisions');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->divisions()->exists();
    }
}
