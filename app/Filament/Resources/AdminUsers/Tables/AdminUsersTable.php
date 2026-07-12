<?php

namespace App\Filament\Resources\AdminUsers\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah')
                    ->modalWidth('md')
                    ->mutateFormDataUsing(function (array $data) {
                        if (! Role::where('name', 'Admin')->where('company_id', $data['company_id'] ?? null)->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Perusahaan ini tidak punya role "Admin"')
                                ->body('Kemungkinan ini company holding (pakai role "Super Admin", bukan "Admin") — perubahan belum disimpan.')
                                ->send();

                            throw new Halt();
                        }

                        return $data;
                    })
                    ->after(function (User $record) {
                        $role = Role::where('name', 'Admin')
                            ->where('company_id', $record->company_id)
                            ->firstOrFail();

                        // Company (jadi role Admin-nya) bisa diganti pas edit — sync biar akun
                        // cuma punya 1 role "Admin" yang sesuai company terbaru.
                        $record->syncRoles([$role]);
                    }),

                DeleteAction::make()
                    ->label('Hapus'),
            ]);
    }
}
