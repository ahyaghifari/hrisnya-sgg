<?php

namespace App\Filament\Resources\LMS\LmsCoursePlanItems\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LmsCoursePlanItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('proposed_title')
                    ->label('Nama Pelatihan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('request_status')
                    ->label('Status Pengajuan'),
                    
                TextColumn::make('requested_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime('d M Y H:i'),
            ])
            ->filters([
                // 
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
