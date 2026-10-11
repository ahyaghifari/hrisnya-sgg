<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected static ?string $title = 'Pengumuman';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['job_position_ids'] = $this->record->jobPositions()->pluck('m_job_positions.id')->all();

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->record->isPublished()) {
            AnnouncementResource::syncTargets($this->record, $this->data['job_position_ids'] ?? []);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            AnnouncementResource::previewAction(),
            // Simpan perubahan form dulu supaya yang terbit = yang terlihat di preview.
            Action::make('publish')
                ->label('Simpan & Terbitkan')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->visible(fn () => ! $this->record->isPublished())
                ->requiresConfirmation()
                ->modalDescription('Pengumuman langsung dikirim ke semua penerima di preview. Tidak bisa ditarik kembali.')
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    AnnouncementResource::publish($this->record->refresh());
                    $this->redirect(AnnouncementResource::getUrl('index'));
                }),
            DeleteAction::make()->visible(fn () => ! $this->record->isPublished()),
        ];
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()->label($this->record->isPublished() ? 'Simpan Perubahan' : 'Simpan Draft'), $this->getCancelFormAction()];
    }
}
