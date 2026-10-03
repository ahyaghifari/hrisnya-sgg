<?php

namespace App\Livewire\Document;

use App\Models\Document\Document;
use App\Services\Document\DocumentSigningService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal konfirmasi tanda tangan (identitas dokumen + penanda tangan + password) yang bisa
 * ditempel di halaman mana pun (instruksi user 2026-10-02): taruh `<livewire:document.sign-document-modal />`
 * lalu buka dengan `$dispatch('open-sign-modal', { id: '<documents.id>' })` (Alpine) atau
 * `$this->dispatch('open-sign-modal', id: ...)` (Livewire). Setelah berhasil menembakkan event
 * `document-signed` supaya halaman induk memuat ulang datanya. Otorisasi (apakah Anda memang
 * ditugaskan) tetap dicek di DocumentSigningService, komponen ini tidak tahu jenis dokumennya.
 */
class SignDocumentModal extends Component
{
    public ?string $documentId = null;

    public ?string $password = null;

    #[On('open-sign-modal')]
    public function open(string $id): void
    {
        $this->documentId = $id;
        $this->password = null;
        $this->resetErrorBag();

        $this->document?->logEvent('signature_viewed', Auth::user());
    }

    public function close(): void
    {
        $this->documentId = null;
        $this->password = null;
    }

    /** Hanya dokumen yang memang menugaskan Anda - selain itu null (modal tidak tampil, bukan bocor info). */
    public function getDocumentProperty(): ?Document
    {
        if (! $this->documentId) {
            return null;
        }

        $document = Document::with(['documentable', 'signatures'])->find($this->documentId);

        return $document?->pendingRoleFor(Auth::user()) ? $document : null;
    }

    public function getRoleLabelProperty(): ?string
    {
        $document = $this->document;
        $role = $document?->pendingRoleFor(Auth::user());

        return $role ? ($document->documentable->expectedSignerRoles()[$role] ?? $role) : null;
    }

    public function confirmSign(): void
    {
        $document = $this->document;
        abort_unless($document, 404);

        $this->validate(['password' => ['required', 'string']], [], ['password' => 'Password']);

        if (! Hash::check($this->password, Auth::user()->password)) {
            $this->addError('password', 'Password salah.');

            return;
        }

        app(DocumentSigningService::class)->sign($document, Auth::user());

        $id = $document->id;
        $this->close();

        // Computed property (getXxxProperty) di-cache sekali per request - tanpa unset() ini render
        // setelah sign() masih memakai $this->document LAMA (diakses di atas), modal tidak menutup
        // walau $documentId sudah null.
        unset($this->document, $this->roleLabel);

        Notification::make()->title('Tanda tangan berhasil disimpan')->success()->send();

        $this->dispatch('document-signed', id: $id);
    }

    public function render()
    {
        return view('livewire.document.sign-document-modal');
    }
}
