<?php

namespace App\Filament\Resources\AdminUsers;

use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\Resources\AdminUsers\Schemas\AdminUserForm;
use App\Filament\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Bikin akun panel /admin (role "Admin") untuk 1 company — beda dari EmployeeResource yang
 * ngurus data karyawan. Sengaja dikunci cuma Super Admin (hardcode hasRole(), bukan lewat
 * permission Shield biasa) — supaya nggak bisa kebuka gara-gara role lain kebagian permission
 * ini secara nggak sengaja lewat halaman Roles nanti.
 */
class AdminUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Admin';

    protected static ?string $modelLabel = 'Admin';

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Pengguna';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', 'Admin'));
    }

    private static function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->hasRole('Super Admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::isSuperAdmin();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::isSuperAdmin();
    }
}
