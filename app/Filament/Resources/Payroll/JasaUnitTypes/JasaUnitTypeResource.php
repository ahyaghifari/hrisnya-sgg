<?php

namespace App\Filament\Resources\Payroll\JasaUnitTypes;

use App\Enum\OrgStructurLevel;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Payroll\JasaUnitTypes\Pages\ManageJasaUnitTypes;
use App\Models\Payroll\JasaUnitType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Katalog jasa/tunjangan per-divisi (Jasa Perujuk Rawat, Jasa Ambulan, dst) — versi admin dari
 * JasaUnitTypesPage portal (`/keuangan/jasa-unit-types`). orgStructures difilter ke divisi milik
 * company yang sedang dipilih di form, sama pola SubDivisionsResource (companyFormField()->live()
 * + Select dependent lewat Get $get).
 */
class JasaUnitTypeResource extends BaseCompanyResource
{
    protected static ?string $model = JasaUnitType::class;

    protected static ?string $navigationLabel = 'Jasa Unit';

    protected static ?string $modelLabel = 'Jasa Unit';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField()->live(),

                TextInput::make('name')
                    ->label('Nama Jasa')
                    ->required()
                    ->maxLength(150),

                Select::make('orgStructures')
                    ->label('Divisi Penerima')
                    ->relationship(
                        'orgStructures',
                        'name',
                        modifyQueryUsing: fn ($query, Get $get) => $query
                            ->where('company_id', $get('company_id'))
                            ->where('level', OrgStructurLevel::LEVEL_DIVISION->value),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),

                TextColumn::make('name')
                    ->label('Nama Jasa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('orgStructures.name')
                    ->label('Divisi Penerima')
                    ->badge()
                    ->separator(',')
                    ->placeholder('-'),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJasaUnitTypes::route('/'),
        ];
    }
}
