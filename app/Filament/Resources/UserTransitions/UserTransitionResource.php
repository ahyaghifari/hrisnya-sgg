<?php

namespace App\Filament\Resources\UserTransitions;

use App\Enum\UserTransitionTypeEnum;
use App\Filament\Clusters\UserTransitions\UserTransitionsCluster;
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
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserTransitionResource extends Resource
{
    protected static ?string $model = UserTransition::class;

    protected static ?string $cluster = UserTransitionsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


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

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Promosi')
                ->url(static::getUrl('index', ['type' => UserTransitionTypeEnum::PROMOTION->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserTransitionTypeEnum::PROMOTION->value),

            NavigationItem::make('Demosi')
                ->url(static::getUrl('index', ['type' => UserTransitionTypeEnum::DEMOTION->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserTransitionTypeEnum::DEMOTION->value),

            NavigationItem::make('Mutasi')
                ->url(static::getUrl('index', ['type' => UserTransitionTypeEnum::MUTATION->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserTransitionTypeEnum::MUTATION->value),

            NavigationItem::make('PHK')
                ->url(static::getUrl('index', ['type' => UserTransitionTypeEnum::TERMINATION->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserTransitionTypeEnum::TERMINATION->value),

            NavigationItem::make('Perubahan Kelas')
                ->url(static::getUrl('index', ['type' => UserTransitionTypeEnum::KELAS_CHANGE->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserTransitionTypeEnum::KELAS_CHANGE->value),

            NavigationItem::make('Perubahan Status Kepegawaian')
                ->url(static::getUrl('index', ['type' => UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserTransitionTypeEnum::EMPLOYMENT_STATUS_CHANGE->value),

            NavigationItem::make('Perpanjangan Kontrak')
                ->url(static::getUrl('index', ['type' => UserTransitionTypeEnum::CONTRACT_EXTENSION->value]))
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->get('type') === UserTransitionTypeEnum::CONTRACT_EXTENSION->value),
        ];
    }
}
