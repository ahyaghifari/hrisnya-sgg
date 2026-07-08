<?php

namespace App\Filament\Resources\LMS\LmsCoursePlanItems\Pages;

use App\Filament\Resources\LMS\LmsCoursePlanItems\LmsCoursePlanItemResource;
use App\Models\LMS\LmsCoursePlanItems;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListLmsCoursePlanItems extends ListRecords
{
    protected static string $resource = LmsCoursePlanItemResource::class;

    protected static ?string $title = 'Daftar Pengajuan Pelatihan';

    public function table(Table $table): Table
    {
        return $table
            ->query(LmsCoursePlanItems::query()->where('request_status', '!=', 'draft'))
            ->columns([
                TextColumn::make('proposed_title')
                    ->label('Nama Pelatihan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('request_status')
                    ->label('Status Pengajuan')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->wrap()
                    ->placeholder('-'),

                TextColumn::make('requested_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime('j F Y')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->recordActions([
                EditAction::make()->hiddenLabel()
            ])
            ->recordUrl(fn (LmsCoursePlanItems $record) => LmsCoursePlanItemResource::getUrl('edit', ['record' => $record->id]));
    }
}
