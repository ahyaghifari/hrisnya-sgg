<?php

namespace App\Filament\Resources\Master\Golongans;

use App\Filament\Resources\Master\Golongans\Pages\CreateGolongans;
use App\Filament\Resources\Master\Golongans\Pages\EditGolongans;
use App\Filament\Resources\Master\Golongans\Pages\ListGolongans;
use App\Filament\Resources\Master\Golongans\Schemas\GolongansForm;
use App\Filament\Resources\Master\Golongans\Tables\GolongansTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\Golongans;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GolongansResource extends Resource
{
    protected static ?string $model = Golongans::class;

    protected static ?string $navigationLabel = "Golongan";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    public static function form(Schema $schema): Schema
    {
        return GolongansForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GolongansTable::configure($table);
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
            'index' => ListGolongans::route('/'),
            // 'create' => CreateGolongans::route('/create'),
            // 'edit' => EditGolongans::route('/{record}/edit'),
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
