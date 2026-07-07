<?php

namespace App\Filament\Candidate\Widgets;

use App\Filament\Candidate\Resources\CandidateProfiles\CandidateProfileResource;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class FlagingOnBoardingWidget extends Widget implements HasInfolists, HasForms
{
    use InteractsWithInfolists, InteractsWithForms;

    protected string $view = 'filament.candidate.widgets.flaging-on-boarding-widget';

    protected int | string | array $columnSpan = 'full';

    public function getFlagingInfolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Onboarding')
                    ->headerActions([
                        Action::make('complete_onboarding')
                            ->label('Complete Onboarding')
                            ->url(CandidateProfileResource::getUrl('edit', ['record' => auth()->guard('candidate')->user()->id])),
                    ])
                    ->visible(function () {
                        return auth()->guard('candidate')->user()->nik === null;
                    })
                    ->icon('heroicon-o-flag')
                    ->iconColor('warning')
                    ->description('Sebelum melanjutkan proses lamaran pekerjaan, silakan lengkapi data profil Anda.')
            ]);
    }
}
