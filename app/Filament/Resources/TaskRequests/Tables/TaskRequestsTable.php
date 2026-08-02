<?php

namespace App\Filament\Resources\TaskRequests\Tables;

use App\Enum\Task\TaskStatus;
use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TaskRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignedTo.name')
                    ->label('Assignee')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('service.name')
                    ->label('Task Service')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                IconColumn::make('is_self_assigned')
                    ->label('Self Assigned')
                    ->boolean(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->icon(fn ($state) => TaskStatus::tryFrom($state)?->getIcon())
                    ->color(fn ($state) => TaskStatus::tryFrom($state)?->getColor())
                    ->formatStateUsing(fn ($state) => TaskStatus::tryFrom($state)?->getLabel() ?? '-'),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel(),
            ])
            ->toolbarActions([
                //BulkActionGroup::make([
                    //DeleteBulkAction::make(),
                //]),
            ]);
    }
}
