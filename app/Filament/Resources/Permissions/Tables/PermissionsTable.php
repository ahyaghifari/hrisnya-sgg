<?php

namespace App\Filament\Resources\Permissions\Tables;

use App\Models\Permission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Permission')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->sortable(),

                TextColumn::make('roles_count')
                    ->label('Dipakai di Role')
                    ->counts('roles')
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('asal')
                    ->label('Asal')
                    ->options([
                        'shield' => 'Dibuat Shield',
                        'manual' => 'Dibuat Manual',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'shield' => $query->where('name', 'like', '%:%'),
                        'manual' => $query->where('name', 'not like', '%:%'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                // Cuma keliatan buat permission yang BUKAN format Shield (Verb:Subject) —
                // permission hasil generate Shield sengaja tidak bisa diubah/dihapus lewat
                // sini (dikelola otomatis oleh shield:generate, bukan manual).
                EditAction::make()
                    ->modalWidth('md')
                    ->visible(fn (Permission $record) => ! str_contains($record->name, ':')),

                // Cuma keliatan buat permission yang BUKAN format Shield (Verb:Subject) —
                // peninggalan seeder lama, aman dihapus karena tidak ada kode yang cek lagi.
                // Disembunyikan juga kalau masih di-assign ke role manapun, biar gak ninggalin
                // role dengan permission yatim.
                DeleteAction::make()
                    ->label('Hapus')
                    ->visible(fn (Permission $record) => ! str_contains($record->name, ':') && ! $record->roles()->exists()),
            ]);
    }
}
