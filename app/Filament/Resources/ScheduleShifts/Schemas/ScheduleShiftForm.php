<?php

namespace App\Filament\Resources\ScheduleShifts\Schemas;

use App\Models\Shift\MasterShift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ScheduleShiftForm
{
    /**
     * Wizard form for CREATE: date range + repeater of [users â†’ shift] groups.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    self::stepTanggal(),
                    self::stepAssignment(),
                ])
                ->columnSpanFull()
            ]);
    }

    /**
     * Simple form for EDIT: shift & notes only (date + user are read-only).
     */
    public static function editConfigure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Jadwal')
                ->description('Date dan karyawan tidak dapat diubah. Hanya shift dan catatan yang dapat diedit.')
                ->columns(2)
                ->schema([
                    TextInput::make('employee_name')
                        ->label('Karyawan')
                        ->placeholder(fn ($record): string => $record?->user?->name ?? '-'),

                    TextInput::make('date_display')
                        ->label('Tanggal')
                        ->placeholder(fn ($record): string => $record
                            ? Carbon::parse($record->date)->translatedFormat('l, d F Y')
                            : '-'),

                    Select::make('shift_id')
                        ->label('Shift')
                        ->options(fn () => MasterShift::where('company_id', Auth::user()->company_id)
                            ->get()
                            ->mapWithKeys(fn ($s) => [
                                $s->id => "{$s->code}  Â·  {$s->name}  ({$s->start_time} â€“ {$s->end_time})",
                            ])
                            ->toArray()
                        )
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2)
                        ->maxLength(500)
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // -------------------------------------------------------------------------

    public static function stepTanggal(): Step
    {
        return Step::make('Tanggal')
            ->description('Tentukan rentang tanggal jadwal shift')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->schema([
                Section::make('Rentang Tanggal')
                    ->description('Pilih tanggal mulai dan akhir. Jika hanya satu hari, isi keduanya dengan tanggal yang sama.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->default(now()->toDateString())
                            ->live()
                            ->native(false)
                            ->displayFormat('d F Y'),

                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->required()
                            ->default(now()->toDateString())
                            ->native(false)
                            ->displayFormat('d F Y'),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('exclude_weekends')
                            ->label('Lewati Sabtu & Minggu')
                            ->helperText('Aktifkan agar akhir pekan tidak di-assign shift.')
                            ->default(false),

                        Toggle::make('override_existing')
                            ->label('Timpa Jadwal yang Ada')
                            ->helperText('Aktifkan untuk mengganti jadwal yang sudah ada. Jika tidak, data lama dipertahankan.')
                            ->default(true),
                    ]),
            ]);
    }

    public static function stepAssignment(): Step
    {
        return Step::make('Grup Shift')
            ->description('Buat grup : tiap baris = pilih karyawan + shift mereka')
            ->icon(Heroicon::OutlinedUsers)
            ->schema([
                Section::make()
                    ->description(new HtmlString(
                        '<span class="text-sm text-gray-500">'
                        . 'Tambahkan satu baris per grup. Tiap grup bisa berisi banyak karyawan dengan <strong>satu shift yang sama</strong>. '
                        . 'Buat baris baru untuk karyawan yang mendapat shift berbeda.'
                        . '</span>'
                    ))
                    ->schema([
                        Repeater::make('assignments')
                            ->label(false)
                            ->addActionLabel('+ Tambah Grup Shift')
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->itemLabel('Grup Shift')
                            ->schema([
                                Section::make()
                                    ->columns(['lg' => 3])
                                    ->schema([
                                        CheckboxList::make('user_ids')
                                            ->label('Karyawan')
                                            ->options(function () {
                                                return self::usersForAssignment()
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->descriptions(function () {
                                                return self::usersForAssignment()
                                                    ->mapWithKeys(fn ($user) => [
                                                        $user->id => $user->profile?->orgStructure?->name ?? 'Tanpa Divisi',
                                                    ])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->bulkToggleable()
                                            ->columns(2)
                                            ->columnSpan(2)
                                            ->gridDirection('row')
                                            ->required(),

                                        Section::make('Shift & Catatan')
                                            ->schema([
                                                Select::make('shift_id')
                                                    ->label('Shift')
                                                    ->options(fn () => MasterShift::where('company_id', Auth::user()->company_id)
                                                        ->get()
                                                        ->mapWithKeys(fn ($s) => [
                                                            $s->id => "{$s->code}  Â·  {$s->name}  (" . Carbon::parse($s->start_time)->format('H:i') . " â€“ " . Carbon::parse($s->end_time)->format('H:i') . ")",
                                                        ])
                                                        ->toArray()
                                                    )
                                                    ->live()
                                                    ->searchable()
                                                    ->required()
                                                    ->native(false),

                                                Textarea::make('notes')
                                                    ->label('Catatan (opsional)')
                                                    ->rows(2)
                                                    ->maxLength(500),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Load and sort users by division once per request (static cache keyed by company).
     */
    private static array $usersCache = [];

    private static function usersForAssignment(): \Illuminate\Support\Collection
    {
        $companyId = Auth::user()->company_id;

        if (!isset(self::$usersCache[$companyId])) {
            self::$usersCache[$companyId] = User::where('company_id', $companyId)
                ->with(['profile.orgStructure.parent'])
                ->orderBy('name')
                ->get()
                ->sortBy(fn ($user) => [
                    $user->profile?->division?->name ?? 'zzz',
                    $user->name,
                ])
                ->values();
        }

        return self::$usersCache[$companyId];
    }
}

