<?php

namespace App\Filament\Resources\EmployeeContracts\Tables;


use App\Filament\Resources\EmployeeContracts\EmployeeContractResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class EmployeeContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.nama_lengkap')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employeeType.name')
                    ->label('Tipe Karyawan')
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Sampai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn ($record) => $record->end_date && Carbon::parse($record->end_date)->isPast()
                        ? 'Berakhir'
                        : 'Aktif')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Aktif' ? 'success' : 'danger'),

                IconColumn::make('file_path')
                    ->label('Surat')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document')
                    ->url(fn ($record) => $record->file_url, shouldOpenInNewTab: true),

                EmployeeContractResource::companyTableColumn(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                EmployeeContractResource::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
