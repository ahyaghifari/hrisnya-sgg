<?php

namespace App\Filament\Resources\EmployeeExecutiveAllowances\Tables;


use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeExecutiveAllowancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.nama_lengkap')
                    ->label('Penerima')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state) => $state === 'earning' ? 'Pendapatan' : 'Potongan')
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', true),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat')
                    ->slideOver()
                    ->modalWidth('md'),

                EditAction::make()
                    ->label('Ubah')
                    ->modalWidth('md'),

                DeleteAction::make()
                    ->label('Hapus')
                    ->slideOver()
                    ->modalWidth('md'),
            ]);
    }
}
