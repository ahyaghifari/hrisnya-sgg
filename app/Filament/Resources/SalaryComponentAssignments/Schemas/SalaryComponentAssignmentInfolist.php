<?php

namespace App\Filament\Resources\SalaryComponentAssignments\Schemas;

use App\Enum\Salary\SalaryComponentCategoryEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalaryComponentAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Komponen Gaji')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nama Komponen'),

                        TextEntry::make('company.name')
                            ->label('Perusahaan')
                            ->placeholder('Semua Perusahaan'),

                        TextEntry::make('category')
                            ->label('Kategori')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => $state ? (SalaryComponentCategoryEnum::tryFrom($state)?->label() ?? $state) : '-'),

                        TextEntry::make('employeeTypes.name')
                            ->label('Tipe Karyawan Eligible')
                            ->badge()
                            ->placeholder('Semua Tipe Karyawan')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
