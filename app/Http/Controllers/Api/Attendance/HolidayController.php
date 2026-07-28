<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Master\Attendance\Holidays;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Holidays::query()
                ->orderBy('date')
                ->orderBy('name');

            if ($request->filled('from')) {
                $query->whereDate('date', '>=', $request->query('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('date', '<=', $request->query('to'));
            }

            $holidays = $query->get()->map(function (Holidays $holiday) {
                return [
                    'id' => $holiday->id,
                    'date' => $holiday->date?->toDateString(),
                    'name' => $holiday->name,
                    'is_collective_leave' => (bool) $holiday->is_collective_leave,
                ];
            });

            return ApiResponseHelper::success($holidays, 'Successfully retrieved holidays and collective leaves.');
        } catch (\Throwable $e) {
            return ApiResponseHelper::error('Failed to retrieve holidays and collective leaves.', 500, $e->getMessage());
        }
    }

    public function showIndependenceDay()
    {
        try {
            $holiday = Holidays::query()
                ->where('name', 'like', '%Kemerdekaan%')
                ->orWhere('date', '2026-08-17')
                ->first();

            if (! $holiday) {
                return ApiResponseHelper::notFound('Hari Kemerdekaan not found.');
            }

            return ApiResponseHelper::success([
                'id' => $holiday->id,
                'date' => $holiday->date?->toDateString(),
                'name' => $holiday->name,
                'is_collective_leave' => (bool) $holiday->is_collective_leave,
            ], 'Successfully retrieved Hari Kemerdekaan.');
        } catch (\Throwable $e) {
            return ApiResponseHelper::error('Failed to retrieve Hari Kemerdekaan.', 500, $e->getMessage());
        }
    }
}
