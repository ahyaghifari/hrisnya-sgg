<?php

namespace App\Livewire\Document;

use App\Models\Document\Document;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Riwayat audit satu dokumen - generik lintas jenis (SP, Berita Acara, Surat Tugas, dst):
 * tanda tangan (siapa, kapan sampai detik, IP, hash isi) dan semua kejadian dokumen, termasuk
 * percobaan tanda tangan yang gagal. Pasang di halaman mana pun:
 *
 *   <livewire:document.audit-trail :document-id="$record->document->id" />
 *
 * Data memuat IP dan user agent, jadi hanya terlihat oleh admin, pembuat dokumen, dan penanda
 * tangan (yang ditugaskan maupun yang sudah menandatangani).
 */
class AuditTrail extends Component
{
    public string $documentId;

    public function mount(string $documentId): void
    {
        $this->documentId = $documentId;

        abort_unless($this->canView(), 403);
    }

    public function getDocumentProperty(): Document
    {
        return Document::with(['documentable', 'signatures.verification'])->findOrFail($this->documentId);
    }

    /** Kejadian urut waktu naik (alur cerita dokumen dari terbit sampai final). */
    public function getEventsProperty()
    {
        return $this->document->events()->with('actor')->orderBy('created_at')->orderBy('id')->get();
    }

    /** Nama peran penanda tangan untuk tampilan (fallback ke key peran). */
    public function roleLabel(?string $role): string
    {
        $roles = $this->document->documentable?->expectedSignerRoles() ?? [];

        return $roles[$role] ?? ($role ?: '-');
    }

    private function canView(): bool
    {
        $user = Auth::user();
        $document = $this->document;

        if ($user->hasAnyRole(['Super Admin', 'Admin']) || $document->created_by === $user->id) {
            return true;
        }

        if ($document->signatures->contains('signer_id', $user->id)) {
            return true;
        }

        return collect($document->documentable?->assignedSigners() ?? [])
            ->contains(fn ($signer) => $signer?->id === $user->id);
    }

    public function render()
    {
        return view('livewire.document.audit-trail');
    }
}
