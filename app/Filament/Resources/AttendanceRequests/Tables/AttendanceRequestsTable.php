<?php

namespace App\Filament\Resources\AttendanceRequests\Tables;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\AttendanceReqStatus;
use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use App\Helpers\PermissionHelper;
use App\Models\ApprovalManagement\ApprovalSnapshots;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

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
                    ->default(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user()) ? \Illuminate\Support\Facades\Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(\Illuminate\Support\Facades\Auth::user())),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel()
                    ->url(fn ($record) => AttendanceRequestResource::getUrl('edit', [
                        'record' => $record,
                        'type' => $record->type,
                    ])),

                // Bersih total, buat kebutuhan tes ulang: hapus snapshot approval (activities +
                // histories ikut cascade DB dari snapshot_id) dulu, baru pengajuannya sendiri
                // (leave/permission/overtime detail ikut cascade DB dari request_id). Snapshot
                // TIDAK punya FK asli ke attendance_requests (module_id cuma string generik,
                // dipakai lintas modul approval), jadi harus dicari manual, bukan otomatis ikut hapus.
                Action::make('deleteRequest')
                    ->label('Hapus')
                    ->hiddenLabel()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Pengajuan')
                    ->modalDescription('Ini akan menghapus PERMANEN pengajuan ini beserta detail (cuti/izin/lembur), snapshot approval, dan riwayatnya. Tidak bisa dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            ApprovalSnapshots::where('module_type', ApprovalModuleEnum::ATTENDANCE_REQUEST->value)
                                ->where('module_id', $record->id)
                                ->each(fn ($snapshot) => $snapshot->delete());

                            $record->delete();
                        });

                        Notification::make()
                            ->title('Pengajuan berhasil dihapus')
                            ->success()
                            ->send();
                    }),
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
