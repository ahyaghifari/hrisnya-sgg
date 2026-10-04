<?php

namespace App\Filament\Resources\EmployeeSps\Tables;

use App\Filament\Resources\UserColumn;
use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
use App\Models\Sp\EmployeeSp;
use App\Services\Sp\SpPdfService;
use App\Services\Sp\SpService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EmployeeSpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->sortable(query: UserColumn::sortName())
                    ->searchable(query: UserColumn::searchName()),

                TextColumn::make('nomor')
                    ->label('Nomor')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('sp_level')
                    ->label('Level')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(function (EmployeeSp $record) {
                        if ($record->isCancelled()) {
                            return 'Dibatalkan';
                        }

                        $isActive = $record->start_date->lte(today()) && ($record->end_date === null || $record->end_date->gte(today()));

                        return $isActive ? 'Aktif' : 'Expired';
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Dibatalkan' => 'danger',
                        'Aktif' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('source_type')
                    ->label('Sumber'),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Berakhir')
                    ->date()
                    ->placeholder('Tidak pernah')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sp_level')
                    ->label('Level SP')
                    ->options(collect(SpLevel::cases())->mapWithKeys(fn ($level) => [$level->value => $level->getLabel()])),

                SelectFilter::make('source_type')
                    ->label('Sumber')
                    ->options(collect(SpSourceType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->getLabel()])),

                TernaryFilter::make('cancelled_at')
                    ->label('Status Batal')
                    ->nullable()
                    ->trueLabel('Dibatalkan')
                    ->falseLabel('Belum Dibatalkan')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('cancelled_at'),
                        false: fn ($query) => $query->whereNull('cancelled_at'),
                    ),
            ])
            ->recordActions([
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(fn (EmployeeSp $record) => app(SpPdfService::class)->download($record)),

                Action::make('cancelSp')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EmployeeSp $record) => ! $record->isCancelled())
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan SP?')
                    ->modalDescription('SP tidak dihapus, cuma ditandai batal - tetap tercatat sebagai riwayat dan tidak lagi dihitung aktif.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(fn (EmployeeSp $record, array $data) => app(SpService::class)->cancel($record, $data['reason'], Auth::id())),

                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
