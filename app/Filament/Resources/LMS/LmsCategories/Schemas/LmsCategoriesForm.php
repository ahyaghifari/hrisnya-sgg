<?php

namespace App\Filament\Resources\LMS\LmsCategories\Schemas;

use App\Enum\LMS\TrainingCategoryEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LmsCategoriesForm
{
    public static function configure(Schema $schema): Schema
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
}
