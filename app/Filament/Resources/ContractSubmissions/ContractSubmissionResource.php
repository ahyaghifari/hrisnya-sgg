<?php

namespace App\Filament\Resources\ContractSubmissions;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\ContractSubmissions\Pages\ManageContractSubmissions;
use App\Models\ContractSubmission;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pantauan HR atas pengajuan perpanjangan kontrak (read-only). Alur sesungguhnya (ajukan,
 * upload, nilai) ada di portal self-service — lihat App\Livewire\Kepegawaian\KontrakSayaPage
 * & KontrakDetailPage.
 */
class ContractSubmissionResource extends BaseCompanyResource
{
    protected static ?string $model = ContractSubmission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Kontrak & Orientasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Pengajuan Perpanjangan Kontrak';

    protected static ?string $modelLabel = 'Pengajuan Perpanjangan Kontrak';

    protected static ?string $pluralModelLabel = 'Pengajuan Perpanjangan Kontrak';

    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Karyawan')->searchable()->sortable(),
                TextColumn::make('user.nip')->label('NIP')->searchable(),
                TextColumn::make('userContract')
                    ->label('Kontrak Berjalan')
                    ->state(fn ($record) => $record->userContract
                        ? $record->userContract->contract_start_date?->format('d M Y') . ' – ' . $record->userContract->contract_end_date?->format('d M Y')
                        : '—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state) => match ($state) {
                        'selesai' => 'success',
                        'ditolak', 'dibatalkan' => 'danger',
                        'diajukan' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('decision')
                    ->label('Keputusan')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? str($state)->replace('_', ' ')->title() : null)
                    ->color(fn (?string $state) => match ($state) {
                        'perpanjang' => 'success',
                        'tidak_perpanjang' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('decision_durasi_bulan')->label('Durasi (bln)')->placeholder('—'),
                TextColumn::make('submitted_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('decided_at')->label('Diputuskan')->dateTime('d M Y H:i')->sortable()->placeholder('—'),
                BaseCompanyResource::companyTableColumn(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                BaseCompanyResource::companyTableFilter(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContractSubmissions::route('/'),
        ];
    }
}
