<?php

namespace App\Livewire\Document;

use App\Models\Document\Document;
use App\Services\Document\DocumentSigningService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
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
    /** ID `documents` yang modal-nya sedang terbuka - null = tidak ada modal terbuka. */
    public ?string $signingId = null;

    public ?string $password = null;

    public function mount(?Document $document = null): void
    {
        // Datang dari notifikasi (link ke 1 dokumen spesifik) - langsung buka modalnya kalau
        // memang dia yang ditugaskan menandatangani (bukan asal buka modal dokumen orang lain).
        // pendingRoleFor() butuh documentable/signatures ke-load dulu (dia guard defensif ke
        // relationLoaded(), bukan lazy-load sendiri).
        if ($document) {
            $document->loadMissing(['documentable', 'signatures']);

            if ($document->pendingRoleFor(Auth::user())) {
                $this->signingId = $document->id;
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, Document> */
    public function getPendingDocumentsProperty()
    {
        return app(DocumentSigningService::class)->pendingFor(Auth::user());
    }

    public function getSigningDocumentProperty(): ?Document
    {
        if (! $this->signingId) {
            return null;
        }

        return $this->pendingDocuments->firstWhere('id', $this->signingId);
    }

    /** Label peran yang akan ditandatangani (mis. "Mengetahui") - generik lewat
     *  Signable::expectedSignerRoles(), bukan hardcode nama peran SP. */
    public function getSigningRoleLabelProperty(): ?string
    {
        $document = $this->signingDocument;

        if (! $document) {
            return null;
        }

        $role = $document->pendingRoleFor(Auth::user());

        return $role ? ($document->documentable->expectedSignerRoles()[$role] ?? $role) : null;
    }

    public function openSignModal(string $id): void
    {
        $this->signingId = $id;
        $this->password = null;
        $this->resetErrorBag();
    }

    public function closeSignModal(): void
    {
        $this->signingId = null;
        $this->password = null;
    }

    public function confirmSign(): void
    {
        $document = $this->signingDocument;
        abort_unless($document, 404);

        $this->validate(['password' => ['required', 'string']], [], ['password' => 'Password']);

        if (! Hash::check($this->password, Auth::user()->password)) {
            $this->addError('password', 'Password salah.');

            return;
        }

        app(DocumentSigningService::class)->sign($document, Auth::user());

        $this->signingId = null;
        $this->password = null;

        // Computed property (getXxxProperty) di-cache sekali per request - tanpa unset() ini,
        // render setelah sign() masih pakai signingDocument/pendingDocuments LAMA (dari akses
        // $this->signingDocument di baris atas method ini), modal kelihatan tidak tertutup
        // walau $signingId sudah null.
        unset($this->signingDocument, $this->pendingDocuments);

        Notification::make()->title('Tanda tangan berhasil disimpan')->success()->send();
    }

    public function render()
    {
        return view('livewire.document.sign-documents-page');
    }
}
