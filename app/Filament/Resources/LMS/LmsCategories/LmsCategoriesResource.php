<?php

namespace App\Filament\Resources\LMS\LmsCategories;

use App\Enum\LMS\TrainingCategoryEnum;
use App\Filament\Resources\LMS\LmsCategories\Pages\ManageLmsCategories;
use App\Models\LMS\LmsCategories;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LmsCategoriesResource extends Resource
{
    protected static ?string $model = LmsCategories::class;

    protected static ?string $slug = 'lms/lms-categories';

    protected static ?string $navigationLabel = 'Kategori';

    protected static string|UnitEnum|null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('training_category')
                    ->label('Jenis Pelatihan')
                    ->helperText('Menentukan skema honor pelatihan yang memakai kategori ini.')
                    ->options(collect(TrainingCategoryEnum::internalCases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->native(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(50),

                TextColumn::make('training_category')
                    ->label('Jenis Pelatihan')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->placeholder('-'),

                TextColumn::make('courses_count')
                    ->label('Jumlah Pelatihan')
                    ->counts('courses')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->visible(fn ($record) => static::canEdit($record)),

                DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn (LmsCategories $record) => static::canDelete($record) && $record->courses_count === 0),
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
            'index' => ManageLmsCategories::route('/'),
        ];
    }
}
