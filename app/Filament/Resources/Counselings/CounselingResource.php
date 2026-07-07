<?php

namespace App\Filament\Resources\Counselings;

use App\Filament\Resources\Counselings\Pages\CreateCounseling;
use App\Filament\Resources\Counselings\Pages\EditCounseling;
use App\Filament\Resources\Counselings\Pages\ListCounselings;
use App\Filament\Resources\Counselings\Schemas\CounselingForm;
use App\Filament\Resources\Counselings\Tables\CounselingsTable;
use App\Helpers\PermissionHelper;
use App\Models\Counseling\Counseling;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CounselingResource extends Resource
{
    protected static ?string $model = Counseling::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return CounselingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CounselingsTable::configure($table);
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
            'index' => ListCounselings::route('/'),
            //'create' => CreateCounseling::route('/create'),
            //'edit' => EditCounseling::route('/{record}/edit'),
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

    public static function canAccess(): bool
    {
        return Gate::allows('view_counseling');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_counseling');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_counseling');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_counseling');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_counseling');
    }
}
