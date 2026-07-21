<?php

namespace App\Filament\Resources\UserTransitions\Pages;

use App\Filament\Resources\UserTransitions\UserTransitionResource;
use App\Services\User\UserTransitionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUserTransition extends CreateRecord
{
    protected static string $resource = UserTransitionResource::class;

    protected array $detailData = [];
    protected array $attachmentData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->detailData = $data['detail'] ?? [];
        unset($data['detail']);

        $this->attachmentData = $data['attachments'] ?? [];
        unset($data['attachments']);

        return $data;
    }

    /** Logic create + snapshot approval ada di UserTransitionService (dipakai bareng portal
     * Kepegawaian) — di sini cuma nyiapin form data lalu delegasikan. */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return app(UserTransitionService::class)->create(
                $data,
                $this->detailData,
                $this->attachmentData,
                Auth::user(),
            );
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal membuat pengajuan')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
