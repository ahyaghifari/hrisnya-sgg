<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ShiftTemplates\Tables;

use App\Filament\Clusters\ShiftPattern\Resources\ShiftTemplates\ShiftTemplateResource;
use App\Helpers\PermissionHelper;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ShiftTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Template')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->description),

                TextColumn::make('cycle_length')
                    ->label('Panjang Siklus')
                    ->formatStateUsing(fn ($state) => "{$state} hari")
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('days_summary')
                    ->label('Hari Kerja / Libur')
                    ->state(function ($record): string {
                        $days    = $record->days;
                        $work    = $days->where('is_off_day', false)->count();
                        $off     = $days->where('is_off_day', true)->count();
                        return "{$work} kerja · {$off} libur";
                    })
                    ->icon(Heroicon::OutlinedCalendarDays),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                Action::make('generate')
                    ->label('Generate Jadwal')
                    ->icon(Heroicon::OutlinedBolt)
                    ->color('success')
                    ->url(fn ($record) => ShiftTemplateResource::getUrl('generate', ['record' => $record]))
                    ->visible(fn ($record) => ShiftTemplateResource::canEdit($record)),

                EditAction::make()
                    ->visible(fn ($record) => ShiftTemplateResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('days'));
    }
}
