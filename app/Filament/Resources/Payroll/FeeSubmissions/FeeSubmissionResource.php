<?php

namespace App\Filament\Resources\Payroll\FeeSubmissions;

use App\Filament\Resources\Payroll\FeeSubmissions\Pages\CreateFeeSubmission;
use App\Filament\Resources\Payroll\FeeSubmissions\Pages\EditFeeSubmission;
use App\Filament\Resources\Payroll\FeeSubmissions\Pages\ListFeeSubmissions;
use App\Filament\Resources\Payroll\FeeSubmissions\Pages\ViewFeeSubmission;
use App\Filament\Resources\Payroll\FeeSubmissions\Schemas\FeeSubmissionForm;
use App\Filament\Resources\Payroll\FeeSubmissions\Schemas\FeeSubmissionInfolist;
use App\Filament\Resources\Payroll\FeeSubmissions\Tables\FeeSubmissionsTable;
use App\Helpers\PermissionHelper;
use App\Models\Payroll\FeeSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FeeSubmissionResource extends Resource
{
    protected static ?string $model = FeeSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Pengajuan Fee';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return FeeSubmissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FeeSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeeSubmissionsTable::configure($table);
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
            'index' => ListFeeSubmissions::route('/'),
            'create' => CreateFeeSubmission::route('/create'),
            'view' => ViewFeeSubmission::route('/{record}'),
            'edit' => EditFeeSubmission::route('/{record}/edit'),
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
