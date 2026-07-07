<?php

namespace App\Filament\Resources\Payroll\PayrollPeriods\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollPeriodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payroll Period')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('company.name')
                                ->label('Company')
                                ->weight('bold'),

                            TextEntry::make('processed_at')
                                ->label('Generated At')
                                ->dateTime()
                                ->placeholder('-'),
                        ]),
                    ]),

                Section::make('Period Information')
                    ->schema([
                        Grid::make(2)->schema([

                            TextEntry::make('start_date')
                                ->label('Start Date')
                                ->date(),

                            TextEntry::make('end_date')
                                ->label('End Date')
                                ->date(),
                        ]),
                    ]),
            ]);
    }
}
