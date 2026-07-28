<?php

namespace App\Filament\Resources\Master\SubDivisions;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\SubDivisions\Pages\ManageSubDivisions;
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

/**
 * Sub Divisi (level 3, parent_id -> Divisi level 2). Cascading paling dalam: Perusahaan ->
 * Departemen -> Divisi (Super Admin, harus pilih berurutan), Admin cuma Departemen -> Divisi
 * (company sudah tetap). Departemen di sini VIRTUAL (bukan kolom asli OrgStructure, cuma alat
 * bantu mempersempit opsi Divisi) - makanya ->dehydrated(false), tidak ikut disimpan.
 *
 * Sub Divisi TIDAK punya child org_structure lagi (level 3 = level terdalam, sudah dicek: 0 dari
 * 20 sub divisi punya children). Relasi anak yang relevan itu jobPositions (Jabatan) - 16/20 sub
 * divisi sudah punya jabatan - dipakai buat kolom "Jumlah Jabatan" & guard hapus.
 */
class SubDivisionsResource extends BaseCompanyResource
{
    protected static ?string $model = OrgStructure::class;

    protected static ?string $navigationLabel = 'Sub Divisi';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Sub Divisi';
    }

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

    protected static function divisionOptions(?string $departmentId): array
    {
        if (! $departmentId) {
            return [];
        }

        return OrgStructure::where('parent_id', $departmentId)
            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField()->live(),

                Hidden::make('level')
                    ->default(OrgStructurLevel::LEVEL_SUBDIVISION->value),

                Select::make('department_id')
                    ->label('Departemen')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Departemen')
                    ->dehydrated(false)
                    ->live()
                    ->options(fn (Get $get) => static::departmentOptions(
                        static::isSuperAdmin() ? $get('company_id') : static::companyId()
                    )),

                Select::make('parent_id')
                    ->label('Divisi')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Divisi')
                    ->options(fn (Get $get) => static::divisionOptions($get('department_id'))),

                TextInput::make('name')
                    ->label('Nama Sub Divisi')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('parent.parent.name')->label('Departemen')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Divisi')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama Sub Divisi')->searchable()->sortable(),
                TextColumn::make('job_positions_count')->label('Jumlah Jabatan')->alignCenter()->sortable(),
                TextColumn::make('capacity')->label('Kapasitas')->alignCenter(),
            ])
            ->filters([
                // company_id/department_id/parent_id digabung 1 Filter (bukan SelectFilter
                // terpisah) - $get() lintas-field cuma jalan kalau berbagi container/schema yang
                // sama, lihat komentar sama persis di DivisionsResource.
                Filter::make('org_scope')
                    ->schema([
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->relationship('company', 'name')
                            ->preload()
                            ->searchable()
                            ->live()
                            ->visible(fn () => static::isSuperAdmin()),

                        Select::make('department_id')
                            ->label('Departemen')
                            ->preload()
                            ->searchable()
                            ->live()
                            ->options(fn (Get $get) => static::departmentOptions(
                                static::isSuperAdmin() ? $get('company_id') : static::companyId()
                            )),

                        Select::make('parent_id')
                            ->label('Divisi')
                            ->preload()
                            ->searchable()
                            ->options(fn (Get $get) => static::divisionOptions($get('department_id'))),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['company_id'] ?? null, fn ($q, $companyId) => $q->where('company_id', $companyId))
                            ->when($data['parent_id'] ?? null, fn ($q, $parentId) => $q->where('parent_id', $parentId));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                // Sub divisi yang masih punya jabatan tidak boleh dihapus - jabatan merujuk
                // org_structure_id ke sub divisi ini, hapus sub divisinya bisa bikin jabatan
                // yatim.
                DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record))
                    ->before(function (DeleteAction $action, $record) {
                        if ($record->jobPositions()->exists()) {
                            Notification::make()
                                ->title('Sub divisi ini masih punya jabatan, tidak bisa dihapus.')
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
            ->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value)
            ->withCount('jobPositions');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubDivisions::route('/'),
        ];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->jobPositions()->exists();
    }
}
