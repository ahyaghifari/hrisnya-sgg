<?php

namespace App\Filament\Resources\UserShiftSwaps\Tables;

use App\Filament\Resources\UserShiftSwaps\UserShiftSwapResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserShiftSwapsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->where('requester_id', Auth::id())
                ->with(['requester', 'target', 'reqSchedule.shift', 'targetSchedule.shift', 'approvalSnapshot.activity'])
                ->latest()
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->formatStateUsing(fn ($state) => $state?->translatedFormat('j F Y H:i'))
                    ->sortable(),

                TextColumn::make('req_date')
                    ->label('Shift Saya')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->translatedFormat('j F Y') : '-')
                    ->description(fn ($record) => $record->reqSchedule?->shift?->name ?? '-'),

                TextColumn::make('target.name')
                    ->label('Karyawan Tujuan')
                    ->description(fn ($record) => $record->target_date
                        ? \Carbon\Carbon::parse($record->target_date)->translatedFormat('j F Y') . ' | ' . ($record->targetSchedule?->shift?->name ?? 'Libur / No Jadwal')
                        : '-'
                    ),

                TextColumn::make('status')
                    ->label('Status')
                    ->description(function ($record) {
                        $snapshot = $record->approvalSnapshot;
                        if (!$snapshot || !$snapshot->activity) {
                            return null;
                        }

                        $activity = $snapshot->activity;

                        return match ($activity->approver_type) {
                            'user' => \App\Models\User::find($activity->approver_ref_id)?->name,
                            'role' => \App\Models\Role::find($activity->approver_ref_id)?->name,
                            'job_position' => \App\Models\Master\JobPositions::find($activity->approver_ref_id)?->name,
                            default => null,
                        };
                    })
                    ->badge()
                    ->alignCenter()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'gray',
                        'in_review' => 'warning',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        'cancelled' => 'gray',
                        default     => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Menunggu',
                        'in_review' => 'Sedang Ditinjau',
                        'approved'  => 'Disetujui',
                        'rejected'  => 'Ditolak',
                        'cancelled' => 'Dibatalkan',
                        default     => $state,
                    })
                    ->description(fn ($record) => self::getReviewerInfo($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->url(fn ($record) => UserShiftSwapResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn ($record) => UserShiftSwapResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function getReviewerInfo($record): ?string
    {
        try {
            $snapshot = $record->approvalSnapshot;
            if (!$snapshot || !$snapshot->activity) {
                return null;
            }
            $activity = $snapshot->activity;

            $approverName = match ($activity->approver_type) {
                'user' => \App\Models\User::find($activity->approver_ref_id)?->name,
                'role' => \App\Models\Role::find($activity->approver_ref_id)?->name,
                'job_position' => \App\Models\Master\JobPositions::find($activity->approver_ref_id)?->name,
                default => null,
            };

            if (!$approverName) {
                return null;
            }

            return match ($activity->status) {
                'pending' => $approverName,
                'in_review' => $approverName,
                'approved' => 'Disetujui oleh ' . $approverName,
                'rejected' => 'Ditolak oleh ' . $approverName,
                default => $approverName,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
