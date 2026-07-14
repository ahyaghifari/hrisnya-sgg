<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function show()
    {
        try {
            $user = $this->userService->findUserById(auth('sanctum')->id());
            
            if (!$user) {
                return ApiResponseHelper::notFound('User not found');
            }
    
            return ApiResponseHelper::success(new UserResource($user), 'User retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user via API', [
                'user_id' => auth('sanctum')->id(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return ApiResponseHelper::error('An error occurred while retrieving user.', 500, ['error' => $e->getMessage()]);
        }
    }
}
