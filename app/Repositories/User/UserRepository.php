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
        return User::with('company', 'profile.jobPosition.orgStructure')->find($id);
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
            $query->whereHas('jobPosition.orgStructure', function ($q) use ($department) {
                $q->where('m_org_structures.parent_id', $department->id)
                    ->orWhere('m_org_structures.id', $department->id);
            });
        })
            ->where('id', '!=', $user->id)
            ->get();
    }

    public function updateProfileUser(User $user, array $data): User
    {
        $user->profile()->update($data);
        // Update massal lewat relasi TIDAK memicu event model, jadi salin ke persons manual
        // (perilaku simpan profil tetap persis seperti semula).
        app(\App\Services\Identity\PersonSyncService::class)->syncUserIdQuietly($user->id);
        app(\App\Services\Identity\EmployeeSyncService::class)->syncUserIdQuietly($user->id);
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
