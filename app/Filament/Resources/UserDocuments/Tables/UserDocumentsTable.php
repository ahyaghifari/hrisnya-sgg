<?php

namespace App\Filament\Resources\UserDocuments\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_name')->label('Nama Karyawan')->searchable(),
                TextColumn::make('document_number')->label('Nomor Dokumen')->searchable(),
                TextColumn::make('issued_date')->label('Tanggal Dikeluarkan')->date('d-m-Y')->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('user.company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
