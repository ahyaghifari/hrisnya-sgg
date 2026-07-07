<?php

namespace App\Filament\Resources\Master\Religions;

use App\Filament\Resources\Master\Religions\Pages\CreateReligions;
use App\Filament\Resources\Master\Religions\Pages\EditReligions;
use App\Filament\Resources\Master\Religions\Pages\ListReligions;
use App\Filament\Resources\Master\Religions\Schemas\ReligionsForm;
use App\Filament\Resources\Master\Religions\Tables\ReligionsTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\Religions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ReligionsResource extends Resource
{
    protected static ?string $model = Religions::class;

    protected static ?string $navigationLabel = "Agama";

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    public static function form(Schema $schema): Schema
    {
        return ReligionsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReligionsTable::configure($table);
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
            'index' => ListReligions::route('/'),
            // 'create' => CreateReligions::route('/create'),
            // 'edit' => EditReligions::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_master_data_agama') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return Auth::user()?->can('view_master_data_agama') ?? false;
    }
    
    public static function canCreate(): bool
    {
        return Auth::user()?->can('create_master_data_agama') ?? false;
    }
    
    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('edit_master_data_agama') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('delete_master_data_agama') ?? false;
    }
}
