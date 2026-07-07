<?php

namespace App\Filament\Candidate\Resources\JobApplicants;

use App\Filament\Candidate\Resources\JobApplicants\Pages\CreateJobApplicant;
use App\Filament\Candidate\Resources\JobApplicants\Pages\EditJobApplicant;
use App\Filament\Candidate\Resources\JobApplicants\Pages\ListJobApplicants;
use App\Filament\Candidate\Resources\JobApplicants\Schemas\JobApplicantForm;
use App\Filament\Candidate\Resources\JobApplicants\Tables\JobApplicantsTable;
use App\Models\Candidate\CandidateJobApplications;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class JobApplicantResource extends Resource
{
    protected static ?string $model = CandidateJobApplications::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Lamaran Saya';

    protected static ?string $recordTitleAttribute = 'CandidateJobApplicants';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('candidate_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return JobApplicantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobApplicantsTable::configure($table);
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
            'index' => ListJobApplicants::route('/'),
            'create' => CreateJobApplicant::route('/create'),
            'edit' => EditJobApplicant::route('/{record}/edit'),
        ];
    }
}
