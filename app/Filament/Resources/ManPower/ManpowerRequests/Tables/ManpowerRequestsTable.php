<?php

namespace App\Filament\Resources\ManPower\ManpowerRequests\Tables;

use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Filament\Resources\ManPower\JobPositions\JobPositionResource;
use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManpowerRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobPosition.name')
                    ->label('Posisi Jabatan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jobPosition.orgStructure.name')
                    ->label('Unit')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipe Perubahan')
                    ->formatStateUsing(fn ($state) => ManpowerRequestType::from($state)->getLabel())
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status Persetujuan')
                    ->formatStateUsing(fn ($state) => JobPositionReqStatus::from($state)->getLabel())
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'requested' => 'warning',
                        default => 'gray',
                    })
                    ->alignCenter(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => JobPositionResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
