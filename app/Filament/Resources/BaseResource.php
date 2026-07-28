<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    public static function user(): User
    {
        /** @var User $user */
        $user = filament()->auth()->user();

        return $user;
    }

    public static function isSuperAdmin(): bool
    {
        return static::user()->hasRole('Super Admin');
    }

    public static function companyId(): ?string
    {
        return static::user()->company_id;
    }

    public static function canAccess(): bool
    {
        return static::user()->hasAnyRole(['Super Admin', 'Admin']);
    }
}
