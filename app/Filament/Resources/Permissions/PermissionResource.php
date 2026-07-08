<?php

namespace App\Filament\Resources\Permissions;

use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\EditPermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Permissions\Schemas\PermissionForm;
use App\Filament\Resources\Permissions\Tables\PermissionsTable;
use App\Models\Permission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationLabel = 'Permission';

    protected static ?string $modelLabel = 'Permission';

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Pengguna';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table);
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
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }

    /**
     * Model Permission punya global scope company (lihat app/Models/Permission.php) yang
     * otomatis filter where('company_id', $user->company_id) — tapi katalog permission di
     * project ini semuanya global (company_id selalu null, dibuat lewat PermissionsSeeder
     * tanpa company_id). Kalau scope itu dibiarkan aktif, admin non-global tidak akan lihat
     * satu permission pun di sini (company_id null tidak pernah cocok dengan where = value).
     * Jadi di-bypass khusus buat resource ini.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope('company');
    }
}
