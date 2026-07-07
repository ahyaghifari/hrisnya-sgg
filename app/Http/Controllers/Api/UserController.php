<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\User\UserService;
use Illuminate\Http\Request;

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
            return ApiResponseHelper::error('An error occurred while retrieving user.', 500, ['error' => $e->getMessage()]);
        }
    }
}
