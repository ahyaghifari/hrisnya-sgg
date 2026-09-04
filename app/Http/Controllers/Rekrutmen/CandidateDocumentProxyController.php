<?php

namespace App\Http\Controllers\Rekrutmen;

use App\Http\Controllers\Controller;
use App\Models\Rekrutmen\JobApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Unduh file pelamar dari DB `recruitment` (shared DB) — file fisik ada di disk `rekrutmen`
 * (REKRUTMEN_STORAGE_PATH, satu server). Dulu proxy HTTP ke app Rekrutmen; sekarang baca
 * disk langsung. Gate `View:JobOpenings` (juga dijaga EnsurePortalPageAccess).
 */
class CandidateDocumentProxyController extends Controller
{
    /** Dokumen kandidat (KTP/ijazah/dll) — belum diport ke shared DB (Fase 6b). */
    public function show(string $jobApplication, string $document)
    {
        abort_unless(Auth::user()?->can('View:JobOpenings'), 403);

        abort(501, 'Unduh dokumen kandidat belum tersedia di integrasi shared DB (Fase 6b).');
    }

    /** Surat penawaran: `recruiter` (dari recruiter) atau `signed` (dari kandidat). */
    public function offeringLetter(string $jobApplication, string $type)
    {
        abort_unless(Auth::user()?->can('View:JobOpenings'), 403);
        abort_unless(in_array($type, ['recruiter', 'signed'], true), 404);

        $app = JobApplication::find($jobApplication);
        $path = $type === 'recruiter' ? $app?->offering_letter_path : $app?->offering_letter_signed_path;

        abort_if(! $path || ! Storage::disk('rekrutmen')->exists($path), 404, 'File tidak tersedia.');

        return response(Storage::disk('rekrutmen')->get($path), 200, [
            'Content-Type' => Storage::disk('rekrutmen')->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="offering-' . $type . '-' . $jobApplication . '"',
        ]);
    }
}
