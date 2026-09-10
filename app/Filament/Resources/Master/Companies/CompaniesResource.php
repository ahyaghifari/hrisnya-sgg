<?php

namespace App\Filament\Resources\Master\Companies;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Master\Companies\Pages\ManageCompanies;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CompaniesResource extends BaseResource
{
    protected static ?string $model = Companies::class;

    protected static ?string $navigationLabel = 'Perusahaan';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Perusahaan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Perusahaan')
                    ->required()
                    ->maxLength(255),

                TextInput::make('location')
                    ->label('Lokasi Perusahaan')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->onIcon('heroicon-s-check-circle')
                    ->offIcon('heroicon-s-x-circle')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(true),

                Section::make('Jabatan Direktur')
                    ->description('Pemegang aktif jabatan ini yang berperan sebagai Direktur perusahaan (Keputusan Direktur Sikartap, pengesahan Ide Inovasi, dll).')
                    ->relationship('companyDirector')
                    ->visibleOn('edit')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('job_position_id')
                            ->label('Jabatan')
                            ->placeholder('Belum diatur')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->options(function ($livewire): array {
                                $company = method_exists($livewire, 'getMountedTableActionRecord')
                                    ? $livewire->getMountedTableActionRecord()
                                    : null;

                                return $company
                                    ? JobPositions::where('company_id', $company->getKey())->orderBy('name')->pluck('name', 'id')->all()
                                    : [];
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->sortable(),
                TextColumn::make('name')->label('Nama Perusahaan')->searchable()->sortable(),
                TextColumn::make('location')->label('Lokasi')->searchable()->sortable(),
                TextColumn::make('companyDirector.jobPosition.name')->label('Jabatan Direktur')->placeholder('—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => static::isSuperAdmin() && ! $record->departments()->exists())
                    ->before(function (DeleteAction $action, $record) {
                        if ($record->departments()->exists()) {
                            Notification::make()
                                ->title('Perusahaan ini masih punya departemen, tidak bisa dihapus.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::canBrowseAllCompanies()) {
            return $query;
        }

        return $query->where('id', static::companyId());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanies::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::isSuperAdmin() && !$record->departments()->exists();
    }
}
