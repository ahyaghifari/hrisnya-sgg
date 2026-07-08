<?php

namespace App\Filament\Resources\Master\Banks;

use App\Filament\Resources\Master\Banks\Pages\CreateBanks;
use App\Filament\Resources\Master\Banks\Pages\EditBanks;
use App\Filament\Resources\Master\Banks\Pages\ListBanks;
use App\Filament\Resources\Master\Banks\Schemas\BanksForm;
use App\Filament\Resources\Master\Banks\Tables\BanksTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\Banks;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BanksResource extends Resource
{
    protected static ?string $model = Banks::class;

    protected static ?string $navigationLabel = "Bank";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function form(Schema $schema): Schema
    {
        return BanksForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BanksTable::configure($table);
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
            'index' => ListBanks::route('/'),
            // 'create' => CreateBanks::route('/create'),
            // 'edit' => EditBanks::route('/{record}/edit'),
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
}
