<?php

namespace App\Livewire\Document;

use App\Models\Document\Document;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
use App\Services\Document\DocumentSigningService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget beranda "Dokumen Menunggu Penandatanganan" (instruksi user 2026-10-02): dokumen apa pun yang
 * menugaskan Anda menandatangani (lewat DocumentSigningService::pendingFor). Sengaja TIDAK
 * menandatangani langsung dari widget - tombolnya mengarah ke halaman detail dokumen supaya
 * isinya dibaca dulu; tanda tangan dilakukan lewat SignDocumentModal di halaman detail itu.
 */
class PendingSignaturesCard extends Component
{
    public function getPendingProperty()
    {
        return Auth::user() ? app(DocumentSigningService::class)->pendingFor(Auth::user()) : collect();
    }

    /** Halaman detail dokumen (tanda tangan dilakukan dari sana, bukan langsung di widget). */
    public function detailUrl(Document $doc): string
    {
        $model = $doc->documentable;
        $mine = ($model instanceof SpReport || $model instanceof EmployeeSp) && $model->user_id === Auth::id();

        return match (true) {
            $model instanceof SpReport => $mine ? route('sp.saya.ba', $model) : route('ba.show', $model),
            $model instanceof EmployeeSp => $mine ? route('sp.saya.show', $model) : route('sp.show', $model),
            default => route('documents.sign', $doc),
        };
    }

    public function render()
    {
        return view('livewire.document.pending-signatures-card');
    }
}
