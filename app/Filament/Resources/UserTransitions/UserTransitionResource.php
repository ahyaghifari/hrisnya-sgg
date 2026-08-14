<?php

namespace App\Filament\Resources\UserTransitions;

use App\Filament\Resources\UserTransitions\Pages\CreateUserTransition;
use App\Filament\Resources\UserTransitions\Pages\EditUserTransition;
use App\Filament\Resources\UserTransitions\Pages\ListUserTransitions;
use App\Filament\Resources\UserTransitions\Pages\ViewUserTransition;
use App\Filament\Resources\UserTransitions\Schemas\UserTransitionForm;
use App\Filament\Resources\UserTransitions\Schemas\UserTransitionInfolist;
use App\Filament\Resources\UserTransitions\Tables\UserTransitionsTable;
use App\Helpers\PermissionHelper;
use App\Models\User\UserTransition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserTransitionResource extends Resource
{
    protected static ?string $model = UserTransition::class;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Perubahan Status Karyawan';

    protected static ?string $modelLabel = 'Perubahan Status Karyawan';

    protected static ?string $pluralModelLabel = 'Perubahan Status Karyawan';


    public static function form(Schema $schema): Schema
    {
        return UserTransitionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserTransitionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserTransitionsTable::configure($table);
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
            'index' => ListUserTransitions::route('/'),
            'create' => CreateUserTransition::route('/create'),
            //'view' => ViewUserTransition::route('/{record}'),
            'edit' => EditUserTransition::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest();

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
