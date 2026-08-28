<?php

namespace App\Services\Job;

use App\Models\Job\JobOpenings;
use App\Models\Master\Companies;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RekrutmenSyncService
{
    /**
     * Push data perusahaan HRIS ke Rekrutmen (upsert by code) supaya tidak perlu dibuat
     * manual dulu di admin panel Rekrutmen sebelum lowongan bisa di-push. Non-fatal - sama
     * pola sync() lowongan, gagal cuma dicatat log.
     *
     * @return array{ok: bool, message: string}
     */
    public function pushCompany(Companies $company): array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return ['ok' => false, 'message' => 'REKRUTMEN_API_URL/REKRUTMEN_API_TOKEN belum diisi di .env.'];
        }

        $url = rtrim($baseUrl, '/') . '/integrations/hris/companies';

        try {
            $response = Http::withToken($token)->timeout(5)->post($url, [
                'hris_code' => $company->code,
                'name' => $company->name,
                'is_active' => (bool) $company->is_active,
            ]);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: pushCompany gagal (response error).', [
                    'company_id' => $company->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['ok' => false, 'message' => "Rekrutmen balas error (HTTP {$response->status()})."];
            }

            return ['ok' => true, 'message' => "Perusahaan dikirim ke {$url} (HTTP {$response->status()})."];
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: pushCompany gagal (exception).', [
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => "Tidak bisa menghubungi {$url}: " . $e->getMessage()];
        }
    }

    /**
     * Daftar lamaran per lowongan dari Rekrutmen (progres tiap kandidat, bukan cuma jumlah).
     * Filter opsional per status. Non-fatal - gagal balikin array kosong.
     *
     * @return list<array{job_application_id:string,status:string,applicant_name:?string,applicant_email:?string,updated_at:?string}>
     */
    public function getJobOpeningApplications(string $hrisJobOpeningId, ?string $status = null): array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return [];
        }

        $url = rtrim($baseUrl, '/') . '/integrations/hris/job-openings/' . $hrisJobOpeningId . '/applications';

        try {
            $response = Http::withToken($token)->timeout(5)->get($url, $status ? ['status' => $status] : []);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: getJobOpeningApplications gagal (response error).', [
                    'hris_job_opening_id' => $hrisJobOpeningId,
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $response->json('data', []);
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: getJobOpeningApplications gagal (exception).', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Info dasar 1 lamaran + riwayat status dari Rekrutmen — selalu ada (beda dari getCandidate
     * yang cuma tersedia setelah onboarding/passed). Non-fatal - gagal balikin null.
     *
     * @return array{job_application_id:string,status:string,applicant_name:?string,applicant_email:?string,job_opening:array,has_candidate_data:bool,status_logs:list<array>}|null
     */
    public function getApplication(string $jobApplicationId): ?array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return null;
        }

        $url = rtrim($baseUrl, '/') . '/integrations/hris/applications/' . $jobApplicationId;

        try {
            $response = Http::withToken($token)->timeout(10)->get($url);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: getApplication gagal.', [
                    'job_application_id' => $jobApplicationId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: getApplication gagal (exception).', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Tarik payload lengkap data diri kandidat (candidate + 8 tabel anak) dari Rekrutmen.
     * Cuma tersedia setelah kandidat onboarding/passed - selain itu Rekrutmen balas 409 dan
     * method ini balikin null.
     *
     * @return array{job_application: array, candidate: array}|null
     */
    public function getCandidate(string $jobApplicationId): ?array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return null;
        }

        $url = rtrim($baseUrl, '/') . '/integrations/hris/candidates/' . $jobApplicationId;

        try {
            $response = Http::withToken($token)->timeout(10)->get($url);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: getCandidate gagal.', [
                    'job_application_id' => $jobApplicationId,
                    'status' => $response->status(),
                    'body' => \Illuminate\Support\Str::limit($response->body(), 200),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: getCandidate gagal (exception).', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Gerakkan status lamaran di Rekrutmen (`PATCH .../applications/{id}/status`). Rekrutmen
     * validasi transisi (whitelist HRIS_TRANSITIONS di sana) & jalankan efek samping
     * (status log, bikin candidate, email pelamar). BEDA dari method lain di service ini:
     * ini bukan non-fatal read - hasilnya menentukan apakah aksi HRIS berhasil, jadi
     * pemanggil WAJIB cek `ok` dan tampilkan `message` ke user.
     *
     * @return array{ok:bool, message:string, candidate_created?:bool}
     */
    public function updateApplicationStatus(string $jobApplicationId, string $status): array
    {
        $result = $this->writeAction(
            'patch',
            '/integrations/hris/applications/' . $jobApplicationId . '/status',
            ['status' => $status],
            "Status di Rekrutmen diperbarui ke '{$status}'.",
        );

        if ($result['ok']) {
            $result['candidate_created'] = (bool) ($result['data']['candidate_created'] ?? false);
        }

        return $result;
    }

    /**
     * Jadwalkan / ubah jadwal wawancara di Rekrutmen (`POST .../interview`) → status `interview`.
     *
     * @param  array{interview_date:string, interview_time?:?string, location?:?string, notes?:?string}  $data
     * @return array{ok:bool, message:string, data?:mixed}
     */
    public function scheduleInterview(string $jobApplicationId, array $data): array
    {
        return $this->writeAction(
            'post',
            '/integrations/hris/applications/' . $jobApplicationId . '/interview',
            $data,
            'Jadwal wawancara dikirim ke Rekrutmen.',
        );
    }

    /**
     * Catat hasil wawancara di Rekrutmen (`POST .../interview-result`) → `offering`/`rejected`.
     *
     * @return array{ok:bool, message:string, data?:mixed}
     */
    public function submitInterviewResult(string $jobApplicationId, string $result): array
    {
        return $this->writeAction(
            'post',
            '/integrations/hris/applications/' . $jobApplicationId . '/interview-result',
            ['result' => $result],
            'Hasil wawancara dikirim ke Rekrutmen.',
        );
    }

    /**
     * Upload surat penawaran (recruiter) ke Rekrutmen — multipart. BUKAN non-fatal.
     * `$file` = TemporaryUploadedFile Livewire (baca isinya lewat ->get(), bukan path filesystem).
     *
     * @return array{ok:bool, message:string}
     */
    public function uploadOfferingLetter(string $jobApplicationId, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return ['ok' => false, 'message' => 'REKRUTMEN_API_URL/REKRUTMEN_API_TOKEN belum diisi di .env.'];
        }

        $url = rtrim($baseUrl, '/') . '/integrations/hris/applications/' . $jobApplicationId . '/offering-letter';

        try {
            // TemporaryUploadedFile: baca isi lewat ->get() (disk Livewire), fallback ke
            // path filesystem kalau ->get() kosong.
            $contents = $file->get() ?: file_get_contents($file->getRealPath());

            $response = Http::withToken($token)
                ->timeout(20)
                ->attach('file', $contents, $file->getClientOriginalName())
                ->post($url);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: uploadOfferingLetter gagal.', [
                    'path' => $url, 'http' => $response->status(),
                    'body' => \Illuminate\Support\Str::limit($response->body(), 300),
                ]);

                return ['ok' => false, 'message' => $response->json('message') ?? "Rekrutmen balas error (HTTP {$response->status()})."];
            }

            return ['ok' => true, 'message' => 'Surat penawaran diunggah ke Rekrutmen.'];
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: uploadOfferingLetter gagal (exception).', ['message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Rekrutmen: ' . $e->getMessage()];
        }
    }

    /**
     * Lanjut setelah offering (`POST .../offering-letter/advance`) → status `mcu`/`onboarding`.
     *
     * @return array{ok:bool, message:string, data?:mixed}
     */
    public function advanceFromOffering(string $jobApplicationId): array
    {
        return $this->writeAction(
            'post',
            '/integrations/hris/applications/' . $jobApplicationId . '/offering-letter/advance',
            [],
            'Kandidat dilanjutkan ke tahap berikutnya.',
        );
    }

    /** URL endpoint surat penawaran di Rekrutmen (butuh token — dipanggil server-side proxy). */
    public function offeringLetterUrl(string $jobApplicationId, string $type): ?string
    {
        $baseUrl = config('services.rekrutmen.base_url');

        if (! $baseUrl) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/integrations/hris/applications/' . $jobApplicationId . '/offering-letter/' . $type;
    }

    /**
     * Aksi tulis ke Rekrutmen (PATCH/POST). BUKAN non-fatal — hasilnya menentukan apakah
     * aksi HRIS berhasil, pemanggil WAJIB cek `ok` + tampilkan `message`.
     *
     * @return array{ok:bool, message:string, data?:mixed}
     */
    private function writeAction(string $method, string $path, array $body, string $okMessage): array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return ['ok' => false, 'message' => 'REKRUTMEN_API_URL/REKRUTMEN_API_TOKEN belum diisi di .env.'];
        }

        $url = rtrim($baseUrl, '/') . $path;

        try {
            $response = Http::withToken($token)->timeout(10)->{$method}($url, $body);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: writeAction gagal.', [
                    'path' => $path,
                    'http' => $response->status(),
                    'body' => \Illuminate\Support\Str::limit($response->body(), 300),
                ]);

                return ['ok' => false, 'message' => $response->json('message') ?? "Rekrutmen balas error (HTTP {$response->status()})."];
            }

            return ['ok' => true, 'message' => $okMessage, 'data' => $response->json()];
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: writeAction gagal (exception).', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Rekrutmen: ' . $e->getMessage()];
        }
    }

    /** URL endpoint dokumen kandidat di Rekrutmen (butuh Bearer token — dipanggil server-side
     * oleh proxy HRIS, BUKAN ditaruh langsung di <a href> browser). Fase 2 bikin route HRIS
     * yang stream ulang hasilnya. Null kalau config kosong. */
    public function candidateDocumentUrl(string $jobApplicationId, string $documentId): ?string
    {
        $baseUrl = config('services.rekrutmen.base_url');

        if (! $baseUrl) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/integrations/hris/candidates/' . $jobApplicationId . '/documents/' . $documentId;
    }

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
     * Ambil lowongan di sistem Rekrutmen yang BELUM terhubung ke HRIS (dibuat manual di sana,
     * `hris_job_opening_id` masih null). Dipakai JobOpeningsPage buat kasih tahu admin HRIS ada
     * lowongan di luar yang belum tercatat di sini. Non-fatal - gagal/endpoint belum ada balikin
     * array kosong, halaman list tetap render.
     *
     * @return list<array{id:string,title:?string,company_name:?string,hris_company_code:?string,quota:?int,max_applicants:?int,applications_deadline:?string,is_active:bool,created_at:?string}>
     */
    public function getUnlinkedJobOpenings(): array
    {
        $baseUrl = config('services.rekrutmen.base_url');
        $token = config('services.rekrutmen.token');

        if (! $baseUrl || ! $token) {
            return [];
        }

        $url = rtrim($baseUrl, '/') . '/integrations/hris/job-openings/unlinked';

        try {
            $response = Http::withToken($token)->timeout(5)->get($url);

            if ($response->failed()) {
                Log::warning('RekrutmenSyncService: getUnlinkedJobOpenings gagal (response error).', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return $response->json('data', []);
        } catch (\Throwable $e) {
            Log::warning('RekrutmenSyncService: getUnlinkedJobOpenings gagal (exception).', [
                'message' => $e->getMessage(),
            ]);

            return [];
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
