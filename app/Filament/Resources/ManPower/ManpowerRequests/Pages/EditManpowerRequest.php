<?php

namespace App\Filament\Resources\ManPower\ManpowerRequests\Pages;

use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Filament\Resources\ManPower\JobPositions\JobPositionResource;
use App\Filament\Resources\ManPower\ManpowerRequests\ManpowerRequestResource;
use App\Services\JobPosition\ManPowerReqService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditManpowerRequest extends EditRecord
{
    protected static string $resource = ManpowerRequestResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        app(ManPowerReqService::class)->ensureApprovalSnapshot($this->record);

        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['approvalSnapshot.activity', 'approvalHistories']);
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => JobPositionResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        $type = $this->record->type === ManpowerRequestType::ADDITIONAL_QUOTA->value ? 'quota' : 'employee_change';
        return $this->getResource()::getUrl('index', ['type' => $type]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make('create_request')
                ->label('Ajukan Ulang')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Simpan Data')
                ->modalDescription('Apakah Anda yakin ingin menyimpan data ini?')
                ->tooltip('Data akan disimpan dan diajukan untuk proses persetujuan.')
                ->visible(fn () => $this->record->approvalSnapshot === null)
                ->action(function () {
                    try {
                        app(ManPowerReqService::class)->requestManPower($this->record);
    
                        Notification::make()
                            ->title('Data berhasil disimpan')
                            ->success()
                            ->send();
    
                        $this->redirect($this->getRedirectUrl());
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }
                }),
                
            Action::make('approve')
                ->label('Approve')
                ->requiresConfirmation()
                ->color('success')
                ->modalHeading('Konfirmasi Persetujuan')
                ->modalDescription('Apakah Anda yakin ingin menyetujui perubahan ini?')
                ->visible(function () {
                    try {
                        return app(\App\Services\Approval\ApprovalPermissionService::class)
                        ->canApprove($this->record->approvalSnapshot->id, Auth::user());
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error Approve Manpower Request')
                            ->body('Tidak ada snapshot approval untuk request ini. Pastikan alur approval sudah dibuat dan aktif.')
                            ->danger()
                            ->send();
                        return false;
                    }
                })
                ->schema([
                    TextInput::make('approved_quantity')
                        ->label('Kuota yang Disetujui')
                        ->numeric()
                        ->default(fn () => $this->record->quantity)
                        ->disabled()
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
                    app(ManPowerReqService::class)->approveManPower(
                        manpowerRequests: $this->record,
                        status: JobPositionReqStatus::APPROVED->value,
                        note: $data['approval_note'],
                    );

                    Notification::make()
                        ->title('Perubahan berhasil disetujui')
                        ->success()
                        ->send();

                    $this->redirect($this->getRedirectUrl());
                }),
        ];
    }
}
