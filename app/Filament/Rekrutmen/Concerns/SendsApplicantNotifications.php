<?php

namespace App\Filament\Rekrutmen\Concerns;

use App\Models\Rekrutmen\ApplicationNotification;
use App\Models\Rekrutmen\JobApplication;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Aksi bulk "Kirim Email Pemberitahuan" untuk halaman pipeline /rekrutmen. Event positif
 * (lulus/undangan) langsung diantrekan lewat ApplicationNotification::createAndSend(); yang
 * tersisa sebagai draft = penolakan tahap ini — admin pilih barisnya lalu antrekan sekaligus.
 * Pengiriman email dilakukan project `rekrutmen` (command notifications:dispatch).
 * Halaman pemakai wajib punya JobApplication $record di tabelnya + method canDecide().
 */
trait SendsApplicantNotifications
{
    protected function kirimNotifBulkAction(): BulkAction
    {
        return BulkAction::make('kirimNotifPemberitahuan')
            ->label('Kirim Email Pemberitahuan')
            ->icon('heroicon-m-paper-airplane')
            ->color('gray')
            ->visible(fn () => $this->canDecide())
            ->requiresConfirmation()
            ->modalHeading('Kirim Email Pemberitahuan')
            ->modalDescription('Email pemberitahuan diantrekan untuk pelamar terpilih yang notifikasinya masih berstatus draft, lalu dikirim otomatis oleh sistem rekrutmen. Pelamar tanpa draft dilewati.')
            ->modalSubmitActionLabel('Kirim')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $ids = $records->map(fn (JobApplication $r) => $r->getKey())->all();
                $queued = ApplicationNotification::flushDraftsFor($ids);

                Notification::make()
                    ->title($queued > 0 ? "{$queued} email diantrekan untuk dikirim" : 'Tidak ada draft untuk dikirim')
                    ->{$queued > 0 ? 'success' : 'warning'}()
                    ->send();
            });
    }
}
