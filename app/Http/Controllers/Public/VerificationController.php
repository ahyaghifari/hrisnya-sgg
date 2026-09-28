<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Document\DocumentVerification;
use Illuminate\View\View;

/**
 * Halaman verifikasi publik /verify/{code} (Fase 1 TTE, docs/2026-09-28-tte-qr-internal-fase-1.md
 * §5) - tanpa login, dipagari throttle di routes/web.php. Status SAH/DATA BERUBAH/DICABUT
 * DIHITUNG LIVE di sini (bukan kolom tersimpan) - lihat komentar migration
 * create_document_verifications_table.
 */
class VerificationController extends Controller
{
    public function show(string $code): View
    {
        $verification = DocumentVerification::where('verification_code', $code)
            ->with(['document.documentable', 'document.signatures' => fn ($q) => $q->orderBy('signed_at')])
            ->first();

        if (! $verification) {
            return view('public.verify-not-found', ['code' => $code]);
        }

        $document = $verification->document;
        $signable = $document->documentable;

        $currentHashesMatch = $signable
            ? hash('sha256', json_encode($signable->signablePayload())) === $document->signatures->last()?->document_hash
            : false;

        // Peran yang diharapkan (Signable::expectedSignerRoles()) tapi belum ada baris
        // document_signatures-nya - generik lintas jenis dokumen, TTE Fase 1 instruksi user
        // 2026-09-28 (knows_by dkk baru sah setelah konfirmasi password sendiri, bukan otomatis
        // saat dokumen dibuat).
        $signedRoles = $document->signatures->pluck('signer_role');
        $pendingRoles = $signable
            ? collect($signable->expectedSignerRoles())->reject(fn ($label, $role) => $signedRoles->contains($role))
            : collect();

        return view('public.verify', [
            'document' => $document,
            'signable' => $signable,
            'signatures' => $document->signatures,
            'isRevoked' => $signable?->isRevoked() ?? false,
            'revokedReason' => $signable?->revokedReason(),
            'hashMatches' => $currentHashesMatch,
            'pendingRoles' => $pendingRoles,
            'code' => $code,
        ]);
    }
}
