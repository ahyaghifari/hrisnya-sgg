<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->only('email', 'password');
            
            $token = $this->authService->login($credentials);
    
            if (!$token) {
                return ApiResponseHelper::error('Email or password is incorrect', 401);
            }
    
            return ApiResponseHelper::success(['token' => $token], 'Login successful');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred during login', 500, $e->getMessage());
        }
    }

    public function requestPasswordReset(Request $request)
    {
        try {
            $email = $request->input('email');
    
            $success = $this->authService->reqResetPassword($email);
    
            if (!$success) {
                return ApiResponseHelper::error('Email not found', 404);
            }
    
            // DEV MODE
            return ApiResponseHelper::success($success, 'Password reset link has been sent if the email exists (Dev Mode)');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred during password reset', 500, $e->getMessage());
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'password' => 'required|min:8|confirmed',
            ]);

            $data = $request->only('token', 'password');

            $success = $this->authService->resetPassword($data['token'], $data['password']);
    
            if (!$success) {
                return ApiResponseHelper::error('Invalid or expired token', 400);
            }
    
            return ApiResponseHelper::success(null, 'Password has been changed successfully');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred during password change', 500, $e->getMessage());
        }
    }

    public function logout()
    {
        try {
            $user = auth('sanctum')->user();
            $this->authService->logout($user);

            return ApiResponseHelper::success(null, 'Logged out successfully');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred during logout', 500, $e->getMessage());
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:8|confirmed',
            ]);

            $data = $request->only('current_password', 'new_password');

            $success = $this->authService->changePassword(auth('sanctum')->user(), $data['current_password'], $data['new_password']);
    
            if (!$success) {
                return ApiResponseHelper::error('Current password is incorrect', 400);
            }
    
            return ApiResponseHelper::success(null, 'Password has been changed successfully');
        } catch (\Exception $e) {
            return ApiResponseHelper::error('An error occurred during password change', 500, $e->getMessage());
        }
    }
}
