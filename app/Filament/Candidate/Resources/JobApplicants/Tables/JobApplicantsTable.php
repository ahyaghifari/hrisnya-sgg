<?php

namespace App\Filament\Candidate\Resources\JobApplicants\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobApplicantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobVacancy.jobPosition.name')
                    ->label('Posisi Dilamar')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jobVacancy.jobPosition.orgStructure.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('applied_at')
                    ->label('Tanggal Melamar')
                    ->date('j F Y')
                    ->sortable(),

                TextColumn::make('current_stage')
                    ->label('Tahap Saat Ini')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                    ->badge()
                    ->color('primary'),

                TextColumn::make('status')
                    ->label('Status Lamaran')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => \App\Enum\Recruitment\ProcessStatus::tryFrom($state)?->getLabel() ?? '-')
                    ->badge()
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->icon(Heroicon::Eye)->hiddenLabel()->color('primary'),
            ])
            ->toolbarActions([
                // 
            ]);
    }
}
