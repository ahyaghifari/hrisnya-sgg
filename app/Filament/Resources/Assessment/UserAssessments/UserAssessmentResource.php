<?php

namespace App\Filament\Resources\Assessment\UserAssessments;

use App\Filament\Clusters\Assessment\AssessmentCluster;
use App\Filament\Resources\Assessment\UserAssessments\Pages\CreateUserAssessment;
use App\Filament\Resources\Assessment\UserAssessments\Pages\EditUserAssessment;
use App\Filament\Resources\Assessment\UserAssessments\Pages\ListUserAssessments;
use App\Filament\Resources\Assessment\UserAssessments\Schemas\UserAssessmentForm;
use App\Filament\Resources\Assessment\UserAssessments\Tables\UserAssessmentsTable;
use App\Models\Assessment\AssessmentUsers;
use App\Models\Assessment\UserAssessment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class UserAssessmentResource extends Resource
{
    protected static ?string $model = AssessmentUsers::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $cluster = AssessmentCluster::class;

    public static function form(Schema $schema): Schema
    {
        return UserAssessmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserAssessmentsTable::configure($table);
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
            'index' => ListUserAssessments::route('/'),
            'create' => CreateUserAssessment::route('/create'),
            'edit' => EditUserAssessment::route('/{record}/edit'),
        ];
    }
}
