<?php

namespace App\Filament\Resources\AttendanceRecords\Schemas;

use App\Models\Attendance\AttendanceRequests;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Data Absensi')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Karyawan')
                            ->disabled()
                            ->dehydrated(false),
                            
                        TextEntry::make('date')
                            ->label('Tanggal')
                            ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('d F Y'))
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Section::make('Waktu & Jam Kerja')
                    ->columnSpanFull()
                    ->schema([
                        
                        TimePicker::make('check_in_time')
                            ->label('Jam Masuk')
                            ->seconds(false),

                        TimePicker::make('check_out_time')
                            ->label('Jam Keluar')
                            ->seconds(false),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'present'  => 'Hadir',
                                'late'     => 'Terlambat',
                                'absent'   => 'Tidak Hadir',
                                'overtime' => 'Lembur',
                                'leave'    => 'Cuti',
                                'sick'     => 'Sakit',
                                'permission'   => 'Izin',
                            ])
                            ->required()
                            ->default('present'),

                        TextInput::make('working_minutes')
                            ->label('Total Waktu Kerja')
                            ->numeric()
                            ->step(1)
                            ->suffix('menit')
                            ->helperText('Dalam menit. Dihitung otomatis saat import; bisa diubah manual.'),

                        TextInput::make('late_minutes')
                            ->label('Terlambat')
                            ->numeric()
                            ->step(1)
                            ->suffix('menit')
                            ->helperText('Menit keterlambatan dari jam mulai shift.'),

                        TextInput::make('early_leave_minutes')
                            ->label('Pulang Lebih Awal')
                            ->numeric()
                            ->step(1)
                            ->suffix('menit')
                            ->helperText('Menit pulang lebih awal dari jam selesai shift.'),

                        TextInput::make('overtime_minutes')
                            ->label('Lembur')
                            ->numeric()
                            ->step(1)
                            ->suffix('menit')
                            ->helperText('Menit lembur setelah jam selesai shift.'),

                        TextInput::make('permission_minutes')
                            ->label('Izin')
                            ->numeric()
                            ->step(1)
                            ->suffix('menit')
                            ->helperText('Total menit izin yang disetujui pada hari ini.'),
                    ])
                    ->columns(3),

                Section::make('Jadwal & Shift')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('schedule.shift.name')
                            ->label('Jadwal Shift')
                            ->belowContent(fn ($record) => $record->schedule ? $record->date->format('d/m/Y') : '')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Jadwal yang berlaku pada tanggal absensi ini.'),
                        // Select::make('schedule_id')
                        //     ->label('Jadwal Shift')
                        //     ->relationship('schedule', 'id')
                        //     ->getOptionLabelFromRecordUsing(fn ($record) => $record->shift?->name . ' — ' . $record->date?->format('d/m/Y'))
                        //     ->searchable()
                        //     ->preload()
                        //     ->nullable()
                        //     ->helperText('Jadwal yang berlaku pada tanggal absensi ini.'),

                        // Select::make('shift_id')
                        //     ->label('Shift')
                        //     ->relationship('shift', 'name')
                        //     ->searchable()
                        //     ->preload()
                        //     ->nullable()
                        //     ->helperText('Shift yang digunakan untuk menghitung keterlambatan & lembur.'),
                    ])
                    ->columns(2),

                Section::make('Permintaan Izin')
                    ->columnSpanFull()
                    ->visible(function ($record) {
                        if (!$record || !$record->exists || !$record->user_id || !$record->date) return false;
                        return AttendanceRequests::where('user_id', $record->user_id)
                            ->where('type', 'permission')
                            ->where('status', 'approved')
                            ->whereHas('permissionDetails', fn ($q) => $q->where('date', $record->date))
                            ->exists();
                    })
                    ->schema([
                        Repeater::make('permission_requests_items')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('permission_type')
                                    ->label('Jenis Izin')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('time_range')
                                    ->label('Waktu')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('purpose')
                                    ->label('Keperluan')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if (!$record || !$record->exists || !$record->user_id || !$record->date) {
                                    $component->state([]);
                                    return;
                                }

                                $requests = AttendanceRequests::where('user_id', $record->user_id)
                                    ->where('type', 'permission')
                                    ->where('status', 'approved')
                                    ->whereHas('permissionDetails', fn ($q) => $q->where('date', $record->date))
                                    ->with(['permissionDetails' => fn ($q) => $q->where('date', $record->date)])
                                    ->get();

                                $items = [];
                                foreach ($requests as $request) {
                                    foreach ($request->permissionDetails as $detail) {
                                        $items[] = [
                                            'permission_type' => $detail->permission_type ?? '–',
                                            'time_range'      => Carbon::parse($detail->start_time)->format('H:i') . ' – ' . Carbon::parse($detail->end_time)->format('H:i'),
                                            'purpose'         => $detail->purpose ?? '–',
                                        ];
                                    }
                                }

                                $component->state($items);
                            }),
                    ]),

                Section::make('Permintaan Lembur')
                    ->columnSpanFull()
                    ->visible(function ($record) {
                        if (!$record || !$record->exists || !$record->user_id || !$record->date) return false;
                        return AttendanceRequests::where('user_id', $record->user_id)
                            ->where('type', 'overtime')
                            ->where('status', 'approved')
                            ->whereHas('overtimeDetails', fn ($q) => $q->where('date', $record->date))
                            ->exists();
                    })
                    ->schema([
                        Repeater::make('overtime_requests_items')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('overtime_type')
                                    ->label('Jenis Lembur')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('time_range')
                                    ->label('Waktu')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total_hours')
                                    ->label('Total Jam')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('task_description')
                                    ->label('Deskripsi Tugas')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if (!$record || !$record->exists || !$record->user_id || !$record->date) {
                                    $component->state([]);
                                    return;
                                }

                                $requests = AttendanceRequests::where('user_id', $record->user_id)
                                    ->where('type', 'overtime')
                                    ->where('status', 'approved')
                                    ->whereHas('overtimeDetails', fn ($q) => $q->where('date', $record->date))
                                    ->with(['overtimeDetails' => fn ($q) => $q->where('date', $record->date)])
                                    ->get();

                                $items = [];
                                foreach ($requests as $request) {
                                    foreach ($request->overtimeDetails as $detail) {
                                        $items[] = [
                                            'overtime_type'    => $detail->overtime_type ?? '–',
                                            'time_range'       => Carbon::parse($detail->start_time)->format('H:i') . ' – ' . Carbon::parse($detail->end_time)->format('H:i'),
                                            'total_hours'      => $detail->total_hours ? number_format($detail->total_hours, 1) . ' jam' : '–',
                                            'task_description' => $detail->task_description ?? '–',
                                        ];
                                    }
                                }

                                $component->state($items);
                            }),
                    ]),

                Section::make('Permintaan Cuti')
                    ->columnSpanFull()
                    ->visible(function ($record) {
                        if (!$record || !$record->exists || !$record->user_id || !$record->date) return false;
                        return AttendanceRequests::where('user_id', $record->user_id)
                            ->where('type', 'leave')
                            ->where('status', 'approved')
                            ->whereHas('leaveDetails', fn ($q) => $q->where(function ($q2) use ($record) {
                                // Multi-day: selected date is within start_date – end_date range
                                $q2->whereNotNull('end_date')
                                    ->where('start_date', '<=', $record->date)
                                    ->where('end_date', '>=', $record->date);
                            })->orWhere(function ($q2) use ($record) {
                                // Single-day: no end_date, start_date matches exactly
                                $q2->whereNull('end_date')
                                    ->where('start_date', $record->date);
                            }))
                            ->exists();
                    })
                    ->schema([
                        Repeater::make('leave_requests_items')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('leave_type')
                                    ->label('Jenis Cuti')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('date_range')
                                    ->label('Periode')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total_days')
                                    ->label('Total Hari')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('reason')
                                    ->label('Alasan')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if (!$record || !$record->exists || !$record->user_id || !$record->date) {
                                    $component->state([]);
                                    return;
                                }

                                $requests = AttendanceRequests::where('user_id', $record->user_id)
                                    ->where('type', 'leave')
                                    ->where('status', 'approved')
                                    ->whereHas('leaveDetails', fn ($q) => $q->where(function ($q2) use ($record) {
                                        $q2->whereNotNull('end_date')
                                            ->where('start_date', '<=', $record->date)
                                            ->where('end_date', '>=', $record->date);
                                    })->orWhere(function ($q2) use ($record) {
                                        $q2->whereNull('end_date')
                                            ->where('start_date', $record->date);
                                    }))
                                    ->with(['leaveDetails' => fn ($q) => $q->where(function ($q2) use ($record) {
                                        $q2->whereNotNull('end_date')
                                            ->where('start_date', '<=', $record->date)
                                            ->where('end_date', '>=', $record->date);
                                    })->orWhere(function ($q2) use ($record) {
                                        $q2->whereNull('end_date')
                                            ->where('start_date', $record->date);
                                    })])
                                    ->get();

                                $items = [];
                                foreach ($requests as $request) {
                                    foreach ($request->leaveDetails as $detail) {
                                        $startDate = Carbon::parse($detail->start_date);
                                        $dateRange = $detail->end_date
                                            ? $startDate->translatedFormat('d M Y') . ' – ' . Carbon::parse($detail->end_date)->translatedFormat('d M Y')
                                            : $startDate->translatedFormat('d M Y');

                                        $items[] = [
                                            'leave_type' => $detail->leave_type ?? '–',
                                            'date_range' => $dateRange,
                                            'total_days' => $detail->total_days ? $detail->total_days . ' hari' : '1 hari',
                                            'reason'     => $request->reason ?? '–',
                                        ];
                                    }
                                }

                                $component->state($items);
                            }),
                    ]),
            ]);
    }
}
