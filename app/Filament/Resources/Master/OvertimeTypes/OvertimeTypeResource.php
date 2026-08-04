<?php

namespace App\Filament\Resources\Master\OvertimeTypes;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\OvertimeTypes\Pages\ManageOvertimeTypes;
use App\Models\Master\Attendance\OvertimeTypes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OvertimeTypeResource extends BaseCompanyResource
{
    protected static ?string $model = OvertimeTypes::class;

    protected static ?string $navigationLabel = 'Jenis Lembur';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 13;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Jenis Lembur';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                TextInput::make('name')
                    ->label('Nama Tipe Lembur')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Tipe Lembur')->searchable()->sortable(),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (OvertimeTypes $record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOvertimeTypes::route('/'),
        ];
    }
}
