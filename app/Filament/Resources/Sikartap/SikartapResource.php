<?php

namespace App\Filament\Resources\Sikartap;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Sikartap\Pages\ListSikartap;
use App\Filament\Resources\Sikartap\Pages\ViewSikartap;
use App\Models\Sikartap\Sikartap;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Data dibuat dari portal karyawan (Livewire), bukan Filament - resource ini
 * khusus sisi HRD/Direktur (lihat knowledge/markdown/rencana-implementasi-sikartap.md
 * § Filament Resources), makanya tidak ada halaman create/edit.
 */
class SikartapResource extends BaseResource
{
    protected static ?string $model = Sikartap::class;

    protected static ?string $navigationLabel = 'Pengajuan Karyawan Tetap';

    protected static ?string $modelLabel = 'Pengajuan Karyawan Tetap';

    protected static ?string $pluralModelLabel = 'Pengajuan Karyawan Tetap';

    protected static string|UnitEnum|null $navigationGroup = 'Sikartap';
    // Urutan grup Sikartap: Pengajuan Orientasi (1), Perpanjangan Kontrak (2), Karyawan Tetap (3).
    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.nip')
                    ->label('NIP')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status_berkas')
                    ->label('Berkas')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state)),

                TextColumn::make('status_tes')
                    ->label('Tes')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state)),

                TextColumn::make('status_wawancara')
                    ->label('Wawancara')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state)),

                TextColumn::make('status_mcu')
                    ->label('MCU')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state)),

                TextColumn::make('status_keputusan_direktur')
                    ->label('Keputusan Direktur')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state)),

                TextColumn::make('is_diskualifikasi')
                    ->label('Diskualifikasi')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak'),

                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('j F Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Karyawan')
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        TextEntry::make('user.nip')->label('NIP'),
                        TextEntry::make('user.name')->label('Nama'),
                        TextEntry::make('user.email')->label('Email'),
                    ]),

                Section::make('Status Pengajuan')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->schema([
                        TextEntry::make('status_berkas')->label('Berkas')->badge()
                            ->color(fn (string $state): string => static::statusColor($state)),
                        TextEntry::make('status_tes')->label('Tes')->badge()
                            ->color(fn (string $state): string => static::statusColor($state)),
                        TextEntry::make('status_wawancara')->label('Wawancara')->badge()
                            ->color(fn (string $state): string => static::statusColor($state)),
                        TextEntry::make('status_mcu')->label('MCU')->badge()
                            ->color(fn (string $state): string => static::statusColor($state)),
                        TextEntry::make('status_keputusan_direktur')->label('Keputusan Direktur')->badge()
                            ->color(fn (string $state): string => static::statusColor($state)),
                        TextEntry::make('alasan_diskualifikasi')->label('Alasan Diskualifikasi')
                            ->visible(fn (Sikartap $record): bool => $record->is_diskualifikasi),
                    ]),

                Section::make('Jadwal & Hasil Tes')
                    ->icon(Heroicon::OutlinedCalendar)
                    ->visible(fn (Sikartap $record): bool => $record->testSchedule !== null)
                    ->schema([
                        TextEntry::make('testSchedule.tanggal_tes')->label('Tanggal')->date('j F Y'),
                        TextEntry::make('testSchedule.waktu_tes')->label('Waktu'),
                        TextEntry::make('testSchedule.tempat_tes')->label('Tempat'),
                        TextEntry::make('nilai_tes')->label('Nilai Tes Bidang')->placeholder('-'),
                        TextEntry::make('nilai_psikotes')->label('Nilai Psikotes')->placeholder('-'),
                        TextEntry::make('catatan_tes')->label('Catatan')->placeholder('-'),
                    ]),

                Section::make('Jadwal & Hasil Wawancara')
                    ->icon(Heroicon::OutlinedCalendar)
                    ->visible(fn (Sikartap $record): bool => $record->interviewSchedule !== null)
                    ->schema([
                        TextEntry::make('interviewSchedule.tanggal_wawancara')->label('Tanggal')->date('j F Y'),
                        TextEntry::make('interviewSchedule.waktu_wawancara')->label('Waktu'),
                        TextEntry::make('interviewSchedule.tempat_wawancara')->label('Tempat'),
                        TextEntry::make('interviewSchedule.pewawancara')->label('Pewawancara 1'),
                        TextEntry::make('interviewSchedule.pewawancara_2')->label('Pewawancara 2')->placeholder('-'),
                        TextEntry::make('catatan_wawancara')->label('Catatan')->placeholder('-'),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user.company', 'testSchedule', 'interviewSchedule']);

        if (! static::canBrowseAllCompanies() && static::companyId()) {
            $query->whereHas('user', fn ($q) => $q->where('company_id', static::companyId()));
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Sikartap\RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSikartap::route('/'),
            'view' => ViewSikartap::route('/{record}'),
        ];
    }

    /**
     * BaseResource::canAccess() default cuma gerbang role Super Admin/Admin literal -
     * Sikartap digerbangi permission Shield (ViewAny:Sikartap) supaya role non-Admin
     * (HRD, Direktur) bisa dikasih akses tanpa perlu jadi role Admin.
     */
    public static function canAccess(): bool
    {
        return static::isSuperAdmin() || static::user()->can('ViewAny:Sikartap');
    }

    protected static function statusColor(string $state): string
    {
        return match ($state) {
            'approved', 'lulus', 'disarankan', 'fit' => 'success',
            'rejected', 'tidak_lulus', 'tidak_disarankan', 'unfit' => 'danger',
            default => 'warning',
        };
    }
}
