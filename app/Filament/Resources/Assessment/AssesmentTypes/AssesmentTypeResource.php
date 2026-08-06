<?php

namespace App\Filament\Resources\Assessment\AssesmentTypes;

use App\Filament\Resources\Assessment\AssesmentTypes\Pages\ManageAssessmentTypes;
use App\Models\Assessment\AssessmentTypes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AssesmentTypeResource extends Resource
{
    protected static ?string $model = AssessmentTypes::class;

    protected static string|UnitEnum|null $navigationGroup = 'Penilaian Karyawan';

    protected static ?string $navigationLabel = 'Tipe Penilaian';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Tipe Penilaian')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Deskripsi Tipe Penilaian')
                    ->rows(3)
                    ->columnSpanFull()
                    ->maxLength(65535)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Tipe Penilaian')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('templates_count')
                    ->label('Jumlah Template')
                    ->counts('templates')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel(),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->disabled(fn (AssessmentTypes $record): bool => $record->templates_count > 0)
                    ->tooltip(fn (AssessmentTypes $record): ?string => $record->templates_count > 0
                        ? 'Tidak bisa dihapus, masih dipakai ' . $record->templates_count . ' template'
                        : null),
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
            'index' => ManageAssessmentTypes::route('/'),
        ];
    }
}
