<?php

namespace App\Filament\Resources\EmployeeExecutiveAllowances\Schemas;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\Employee\Employee;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmployeeExecutiveAllowanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Penerima')
                    ->options(fn () => Employee::query()
                        ->whereIn('user_id', User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS)
                        ->orderBy('nama_lengkap')
                        ->pluck('nama_lengkap', 'id'))
                    ->required(),

                Select::make('source')
                    ->label('Sumber Label')
                    ->options([
                        'manual' => 'Manual (tulis label sendiri)',
                        'component' => 'Dari Komponen Gaji',
                    ])
                    ->default(fn ($record) => $record?->component ? 'component' : 'manual')
                    ->dehydrated(false)
                    ->live()
                    ->required(),

                Select::make('component')
                    ->label('Komponen')
                    ->options(fn () => collect(PayrollComponentEnum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->visible(fn ($get) => $get('source') === 'component')
                    ->required(fn ($get) => $get('source') === 'component')
                    ->dehydrated(fn ($get) => $get('source') === 'component')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $component = PayrollComponentEnum::tryFrom($state ?? '');
                        if ($component) {
                            $set('label', $component->label());
                            $set('type', $component->type()->value);
                        }
                    })
                    ->helperText('Jenis (Pendapatan/Potongan) & label baris ikut otomatis dari komponen ini.'),

                Select::make('type')
                    ->label('Jenis')
                    ->options([
                        'earning' => 'Pendapatan',
                        'deduction' => 'Potongan',
                    ])
                    ->visible(fn ($get) => $get('source') !== 'component')
                    ->required(fn ($get) => $get('source') !== 'component'),

                TextInput::make('label')
                    ->label('Label')
                    ->visible(fn ($get) => $get('source') !== 'component')
                    ->required(fn ($get) => $get('source') !== 'component')
                    ->maxLength(255),

                TextInput::make('amount')
                    ->label('Nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Textarea::make('note')
                    ->label('Catatan')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2);
    }
}
