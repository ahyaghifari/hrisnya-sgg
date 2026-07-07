<?php

namespace App\Filament\Resources\AttendanceRecords\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceRecordCreateForm
{
    public static function configure(Schema $schema, ?string $userId = null): Schema
    {
        return $schema
            ->schema([
                Section::make('Data Absensi')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('user_id')
                            ->label('Karyawan')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn () => $userId !== null)
                            ->dehydrated(true),

                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->required()
                            ->default(Carbon::today()),
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
                                'present'    => 'Hadir',
                                'late'       => 'Terlambat',
                                'absent'     => 'Tidak Hadir',
                                'overtime'   => 'Lembur',
                                'leave'      => 'Cuti',
                                'sick'       => 'Sakit',
                                'permission' => 'Izin',
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
            ]);
    }
}
