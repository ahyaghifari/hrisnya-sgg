<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SopController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            ['name' => 'SOP PENGGUNAAN KOMPUTER', 'category' => 'IT'],
            ['name' => 'SOP Downtime Tidak Terencana', 'category' => 'IT'],
            ['name' => 'SOP Downtime Terencana', 'category' => 'IT'],
            ['name' => 'SOP Backup & Restore database', 'category' => 'IT'],
        ]);
    }
}
