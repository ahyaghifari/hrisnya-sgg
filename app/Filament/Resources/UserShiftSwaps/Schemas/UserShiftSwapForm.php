<?php

namespace App\Filament\Resources\UserShiftSwaps\Schemas;

use App\Enum\ShiftType;
use App\Models\Shift\ShiftScheduleAssignments;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class UserShiftSwapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                $schema->getLivewire() instanceof CreateRecord
                    ? self::createSchema()
                    : self::editSchema()
            )
            ->columns(1);
    }

    private static function shiftTypeLabel(?string $shiftType): string
    {
        return match ($shiftType) {
            ShiftType::PAGI->value   => '☀️ Pagi',
            ShiftType::SIANG->value  => '🌤️ Siang',
            ShiftType::MALAM->value  => '🌙 Malam',
            default                  => $shiftType ?? '-',
        };
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

    public static function createSchema(): array
    {
        return [
            Section::make('Pengajuan Tukar Shift')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->description(
                    'Tukar shift hanya berlaku dalam bulan yang sama. ' .
                    'Shift pagi dan siang dapat saling ditukar (pagi ↔ siang). Shift malam hanya bisa ditukar dengan sesama malam. ' .
                    'Dapat juga ditukar dengan karyawan yang sedang libur / tidak ada jadwal.'
                )
                ->columns(2)
                ->schema([
                    // == Shift Saya ==
                    Select::make('req_schedule_id')
                        ->label('Shift Saya (yang akan ditukar)')
                        ->columnSpanFull()
                        ->searchable()
                        ->options(function () {
                            $userId  = Auth::id();
                            return ShiftScheduleAssignments::with('shift')
                                ->where('user_id', $userId)
                                ->where('date', '>=', now()->toDateString())
                                ->orderBy('date')
                                ->get()
                                ->mapWithKeys(function ($s) {
                                    $shiftTypeLabel = self::shiftTypeLabel($s->shift?->shift_type);
                                    $date = \Carbon\Carbon::parse($s->date)->translatedFormat('l, j F Y');
                                    $time = optional($s->shift)->start_time
                                        ? substr($s->shift->start_time, 0, 5) . ' – ' . substr($s->shift->end_time, 0, 5)
                                        : '';
                                    return [$s->id => "{$date} | {$s->shift?->name} ({$shiftTypeLabel}) {$time}"];
                                });
                        })
                        ->reactive()
                        ->required()
                        ->helperText('Menampilkan shift Anda mulai hari ini.'),

                    // == Mode Tukar ==
                    Radio::make('swap_mode')
                        ->label('Mode Penukaran')
                        ->columnSpanFull()
                        ->options([
                            'schedule' => '📅 Tukar dengan shift karyawan lain yang terdaftar',
                            'off_day'  => '🗓️ Berikan shift saya ke karyawan yang sedang libur',
                        ])
                        ->default('schedule')
                        ->reactive()
                        ->visible(fn (callable $get) => (bool) $get('req_schedule_id'))
                        ->helperText('Pilih apakah Anda menukar dengan shift karyawan lain, atau memberikan shift Anda ke karyawan yang tidak memiliki jadwal pada tanggal tersebut.'),

                    // == Mode: Shift Terdaftar ==
                    Select::make('target_schedule_id')
                        ->label('Shift Karyawan Lain (tujuan tukar)')
                        ->columnSpanFull()
                        ->searchable()
                        ->options(function (callable $get) {
                            $reqScheduleId = $get('req_schedule_id');
                            if (!$reqScheduleId) {
                                return [];
                            }

                            $reqSchedule = ShiftScheduleAssignments::with('shift')->find($reqScheduleId);
                            if (!$reqSchedule) {
                                return [];
                            }

                            $reqDate    = \Carbon\Carbon::parse($reqSchedule->date);
                            $monthStart = $reqDate->copy()->startOfMonth()->toDateString();
                            $monthEnd   = $reqDate->copy()->endOfMonth()->toDateString();
                            $reqType    = $reqSchedule->shift?->shift_type; // pagi | siang | malam
                            $authId     = Auth::id();
                            $companyId  = Auth::user()->company_id;

                            // Pagi ↔ Siang diperbolehkan, Malam hanya bisa dengan Malam
                            $allowedTypes = match ($reqType) {
                                ShiftType::PAGI->value  => [ShiftType::PAGI->value, ShiftType::SIANG->value],
                                ShiftType::SIANG->value => [ShiftType::PAGI->value, ShiftType::SIANG->value],
                                default                 => [ShiftType::MALAM->value],
                            };

                            return ShiftScheduleAssignments::with(['shift', 'user'])
                                ->whereHas('shift', fn ($q) => $q->whereIn('shift_type', $allowedTypes))
                                ->whereHas('user', fn ($q) => $q->where('company_id', $companyId))
                                ->where('user_id', '!=', $authId)
                                ->where('date', '>=', now()->toDateString())
                                ->where('date', '<=', $monthEnd)
                                ->orderBy('date')
                                ->get()
                                ->mapWithKeys(function ($s) {
                                    $shiftTypeLabel = self::shiftTypeLabel($s->shift?->shift_type);
                                    $date     = \Carbon\Carbon::parse($s->date)->translatedFormat('l, j F Y');
                                    $time     = optional($s->shift)->start_time
                                        ? substr($s->shift->start_time, 0, 5) . ' – ' . substr($s->shift->end_time, 0, 5)
                                        : '';
                                    $employee = $s->user?->name ?? '-';
                                    return [$s->id => "{$employee} | {$date} | {$s->shift?->name} ({$shiftTypeLabel}) {$time}"];
                                });
                        })
                        ->reactive()
                        ->required(fn (callable $get) => $get('swap_mode') !== 'off_day')
                        ->visible(fn (callable $get) => $get('swap_mode') !== 'off_day')
                        ->helperText(
                            'Menampilkan shift karyawan lain yang kompatibel (pagi ↔ siang, atau malam ↔ malam), dalam bulan yang sama.'
                        )
                        ->disabled(fn (callable $get) => !$get('req_schedule_id')),

                    // == Mode: Karyawan Libur ==
                    Select::make('target_user_off')
                        ->label('Karyawan Penerima Shift (sedang libur di tanggal tersebut)')
                        ->columnSpanFull()
                        ->searchable()
                        ->options(function (callable $get) {
                            $reqScheduleId = $get('req_schedule_id');
                            if (!$reqScheduleId) {
                                return [];
                            }

                            $reqSchedule = ShiftScheduleAssignments::find($reqScheduleId);
                            if (!$reqSchedule) {
                                return [];
                            }

                            $reqDate   = $reqSchedule->date;
                            $companyId = Auth::user()->company_id;
                            $authId    = Auth::id();

                            // Employees who already have a schedule on req_date (can't receive)
                            $busyUserIds = ShiftScheduleAssignments::where('date', $reqDate)
                                ->pluck('user_id')
                                ->toArray();

                            return User::where('company_id', $companyId)
                                ->where('id', '!=', $authId)
                                ->whereNotIn('id', $busyUserIds)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($u) => [
                                    $u->id => $u->name . ($u->nip ? " ({$u->nip})" : ''),
                                ]);
                        })
                        ->visible(fn (callable $get) => $get('swap_mode') === 'off_day' && (bool) $get('req_schedule_id'))
                        ->required(fn (callable $get) => $get('swap_mode') === 'off_day')
                        ->helperText(
                            'Menampilkan karyawan perusahaan yang tidak memiliki jadwal di tanggal shift Anda. ' .
                            'Shift Anda akan dipindahkan ke karyawan ini, dan Anda menjadi libur.'
                        )
                        ->disabled(fn (callable $get) => !$get('req_schedule_id')),

                    Textarea::make('notes')
                        ->label('Catatan / Alasan')
                        ->columnSpanFull()
                        ->rows(3)
                        ->placeholder('Opsional – jelaskan alasan pengajuan tukar shift...'),
                ]),
        ];
    }

    public static function editSchema(): array
    {
        return [
            Tabs::make('Detail Pengajuan Tukar Shift')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('Informasi Tukar Shift')
                        ->schema(self::viewInfoSchema()),

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

    private static function viewInfoSchema(): array
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
                            ->label('Tanggal Shift Saya')
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->translatedFormat('l, j F Y') : '-'),
                        TextEntry::make('reqSchedule.shift.name')->label('Shift Saya'),
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
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->translatedFormat('l, j F Y') : '-'),
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
                        TextEntry::make('notes')->label('Catatan')->placeholder('-'),
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
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->translatedFormat('j F Y H:i') : '-'),
        ];
    }
}
