<?php

namespace App\Http\Controllers\Api\Coaching;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\UpdateCoachingRequest;
use App\Http\Resources\Coaching\CoachingDetailResource;
use App\Http\Resources\Coaching\CoachingListResource;
use App\Http\Resources\Coaching\CoachingProofResource;
use App\Services\Coaching\CoachingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachingController extends Controller
{
    public function __construct(
        protected CoachingService $coachingService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $data = $this->coachingService->getList();
            return ApiResponseHelper::success(
                CoachingListResource::collection($data),
                'Coaching list retrieved.'
            );
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Failed to load coachings.', 500, $e->getMessage());
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $data = $this->coachingService->getDetail($id);
            return ApiResponseHelper::success(
                new CoachingDetailResource($data),
                'Coaching detail retrieved.'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseHelper::notFound('Coaching not found.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Failed to load detail.', 500, $e->getMessage());
        }
    }

    public function update(UpdateCoachingRequest $request, string $id): JsonResponse
    {
        try {
            $coaching = $this->coachingService->update($id, $request);

            return ApiResponseHelper::success(
                new CoachingDetailResource($coaching),
                'Proof uploaded successfully.'
            );
        } catch (ModelNotFoundException) {
            return ApiResponseHelper::notFound('Coaching not found.');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('Failed to upload proof.', 500, $e->getMessage());
        }
    }
}
