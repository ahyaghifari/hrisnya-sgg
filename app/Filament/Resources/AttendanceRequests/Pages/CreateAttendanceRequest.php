<?php

namespace App\Filament\Resources\AttendanceRequests\Pages;

use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use App\Services\Attendance\AttendaceReqService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;

class CreateAttendanceRequest extends CreateRecord
{
    protected static string $resource = AttendanceRequestResource::class;
    public $type;

    public function mount(): void
    {
        parent::mount();

        $type = request()->query('type');
        $this->type = $type;
    }

    public function getFormActionsAlignment(): string | Alignment
    {
        return Alignment::End;
    }

    public function getTitle(): string
    {
        if ($this->type === 'permission') {
            return 'Buat Pengajuan Izin';
        } else if ($this->type === 'leave') {
            return 'Buat Pengajuan Cuti';
        } else if ($this->type === 'overtime') {
            return 'Buat Pengajuan Lembur';
        }

        return 'Buat Pengajuan';
    }

    public function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Ajukan')
                ->action(function () {
                    $state = $this->form->getState();
                    $service = app(AttendaceReqService::class);

                    try {
                        match ($this->type) {
                            'leave' => $service->storeLeaveRequest([
                                'type_id' => $state['leave_type'] ?? null,
                                'is_one_day' => $state['is_one_day'] ?? true,
                                'start_date' => $state['start_date'] ?? null,
                                'end_date' => ($state['is_one_day'] ?? true) ? ($state['start_date'] ?? null) : ($state['end_date'] ?? null),
                            ],
                                \App\Models\User::findOrFail($state['target_user_id']),
                                skipApproval: ! ($state['need_approval'] ?? true),
                                note: $state['note'] ?? null,
                                createdBy: Auth::user(),
                            ),
                            'overtime' => $service->storeOvertimeRequest([
                                'type_id' => $state['overtime_type'] ?? null,
                                'date' => $state['date'] ?? null,
                                'tasks' => $state['tasks'] ?? null,
                                'attachments' => $state['attachments'] ?? null,
                            ],
                                \App\Models\User::findOrFail($state['target_user_id']),
                                skipApproval: ! ($state['need_approval'] ?? true),
                                note: $state['note'] ?? null,
                                createdBy: Auth::user(),
                            ),
                            default => $service->storePermissionRequest([
                                'type_id' => $state['permission_type'] ?? null,
                                'category' => $state['category'] ?? null,
                                'date' => $state['date'] ?? null,
                                'end_date' => $state['end_date'] ?? null,
                                'start_time' => $state['start_time'] ?? null,
                                'end_time' => $state['end_time'] ?? null,
                                'purpose' => $state['purpose'] ?? null,
                                'location' => $state['location'] ?? null,
                                'substitute_user_name' => ($state['with_substitute'] ?? false) ? ($state['substitute_user_name'] ?? null) : null,
                                'substitute_task' => ($state['with_substitute'] ?? false) ? ($state['substitute_task'] ?? null) : null,
                                'attachments' => $state['attachments'] ?? null,
                            ],
                                \App\Models\User::findOrFail($state['target_user_id']),
                                skipApproval: ! ($state['need_approval'] ?? true),
                                note: $state['note'] ?? null,
                                createdBy: Auth::user(),
                            ),
                        };

                        $this->redirect($this->getResource()::getUrl('index', ['type' => $this->type]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal mengajukan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
