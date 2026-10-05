<?php

namespace App\Filament\Resources\Task\Tasks\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Moderasi komentar: admin bisa mengoreksi atau menghapus, tidak menulis komentar baru atas nama orang lain. */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Komentar';

    protected static ?string $modelLabel = 'Komentar';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('comment')->label('Komentar')->required()->rows(4)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comment')
            ->columns([
                TextColumn::make('user.name')->label('Pengirim')->placeholder('Pengguna dihapus'),
                TextColumn::make('comment')->label('Komentar')->limit(120)->wrap(),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('created_at')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
