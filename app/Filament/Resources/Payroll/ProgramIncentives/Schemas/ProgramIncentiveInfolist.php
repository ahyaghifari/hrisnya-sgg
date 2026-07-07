<?php

namespace App\Filament\Resources\Payroll\ProgramIncentives\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProgramIncentiveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Program Incentive Information')
                    ->schema([

                        TextEntry::make('company.name')
                            ->label('Company'),

                        TextEntry::make('user.name')
                            ->label('Program Holder'),

                        TextEntry::make('program_name')
                            ->label('Program Name'),

                        TextEntry::make('incentive_date')
                            ->label('Incentive Date')
                            ->date(),

                        TextEntry::make('amount')
                            ->label('Amount')
                            ->money('IDR', true),

                        TextEntry::make('note')
                            ->label('Note')
                            ->placeholder('-')
                            ->columnSpanFull(),

                    ])
                    ->columns(1),

                Section::make('Status')
                    ->schema([

                        IconEntry::make('is_processed')
                            ->label('Processed')
                            ->boolean(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),

                    ])
                    ->columns(2),
            ]);
    }
}
