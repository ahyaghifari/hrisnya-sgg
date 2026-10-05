<?php

namespace App\Filament\Resources\Task\Tasks\Tables;

use App\Enum\Task\TaskPriority;
use App\Enum\Task\TaskStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Daftar ini cuma penugasan individu; tugas Board dibuka lewat menu Board.
            ->modifyQueryUsing(fn ($query) => $query->whereNull('project_id'))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('requirement')->label('Tugas')->searchable()->limit(50)->wrap(),
                TextColumn::make('assignedTo.name')->label('Pelaksana')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => TaskStatus::tryFrom((string) $state)?->getLabel() ?? $state),
                TextColumn::make('priority')->label('Prioritas')->badge()
                    ->formatStateUsing(fn ($state) => ($state instanceof TaskPriority ? $state : TaskPriority::tryFrom((string) $state))?->getLabel() ?? $state),
                TextColumn::make('due_date')->label('Tenggat')->dateTime('d M Y H:i')->sortable()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')
                    ->options(collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all()),
                SelectFilter::make('priority')->label('Prioritas')
                    ->options(collect(TaskPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->getLabel()])->all()),
            ])
            ->recordActions([EditAction::make()->label('Edit')])
            ->defaultSort('created_at', 'desc');
    }
}
