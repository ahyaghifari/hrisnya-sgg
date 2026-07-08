<?php

namespace App\Filament\Resources\Master\Pangkats;

use App\Filament\Resources\Master\Pangkats\Pages\CreatePangkats;
use App\Filament\Resources\Master\Pangkats\Pages\EditPangkats;
use App\Filament\Resources\Master\Pangkats\Pages\ListPangkats;
use App\Filament\Resources\Master\Pangkats\Schemas\PangkatsForm;
use App\Filament\Resources\Master\Pangkats\Tables\PangkatsTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\Pangkats;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PangkatsResource extends Resource
{
    protected static ?string $model = Pangkats::class;

    protected static ?string $navigationLabel = "Pangkat";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare2Stack;

    public static function form(Schema $schema): Schema
    {
        return PangkatsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PangkatsTable::configure($table);
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
            'index' => ListPangkats::route('/'),
            // 'create' => CreatePangkats::route('/create'),
            // 'edit' => EditPangkats::route('/{record}/edit'),
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
