<?php

namespace App\Filament\Resources\Master\Divisions;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\Divisions\Pages\ManageDivisions;
use App\Models\Master\OrgStructure;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DivisionsResource extends BaseCompanyResource
{
    protected static ?string $model = OrgStructure::class;

    protected static ?string $navigationLabel = 'Divisi';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Divisi';
    }

    /** Opsi departemen di-scope ke company_id yang dipilih (Super Admin) atau company sendiri
     * (Admin) - dipakai bareng oleh form field & table filter supaya logicnya 1 tempat. */
    protected static function departmentOptions(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return OrgStructure::where('company_id', $companyId)
            ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField()->live(),

                Hidden::make('level')
                    ->default(OrgStructurLevel::LEVEL_DIVISION->value),

                Select::make('parent_id')
                    ->label('Departemen')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Departemen')
                    ->options(fn (Get $get) => static::departmentOptions(
                        static::isSuperAdmin() ? $get('company_id') : static::companyId()
                    )),

                TextInput::make('name')
                    ->label('Nama Divisi')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('parent.name')->label('Departemen')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama Divisi')->searchable()->sortable(),
                TextColumn::make('subdivisions_count')->label('Jumlah Sub Divisi')->alignCenter()->sortable(),
                TextColumn::make('capacity')->label('Kapasitas')->alignCenter(),
            ])
            ->filters([
                Filter::make('org_scope')
                    ->schema([
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->relationship('company', 'name')
                            ->preload()
                            ->searchable()
                            ->live()
                            ->visible(fn () => static::isSuperAdmin()),

                        Select::make('parent_id')
                            ->label('Departemen')
                            ->options(fn (Get $get) => static::departmentOptions(
                                static::isSuperAdmin() ? $get('company_id') : static::companyId()
                            ))
                            ->preload()
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['company_id'] ?? null, fn ($q, $companyId) => $q->where('company_id', $companyId))
                            ->when($data['parent_id'] ?? null, fn ($q, $parentId) => $q->where('parent_id', $parentId));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                // Divisi yang masih punya sub divisi tidak boleh dihapus - sub divisi merujuk
                // parent_id ke divisi ini, hapus divisinya bisa bikin sub divisi yatim.
                DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record))
                    ->before(function (DeleteAction $action, $record) {
                        if ($record->subdivisions()->exists()) {
                            Notification::make()
                                ->title('Divisi ini masih punya sub divisi, tidak bisa dihapus.')
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
            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
            ->withCount('subdivisions');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDivisions::route('/'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->subdivisions()->exists();
    }
}
