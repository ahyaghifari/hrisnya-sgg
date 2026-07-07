<?php

namespace App\Filament\Resources\ManPower\JobPositions\Pages;

use App\Enum\JobPositionReqStatus;
use App\Filament\Resources\ManPower\JobPositions\JobPositionResource;
use App\Helpers\PermissionHelper;
use App\Models\User;
use App\Services\JobPosition\JobPositionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditJobPosition extends EditRecord
{
    protected static string $resource = JobPositionResource::class;
    
    protected static ?string $title = 'Detail Jabatan';

    public function mount($record): void
    {
        parent::mount($record);
        if ($this->record->status === JobPositionReqStatus::DRAFT->value || $this->record->status === JobPositionReqStatus::PENDING->value) {
            try {
                Notification::make()
                    ->title("Jabatan ini Masih Dalam Draft / Pending")
                    ->body('Jabatan ini masih dalam draft, silahkan edit dan ajukan atau publish untuk membuat permintaan persetujuan otomatis.')
                    ->info()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Gagal membuat permintaan persetujuan otomatis: Tidak ada alur persetujuan aktif untuk permintaan jabatan pada perusahaan ini.')
                    ->danger()
                    ->send();
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => JobPositionResource::canDelete($this->record)),

            Action::make('repeat_request')
                ->label('Edit Dan Ajukan Ulang')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pengajuan Ulang')
                ->visible(fn () => JobPositionResource::canEdit($this->record))
                ->modalDescription('Apakah Anda yakin ingin mengedit dan mengajukan ulang data ini?')
                ->action(function () {
                    $status = JobPositionReqStatus::DRAFT->value;
                    $this->record->update(['status' => $status]);
                })
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getFormActions(): array
    {
        return [
            Action::make('create_request')
                ->label('Simpan dan Ajukan')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                ->tooltip('Data akan disimpan dan diajukan untuk proses persetujuan.')
                ->visible(fn () => in_array($this->record->status, [JobPositionReqStatus::DRAFT->value, JobPositionReqStatus::REJECTED->value]))
                ->action(function () {
                    $this->handleRecordUpdate($this->record, $this->data);
                    try {
                        app(JobPositionService::class)->requestJobPosition($this->record, JobPositionReqStatus::REQUESTED->value);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal membuat permintaan persetujuan: Tidak ada alur persetujuan aktif untuk permintaan jabatan pada perusahaan ini.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Data berhasil disimpan')
                        ->success()
                        ->send();

                    // $this->redirect($this->getRedirectUrl());
                }),

            Action::make('publish')
                ->label('Simpan dan Publish')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan dan Publish')
                ->modalDescription('Apakah Anda yakin ingin menyimpan dan mempublish data ini? Status akan langsung disetujui.')
                ->tooltip('Data akan disimpan dan langsung disetujui tanpa melalui proses persetujuan.')
                ->visible(fn () => in_array($this->record->status, [JobPositionReqStatus::DRAFT->value, JobPositionReqStatus::REJECTED->value]))
                ->action(function () {
                    $this->handleRecordUpdate($this->record, $this->data);
                    $this->record->update([
                        'status' => JobPositionReqStatus::APPROVED->value,
                        'actual_manpower' => $this->record->planned_manpower,
                        'request_type' => 'published',
                        'is_draft' => false,
                    ]);

                    Notification::make()
                        ->title('Data berhasil disimpan dan dipublish')
                        ->success()
                        ->send();

                    // $this->redirect($this->getRedirectUrl());
                }),

            Action::make('edit')
                ->label('Simpan Draft')
                ->requiresConfirmation()
                ->outlined()
                ->modalHeading('Konfirmasi Simpan Draft')
                ->modalDescription('Apakah Anda yakin ingin menyimpan perubahan ini?')
                ->visible(fn () => in_array($this->record->status, [JobPositionReqStatus::DRAFT->value, JobPositionReqStatus::REJECTED->value]))
                ->action(function () {
                    $this->handleRecordUpdate($this->record, $this->data);

                    Notification::make()
                        ->title('Data berhasil diupdate')
                        ->success()
                        ->send();

                    // $this->redirect($this->getRedirectUrl());
                }),

            Action::make('approve')
                ->label('Approve')
                ->requiresConfirmation()
                ->color('success')
                ->modalHeading('Konfirmasi Persetujuan')
                ->modalDescription('Apakah Anda yakin ingin menyetujui perubahan ini?')
                ->visible(function () {
                    if (!$this->record->approvalSnapshots || $this->record->approvalSnapshots->isEmpty()) {
                        return false;
                    }
                    return app(\App\Services\Approval\ApprovalPermissionService::class)
                        ->canApprove($this->record->approvalSnapshots->last()->id, Auth::user())
                    || PermissionHelper::hasGlobalRole(User::find($this->record->request_by));
                })
                ->schema([
                    TextInput::make('actual_manpower')
                        ->helperText('Kuota Sebelumnya: ' . $this->record->actual_manpower . ' | Pengajuan Baru: ' . $this->record->planned_manpower)
                        ->label('Realisasi Kuota Karyawan')
                        ->numeric()
                        ->minValue(0)
                        ->required(),

                    Textarea::make('approval_note')
                        ->label('Catatan Persetujuan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan catatan untuk persetujuan ini...')
                        ->columnSpan('full'),
                ])
                ->action(function (array $data) {
                    app(JobPositionService::class)->approveJobPosition(
                        jobPositions: $this->record,
                        status: JobPositionReqStatus::APPROVED->value,
                        actualManPower: $data['actual_manpower'],
                        note: $data['approval_note'],
                    );

                    Notification::make()
                        ->title('Perubahan berhasil disetujui')
                        ->success()
                        ->send();

                    // $this->redirect($this->getRedirectUrl());
                }),

            Action::make('reject')
                ->label('Tolak')
                ->requiresConfirmation()
                ->color('danger')
                ->modalHeading('Konfirmasi Penolakan')
                ->modalDescription('Apakah Anda yakin ingin menolak perubahan ini?')
                ->visible(function () {
                    if (!$this->record->approvalSnapshots || $this->record->approvalSnapshots->isEmpty()) {
                        return false;
                    }
                    return app(\App\Services\Approval\ApprovalPermissionService::class)
                        ->canApprove($this->record->approvalSnapshots->last()->id, Auth::user())
                    || PermissionHelper::hasGlobalRole(User::find($this->record->request_by));
                })
                ->schema([
                    Textarea::make('rejection_note')
                        ->label('Alasan Penolakan')
                        ->rows(3)
                        ->required()
                        ->placeholder('Tambahkan alasan penolakan...'),
                ])
                ->action(function (array $data) {
                    app(JobPositionService::class)->approveJobPosition(
                        jobPositions: $this->record,
                        status: JobPositionReqStatus::REJECTED->value,
                        actualManPower: 0,
                        note: $data['rejection_note'],
                    );
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url($this->getRedirectUrl()),
        ];
    }
}
