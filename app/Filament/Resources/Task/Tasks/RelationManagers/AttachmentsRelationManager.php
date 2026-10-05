<?php

namespace App\Filament\Resources\Task\Tasks\RelationManagers;

use App\Models\Task\TaskAttachment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Lampiran: admin bisa mengoreksi keterangan/judul link dan menghapus. Unggah baru lewat portal. */
class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Lampiran';

    protected static ?string $modelLabel = 'Lampiran';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('file_name')->label('Nama / Judul')->maxLength(255),
            TextInput::make('url')->label('URL')->url()->maxLength(2048)
                ->visible(fn (?TaskAttachment $record) => $record?->type === TaskAttachment::TYPE_LINK),
            TextInput::make('description')->label('Keterangan')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_name')
            ->columns([
                TextColumn::make('type')->label('Jenis')->badge()
                    ->formatStateUsing(fn ($state) => $state === TaskAttachment::TYPE_LINK ? 'Link' : 'File'),
                TextColumn::make('file_name')->label('Nama / Judul')->wrap()->placeholder('-'),
                TextColumn::make('description')->label('Keterangan')->wrap()->limit(80)->placeholder('-'),
                TextColumn::make('uploadedBy.name')->label('Pengunggah')->placeholder('Pengguna dihapus'),
                TextColumn::make('created_at')->label('Diunggah')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
