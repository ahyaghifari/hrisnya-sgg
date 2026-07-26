<?php

namespace App\Filament\Resources\Counselings\Tables;

use App\Enum\CounselingStatus;
use App\Filament\Resources\Master\TaskServices\TaskServiceResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CounselingsTable
{
    /**
     * Isi keluhan/tanggapan RAHASIA — cuma karyawan yang konsul & konselor yang ditunjuk yang
     * boleh baca (lihat Counseling::isVisibleTo()), TERMASUK Admin/Super Admin panel ini tidak
     * boleh, walau Shield permission-nya lengkap (instruksi user 2026-07-26). Dipakai di semua
     * kolom/field issue/response/solution di table & infolist ini.
     */
    private static function redacted($record, ?string $value): string
    {
        return $record->isVisibleTo(Auth::id()) ? (string) ($value ?? '-') : '🔒 Rahasia';
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('counselor.name')
                    ->label('Konselor')
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Company')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('issue')
                    ->label('Issue')
                    ->formatStateUsing(fn ($record, $state) => self::redacted($record, $state))
                    ->limit(30),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->icon(fn ($state) => CounselingStatus::tryFrom($state)?->getIcon())
                    ->color(fn ($state) => CounselingStatus::tryFrom($state)?->getColor())
                    ->formatStateUsing(fn ($state) => CounselingStatus::tryFrom($state)?->getLabel() ?? '-'),

                TextColumn::make('respondedBy.name')
                    ->label('Responded By')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalHeading('Detail Konseling')
                    ->modalWidth('lg')
                    ->hiddenLabel()
                    ->infolist([
                        Section::make('Informasi Konseling')
                            ->schema([
                                TextEntry::make('user.name')->label('User'),
                                TextEntry::make('company.name')->label('Company'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string =>
                                        CounselingStatus::tryFrom($state)?->getColor() ?? 'gray'
                                    )
                                    ->formatStateUsing(fn (string $state): string =>
                                        CounselingStatus::tryFrom($state)?->getLabel() ?? $state
                                    ),
                            ]),

                        Section::make('Issue')
                            ->schema([
                                TextEntry::make('issue')
                                    ->label('Issue')
                                    ->formatStateUsing(fn ($record, $state) => self::redacted($record, $state))
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Tanggapan')
                            ->schema([
                                TextEntry::make('response')
                                    ->label('Response')
                                    ->formatStateUsing(fn ($record, $state) => self::redacted($record, $state))
                                    ->columnSpanFull(),
                                TextEntry::make('solution')
                                    ->label('Solution')
                                    ->formatStateUsing(fn ($record, $state) => self::redacted($record, $state))
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Detail Tanggapan')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('respondedBy.name')->label('Responded By'),
                                    TextEntry::make('responded_at')->label('Responded At'),
                                ]),
                            ]),
                    ]),
                EditAction::make()
                    ->hiddenLabel()
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Tanggapan')
                    ->visible(fn ($record) => $record->status == CounselingStatus::PENDING->value && $record->counselor_id === Auth::id())
                    ->using(function ($record, array $data) {
                        $record->update([
                            'response'      => $data['response'],
                            'solution'      => $data['solution'],
                            'responded_by'  => Auth::id(),
                            'responded_at'  => now(),
                            'status'        => CounselingStatus::DONE->value,
                        ]);

                        return $record;
                    }),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
