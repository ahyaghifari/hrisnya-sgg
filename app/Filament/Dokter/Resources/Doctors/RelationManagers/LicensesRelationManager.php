<?php

namespace App\Filament\Dokter\Resources\Doctors\RelationManagers;

use App\Enum\Doctor\DoctorLicenseType;
use App\Models\Doctor\DoctorLicense;
use App\Services\Doctor\DoctorLicenseService;
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

/** SIP / STR / STR-KT dokter (Fase 3). File scan opsional: disimpan sebagai dokumen terenkripsi & ditautkan. */
class LicensesRelationManager extends RelationManager
{
    protected static string $relationship = 'licenses';

    protected static ?string $title = 'Lisensi (SIP/STR)';

    public const STATUS_LABELS = ['aktif' => 'Aktif', 'akan_expired' => 'Akan Expired', 'expired' => 'Expired', 'nonaktif' => 'Nonaktif'];

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('jenis')->label('Jenis')->options(DoctorLicenseType::class)->required(),
            TextInput::make('nomor')->label('Nomor')->maxLength(150),
            DatePicker::make('tanggal_terbit')->label('Tanggal Terbit'),
            DatePicker::make('tanggal_berlaku')->label('Berlaku Mulai'),
            DatePicker::make('tanggal_berakhir')->label('Berakhir')->afterOrEqual('tanggal_berlaku'),
            Select::make('status')->label('Status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),
            // storeFiles(false): disimpan manual (terenkripsi) oleh DoctorLicenseService; hanya saat tambah.
            FileUpload::make('file')->label('File Scan (opsional)')->storeFiles(false)->dehydrated()->columnSpanFull()
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)
                ->helperText('PDF/JPG/PNG, maks. 10 MB. Ikut muncul di tab Dokumen.')->visibleOn('create'),
            Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_berakhir', 'desc')
            ->columns([
                TextColumn::make('jenis')->label('Jenis')->badge()->formatStateUsing(fn ($state) => DoctorLicenseType::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('nomor')->label('Nomor')->placeholder('-'),
                TextColumn::make('tanggal_berlaku')->label('Berlaku Mulai')->date('j M Y')->placeholder('-'),
                TextColumn::make('tanggal_berakhir')->label('Berakhir')->date('j M Y')->placeholder('-'),
                TextColumn::make('status_efektif')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'aktif' => 'success', 'akan_expired' => 'warning', 'expired' => 'danger', default => 'gray' }),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Lisensi')
                    ->using(fn (array $data) => app(DoctorLicenseService::class)->store(
                        $this->getOwnerRecord(), collect($data)->except('file')->all(), $data['file'] ?? null,
                    )),
            ])
            ->recordActions([
                Action::make('lihat')->label('Lihat File')->icon('heroicon-o-eye')
                    ->visible(fn (DoctorLicense $record) => (bool) $record->doctor_document_id)
                    ->url(fn (DoctorLicense $record) => route('filament.dokter.dokumen.show', $record->doctor_document_id), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
