<?php

namespace App\Filament\Resources\Master\JobPositions\Tables;

use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use App\Helpers\PermissionHelper;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobPositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama Pangkat')->searchable()->sortable(),
                TextColumn::make('orgStructure.name')
                    ->alignCenter()
                    ->label('Unit'),
                TextColumn::make('actual_manpower')
                    ->label('Kuota')
                    ->formatStateUsing(fn ($state) => $state ?? 0)
                    ->alignCenter(),
                TextColumn::make('manpower_filled')
                    ->label('Terisi')
                    ->alignCenter()
                    ->getStateUsing(fn ($record) => $record->userAssignments()->count()),
                TextColumn::make('available_manpower')
                    ->label('Tersedia')
                    ->alignCenter()
                    ->getStateUsing(fn ($record) => ($record->actual_manpower ?? 0) - $record->userAssignments()->count()),
                TextColumn::make('status')
                    ->label('Status Persetujuan')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'requested' => 'warning',
                        default => 'gray',
                    })
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('View / Edit')
                        ->visible(fn ($record) => JobPositionResource::canEdit($record)),

                    Action::make('history_action')
                        ->label('History')
                        ->icon('heroicon-o-clock')
                        ->color('primary')
                        ->slideOver()
                        ->modalWidth('md')
                        ->modalHeading('Riwayat Pengajuan Jabatan')
                        ->schema([
                            RepeatableEntry::make('approvalHistories')
                                ->hiddenLabel()
                                ->schema([
                                    TextEntry::make('actor_name')
                                        ->hiddenLabel()
                                        ->alignCenter(),
                                    TextEntry::make('actor_id')
                                        ->hiddenLabel()
                                        ->alignCenter()
                                        ->formatStateUsing(function ($state) {
                                            if (!$state) {
                                                return null;
                                            }
                                            $user = \App\Models\User::find($state);
                                            if (!$user) {
                                                return null;
                                            }
                                            $roleName = $user->roles()->first()?->name ?? '-';
                                            return "{$roleName}";
                                        }),
                                    TextEntry::make('note')
                                        ->hiddenLabel()
                                        ->alignCenter(),
                                    TextEntry::make('created_at')
                                        ->hiddenLabel()
                                        ->alignCenter()
                                        ->dateTime()
                                        ->formatStateUsing(fn ($state) => $state?->translatedFormat('d F Y H:i')),
                                    TextEntry::make('action')
                                        ->hiddenLabel()
                                        ->badge()
                                        ->formatStateUsing(function ($state) {
                                            $actionLabels = [
                                                'requested' => 'Diajukan',
                                                'approved' => 'Disetujui',
                                                'rejected' => 'Ditolak',
                                                'completed' => 'Selesai',
                                            ];
                                            return $actionLabels[$state] ?? $state;
                                        })
                                        ->color(fn (string $state): string => match ($state) {
                                            'requested' => 'gray',
                                            'approve' => 'warning',
                                            'reject' => 'danger',
                                            'completed' => 'success',
                                            default => 'gray',
                                        })
                                        ->alignCenter(),
                                ])
                        ])
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
