<?php

namespace App\Filament\Resources\Announcements\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Moderasi komentar pengumuman: admin cuma bisa membaca dan menghapus (pengirim lalu boleh komentar ulang). */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Komentar';

    protected static ?string $modelLabel = 'Komentar';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('user.name')->label('Pengirim')->placeholder('Pengguna dihapus'),
                TextColumn::make('body')->label('Komentar')->wrap(),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('created_at')
            ->recordActions([DeleteAction::make()]);
    }
}
