<?php

namespace App\Filament\Resources\LMS\LmsCoursePlanItems\Pages;

use App\Filament\Resources\LMS\LmsCoursePlanItems\LmsCoursePlanItemResource;
use App\Services\LMS\CourseService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditLmsCoursePlanItem extends EditRecord
{
    protected static string $resource = LmsCoursePlanItemResource::class;
    protected static ?string $title = 'Edit Pengajuan Pelatihan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn ($record) => LmsCoursePlanItemResource::canDelete($record)),
        ];
    }

    public function getRedirectUrl(): string
    {
        return LmsCoursePlanItemResource::getUrl('index');
    }

    public function getFormActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->requiresConfirmation()
                ->color('success')
                ->modalHeading('Konfirmasi Persetujuan')
                ->modalDescription('Apakah Anda yakin ingin menyetujui perubahan ini?')
                ->visible(function () {
                    if ($this->record->request_status === 'draft') {
                        return false;
                    }

                    if (!$this->record->approvalSnapshot) {
                        return false;
                    }
                    return app(\App\Services\Approval\ApprovalPermissionService::class)
                        ->canApprove($this->record->approvalSnapshot?->id, Auth::user());
                })
                ->schema([
                    Textarea::make('approval_note')
                        ->label('Catatan Persetujuan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan catatan untuk persetujuan ini...')
                        ->columnSpan('full'),
                ])
                ->action(function (array $data) {
                    app(CourseService::class)->approveCourseRequest(
                        coursePlanItem: $this->record,
                        status: 'approved',
                        note: $data['approval_note'],
                    );

                    Notification::make()
                        ->title('Perubahan berhasil disetujui')
                        ->success()
                        ->send();

                    $this->redirect($this->getRedirectUrl());
                }),

            Action::make('reject')
                ->label('Reject')
                ->requiresConfirmation()
                ->color('danger')
                ->modalHeading('Konfirmasi Penolakan')
                ->modalDescription('Apakah Anda yakin ingin menolak pengajuan ini?')
                ->visible(function () {
                    if ($this->record->request_status === 'draft') {
                        return false;
                    }

                    if (!$this->record->approvalSnapshot) {
                        return false;
                    }
                    return app(\App\Services\Approval\ApprovalPermissionService::class)
                        ->canApprove($this->record->approvalSnapshot?->id, Auth::user());
                })
                ->schema([
                    Textarea::make('rejection_note')
                        ->label('Catatan Penolakan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan catatan untuk penolakan ini')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    app(CourseService::class)->approveCourseRequest(
                        coursePlanItem: $this->record,
                        status: 'rejected',
                        note: $data['rejection_note'],
                    );

                    Notification::make()
                        ->title('Pengajuan pelatihan berhasil ditolak')
                        ->success()
                        ->send();

                    $this->redirect($this->getRedirectUrl());
                }),
        ];
    }
}
