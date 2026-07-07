<?php

namespace App\Services\User;

use App\Repositories\User\UserRepository;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {}

    public function findUserById($id)
    {
        return $this->userRepository->findById($id);
    }

    public function updateUserProfile($user, array $data)
    {
        return $this->userRepository->updateProfileUser($user, $data);
    }
}
