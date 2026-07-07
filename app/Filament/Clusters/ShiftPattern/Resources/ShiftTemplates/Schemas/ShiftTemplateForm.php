<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ShiftTemplates\Schemas;

use App\Models\Shift\MasterShift;
use Carbon\Carbon;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ShiftTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Template Shift')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Hidden::make('company_id')
                            ->default(fn () => Auth::user()->company_id),

                        TextInput::make('name')
                            ->label('Nama Template')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Contoh: Pola Shift Mingguan 5-2'),

                        TextInput::make('cycle_length')
                            ->label('Panjang Siklus (hari)')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->live(debounce: 600)
                            ->helperText('Jumlah hari dalam satu siklus. Contoh: 7 untuk pola mingguan, 14 untuk dua mingguan.')
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $length = max(1, (int) $state);
                                $existing = $get('days') ?? [];

                                // Extend or trim days array to match new cycle_length
                                $newDays = [];
                                for ($i = 1; $i <= $length; $i++) {
                                    $newDays[] = $existing[$i - 1] ?? [
                                        'day_number' => $i,
                                        'shift_id'   => null,
                                        'is_off_day' => false,
                                    ];
                                    // Always keep day_number in sync
                                    $newDays[$i - 1]['day_number'] = $i;
                                }

                                $set('days', $newDays);
                            }),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(2)
                            ->maxLength(500)
                            ->nullable()
                            ->placeholder('Deskripsi singkat tentang pola shift ini...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pola Hari dalam Siklus')
                    ->description('Atur shift untuk setiap hari dalam siklus template. Isi kolom shift untuk hari kerja, atau aktifkan Hari Libur untuk hari off.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('days')
                            ->label(false)
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->default(
                                collect(range(1, 7))->map(fn (int $i) => [
                                    'day_number' => $i,
                                    'shift_id'   => null,
                                    'is_off_day' => false,
                                ])->toArray()
                            )
                            ->itemLabel(fn (array $state): string => 'Hari ' . ($state['day_number'] ?? '?'))
                            ->collapsible()
                            ->schema([
                                Hidden::make('day_number'),

                                Toggle::make('is_off_day')
                                    ->label('Hari Libur / Off')
                                    ->helperText('Aktifkan jika hari ini adalah hari libur (tidak ada shift).')
                                    ->live()
                                    ->inline(false)
                                    ->columnSpan(1),

                                Select::make('shift_id')
                                    ->label('Shift yang Berlaku')
                                    ->options(fn () => MasterShift::where('company_id', Auth::user()->company_id)
                                        ->get()
                                        ->mapWithKeys(fn ($s) => [
                                            $s->id => trim("{$s->code} · {$s->name}") . '  (' . Carbon::parse($s->start_time)->format('H:i') . ' – ' . Carbon::parse($s->end_time)->format('H:i') . ')',
                                        ])
                                        ->toArray()
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder('Pilih shift...')
                                    ->hidden(fn (Get $get): bool => (bool) $get('is_off_day'))
                                    ->required(fn (Get $get): bool => !(bool) $get('is_off_day'))
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
