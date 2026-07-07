<?php

namespace App\Filament\Resources\Counselings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CounselingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('response')
                    ->label('Response')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),

                Textarea::make('solution')
                    ->label('Solution')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
