<?php

namespace App\Filament\Resources\LMS\LmsCourses;

use App\Filament\Resources\LMS\LmsCourses\Pages\CreateLmsCourses;
use App\Filament\Resources\LMS\LmsCourses\Pages\DetailParticipantCourses;
use App\Filament\Resources\LMS\LmsCourses\Pages\EditLmsCourses;
use App\Filament\Resources\LMS\LmsCourses\Pages\ListLmsCourses;
use App\Filament\Resources\LMS\LmsCourses\Pages\ParticipantCourses;
use App\Filament\Resources\LMS\LmsCourses\Pages\ViewLsmCourses;
use App\Filament\Resources\LMS\LmsCourses\Pages\ViewParticipantCourses;
use App\Filament\Resources\LMS\LmsCourses\RelationManagers\MeetingsRelationManager;
use App\Filament\Resources\LMS\LmsCourses\Schemas\LmsCoursesForm;
use App\Filament\Resources\LMS\LmsCourses\Schemas\LmsCoursesInfolist;
use App\Filament\Resources\LMS\LmsCourses\Tables\LmsCoursesTable;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCourses;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LmsCoursesResource extends Resource
{
    protected static ?string $model = LmsCourses::class;

    protected static ?string $navigationLabel = "Pelatihan";

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    public static function form(Schema $schema): Schema
    {
        return LmsCoursesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LmsCoursesTable::configure($table);
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
            'index' => ListLmsCourses::route('/'),
            'create' => CreateLmsCourses::route('/create'),
            'edit' => EditLmsCourses::route('/{record}/edit'),
            'participants' => ParticipantCourses::route('/{record}/participants'),
            'detail-participant' => DetailParticipantCourses::route('/{record}/participant/{user}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['category', 'company', 'meetings']);

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_lms') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return Auth::user()?->can('view_lms') ?? false;
    }
    
    public static function canCreate(): bool
    {
        return Auth::user()?->can('create_lms') ?? false;
    }
    
    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('edit_lms') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('delete_lms') ?? false;
    }
}
