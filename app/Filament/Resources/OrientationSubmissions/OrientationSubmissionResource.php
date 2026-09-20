<?php

namespace App\Filament\Resources\OrientationSubmissions;

use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\OrientationSubmissions\Pages\ManageOrientationSubmissions;
use App\Models\OrientationSubmission;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pantauan HR atas pengajuan penilaian orientasi (read-only). Alur sesungguhnya (ajukan,
 * upload, nilai) ada di portal self-service — lihat App\Livewire\Kepegawaian\OrientasiPage
 * & OrientasiDetailPage.
 */
class OrientationSubmissionResource extends BaseCompanyResource
{
    protected static ?string $model = OrientationSubmission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Sikartap';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Pengajuan Orientasi';

    protected static ?string $modelLabel = 'Pengajuan Orientasi';

    protected static ?string $pluralModelLabel = 'Pengajuan Orientasi';

    protected static ?int $navigationSort = 1; // urutan grup Sikartap: Orientasi, Kontrak, Karyawan Tetap

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
                TextColumn::make('userOrientation')
                    ->label('Periode Orientasi')
                    ->state(fn ($record) => $record->userOrientation
                        ? $record->userOrientation->start_date?->format('d M Y') . ' – ' . $record->userOrientation->end_date?->format('d M Y')
                        : '—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state) => match ($state) {
                        'selesai' => 'success',
                        'dibatalkan' => 'danger',
                        'menunggu_persetujuan', 'dinilai' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('decision')
                    ->label('Keputusan')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? str($state)->replace('_', ' ')->title() : null)
                    ->color(fn (?string $state) => match ($state) {
                        'lanjut_kontrak' => 'success',
                        'tidak_dilanjutkan' => 'danger',
                        default => 'gray',
                    }),
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
            'index' => ManageOrientationSubmissions::route('/'),
        ];
    }
}
