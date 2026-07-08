<?php

namespace App\Filament\Resources\Master\Kelas;

use App\Filament\Resources\Master\Kelas\Pages\CreateKelas;
use App\Filament\Resources\Master\Kelas\Pages\EditKelas;
use App\Filament\Resources\Master\Kelas\Pages\ListKelas;
use App\Filament\Resources\Master\Kelas\Schemas\KelasForm;
use App\Filament\Resources\Master\Kelas\Tables\KelasTable;
use App\Helpers\PermissionHelper;
use App\Models\Master\Kelas;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class KelasResource extends Resource
{
    protected static ?string $model = Kelas::class;

    protected static ?string $navigationLabel = "Kelas";

    protected static string | UnitEnum | null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return KelasForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KelasTable::configure($table);
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
            'index' => ListKelas::route('/'),
            // 'create' => CreateKelas::route('/create'),
            // 'edit' => EditKelas::route('/{record}/edit'),
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
