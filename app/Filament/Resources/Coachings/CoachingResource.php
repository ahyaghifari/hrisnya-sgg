<?php

namespace App\Filament\Resources\Coachings;

use App\Filament\Resources\Coachings\Pages\CreateCoaching;
use App\Filament\Resources\Coachings\Pages\EditCoaching;
use App\Filament\Resources\Coachings\Pages\ListCoachings;
use App\Filament\Resources\Coachings\Schemas\CoachingForm;
use App\Filament\Resources\Coachings\Tables\CoachingsTable;
use App\Helpers\PermissionHelper;
use App\Models\Coaching\Coaching;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CoachingResource extends Resource
{
    protected static ?string $model = Coaching::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return CoachingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoachingsTable::configure($table);
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
            'index' => ListCoachings::route('/'),
            'create' => CreateCoaching::route('/create'),
            'edit' => EditCoaching::route('/{record}/edit'),
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
