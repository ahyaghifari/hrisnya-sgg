<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SopController extends Controller
{
    private const SOPS = [
        'k3j9x2p1' => ['name' => 'SOP PENGGUNAAN KOMPUTER', 'category' => 'IT', 'file' => 'sop-penggunaan-komputer.txt'],
        'q7m4w8v2' => ['name' => 'SOP Downtime Tidak Terencana', 'category' => 'IT', 'file' => 'sop-downtime-tidak-terencana.txt'],
        'r5t1n6y9' => ['name' => 'SOP Downtime Terencana', 'category' => 'IT', 'file' => 'sop-downtime-terencana.txt'],
        'z2b8h4c3' => ['name' => 'SOP Backup & Restore database', 'category' => 'IT', 'file' => 'sop-backup-restore-database.txt'],
    ];

    public function index(): JsonResponse
    {
        $sops = collect(self::SOPS)->map(fn ($sop, $id) => [
            'id' => $id,
            'name' => $sop['name'],
            'category' => $sop['category'],
        ])->values();

        return response()->json($sops);
    }

    public function download(string $id): StreamedResponse|Response
    {
        $sop = self::SOPS[$id] ?? null;

        if (! $sop) {
            return response()->noContent(404);
        }

        return Storage::disk('local')->download('sop/'.$sop['file'], $sop['file']);
    }
}
