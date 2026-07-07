<?php

namespace App\Http\Controllers\Api\Counseling;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Counseling\StoreCounselingRequest;
use App\Http\Resources\Counseling\CounselingDetailResource;
use App\Http\Resources\Counseling\CounselingListResource;
use App\Services\Counseling\CounselingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounselingController extends Controller
{
    public function __construct(
        protected CounselingService $counselingService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $data = $this->counselingService->getList();
            return ApiResponseHelper::success(
                CounselingListResource::collection($data),
                'Counselings retrieved successfully.'
            );
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Failed retrieving counseling list.', 500, $e->getMessage());
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $data = $this->counselingService->getDetail($id);
            return ApiResponseHelper::success(
                new CounselingDetailResource($data),
                'Counseling detail retrieved successfully.'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseHelper::notFound('Counseling not found.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Failed retrieving counseling detail.', 500, $e->getMessage());
        }
    }

    public function store(StoreCounselingRequest $request): JsonResponse
    {
        try {
            $data = $this->counselingService->create($request);
            return ApiResponseHelper::success(
                new CounselingDetailResource($data),
                'Counseling created successfully.'
            );
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Failed to create counseling.', 500, $e->getMessage());
        }
    }
}
