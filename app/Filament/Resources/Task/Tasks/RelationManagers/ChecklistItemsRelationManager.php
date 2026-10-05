<?php

namespace App\Filament\Resources\Task\Tasks\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItems';

    protected static ?string $title = 'Checklist';

    protected static ?string $modelLabel = 'Item Checklist';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Judul')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('position')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_done')->label('Selesai'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('position')->label('#'),
                TextColumn::make('title')->label('Judul')->wrap(),
                IconColumn::make('is_done')->label('Selesai')->boolean(),
                TextColumn::make('done_at')->label('Selesai Pada')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->defaultSort('position')
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['created_by' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(function (array $data) {
                    // Jaga konsistensi done_at dengan is_done.
                    $data['done_at'] = ($data['is_done'] ?? false) ? now() : null;

                    return $data;
                }),
                DeleteAction::make(),
            ]);
    }
}
