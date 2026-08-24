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

    /** Boleh pilih/lihat/filter company LAIN (bukan cuma company sendiri) di admin panel -
     * dulu cuma Super Admin literal, sekarang role apa pun yang dikasih permission
     * LIHAT_PERUSAHAAN_LAIN juga bisa (padanan User::canFilterByCompany() di portal). Dipakai
     * KHUSUS buat gerbang "pilih/lihat company lain" (field/kolom/filter Perusahaan,
     * getEloquentQuery scoping) - BUKAN pengganti isSuperAdmin() di tempat yang mengelola data
     * sensitif seperti CRUD record Companies itu sendiri (CompaniesResource::canCreate/
     * canDelete tetap Super-Admin-only). */
    public static function canBrowseAllCompanies(): bool
    {
        return static::isSuperAdmin()
            || static::user()->can(\App\Enum\CustomPermissionEnum::LIHAT_PERUSAHAAN_LAIN->value);
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
