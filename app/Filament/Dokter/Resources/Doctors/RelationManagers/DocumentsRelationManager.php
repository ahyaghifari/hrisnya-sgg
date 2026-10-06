<?php

namespace App\Filament\Dokter\Resources\Doctors\RelationManagers;

use App\Enum\Doctor\DoctorDocumentType;
use App\Enum\Doctor\DoctorLicenseType;
use App\Models\Doctor\DoctorDocument;
use App\Services\Doctor\DoctorDocumentService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Dokumen dokter (Fase 2). File dienkripsi (Crypt) di disk privat 'local' dengan nama acak, dibuka
 * lewat route filament.dokter.dokumen.show (cek izin panel + audit log), bukan URL publik.
 * Ganti dokumen = unggah baru lalu nonaktifkan yang lama (riwayat tidak ditimpa).
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumen';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('document_type')->label('Jenis Dokumen')->options(DoctorDocumentType::class)->required(),
            // storeFiles(false): file disimpan manual (terenkripsi) di storeDocument(), bukan oleh Filament.
            FileUpload::make('file')->label('File')->storeFiles(false)->dehydrated()
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(10240)->helperText('PDF/JPG/PNG, maksimal 10 MB.')
                ->required(fn (string $operation) => $operation === 'create')
                ->visibleOn('create'),
            TextInput::make('document_number')->label('Nomor Dokumen')->maxLength(150),
            DatePicker::make('issued_at')->label('Tanggal Terbit'),
            DatePicker::make('expired_at')->label('Berlaku Sampai')->afterOrEqual('issued_at'),
            Select::make('status')->label('Status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),
            Textarea::make('notes')->label('Catatan')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('document_type')->label('Jenis')->badge()->formatStateUsing(fn ($state) => DoctorDocumentType::tryFrom($state)?->getLabel() ?? DoctorLicenseType::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('file_name')->label('File')->limit(30),
                TextColumn::make('document_number')->label('Nomor')->placeholder('-'),
                TextColumn::make('expired_at')->label('Berlaku Sampai')->date('j M Y')->placeholder('-'),
                TextColumn::make('status_efektif')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) { 'aktif' => 'success', 'expired' => 'danger', default => 'gray' }),
            ])
            ->headerActions([
                CreateAction::make()->label('Unggah Dokumen')
                    ->using(fn (array $data) => $this->storeDocument($data)),
            ])
            ->recordActions([
                Action::make('lihat')->label('Lihat')->icon('heroicon-o-eye')
                    ->url(fn (DoctorDocument $record) => route('filament.dokter.dokumen.show', $record), shouldOpenInNewTab: true),
                Action::make('unduh')->label('Unduh')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (DoctorDocument $record) => route('filament.dokter.dokumen.show', [$record, 'download' => 1])),
                EditAction::make()->after(fn (DoctorDocument $record) => $this->audit($record, 'ubah')),
                DeleteAction::make()->after(fn (DoctorDocument $record) => $this->audit($record, 'hapus')),
            ]);
    }

    private function storeDocument(array $data): DoctorDocument
    {
        return app(DoctorDocumentService::class)->store($this->getOwnerRecord(), collect($data)->except('file')->all(), $data['file']);
    }

    private function audit(DoctorDocument $document, string $aksi): void
    {
        app(DoctorDocumentService::class)->audit($document, $aksi);
    }
}
