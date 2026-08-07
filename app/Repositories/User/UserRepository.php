<?php

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;


class UserRepository
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function save(User $user): User
    {
        $user->save();
        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function findById(string $id): ?User
    {
        return User::with('company', 'profile.orgStructure')->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByLogin(string $login): ?User
    {
        return filter_var($login, FILTER_VALIDATE_EMAIL)
            ? $this->findByEmail($login)
            : User::where('nip', $login)->first();
    }

    public function getReviewers($user): Collection
    {
        $department = $user->profile?->department;

        if (!$department) {
            return collect();
        }

        return User::whereHas('profile', function ($query) use ($department) {
            $query->where('org_structure_id', $department->id)
                ->orWhereHas('orgStructure', function ($q) use ($department) {
                    $q->where('parent_id', $department->id)
                        ->orWhere('id', $department->id);
                });
        })
            ->where('id', '!=', $user->id)
            ->get();
    }

    public function updateProfileUser(User $user, array $data): User
    {
        $user->profile()->update($data);
        return $user->refresh();
    }

    public function createUserProfile(User $user, array $data): User
    {
        $user->profile()->create($data);
        return $user->refresh();
    }

    public function createUserPersonalDocuments(User $user, array $data): User
    {
        $user->personalDocuments()->createMany($data);
        return $user->refresh();
    }
}
