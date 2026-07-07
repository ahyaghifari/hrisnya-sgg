<?php

namespace App\Http\Controllers\Api\JobOpening;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobOpening\JobOpeningDetailResource;
use App\Http\Resources\JobOpening\JobOpeningListResource;
use App\Services\JobOpening\JobOpeningService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobOpeningController extends Controller
{
    public function __construct(
        protected JobOpeningService $jobOpeningService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);

            $data = $this->jobOpeningService->getList($perPage);

            return ApiResponseHelper::successPaginated(
                JobOpeningListResource::collection($data),
                'Job list retrieved.'
            );
        } catch (\Exception $e) {
            return ApiResponseHelper::error(
                'Failed to load Job.',
                500,
                $e->getMessage()
            );
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $data = $this->jobOpeningService->getDetail($id);
            return ApiResponseHelper::success(
                new JobOpeningDetailResource($data),
                'Job detail retrieved.'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseHelper::notFound('Job not found.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Failed to load detail.', 500, $e->getMessage());
        }
    }
}
