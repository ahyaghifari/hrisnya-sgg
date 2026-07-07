<?php

namespace App\Filament\Resources\Payroll\TrainerSalaries\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TrainerSalariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trainer.name')
                    ->label('Trainer Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('course.title')
                    ->label('Nama Pelatihan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('honor_amount')
                    ->label('Honor')
                    ->money('idr', true)
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date('j F Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
