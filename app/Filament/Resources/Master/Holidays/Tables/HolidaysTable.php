<?php

namespace App\Filament\Resources\Master\Holidays\Tables;

use App\Models\Master\Attendance\Holidays;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HolidaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eksplisit (bukan cuma andalkan inferensi dari HolidayResource) — tabel ini juga
            // dipakai berdiri sendiri di luar Resource lewat HolidaysPage (portal), yang tidak
            // punya konteks Resource buat nebak model-nya otomatis.
            ->query(fn () => Holidays::query())
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->placeholder('Semua Perusahaan')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_collective_leave')
                    ->label('Cuti Bersama')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_collective_leave')
                    ->label('Cuti Bersama'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
