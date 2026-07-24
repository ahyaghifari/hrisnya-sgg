<?php

namespace App\Filament\Resources\Incentives\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Incentive\Incentive;
use App\Models\Master\Companies;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class IncentivesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Penerima')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Program')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('incentive_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Companies::query()->pluck('name', 'id'))
                    ->preload()
                    ->searchable()
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $companyId) => $q->whereHas('user', fn ($q2) => $q2->where('company_id', $companyId)),
                    ))
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat')
                    ->slideOver()
                    ->modalWidth('md'),

                EditAction::make()
                    ->label('Ubah')
                    ->modalWidth('md')
                    ->fillForm(fn (Incentive $record) => [
                        ...$record->attributesToArray(),
                        'company_id' => $record->user?->company_id,
                    ]),

                DeleteAction::make()
                    ->label('Hapus')
                    ->slideOver()
                    ->modalWidth('md'),
            ]);
    }
}
