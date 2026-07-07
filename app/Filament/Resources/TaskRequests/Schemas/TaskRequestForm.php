<?php

namespace App\Filament\Resources\TaskRequests\Schemas;

use App\Enum\Task\TaskRiskLevel;
use App\Enum\Task\TaskStatus;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaskRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dasar')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')->label('Kode Tugas'),
                        TextEntry::make('service.name')->label('Layanan Tugas'),
                        TextEntry::make('risk_level')
                            ->label('Tingkat Risiko')
                            ->placeholder('-')
                            ->badge()
                            ->color(fn($state) => TaskRiskLevel::tryFrom($state)?->getColor())
                            ->formatStateUsing(fn($state) => TaskRiskLevel::tryFrom($state)?->getLabel()),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->icon(fn($state) => TaskStatus::tryFrom($state)?->getIcon())
                            ->color(fn($state) => TaskStatus::tryFrom($state)?->getColor())
                            ->formatStateUsing(fn($state) => TaskStatus::tryFrom($state)?->getLabel()),
                    ]),

                Section::make('Penugasan & Persetujuan')
                    ->icon('heroicon-o-user-group')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('assignedTo.name')->label('Diberikan Kepada')->placeholder('-'),
                        TextEntry::make('approvedBy.name')->label('Disetujui Oleh')->placeholder('-'),
                        TextEntry::make('slaReviewer.name')->label('Reviewer SLA')->placeholder('-'),
                        TextEntry::make('is_self_assigned')
                            ->label('Penugasan Diri Sendiri')
                            ->formatStateUsing(fn($state) => $state ? 'Ya' : 'Tidak'),
                    ]),

                Section::make('Lampiran & Bukti')
                    ->icon('heroicon-o-paper-clip')
                    ->columns(1)
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->label('Lampiran')
                            ->columnSpanFull()
                            ->schema([
                                ImageEntry::make('file_url')
                                    ->hiddenLabel()
                                    ->openUrlInNewTab()
                                    ->imageHeight(180)
                                    ->extraAttributes([
                                        'class' => 'w-full h-full object-cover rounded-lg border border-gray-200 shadow-sm overflow-hidden'
                                    ])
                            ])
                            ->grid(4)
                            ->placeholder('Tidak ada lampiran.'),

                        RepeatableEntry::make('proofs')
                            ->label('Bukti Pelaksanaan')
                            ->columnSpanFull()
                            ->schema([
                                ImageEntry::make('file_url')
                                    ->hiddenLabel()
                                    ->openUrlInNewTab()
                            ])
                            ->placeholder('Tidak ada bukti pelaksanaan.')
                            ->grid(4),
                    ])
                    ->columnSpanFull(),

                Section::make('Timeline')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('due_date')
                            ->label('Tanggal Tenggat')
                            ->placeholder('-')
                            ->date(),
                        TextEntry::make('responded_date')
                            ->label('Tanggal Direspon')
                            ->placeholder('-')
                            ->date(),
                        TextEntry::make('completed_date')
                            ->label('Tanggal Selesai')
                            ->placeholder('-')
                            ->date(),
                        TextEntry::make('duration')
                            ->label('Durasi Penyelesaian')
                            ->placeholder('-')
                            ->formatStateUsing(fn($state) => $state !== null ? "{$state} hari" : '-'),
                        TextEntry::make('overdue_days')
                            ->label('Lewat Target')
                            ->placeholder('-')
                            ->badge()
                            ->color(function ($state) {
                                if ($state === null) {
                                    return 'gray';
                                }

                                return $state > 0 ? 'danger' : 'success';
                            })
                            ->formatStateUsing(function ($state) {
                                if ($state === null) {
                                    return '-';
                                }

                                return $state > 0
                                    ? "{$state} hari"
                                    : 'Tidak';
                            }),
                    ]),

                Section::make('Review')
                    ->icon('heroicon-o-star')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('review.reviewer.name')->label('Reviewer')->placeholder('-'),
                        TextEntry::make('review.rating')
                            ->label('Rating')
                            ->placeholder('-')
                            ->formatStateUsing(fn($state) => $state ? str_repeat('⭐', $state) : '-'),
                        TextEntry::make('review.comment')
                            ->label('Komentar')
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ]),

                Section::make('Biaya & Catatan')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('unit_cost')
                            ->label('Biaya per Unit')
                            ->money('IDR', true)
                            ->placeholder('-'),
                        TextEntry::make('total_cost')
                            ->label('Total Biaya')
                            ->money('IDR', true)
                            ->placeholder('-'),
                        TextEntry::make('collaboration_note')
                            ->label('Catatan Kolaborasi')
                            ->columnSpanFull()
                            ->placeholder('-')
                            ->alignJustify(),
                        TextEntry::make('root_cause')
                            ->label('Akar Permasalahan')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->alignJustify(),
                        TextEntry::make('solution')
                            ->label('Solusi')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->alignJustify(),
                    ]),
                Section::make('Lainnya')
                    ->icon('heroicon-o-user-group')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('slaReviewer.profile.department.name')
                            ->label('Department')
                            ->placeholder('-'),
                        TextEntry::make('slaReviewer.profile.division.name')
                            ->label('Division')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
