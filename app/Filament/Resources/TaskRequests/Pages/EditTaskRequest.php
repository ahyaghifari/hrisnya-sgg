<?php

namespace App\Filament\Resources\TaskRequests\Pages;

use App\Enum\Task\TaskStatus;
use App\Filament\Resources\TaskRequests\TaskRequestResource;
use App\Models\User;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Approval\ApprovalPermissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTaskRequest extends EditRecord
{
    protected static string $resource = TaskRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function getFormActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    $user = Auth::user();

                    return $record->status === TaskStatus::PENDING->value
                        && app(ApprovalPermissionService::class)
                            ->canApprove($record->approvalSnapshot->id, $user);
                })
                ->schema([
                    Select::make('assigned_to')
                        ->label('Assign ke Pegawai')
                        ->options(function ($record) {
                            $departmentId = $record->department_id;

                            if (! $departmentId) {
                                return [];
                            }

                            return User::whereHas('profile.orgStructure', function ($query) use ($departmentId) {
                                $query->where(function ($q) use ($departmentId) {
                                    $q->where('id', $departmentId)
                                        ->orWhere('parent_id', $departmentId)
                                        ->orWhereHas('parent', fn($qq) => $qq->where('parent_id', $departmentId));
                                });
                            })
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->helperText('Pilih pegawai dari departemen yang sama dengan tugas ini.'),

                    DatePicker::make('due_date')
                        ->label('Tanggal Tenggat')
                        ->required()
                        ->minDate(now())
                        ->helperText('Pilih tanggal tenggat untuk tugas ini.'),
                ])
                ->action(function ($record, array $data) {
                    try {
                        app(ApprovalFlowService::class)
                            ->advanceStep($record->approvalSnapshot, Auth::id(), null, 'approved', $data);

                        $record->refresh();

                        Notification::make()
                            ->title('Tugas berhasil disetujui')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menyetujui: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    $user = Auth::user();

                    return $record->status === TaskStatus::PENDING->value
                        && app(ApprovalPermissionService::class)
                            ->canApprove($record->approvalSnapshot->id, $user);
                })
                ->schema([

                ])
                ->action(function ($record) {
                    try {
                        app(ApprovalFlowService::class)
                            ->advanceStep($record->approvalSnapshot, Auth::id(), null, 'rejected');

                        $record->refresh();

                        Notification::make()
                            ->title('Berhasil ditolak')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menolak: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
