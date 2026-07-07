<?php

namespace App\Filament\Resources\ManPower\JobOpenings;

use App\Filament\Resources\ManPower\JobOpenings\Pages\CreateJobOpening;
use App\Filament\Resources\ManPower\JobOpenings\Pages\EditJobOpening;
use App\Filament\Resources\ManPower\JobOpenings\Pages\ListJobOpenings;
use App\Filament\Resources\ManPower\JobOpenings\Pages\ViewCandidateApplicant;
use App\Filament\Resources\ManPower\JobOpenings\Pages\ViewJobOpening;
use App\Filament\Resources\ManPower\JobOpenings\RelationManagers\ApplicantRelationManager;
use App\Filament\Resources\ManPower\JobOpenings\Schemas\JobOpeningForm;
use App\Filament\Resources\ManPower\JobOpenings\Tables\JobOpeningsTable;
use App\Helpers\PermissionHelper;
use App\Models\Job\JobOpenings;
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

class JobOpeningResource extends Resource
{
    protected static ?string $model = JobOpenings::class;

    protected static ?string $navigationLabel = "Manajemen Lowongan Kerja";

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return JobOpeningForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobOpeningsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ApplicantRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobOpenings::route('/'),
            'create' => CreateJobOpening::route('/create'),
            'edit' => EditJobOpening::route('/{record}/edit'),
            'view' => ViewJobOpening::route('/{record}'),
            'view-candidate-applicant' => ViewCandidateApplicant::route('/applicants/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'jobPosition.orgStructure', 'applicants']);

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view_manajemen_lowongan_kerja');
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view_manajemen_lowongan_kerja');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_manajemen_lowongan_kerja');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('edit_manajemen_lowongan_kerja');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete_manajemen_lowongan_kerja');
    }
}
