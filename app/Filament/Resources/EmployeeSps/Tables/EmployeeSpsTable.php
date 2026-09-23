<?php

namespace App\Filament\Resources\EmployeeSps\Tables;

use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
use App\Models\Sp\EmployeeSp;
use App\Services\Sp\SpPdfService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeSpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nomor')
                    ->label('Nomor')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('sp_level')
                    ->label('Level')
                    ->badge()
                    ->sortable(),

                TextColumn::make('source_type')
                    ->label('Sumber'),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Berakhir')
                    ->date()
                    ->placeholder('Tidak pernah')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sp_level')
                    ->label('Level SP')
                    ->options(collect(SpLevel::cases())->mapWithKeys(fn ($level) => [$level->value => $level->getLabel()])),

                SelectFilter::make('source_type')
                    ->label('Sumber')
                    ->options(collect(SpSourceType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->getLabel()])),
            ])
            ->recordActions([
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(fn (EmployeeSp $record) => app(SpPdfService::class)->download($record)),

                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
