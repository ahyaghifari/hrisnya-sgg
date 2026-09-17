<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {}

    public function login(array $credentials): ?string
    {
        $user = $this->userRepository->findByLogin($credentials['email']);

        if (!$user || !password_verify($credentials['password'], $user->password)) {
        return null;
        }

        if (!$user->is_active) {
            throw new \RuntimeException('account_inactive');
        }

        return $user->createToken('auth_token')->plainTextToken;
    }

    public function  reqResetPassword(string $email): array|bool
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return false;
        }

        $token = Str::uuid();
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $token,
                'created_at' => now()->addMinutes(5)->toDateTimeString()
            ]
        );

        // DEV MODE
        $result = [
            'link' => 'https://example.com/reset-password?token=' . $token,
            'expires_at' => now()->addMinutes(5)->toDateTimeString()
        ];

        return $result;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $record = DB::table('password_reset_tokens')->where('token', $token)->first();

        if (
            !$record ||
            now()->greaterThan(
                \Carbon\Carbon::parse($record->created_at)->addMinutes(1)
            )
        ) {
            return false;
        }

        $user = $this->userRepository->findByEmail($record->email);

        if (!$user) {
            return false;
        }

        $user->password = bcrypt($newPassword);
        $this->userRepository->save($user);

        DB::table('password_reset_tokens')->where('email', $record->email)->delete();

        return true;
    }

    /**
     * Password LOKAL HRIS saja - beda dari password SSO Keycloak, yang diganti terpisah lewat
     * UbahAkunLogin (halaman "Username & Password SSO", instruksi user 2026-09-17: dua akun
     * dipisah jelas, jangan disamakan/disinkron otomatis).
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!password_verify($currentPassword, $user->password)) {
            return false;
        }

        $user->password = bcrypt($newPassword);
        $this->userRepository->save($user);

        return true;
    }

    public function logout($user): void
    {
        $user->tokens()->delete();
    }
}
