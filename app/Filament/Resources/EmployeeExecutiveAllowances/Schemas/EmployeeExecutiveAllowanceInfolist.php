<?php

namespace App\Filament\Resources\EmployeeExecutiveAllowances\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeExecutiveAllowanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Tunjangan Eksekutif')
                    ->schema([

                        TextEntry::make('employee.nama_lengkap')
                            ->label('Penerima'),

                        TextEntry::make('type')
                            ->label('Jenis')
                            ->formatStateUsing(fn (string $state) => $state === 'earning' ? 'Pendapatan' : 'Potongan'),

                        TextEntry::make('label')
                            ->label('Label'),

                        TextEntry::make('amount')
                            ->label('Nominal')
                            ->money('IDR', true),

                        IconEntry::make('is_active')
                            ->label('Aktif')
                            ->boolean(),

                        TextEntry::make('note')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('updated_at')
                            ->label('Terakhir Diubah')
                            ->dateTime(),

                    ])
                    ->columns(1),
            ]);
    }
}
