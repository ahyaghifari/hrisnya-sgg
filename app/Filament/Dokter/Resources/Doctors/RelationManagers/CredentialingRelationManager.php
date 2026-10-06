<?php

namespace App\Filament\Dokter\Resources\Doctors\RelationManagers;

use App\Enum\Doctor\CredentialingStatus;
use App\Models\Doctor\CredentialingCase;
use App\Models\Master\Companies;
use App\Services\Doctor\DoctorCredentialingService;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Credentialing dokter (Fase 5): satu case per proses di satu RS, dengan 1 PDF final dan data SK SPK
 * (nomor + masa berlaku). Riwayat case lama tidak ditimpa; PDF baru ditautkan, PDF lama tetap di tab Dokumen.
 */
class CredentialingRelationManager extends RelationManager
{
    protected static string $relationship = 'credentialingCases';

    protected static ?string $title = 'Credentialing';

    public const SPK_LABELS = ['aktif' => 'Aktif', 'akan_expired' => 'Akan Expired', 'expired' => 'Expired'];

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('rumah_sakit_id')->label('Rumah Sakit')->required()->searchable()
                ->options(fn () => Companies::query()->orderBy('name')->pluck('name', 'id')),
            TextInput::make('nomor')->label('Nomor Credentialing')->maxLength(150),
            Select::make('jenis')->label('Jenis')->options(['kredensial_awal' => 'Kredensial Awal', 'rekredensial' => 'Rekredensial']),
            Select::make('status')->label('Status')->options(CredentialingStatus::class)->default('draft')->required(),
            DatePicker::make('tanggal_pengajuan')->label('Tanggal Pengajuan'),
            DatePicker::make('tanggal_selesai')->label('Tanggal Selesai'),
            Section::make('SK SPK (Surat Penugasan Klinis)')->columnSpanFull()->columns(3)->schema([
                TextInput::make('spk_nomor')->label('Nomor SK SPK')->maxLength(150),
                DatePicker::make('spk_terbit')->label('Tanggal Terbit'),
                DatePicker::make('spk_berakhir')->label('Berlaku Sampai')->afterOrEqual('spk_terbit'),
            ]),
            // storeFiles(false): disimpan manual (terenkripsi) oleh DoctorCredentialingService.
            FileUpload::make('file')->label('PDF Final')->storeFiles(false)->dehydrated()->columnSpanFull()
                ->acceptedFileTypes(['application/pdf'])->maxSize(20480)
                ->helperText('Satu PDF gabungan: permohonan, undangan, berkas RKK, absensi, rekomendasi, SK SPK. Maks. 20 MB. Kalau diunggah ulang, PDF lama tetap tersimpan di tab Dokumen.'),
            Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_pengajuan', 'desc')
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->placeholder('-'),
                TextColumn::make('rumah_sakit_id')->label('RS')
                    ->formatStateUsing(fn (string $state) => Companies::find($state)?->name ?? '-'),
                TextColumn::make('jenis')->label('Jenis')->placeholder('-')
                    ->formatStateUsing(fn (?string $state) => ['kredensial_awal' => 'Kredensial Awal', 'rekredensial' => 'Rekredensial'][$state] ?? $state),
                TextColumn::make('tanggal_pengajuan')->label('Pengajuan')->date('j M Y')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => CredentialingStatus::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state) => CredentialingStatus::tryFrom($state)?->getColor() ?? 'gray'),
                TextColumn::make('spk_status')->label('SK SPK')->badge()->placeholder('-')
                    ->formatStateUsing(fn (?string $state) => self::SPK_LABELS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'aktif' => 'success', 'akan_expired' => 'warning', 'expired' => 'danger', default => 'gray' }),
                TextColumn::make('spk_berakhir')->label('SPK Berakhir')->date('j M Y')->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Credentialing')
                    ->using(fn (array $data) => app(DoctorCredentialingService::class)->save($this->getOwnerRecord(), $data, $data['file'] ?? null)),
            ])
            ->recordActions([
                Action::make('lihat')->label('Lihat PDF')->icon('heroicon-o-eye')
                    ->visible(fn (CredentialingCase $record) => (bool) $record->doctor_document_id)
                    ->url(fn (CredentialingCase $record) => route('filament.dokter.dokumen.show', $record->doctor_document_id), shouldOpenInNewTab: true),
                EditAction::make()->using(fn (CredentialingCase $record, array $data) => app(DoctorCredentialingService::class)
                    ->save($this->getOwnerRecord(), $data, $data['file'] ?? null, $record)),
                DeleteAction::make(),
            ]);
    }
}
