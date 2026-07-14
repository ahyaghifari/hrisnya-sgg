<?php

namespace App\Filament\Widgets;

use App\Enum\AttendanceReqStatus;
use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use App\Helpers\PermissionHelper;
use App\Models\Attendance\AttendanceRequests;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Services\Approval\ApprovalPermissionService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NotificationWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Notifikasi Pengajuan Izin, Cuti, Lembur';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('user_name')
                    ->label('User')
                    ->default(function ($record): ?string {
                        $data = $record->data;
                        return $data['requester_name'] ?? null;
                    })
                    ->searchable()
                    ->placeholder('User not found'),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            'App\\Notifications\\OvertimeRequestSubmitted' => 'Pengajuan Lembur',
                            'App\\Notifications\\LeaveRequestSubmitted' => 'Pengajuan Cuti',
                            'App\\Notifications\\PermissionRequestSubmitted' => 'Pengajuan Izin',
                            default => class_basename($state),
                        };
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            'App\\Notifications\\OvertimeRequestSubmitted' => 'warning',
                            'App\\Notifications\\LeaveRequestSubmitted' => 'info',
                            'App\\Notifications\\PermissionRequestSubmitted' => 'success',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('message')
                    ->label('Message')
                    ->limit(50)
                    ->formatStateUsing(function ($record): string {
                        $data = $record->data;
                        $requestType = $data['request_type'] ?? '';
                        $requesterName = $data['requester_name'] ?? '';

                        $typeLabel = match ($requestType) {
                            'overtime' => 'Lembur',
                            'leave' => 'Cuti',
                            'permission' => 'Izin',
                            default => ucfirst($requestType),
                        };

                        return "Pengajuan {$typeLabel} dari {$requesterName}";
                    })
                    ->tooltip(function ($record): string {
                        $data = $record->data;
                        $body = $data['body'] ?? '';
                        $details = [];

                        if (isset($data['overtime_type'])) {
                            $details[] = "Jenis: {$data['overtime_type']}";
                        }
                        if (isset($data['leave_type'])) {
                            $details[] = "Jenis: {$data['leave_type']}";
                        }
                        if (isset($data['permission_type'])) {
                            $details[] = "Jenis: {$data['permission_type']}";
                        }
                        if (isset($data['date'])) {
                            $details[] = "Tanggal: {$data['date']}";
                        }
                        if (isset($data['start_date']) && isset($data['end_date'])) {
                            $details[] = "Periode: {$data['start_date']} - {$data['end_date']}";
                        }
                        if (isset($data['start_time']) && isset($data['end_time'])) {
                            $details[] = "Waktu: {$data['start_time']} - {$data['end_time']}";
                        }

                        return $body . (count($details) > 0 ? "\n\n" . implode("\n", $details) : '');
                    }),

                TextColumn::make('request_status')
                    ->label('Status Pengajuan')
                    ->badge()
                    ->default(function ($record): string {
                        $data = $record->data;
                        $requestId = $data['request_id'] ?? null;

                        if ($requestId) {
                            $attendanceRequest = AttendanceRequests::with('approvalSnapshot.activity')->find($requestId);
                            if ($attendanceRequest) {
                                $statusLabel = AttendanceReqStatus::from($attendanceRequest->status)->getLabel();

                                // Show reviewer info for in_review status
                                if ($attendanceRequest->status === 'in_review') {
                                    $reviewerInfo = $this->getReviewerInfo($attendanceRequest);
                                    if ($reviewerInfo) {
                                        return $statusLabel . ' • ' . $reviewerInfo;
                                    }
                                }

                                return $statusLabel;
                            }
                        }

                        return 'Status tidak diketahui';
                    })
                    ->color(function ($record): string {
                        $data = $record->data;
                        $requestId = $data['request_id'] ?? null;

                        if ($requestId) {
                            $attendanceRequest = AttendanceRequests::find($requestId);
                            if ($attendanceRequest) {
                                return AttendanceReqStatus::from($attendanceRequest->status)->getColor();
                            }
                        }

                        return 'gray';
                    })
                    ->icon(function ($record): string {
                        $data = $record->data;
                        $requestId = $data['request_id'] ?? null;

                        if ($requestId) {
                            $attendanceRequest = AttendanceRequests::find($requestId);
                            if ($attendanceRequest) {
                                return AttendanceReqStatus::from($attendanceRequest->status)->getIcon();
                            }
                        }

                        return 'heroicon-o-question-mark-circle';
                    }),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                EditAction::make('view_request')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->url(function ($record): ?string {
                        // Mark notification as read when URL is generated
                        if (!$record->read_at) {
                            $record->update(['read_at' => now()]);
                        }

                        $data = $record->data;
                        $requestId = $data['request_id'] ?? null;

                        if ($requestId) {
                            return AttendanceRequestResource::getUrl('edit', ['record' => $requestId]);
                        }

                        return null;
                    })
                    ->openUrlInNewTab(false)
                    ->visible(function ($record): bool {
                        $data = $record->data;
                        $requestId = $data['request_id'] ?? null;

                        if (!$requestId) {
                            return false;
                        }

                        $user = Auth::user();
                        if (!$user) {
                            return false;
                        }

                        if (PermissionHelper::hasGlobalRole($user)) {
                            return true;
                        }

                        $attendanceRequest = AttendanceRequests::with('approvalSnapshot')->find($requestId);
                        if ($attendanceRequest && $attendanceRequest->approvalSnapshot) {
                            $approvalPermissionService = app(ApprovalPermissionService::class);
                            return $approvalPermissionService->canApprove($attendanceRequest->approvalSnapshot->id, $user);
                        }

                        return false;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ])
            ->emptyStateHeading('Tidak ada notifikasi');
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        if (!$user) {
            return DatabaseNotification::query()->whereRaw('1 = 0'); // Return empty result
        }

        $query = DatabaseNotification::query()
            ->with(['user'])
            ->whereIn('type', [
                'App\\Notifications\\OvertimeRequestSubmitted',
                'App\\Notifications\\LeaveRequestSubmitted',
                'App\\Notifications\\PermissionRequestSubmitted'
            ]);

        if (PermissionHelper::hasGlobalRole($user)) {
            return $query;
        }

        $approvalPermissionService = app(ApprovalPermissionService::class);

        $allNotifications = $query->get();
        $allowedNotificationIds = [];

        foreach ($allNotifications as $notification) {
            $data = $notification->data;
            $requestId = $data['request_id'] ?? null;

            if ($requestId) {
                $attendanceRequest = AttendanceRequests::with('approvalSnapshot')->find($requestId);
                if ($attendanceRequest && $attendanceRequest->approvalSnapshot) {
                    if ($approvalPermissionService->hasPermission($attendanceRequest->approvalSnapshot->id, $user)) {
                        $allowedNotificationIds[] = $notification->id;
                    }
                }
            }
        }

        return DatabaseNotification::query()
            ->with(['user'])
            ->whereIn('id', $allowedNotificationIds);
    }

    private function getReviewerInfo($record): ?string
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
                    $user = User::find($activity->approver_ref_id);
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
