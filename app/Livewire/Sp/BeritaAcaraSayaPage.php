<?php

namespace App\Livewire\Sp;

use App\Models\Sp\SpReport;
use App\Services\Sp\SpReportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Berita Acara milik karyawan sendiri (alur baru, instruksi user 2026-10-01): mengisi klarifikasi
 * apa adanya, membaca parafrase HC, lalu menerima (tanda tangan lewat halaman Tanda Tangan
 * generik) atau mengajukan ulang klarifikasi. Draft HC belum terlihat sama sekali. Aturan
 * transisi ada di SpReportService.
 */
#[Layout('components.layout', ['title' => 'Berita Acara'])]
class BeritaAcaraSayaPage extends Component
{
    public SpReport $record;

    public string $klarifikasi = '';

    public string $alasan_ajukan_ulang = '';

    public function mount(SpReport $record): void
    {
        abort_if($record->isLegacy(), 404);
        abort_unless($record->user_id === Auth::id(), 403);
        abort_if($record->status->value === 'draft', 404);

        $this->record = $record;
        $this->klarifikasi = (string) $record->subject_clarification;
    }

    /** HC baru memparafrase/menganalisis setelah klarifikasi diajukan - sebelum dikirim untuk TTD
     *  itu masih konsep HC, belum untuk dilihat karyawan. */
    public function getShowsHcContentProperty(): bool
    {
        return in_array($this->record->status->value, ['menunggu_ttd', 'selesai'], true);
    }

    public function getSignDocumentIdProperty(): ?string
    {
        $document = $this->record->document;

        return $document && $document->loadMissing(['documentable', 'signatures'])->pendingRoleFor(Auth::user()) ? $document->id : null;
    }

    private function run(callable $action, string $success): void
    {
        try {
            $action(app(SpReportService::class));
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        $this->record->refresh()->unsetRelation('document');

        Notification::make()->title($success)->success()->send();
    }

    public function submitClarification(): void
    {
        $this->run(fn ($s) => $s->submitClarification($this->record, Auth::user(), $this->klarifikasi), 'Klarifikasi diajukan');
    }

    public function requestReclarification(): void
    {
        $this->run(function ($s) {
            $s->requestReclarification($this->record, Auth::user(), $this->alasan_ajukan_ulang);
            $this->alasan_ajukan_ulang = '';
        }, 'Permintaan klarifikasi ulang dikirim');
    }

    public function render()
    {
        return view('livewire.sp.berita-acara-saya-page');
    }
}
