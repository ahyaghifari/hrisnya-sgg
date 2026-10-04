<?php

namespace App\Filament\Resources;

use App\Models\Employee\Employee;
use App\Models\Master\Companies;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Closure pencarian/urutan untuk kolom tabel Filament yang menampilkan nama/NIP milik relasi `User`
 * (mis. `TextColumn::make('user.name')`). Kolom `users.name`/`users.nip` sumbernya sekarang
 * employees, jadi pencarian/urutan default Filament (kolom users) diganti subquery employees.
 *
 *   TextColumn::make('user.name')
 *       ->searchable(query: UserColumn::searchName('user'))
 *       ->sortable(query: UserColumn::sortName('user'))
 */
final class UserColumn
{
    public static function searchName(string $relation = 'user'): Closure
    {
        return fn (Builder $query, string $search) => $query->whereHas($relation, fn ($u) => $u->searchName($search));
    }

    public static function searchNip(string $relation = 'user'): Closure
    {
        return fn (Builder $query, string $search) => $query->whereHas($relation, fn ($u) => $u->whereNipLike($search));
    }

    public static function sortName(string $relation = 'user'): Closure
    {
        return fn (Builder $query, string $direction) => static::sortBy($query, $relation, 'nama_lengkap', $direction);
    }

    public static function sortNip(string $relation = 'user'): Closure
    {
        return fn (Builder $query, string $direction) => static::sortBy($query, $relation, 'nip', $direction);
    }

    /** Untuk `TextColumn::make('user.company.name')` (users.company_id -> employees.company_id). */
    public static function searchCompany(string $relation = 'user'): Closure
    {
        return fn (Builder $query, string $search) => $query->whereHas($relation, fn ($u) => $u->whereCompany(
            Companies::query()->where('name', 'like', "%{$search}%")->pluck('id')->all()
        ));
    }

    public static function sortCompany(string $relation = 'user'): Closure
    {
        return function (Builder $query, string $direction) use ($relation) {
            $model = $query->getModel();

            return $query->orderBy(
                Companies::query()->select('name')
                    ->whereIn('m_companies.id', Employee::query()->select('company_id')
                        ->whereColumn('employees.user_id', $model->qualifyColumn($model->{$relation}()->getForeignKeyName())))
                    ->limit(1),
                $direction,
            );
        };
    }

    private static function sortBy(Builder $query, string $relation, string $employeeColumn, string $direction): Builder
    {
        $model = $query->getModel();

        return $query->orderBy(
            Employee::query()->select($employeeColumn)
                ->whereColumn('employees.user_id', $model->qualifyColumn($model->{$relation}()->getForeignKeyName()))
                ->limit(1),
            $direction,
        );
    }
}
