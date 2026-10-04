<?php

namespace App\Filament\Resources\ExecutiveEmployees\Tables;

use App\Filament\Resources\ExecutiveEmployees\ExecutiveEmployeeResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Cuma 2 baris tetap (lihat ExecutiveEmployeeResource::getEloquentQuery()) - tidak perlu
 * filter/pencarian seperti EmployeesTable biasa, kolom dipangkas ke yang relevan saja.
 */
class ExecutiveEmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->description(fn ($record) => $record->email)
                    ->label('Nama'),
                TextColumn::make('nip')->label('NIP'),
                TextColumn::make('profile.jobPosition.name')->label('Jabatan')->default('-'),
                TextColumn::make('profile.pangkat.name')->label('Pangkat')->default('-'),
                TextColumn::make('profile.kelas.name')->label('Kelas')->default('-'),
                TextColumn::make('profile.employeeType.name')->label('Tipe Karyawan')->default('-'),
            ])
            ->defaultSort(fn ($query, string $direction) => $query->orderByName($direction))
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => ExecutiveEmployeeResource::canEdit($record)),
            ]);
    }
}
