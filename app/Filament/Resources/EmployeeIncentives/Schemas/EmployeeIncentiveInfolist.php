<?php

namespace App\Filament\Resources\EmployeeIncentives\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeIncentiveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Insentif')
                    ->schema([

                        TextEntry::make('employee.company.name')
                            ->label('Perusahaan'),

                        TextEntry::make('employee.nama_lengkap')
                            ->label('Penerima'),

                        TextEntry::make('name')
                            ->label('Nama Program'),

                        TextEntry::make('incentive_date')
                            ->label('Tanggal')
                            ->date(),

                        TextEntry::make('amount')
                            ->label('Nominal')
                            ->money('IDR', true),

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
