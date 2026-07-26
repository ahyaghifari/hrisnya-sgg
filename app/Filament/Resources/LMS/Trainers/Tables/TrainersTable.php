<?php

namespace App\Filament\Resources\LMS\Trainers\Tables;

use App\Filament\Resources\LMS\Trainers\TrainersResource;
use App\Models\LMS\Trainer\Trainers;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class TrainersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                // Jumlah PELATIHAN (course distinct), bukan jumlah baris pertemuan — 1 pelatihan
                // bisa punya beberapa pertemuan dengan trainer yang sama.
                'meetings as trainings_count' => fn ($q) => $q->select(DB::raw('count(distinct course_id)')),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === 'internal' ? 'info' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('trainings_count')
                    ->label('Jumlah Pelatihan')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('expertise')
                    ->label('Keahlian')
                    ->placeholder('-')
                    ->limit(40),

                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('-'),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => TrainersResource::canEdit($record)),
                // Pemateri yang sudah pernah mengisi pelatihan tidak boleh dihapus (riwayat
                // pertemuan/penilaiannya ikut hilang) — cuma bisa dinonaktifkan.
                Action::make('toggleActive')
                    ->label(fn (Trainers $record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (Trainers $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Trainers $record) => $record->is_active ? 'warning' : 'success')
                    ->visible(fn (Trainers $record) => TrainersResource::canEdit($record) && $record->trainings_count > 0)
                    ->action(fn (Trainers $record) => $record->update(['is_active' => ! $record->is_active])),
                DeleteAction::make()
                    ->visible(fn (Trainers $record) => TrainersResource::canDelete($record) && $record->trainings_count === 0),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
