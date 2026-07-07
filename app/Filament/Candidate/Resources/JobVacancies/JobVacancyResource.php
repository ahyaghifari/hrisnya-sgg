<?php

namespace App\Filament\Candidate\Resources\JobVacancies;

use App\Filament\Candidate\Resources\JobVacancies\Pages\CreateJobVacancy;
use App\Filament\Candidate\Resources\JobVacancies\Pages\EditJobVacancy;
use App\Filament\Candidate\Resources\JobVacancies\Pages\ListJobVacancies;
use App\Filament\Candidate\Resources\JobVacancies\Schemas\JobVacancyForm;
use App\Filament\Candidate\Resources\JobVacancies\Tables\JobVacanciesTable;
use App\Models\Job\JobOpenings;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JobVacancyResource extends Resource
{
    protected static ?string $model = JobOpenings::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return JobVacancyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobVacanciesTable::configure($table);
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
            'index' => ListJobVacancies::route('/'),
            'create' => CreateJobVacancy::route('/create'),
            'edit' => EditJobVacancy::route('/{record}/edit'),
        ];
    }
}
