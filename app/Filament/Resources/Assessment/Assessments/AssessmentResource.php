<?php

namespace App\Filament\Resources\Assessment\Assessments;

use App\Filament\Clusters\Assessment\AssessmentCluster;
use App\Filament\Resources\Assessment\Assessments\Pages\CreateAssessment;
use App\Filament\Resources\Assessment\Assessments\Pages\EditAssessment;
use App\Filament\Resources\Assessment\Assessments\Pages\ListAssessments;
use App\Filament\Resources\Assessment\Assessments\Pages\ViewAssessment;
use App\Filament\Resources\Assessment\Assessments\RelationManagers\UserAssessmentsRelationManager;
use App\Filament\Resources\Assessment\Assessments\Schemas\AssessmentForm;
use App\Filament\Resources\Assessment\Assessments\Tables\AssessmentsTable;
use App\Helpers\PermissionHelper;
use App\Models\Assessment\Assessments;
use App\Models\Assessment\AssessmentTypes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AssessmentResource extends Resource
{
    protected static ?string $model = Assessments::class;

    protected static ?string $cluster = AssessmentCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AssessmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssessmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UserAssessmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssessments::route('/'),
            'create' => CreateAssessment::route('/create'),
            'edit' => EditAssessment::route('/{record}/edit'),
            'view' => ViewAssessment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['company', 'template', 'jobPosition']);

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function getNavigationItems(): array
    {
        return AssessmentTypes::all()
            ->map(function ($type) {
                return NavigationItem::make()
                    ->label($type->name)
                    ->icon(static::$navigationIcon)
                    ->group('List Assessments')
                    ->url(static::getUrl('index', ['type' => $type->code]))
                    ->isActiveWhen(fn (): bool => request()->get('type') === $type->code);
            })
            ->toArray();
    }

    public static function infolist(Schema $schema): Schema
    {
        return self::viewSchema($schema);
    }

    public static function viewSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Penilaian')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nama Penilaian'),

                        TextEntry::make('template.assessmentType.name')
                            ->label('Tipe Penilaian'),
                    ]),

                Section::make('Jadwal Penilaian')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        TextEntry::make('start_date')
                            ->label('Mulai Penilaian')
                            ->dateTime('j F Y, H:i'),

                        TextEntry::make('end_date')
                            ->label('Akhir Penilaian')
                            ->dateTime('j F Y, H:i'),
                    ]),
            ]);
    }
}
