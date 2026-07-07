<?php

namespace App\Filament\Resources\Payroll\JobPositionSalaryComponents\Tables;

use App\Enum\Salary\CalculationTypeEnum;
use App\Models\Payroll\MasterSalaryComponents;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalaryComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Jabatan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kelas.name')
                    ->label('Kelas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('calculation_type')
                    ->label('Metode Perhitungan')
                    ->getStateUsing(function ($record, $livewire) {
                        $sc = $record->selectedSalaryComponent($livewire->componentType);

                        if (! $sc) {
                            return '-';
                        }

                        $method = $sc->salaryComponent?->calculation_type;

                        $enum = CalculationTypeEnum::tryFrom($method);

                        return $enum?->label() ?? '-';
                    })
                    ->badge(),

                TextColumn::make('component_value')
                    ->label('Nilai')
                    ->getStateUsing(function ($record, $livewire) {
                        $sc = $record->selectedSalaryComponent($livewire->componentType);

                        if (! $sc) {
                            return '-';
                        }

                        $method = $sc->salaryComponent?->calculation_type;

                        $enum = CalculationTypeEnum::tryFrom($method);

                        return match ($enum) {
                            CalculationTypeEnum::FIXED =>
                                'IDR ' . number_format($sc->amount, 0, ',', '.'),

                            CalculationTypeEnum::PERCENTAGE =>
                                $sc->percentage . '%',

                            CalculationTypeEnum::FORMULA =>
                            'Formula',

                            default => '-',
                        };
                    })
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('lg')
                    ->mutateFormDataUsing(function ($data, $record, $livewire) {
                        $type = $livewire->componentType;
                        $sc = $record->selectedSalaryComponent($type);

                        if (! $sc) {
                            $sc = $record->salaryComponents()->create([
                                'salary_component_id' =>
                                    MasterSalaryComponents::where('name', $type)->value('id'),
                            ]);
                        }

                        if (isset($data['amount'])) {
                            $sc->amount = $data['amount'];
                        }

                        if (isset($data['percentage'])) {
                            $sc->percentage = $data['percentage'];
                        }

                        $sc->save();

                        return $data;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
