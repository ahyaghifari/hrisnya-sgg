<?php

namespace App\Http\Controllers\Rekrutmen;

use App\Http\Controllers\Controller;
use App\Models\Rekrutmen\CandidateDocument;
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
    /**
     * Berkas pelamar, dibuka inline dari disk `rekrutmen`. `{document}` angka = id
     * candidate_documents (milik kandidat lamaran ini); selain itu = field_key jawaban form
     * lamaran bertipe file (mis. cv_file).
     */
    public function show(string $jobApplication, string $document)
    {
        abort_unless(Auth::user()?->can('View:JobOpenings'), 403);

        $app = JobApplication::with('candidate')->findOrFail($jobApplication);

        $path = ctype_digit($document)
            ? CandidateDocument::where('candidate_id', $app->candidate?->id)->find($document)?->file_path
            : $app->answers()->where('field_key', $document)->value('value');

        abort_if(! $path || ! Storage::disk('rekrutmen')->exists($path), 404, 'File tidak tersedia.');

        return Storage::disk('rekrutmen')->response($path, basename($path), ['Content-Disposition' => 'inline; filename="' . basename($path) . '"']);
    }

    /**
     * Surat penawaran: `signed` selalu file upload kandidat. `recruiter` = file upload manual
     * recruiter kalau ada (override); kalau tidak ada, dirender LANGSUNG dari data kandidat +
     * config perusahaan/lowongan saat ini (JobApplication::renderOfferingLetterPdf(), tidak
     * disimpan) — selalu versi terbaru, tidak ada tombol "generate" terpisah.
     */
    public function offeringLetter(string $jobApplication, string $type)
    {
        abort_unless(Auth::user()?->can('View:JobOpenings'), 403);
        abort_unless(in_array($type, ['recruiter', 'signed'], true), 404);

        $app = JobApplication::with('jobOpening.company')->find($jobApplication);

        if ($type === 'signed') {
            $path = $app?->offering_letter_signed_path;
            abort_if(! $path || ! Storage::disk('rekrutmen')->exists($path), 404, 'File tidak tersedia.');

            return response(Storage::disk('rekrutmen')->get($path), 200, [
                'Content-Type' => Storage::disk('rekrutmen')->mimeType($path) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="offering-signed-' . $jobApplication . '"',
            ]);
        }

        if ($app?->offering_letter_path && Storage::disk('rekrutmen')->exists($app->offering_letter_path)) {
            return response(Storage::disk('rekrutmen')->get($app->offering_letter_path), 200, [
                'Content-Type' => Storage::disk('rekrutmen')->mimeType($app->offering_letter_path) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="offering-recruiter-' . $jobApplication . '"',
            ]);
        }

        abort_if(! $app?->offering_data_confirmed_at, 404, 'File tidak tersedia.');

        return $app->renderOfferingLetterPdf()->stream('offering-letter.pdf');
    }
}
