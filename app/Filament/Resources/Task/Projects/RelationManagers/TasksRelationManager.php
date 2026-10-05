<?php

namespace App\Filament\Resources\Task\Projects\RelationManagers;

use App\Enum\Task\TaskStatus;
use App\Filament\Resources\Task\Tasks\TaskResource;
use App\Models\Task\Task;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Pilihan tugas di dalam Board - klik baris membuka halaman edit tugas (checklist/komentar/lampiran ada di sana). */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tugas';

    protected static ?string $modelLabel = 'Tugas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('requirement')
            ->recordUrl(fn (Task $record) => TaskResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('requirement')->label('Tugas')->searchable()->limit(50)->wrap(),
                TextColumn::make('list.name')->label('List')->badge()->placeholder('-'),
                TextColumn::make('assignedTo.name')->label('Pelaksana')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => TaskStatus::tryFrom((string) $state)?->getLabel() ?? $state),
                TextColumn::make('due_date')->label('Tenggat')->dateTime('d M Y H:i')->sortable()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('list_id')->label('List')
                    ->options(fn () => $this->getOwnerRecord()->lists()->pluck('name', 'id')->all()),
                SelectFilter::make('status')->label('Status')
                    ->options(collect(TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
