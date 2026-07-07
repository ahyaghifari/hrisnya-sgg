<?php

namespace App\Filament\Resources\Payroll\DoctorPayrollPeriods\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DoctorPayrollPeriodInfolist
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
