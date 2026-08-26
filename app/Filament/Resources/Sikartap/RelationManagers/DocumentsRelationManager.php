<?php

namespace App\Filament\Resources\Sikartap\RelationManagers;

use App\Enum\Sikartap\SikartapDocumentType;
use App\Models\Sikartap\SikartapDocumentAttachment;
use App\Notifications\Sikartap\SikartapDocumentRejected;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Rewrite verifikasi_berkas.php legacy — approve/reject per dokumen oleh HRD. BUKAN lewat
 * Approval Engine (lihat analisis knowledge/markdown/rencana-implementasi-sikartap.md §
 * Analisis Approval Engine vs custom): 1 role cek 11 dokumen independen, bukan chain
 * approver berjenjang.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumen Administratif';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('doc_type')
            ->columns([
                TextColumn::make('doc_type')
                    ->label('Jenis Dokumen')
                    ->formatStateUsing(fn (SikartapDocumentType $state): string => $state->getLabel())
                    ->description(fn (SikartapDocumentAttachment $record): ?string => $record->doc_type->isRequired() ? 'Wajib' : null),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('catatan_penolakan')
                    ->label('Catatan Penolakan')
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->icon(Heroicon::OutlinedEye)
                    ->hiddenLabel()
                    ->url(fn (SikartapDocumentAttachment $record): string => Storage::disk('public')->url($record->file_path))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->hiddenLabel()
                    ->requiresConfirmation()
                    ->visible(fn (SikartapDocumentAttachment $record): bool => $record->status !== 'approved'
                        && Auth::user()->can('Update:Sikartap'))
                    ->action(function (SikartapDocumentAttachment $record): void {
                        $record->update(['status' => 'approved', 'catatan_penolakan' => null]);

                        Notification::make()->title('Dokumen disetujui')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->hiddenLabel()
                    ->visible(fn (SikartapDocumentAttachment $record): bool => $record->status !== 'rejected'
                        && Auth::user()->can('Update:Sikartap'))
                    ->schema([
                        Textarea::make('catatan_penolakan')
                            ->label('Catatan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (SikartapDocumentAttachment $record, array $data): void {
                        $record->update(['status' => 'rejected', 'catatan_penolakan' => $data['catatan_penolakan']]);

                        $record->sikartap->user?->notify(
                            new SikartapDocumentRejected($record->sikartap_id, $record->doc_type->getLabel(), $data['catatan_penolakan'])
                        );

                        Notification::make()->title('Dokumen ditolak')->success()->send();
                    }),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
