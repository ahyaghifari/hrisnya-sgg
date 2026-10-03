<?php

namespace App\Livewire\Document;

use App\Models\Document\Document;
use App\Services\Document\DocumentSigningService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Daftar dokumen yang menunggu tanda tangan Anda + modal konfirmasi (TTE Fase 1, instruksi user
 * 2026-09-28: bakal ada banyak jenis dokumen - SP, Berita Acara, nanti Surat Tugas dst - jadi
 * SATU halaman+modal generik, bukan 1 halaman per jenis dokumen). Semua logika "dokumen apa yang
 * pending" dan "siapa boleh menandatangani apa" ada di DocumentSigningService, komponen ini
 * TIDAK tahu detail SP/EmployeeSp sama sekali - hanya bicara lewat kontrak Signable & model
 * Document generik.
 */
#[Layout('components.layout', ['title' => 'Tanda Tangan Dokumen'])]
class SignDocumentsPage extends Component
{
    /** ID `documents` yang langsung dibuka modalnya saat halaman dimuat (dari link notifikasi). */
    public ?string $initialSigningId = null;

    public function mount(?Document $document = null): void
    {
        // Datang dari notifikasi (link ke 1 dokumen spesifik) - langsung buka modalnya kalau
        // memang dia yang ditugaskan menandatangani (bukan asal buka modal dokumen orang lain).
        // pendingRoleFor() butuh documentable/signatures ke-load dulu (dia guard defensif ke
        // relationLoaded(), bukan lazy-load sendiri).
        if ($document) {
            $document->loadMissing(['documentable', 'signatures']);

            if ($document->pendingRoleFor(Auth::user())) {
                $this->initialSigningId = $document->id;
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, Document> */
    public function getPendingDocumentsProperty()
    {
        return app(DocumentSigningService::class)->pendingFor(Auth::user());
    }

    /** Modalnya komponen terpisah (SignDocumentModal) - halaman ini cuma membukanya. */
    public function openSignModal(string $id): void
    {
        $this->dispatch('open-sign-modal', id: $id);
    }

    /** Re-render setelah modal berhasil menandatangani supaya dokumen hilang dari daftar. */
    #[On('document-signed')]
    public function refreshList(): void
    {
    }

    public function render()
    {
        return view('livewire.document.sign-documents-page');
    }
}
