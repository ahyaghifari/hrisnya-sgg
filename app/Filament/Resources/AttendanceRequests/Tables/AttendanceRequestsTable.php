<?php

namespace App\Filament\Resources\AttendanceRequests\Tables;

use App\Enum\AttendanceReqStatus;
use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tanggal Pengajuan')->dateTime()->formatStateUsing(fn ($state) => $state?->translatedFormat('j F Y H:i')),
                TextColumn::make('user.name')->label('Nama Karyawan')->searchable()
                    ->description(fn ($record) => $record->user->company ? $record->user->company->name : '-'),
                TextColumn::make('status')->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->description(fn ($record) => self::getReviewerInfo($record))
                    ->color(fn (string $state): string => AttendanceReqStatus::from($state)->getColor())
                    ->icon(fn (string $state): string => AttendanceReqStatus::from($state)->getIcon())
                    ->formatStateUsing(fn (string $state): string => AttendanceReqStatus::from($state)->getLabel()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('user.company', 'name')
                    ->preload()
                    ->searchable()
                    ->visible(fn () => AttendanceRequestResource::isSuperAdmin()),
            ])
            ->recordActions([
                // Hapus dipindah ke halaman detail (EditAttendanceRequest) - cuma tampil di situ
                // buat pengajuan yang dibuat admin, lihat gate created_by di sana.
                EditAction::make()->hiddenLabel()
                    ->url(fn ($record) => AttendanceRequestResource::getUrl('edit', [
                        'record' => $record,
                        'type' => $record->type,
                    ])),
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
            
            if ($activity->status !== 'pending') {
                return null;
            }
            
            switch ($activity->approver_type) {
                case 'user':
                    $user = \App\Models\User::find($activity->approver_ref_id);
                    return $user ? $user->name : null;
                    
                case 'role':
                    $role = \App\Models\Role::find($activity->approver_ref_id);
                    if ($role) {
                        if ($activity->scope_type === 'specific_department') {
                            $department = \App\Models\Master\OrgStructure::find($activity->scope_ref_id);
                            $departmentName = $department ? $department->name : 'Dept';
                            return "{$role->name} ({$departmentName})";
                        } else {
                            return $role->name;
                        }
                    }
                    return null;
                    
                case 'job_position':
                    $jobPosition = \App\Models\Master\JobPositions::find($activity->approver_ref_id);
                    return $jobPosition ? $jobPosition->name : null;
                    
                default:
                    return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
