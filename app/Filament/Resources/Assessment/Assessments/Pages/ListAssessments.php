<?php

namespace App\Filament\Resources\Assessment\Assessments\Pages;

use App\Filament\Resources\Assessment\Assessments\AssessmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListAssessments extends ListRecords
{
    protected static string $resource = AssessmentResource::class;

    public function getTitle(): string|Htmlable
    {
        $typeCode = request()->query('type');

        $type = \App\Models\Assessment\AssessmentTypes::where('code', $typeCode)->first();

        return $type ? "Penilaian " . $type->name : 'Penilaian';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Penilaian')
                ->visible(fn () => AssessmentResource::canCreate()),
        ];
    }

    public function getTableQuery(): Builder|Relation|null
    {
        $typeCode = request()->query('type');

        $type = \App\Models\Assessment\AssessmentTypes::where('code', $typeCode)->first();

        $query = AssessmentResource::getEloquentQuery();

        if ($type) {
            return $query->whereHas('template', function ($templateQuery) use ($type) {
                $templateQuery->where('type_id', $type->id);
            });
        }

        return $query;
    }
}
