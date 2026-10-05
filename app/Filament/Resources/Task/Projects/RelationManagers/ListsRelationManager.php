<?php

namespace App\Filament\Resources\Task\Projects\RelationManagers;

use App\Models\Task\TaskList;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** List (kolom) Board. Maksimal 1 List Selesai dan 1 List Review per Board. */
class ListsRelationManager extends RelationManager
{
    protected static string $relationship = 'lists';

    protected static ?string $title = 'List';

    protected static ?string $modelLabel = 'List';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama List')->required()->maxLength(100),
            Select::make('color')->label('Warna')->options(TaskList::COLOR_PRESETS),
            TextInput::make('position')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_review')->label('List Review')
                ->rule(fn (?TaskList $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    if ($value && $this->otherListWith('is_review', $record)) {
                        $fail('Board ini sudah punya List Review.');
                    }
                }),
            Toggle::make('is_final')->label('List Selesai')
                ->rule(fn (?TaskList $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    if ($value && $this->otherListWith('is_final', $record)) {
                        $fail('Board ini sudah punya List Selesai.');
                    }
                }),
        ])->columns(2);
    }

    private function otherListWith(string $flag, ?TaskList $record): bool
    {
        return $this->getOwnerRecord()->lists()->where($flag, true)
            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->label('List'),
                TextColumn::make('color')->label('Warna')->formatStateUsing(fn ($state) => TaskList::COLOR_PRESETS[$state] ?? $state)->placeholder('-'),
                IconColumn::make('is_review')->label('Review')->boolean(),
                IconColumn::make('is_final')->label('Selesai')->boolean(),
                TextColumn::make('tasks_count')->label('Tugas')->counts('tasks'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                // Tidak boleh menghapus List yang masih berisi tugas - pindahkan dulu lewat edit tugas.
                DeleteAction::make()->hidden(fn (TaskList $record) => $record->tasks()->exists()),
            ]);
    }
}
