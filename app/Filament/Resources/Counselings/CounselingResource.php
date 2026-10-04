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
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CounselingResource extends Resource
{
    protected static ?string $model = Counseling::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Pembinaan';

    protected static ?int $navigationSort = 2;

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
            $query->whereHas('user', fn ($q) => $q->whereCompany($user->company_id));
        }

        return $query;
    }
}
