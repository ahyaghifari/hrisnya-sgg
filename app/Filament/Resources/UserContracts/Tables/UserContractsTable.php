<?php

namespace App\Filament\Resources\UserContracts\Tables;

use App\Filament\Resources\UserColumn;
use App\Filament\Resources\UserContracts\UserContractResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class UserContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable(query: UserColumn::searchName())
                    ->sortable(query: UserColumn::sortName()),

                TextColumn::make('employeeType.name')
                    ->label('Tipe Karyawan')
                    ->sortable(),

                TextColumn::make('contract_start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('contract_end_date')
                    ->label('Sampai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn ($record) => $record->contract_end_date && Carbon::parse($record->contract_end_date)->isPast()
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

                UserContractResource::companyTableColumn(),
            ])
            ->defaultSort('contract_start_date', 'desc')
            ->filters([
                UserContractResource::companyTableFilter(),
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
