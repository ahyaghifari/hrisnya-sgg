<?php

namespace App\Filament\Resources\Assessment\Assessments\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AssessmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Penilaian')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('template.assessmentType.name')
                    ->label('Tipe Penilaian')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'published' => 'Published',
                        default => $state,
                    })
                    ->colors([
                        'danger' => 'inactive',
                        'success' => 'active',
                    ])
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Mulai Penilaian')
                    ->dateTime('j F Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Akhir Penilaian')
                    ->dateTime('j F Y')
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
                ViewAction::make()->hiddenLabel()->color('primary'),
                // EditAction::make()->hiddenLabel()->color('primary'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
