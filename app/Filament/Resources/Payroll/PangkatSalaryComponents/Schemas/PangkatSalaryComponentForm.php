<?php

namespace App\Filament\Resources\Payroll\PangkatSalaryComponents\Schemas;

use App\Models\Payroll\MasterSalaryComponents;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PangkatSalaryComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Jabatan')
                    ->readOnly()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                TextInput::make('component_name')
                    ->label('Komponen Gaji')
                    ->afterStateHydrated(function (TextInput $component, $state, $record, $livewire) {
                        $component->state($livewire->componentType);
                    })
                    ->dehydrated(false)
                    ->readOnly()
                    ->columnSpanFull(),

                TextInput::make('amount')
                    ->label('Amount')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->visible(function ($record, $livewire) {
                        $selected = $record->selectedSalaryComponent($livewire->componentType);
                        return $selected
                            ? $selected->salaryComponent?->calculation_type === 'fixed'
                            : MasterSalaryComponents::where('name', $livewire->componentType)
                                ->first()?->calculation_type === 'fixed';
                    })
                    ->afterStateHydrated(function (TextInput $component, $state, $record, $livewire) {
                        $selected = $record->selectedSalaryComponent($livewire->componentType);

                        if ($selected) {
                            $component->state($selected->amount);
                        }
                    })
                    ->columnSpanFull(),

                TextInput::make('percentage')
                    ->label('Persentase (%)')
                    ->suffix('%')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->visible(function ($record, $livewire) {
                        $selected = $record->selectedSalaryComponent($livewire->componentType);
                        return $selected
                            ? $selected->salaryComponent?->calculation_type === 'percentage'
                            : MasterSalaryComponents::where('name', $livewire->componentType)
                                ->first()?->calculation_type === 'percentage';
                    })
                    ->columnSpanFull(),

                TextInput::make('formula_info')
                    ->label('Informasi Formula')
                    ->disabled()
                    ->visible(function ($record, $livewire) {
                        $selected = $record->selectedSalaryComponent($livewire->componentType);
                        return $selected
                            ? $selected->salaryComponent?->calculation_type === 'formula'
                            : MasterSalaryComponents::where('name', $livewire->componentType)
                                ->first()?->calculation_type === 'formula';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        $component = MasterSalaryComponents::where('name', request()->query('type'))->first();
                        return 'Komponen ini menggunakan formula: ' . ($component->formula_key ?? 'Belum ditentukan');
                    })
                    ->dehydrated(false)
                    ->columnSpanFull(),

                TextInput::make('is_overrideable')
                    ->label('Dapat Diubah')
                    ->default(true)
                    ->hidden(),
            ]);
    }
}
