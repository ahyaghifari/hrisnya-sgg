<?php

namespace App\Http\Controllers\Rekrutmen;

use App\Http\Controllers\Controller;
use App\Services\Job\RekrutmenSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Proxy unduh file dari project Rekrutmen eksternal — file ada di disk privat sana,
 * endpoint-nya butuh Bearer token, jadi browser tidak bisa akses langsung. HRIS panggil
 * server-side lalu stream ulang. Gate `View:JobOpenings` (juga dijaga EnsurePortalPageAccess).
 */
class CandidateDocumentProxyController extends Controller
{
    /** Dokumen kandidat (KTP/ijazah/dll). */
    public function show(string $jobApplication, string $document)
    {
        return $this->stream(
            app(RekrutmenSyncService::class)->candidateDocumentUrl($jobApplication, $document)
        );
    }

    /** Surat penawaran: `recruiter` (dari recruiter) atau `signed` (dari kandidat). */
    public function offeringLetter(string $jobApplication, string $type)
    {
        abort_unless(in_array($type, ['recruiter', 'signed'], true), 404);

        return $this->stream(
            app(RekrutmenSyncService::class)->offeringLetterUrl($jobApplication, $type)
        );
    }

    private function stream(?string $url)
    {
        abort_unless(Auth::user()?->can('View:JobOpenings'), 403);
        abort_if($url === null, 503, 'Integrasi Rekrutmen belum dikonfigurasi.');

        try {
            $response = Http::withToken(config('services.rekrutmen.token'))->timeout(20)->get($url);
        } catch (\Throwable $e) {
            abort(502, 'Tidak bisa menghubungi Rekrutmen.');
        }

        abort_unless($response->successful(), $response->status() === 404 ? 404 : 502, 'File tidak tersedia.');

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => $response->header('Content-Disposition') ?: 'inline',
        ]);
    }
}
