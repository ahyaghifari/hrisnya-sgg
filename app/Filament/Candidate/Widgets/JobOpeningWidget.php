<?php

namespace App\Filament\Candidate\Widgets;

use App\Filament\Candidate\Resources\JobVacancies\JobVacancyResource;
use App\Models\Job\JobOpenings;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class JobOpeningWidget extends Widget implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists;

    protected string $view = 'filament/candidate.widgets.infolist-widget';

    protected int | string | array $columnSpan = 'full';

    public function jobOpeningInfolist(Schema $infolist): Schema
    {
        $jobOpenings = JobOpenings::with(['jobPosition', 'company'])
            ->where('is_active', true)
            ->get();

        $sections = $jobOpenings->map(function ($jobOpening) {
            return Section::make($jobOpening->jobPosition->name . ' - ' . $jobOpening->jobPosition->orgStructure->name ?? 'Job Position')
                ->description($jobOpening->company->name ?? 'Company')
                ->icon('heroicon-o-briefcase')
                ->iconColor('primary')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('description')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->state("Lowongan Posisi {$jobOpening->jobPosition->name} di {$jobOpening->company->name}"),

                            Action::make('apply')
                                ->label('Detail Lowongan')
                                ->link()
                                ->url(JobVacancyResource::getUrl('edit', ['record' => $jobOpening->id]))
                        ]),
                    ]);
        })->toArray();

        return $infolist
            ->state([])
            ->schema([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema($sections)
            ]);
    }
}
