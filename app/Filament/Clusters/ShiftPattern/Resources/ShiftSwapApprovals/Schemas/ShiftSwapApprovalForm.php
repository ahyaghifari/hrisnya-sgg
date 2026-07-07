<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ShiftSwapApprovals\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ShiftSwapApprovalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(self::viewSchema())
            ->columns(1);
    }

    public static function buildSwapSummary($record): string
    {
        $requester = e($record->requester?->name ?? '-');
        $target    = e($record->target?->name    ?? '-');
        $reqDate   = $record->req_date
            ? \Carbon\Carbon::parse($record->req_date)->locale('id')->translatedFormat('l, j F Y')
            : '-';
        $targetDate = $record->target_date
            ? \Carbon\Carbon::parse($record->target_date)->locale('id')->translatedFormat('l, j F Y')
            : '-';

        $reqShiftName    = e($record->reqSchedule?->shift?->name    ?? '-');
        $targetShiftName = e($record->targetSchedule?->shift?->name ?? null);
        $isOffDay        = $record->target_schedule_id === null;

        if ($isOffDay) {
            $desc = "<strong>{$requester}</strong> mengajukan pemindahan shiftnya kepada "
                . "<strong>{$target}</strong> yang tidak memiliki jadwal pada {$reqDate}. "
                . "Jika disetujui, <strong>{$requester}</strong> akan menjadi <em>libur</em> "
                . "dan <strong>{$target}</strong> akan bertugas di shift <em>{$reqShiftName}</em>.";

            $before = "<li><strong>{$requester}</strong> bertugas <em>{$reqShiftName}</em> pada {$reqDate}</li>"
                . "<li><strong>{$target}</strong> <em>libur</em> / tidak ada jadwal pada {$reqDate}</li>";

            $after  = "<li><strong>{$requester}</strong> menjadi <strong>libur</strong> pada {$reqDate}</li>"
                . "<li><strong>{$target}</strong> bertugas <em>{$reqShiftName}</em> pada {$reqDate}</li>";
        } else {
            $targetLabel = $targetShiftName ?? '-';
            if ($reqDate === $targetDate) {
                $desc = "<strong>{$requester}</strong> dan <strong>{$target}</strong> menukar sesi shift "
                    . "pada {$reqDate}: <strong>{$requester}</strong> berpindah ke shift <em>{$targetLabel}</em>, "
                    . "<strong>{$target}</strong> berpindah ke shift <em>{$reqShiftName}</em>.";
            } else {
                $desc = "<strong>{$requester}</strong> menukar jadwal tanggal {$reqDate} "
                    . "(shift <em>{$reqShiftName}</em>) dengan jadwal <strong>{$target}</strong> "
                    . "tanggal {$targetDate} (shift <em>{$targetLabel}</em>).";
            }

            $before = "<li><strong>{$requester}</strong> bertugas <em>{$reqShiftName}</em> pada {$reqDate}</li>"
                . "<li><strong>{$target}</strong> bertugas <em>{$targetLabel}</em> pada {$targetDate}</li>";

            $after  = "<li><strong>{$requester}</strong> bertugas <em>{$targetLabel}</em> pada {$targetDate}</li>"
                . "<li><strong>{$target}</strong> bertugas <em>{$reqShiftName}</em> pada {$reqDate}</li>";
        }

        return <<<HTML
            <div class="space-y-3">
                <p class="text-sm text-gray-700">{$desc}</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Kondisi Saat Ini</p>
                        <ul class="space-y-1 text-sm text-gray-700 list-disc list-inside">{$before}</ul>
                    </div>
                    <div class="rounded-lg border border-green-200 bg-green-50 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-green-600">Setelah Disetujui</p>
                        <ul class="space-y-1 text-sm text-gray-700 list-disc list-inside">{$after}</ul>
                    </div>
                </div>
            </div>
        HTML;
    }

    public static function viewSchema(): array
    {
        return [
            Tabs::make('Detail Pengajuan Tukar Shift')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('Informasi Tukar Shift')
                        ->schema(self::infoSchema()),

                    Tabs\Tab::make('Riwayat Approval')
                        ->schema([
                            Repeater::make('approvalHistories')
                                ->hiddenLabel()
                                ->relationship('approvalHistories')
                                ->addable(false)
                                ->deletable(false)
                                ->table([
                                    TableColumn::make('Aktor'),
                                    TableColumn::make('Aksi'),
                                    TableColumn::make('Catatan'),
                                    TableColumn::make('Tanggal'),
                                ])
                                ->schema(self::historySchema()),
                        ]),
                ]),
        ];
    }

    private static function infoSchema(): array
    {
        return [
            Section::make('Pemohon')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Grid::make()->columns(2)->schema([
                        TextEntry::make('requester.name')->label('Nama Karyawan'),
                        TextEntry::make('requester.nip')->label('NIP'),
                        TextEntry::make('req_date')
                            ->label('Tanggal Shift Pemohon')
                            ->formatStateUsing(fn ($state) => $state
                                ? \Carbon\Carbon::parse($state)->translatedFormat('l, j F Y') : '-'),
                        TextEntry::make('reqSchedule.shift.name')->label('Shift Pemohon'),
                    ]),
                ]),

            Section::make('Karyawan Tujuan')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Grid::make()->columns(2)->schema([
                        TextEntry::make('target.name')->label('Nama Karyawan'),
                        TextEntry::make('target.nip')->label('NIP'),
                        TextEntry::make('target_date')
                            ->label('Tanggal Shift Tujuan')
                            ->formatStateUsing(fn ($state) => $state
                                ? \Carbon\Carbon::parse($state)->translatedFormat('l, j F Y') : '-'),
                        TextEntry::make('targetSchedule.shift.name')
                            ->label('Shift Tujuan')
                            ->placeholder('Libur / Tidak ada jadwal'),
                    ]),
                ]),

            Section::make('Status & Catatan')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Grid::make()->columns(2)->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'pending'   => 'gray',
                                'in_review' => 'warning',
                                'approved'  => 'success',
                                'rejected'  => 'danger',
                                'cancelled' => 'gray',
                                default     => 'secondary',
                            })
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'pending'   => 'Menunggu',
                                'in_review' => 'Sedang Ditinjau',
                                'approved'  => 'Disetujui',
                                'rejected'  => 'Ditolak',
                                'cancelled' => 'Dibatalkan',
                                default     => $state,
                            }),
                        TextEntry::make('notes')->label('Catatan Pemohon')->placeholder('-'),
                    ]),
                ]),

            Section::make('Ringkasan Perubahan Shift')
                ->columnSpanFull()
                ->icon(Heroicon::OutlinedInformationCircle)
                ->schema([
                    TextEntry::make('swap_summary')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->html()
                        ->getStateUsing(fn ($record) => static::buildSwapSummary(
                            $record->load(['requester', 'target', 'reqSchedule.shift', 'targetSchedule.shift'])
                        )),
                ]),
        ];
    }

    private static function historySchema(): array
    {
        return [
            TextEntry::make('actor_name')->hiddenLabel(),
            TextEntry::make('action')
                ->hiddenLabel()
                ->badge()
                ->color(fn ($state) => match ($state) {
                    'requested'    => 'info',
                    'approved'     => 'success',
                    'rejected'     => 'danger',
                    'completed'    => 'success',
                    'cancelled'    => 'gray',
                    'flow_updated' => 'warning',
                    default        => 'secondary',
                })
                ->formatStateUsing(fn ($state) => match ($state) {
                    'requested'    => 'Diajukan',
                    'approved'     => 'Disetujui',
                    'rejected'     => 'Ditolak',
                    'completed'    => 'Selesai',
                    'cancelled'    => 'Dibatalkan',
                    'flow_updated' => 'Alur Diperbarui',
                    default        => $state,
                }),
            TextEntry::make('note')->hiddenLabel()->placeholder('-'),
            TextEntry::make('created_at')
                ->hiddenLabel()
                ->formatStateUsing(fn ($state) => $state
                    ? \Carbon\Carbon::parse($state)->translatedFormat('j F Y H:i') : '-'),
        ];
    }
}

