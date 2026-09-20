<?php

namespace App\Filament\Resources\Coachings\Pages;

use App\Enum\CoachingStatus;
use App\Filament\Resources\Coachings\CoachingResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCoaching extends EditRecord
{
    protected static string $resource = CoachingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //DeleteAction::make(),
        ];
    }

    public function getFormActions(): array
    {
        return [
            Action::make('approve')
                ->label('Tandai Selesai')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    $user = Auth::user();

                    return $record->status == CoachingStatus::SUBMITTED->value
                        && $record->coach_by == $user->id;
                })
                ->action(function ($record) {
                    try {
                        $record->update([
                            'status' => CoachingStatus::COMPLETED->value,
                        ]);

                        $record->proofs()
                            ->where('status', CoachingStatus::SUBMITTED->value)
                            ->update([
                                'status' => CoachingStatus::COMPLETED->value,
                            ]);

                        $record->refresh();

                        Notification::make()
                            ->title('Coaching berhasil ditandai selesai.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menyelesaikan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('reject')
                ->label('Tolak')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    $user = Auth::user();

                    return $record->status == CoachingStatus::SUBMITTED->value
                        && $record->coach_by == $user->id;
                })
                ->action(function ($record) {
                    try {
                        $record->update([
                            'status' => CoachingStatus::REJECTED->value,
                        ]);

                        $record->proofs()
                            ->where('status', CoachingStatus::SUBMITTED->value)
                            ->update([
                                'status' => CoachingStatus::REJECTED->value,
                            ]);

                        Notification::make()
                            ->title('Coaching berhasil ditolak.')
                            ->danger()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menolak: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('startCoaching')
                ->label('Mulai Coaching')
                ->color('primary')
                ->icon('heroicon-o-play')
                ->visible(fn ($record) => $record->status === CoachingStatus::PENDING->value)
                ->form(function () {
                    $auth = auth()->user();
                    $departmentId = $auth->profile?->department?->id;

                    return [
                        Select::make('coach_by')
                            ->label('Coach')
                            ->searchable()
                            ->options(
                                User::whereHas('profile', function ($q) use ($departmentId) {
                                    $q->whereHas('jobPosition.orgStructure', fn ($o) => $o->where('m_org_structures.id', $departmentId));
                                })->pluck('name', 'id')
                            )
                            ->default($auth->id)
                            ->required(),

                        DatePicker::make('improvement_due_date')
                            ->label('Deadline Perbaikan')
                            ->required(),

                        Textarea::make('goals')
                            ->label('Tujuan Coaching')
                            ->rows(3)
                            ->required(),

                        Textarea::make('employee_condition')
                            ->label('Kondisi Karyawan')
                            ->rows(3)
                            ->required(),

                        Textarea::make('solution')
                            ->label('Solusi')
                            ->rows(3)
                            ->required(),

                        Textarea::make('improvement_action')
                            ->label('Tindakan Perbaikan')
                            ->rows(3)
                            ->required(),
                    ];
                })
                ->action(function ($record, array $data) {
                    $record->update([
                        'coach_by'             => $data['coach_by'],
                        'improvement_due_date' => $data['improvement_due_date'],
                        'goals'                => $data['goals'],
                        'employee_condition'   => $data['employee_condition'],
                        'solution'             => $data['solution'],
                        'improvement_action'   => $data['improvement_action'],
                        'status'               => CoachingStatus::IN_PROGRESS->value,
                    ]);

                    Notification::make()
                        ->title('Coaching berhasil dimulai')
                        ->success()
                        ->send();
                }),
        ];
    }
}
