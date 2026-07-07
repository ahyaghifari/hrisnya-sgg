<?php

namespace App\Filament\Resources\Coachings\Tables;

use App\Enum\CoachingStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoachingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('coachBy.name')
                    ->label('Coach')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Judul')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->icon(fn (string $state) => CoachingStatus::from($state)->getIcon())
                    ->color(fn (string $state) => CoachingStatus::from($state)->getColor())
                    ->formatStateUsing(fn (string $state) => CoachingStatus::from($state)->getLabel())
                    ->sortable(),

                TextColumn::make('improvement_due_date')
                    ->label('Deadline Perbaikan')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //BulkActionGroup::make([
                    //DeleteBulkAction::make(),
                //]),
            ]);
    }
}
