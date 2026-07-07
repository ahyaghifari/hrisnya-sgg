<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ShiftSwapApprovals\Tables;

use App\Filament\Clusters\ShiftPattern\Resources\ShiftSwapApprovals\ShiftSwapApprovalResource;
use App\Helpers\PermissionHelper;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ShiftSwapApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with([
                    'requester',
                    'target',
                    'reqSchedule.shift',
                    'targetSchedule.shift',
                    'approvalSnapshot.activity',
                ])
                ->latest()
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->formatStateUsing(fn ($state) => $state?->translatedFormat('j F Y H:i'))
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->description(fn ($record) => $record->requester?->nip ?? '-'),

                TextColumn::make('req_date')
                    ->label('Shift Pemohon')
                    ->formatStateUsing(fn ($state) => $state
                        ? \Carbon\Carbon::parse($state)->translatedFormat('j F Y') : '-')
                    ->description(fn ($record) => $record->reqSchedule?->shift?->name ?? '-'),

                TextColumn::make('target.name')
                    ->label('Karyawan Tujuan')
                    ->description(fn ($record) => $record->target_date
                        ? \Carbon\Carbon::parse($record->target_date)->translatedFormat('j F Y')
                          . ' | ' . ($record->targetSchedule?->shift?->name ?? 'Libur / No Jadwal')
                        : '-'
                    ),

                TextColumn::make('status')
                    ->label('Status')
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
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'   => 'Menunggu',
                        'in_review' => 'Sedang Ditinjau',
                        'approved'  => 'Disetujui',
                        'rejected'  => 'Ditolak',
                        'cancelled' => 'Dibatalkan',
                    ]),

                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('requester.company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user())
                        ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel()
                    ->url(fn ($record) => ShiftSwapApprovalResource::getUrl('edit', ['record' => $record]))
            ])
            ->toolbarActions([]);
    }

    private static function getReviewerInfo($record): ?string
    {
        try {
            $snapshot = $record->approvalSnapshot;
            if (!$snapshot || !$snapshot->activity) {
                return null;
            }

            $activity = $snapshot->activity;
            if ($activity->status !== 'pending') {
                return null;
            }

            return match ($activity->approver_type) {
                'user'         => \App\Models\User::find($activity->approver_ref_id)?->name,
                'role'         => \App\Models\Role::find($activity->approver_ref_id)?->name,
                'job_position' => \App\Models\Master\JobPositions::find($activity->approver_ref_id)?->name,
                default        => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
