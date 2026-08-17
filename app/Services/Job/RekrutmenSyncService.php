<?php

namespace App\Services\Job;

use App\Models\Job\JobOpenings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RekrutmenSyncService
{
    /** Ping sederhana buat ngetes konektivitas ke sistem Rekrutmen, tidak terikat 1 lowongan
     * tertentu - dipakai tomol "Tes Koneksi Rekrutmen" di JobOpeningsPage (halaman list).
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return ['ok' => false, 'message' => 'REKRUTMEN_API_URL/REKRUTMEN_API_TOKEN belum diisi di .env.'];
        }

        $url = rtrim($baseUrl, '/') . '/hris-test';

        try {
            $response = Http::withToken($token)->timeout(5)->get($url);

            if ($response->failed()) {
                return ['ok' => false, 'message' => "Rekrutmen balas error (HTTP {$response->status()}) dari {$url}."];
            }

            return ['ok' => true, 'message' => "Terhubung ke {$url} (HTTP {$response->status()})."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => "Tidak bisa menghubungi {$url}: " . $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string} — dipakai JobOpeningService (hasil diabaikan,
     * sync tetap non-fatal) DAN tombol "Tes Koneksi Rekrutmen" di JobOpeningDetailPage (hasil
     * ditampilkan ke user).
     */
    public function sync(JobOpenings $jobOpening): array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            $message = 'REKRUTMEN_API_URL/REKRUTMEN_API_TOKEN belum diisi di .env.';

            Log::info("RekrutmenSyncService: skip sync — {$message}", [
                'job_opening_id' => $jobOpening->id,
            ]);

            return ['ok' => false, 'message' => $message];
        }

        $jobOpening->loadMissing(['jobPosition', 'company']);

        $payload = [
            'hris_job_opening_id' => $jobOpening->id,
            'hris_company_code' => $jobOpening->company?->code,
            'title' => $jobOpening->display_name,
            'quota' => $jobOpening->quota,
            'max_applicants' => $jobOpening->max_applicants,
            'applications_deadline' => $jobOpening->application_deadline?->format('Y-m-d'),
            'description' => $jobOpening->description,
            'requirements' => $jobOpening->requirements,
            'is_active' => $jobOpening->is_active,
        ];

        $url = rtrim($baseUrl, '/') . '/integrations/hris/job-openings/' . $jobOpening->id;

        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->put($url, $payload);

            if ($response->failed()) {
                $message = "Rekrutmen balas error (HTTP {$response->status()}): " . \Illuminate\Support\Str::limit($response->body(), 200);

                Log::warning('RekrutmenSyncService: sync gagal (response error).', [
                    'job_opening_id' => $jobOpening->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['ok' => false, 'message' => $message];
            }

            return ['ok' => true, 'message' => "Berhasil dikirim ke {$url} (HTTP {$response->status()})."];
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: sync gagal (exception).', [
                'job_opening_id' => $jobOpening->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => "Tidak bisa menghubungi {$url}: " . $e->getMessage()];
        }
    }

    /**
     * Ambil jumlah pelamar per lowongan dari Rekrutmen - 1 panggilan batch buat banyak id
     * sekaligus (bukan 1 request per baris/N+1), dipakai JobOpeningsPage (halaman list).
     * Kontrak: HRIS kirim daftar `hris_job_opening_id`, Rekrutmen balikin map id => jumlah.
     * Non-fatal - gagal/endpoint belum ada balikin array kosong, pemanggil tinggal fallback
     * ke 0/'-' per baris, tidak boleh bikin halaman list gagal render.
     *
     * @param  string[]  $hrisJobOpeningIds
     * @return array<string, int> id => jumlah pelamar
     */
    public function getApplicantCounts(array $hrisJobOpeningIds): array
    {
        if (empty($hrisJobOpeningIds)) {
            return [];
        }

        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return [];
        }

        $url = rtrim($baseUrl, '/') . '/integrations/hris/job-openings/applicant-counts';

        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->post($url, ['ids' => array_values($hrisJobOpeningIds)]);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: getApplicantCounts gagal (response error).', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            // Kontrak: body { "counts": { "{id}": 12, ... } } - dibungkus key "counts" (bukan
            // objek/array telanjang) supaya response bisa ditambah metadata lain belakangan
            // tanpa breaking change.
            return $response->json('counts', []);
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: getApplicantCounts gagal (exception).', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
