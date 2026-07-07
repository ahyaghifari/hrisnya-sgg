<?php

namespace App\Filament\Candidate\Pages;

use App\Filament\Candidate\Widgets\FlagingOnBoardingWidget;
use App\Filament\Candidate\Widgets\JobOpeningWidget;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard as PagesDashboard;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

class Dashboard extends PagesDashboard
{
    protected static ?string $title = 'Portal Hiring';

    public function getWidgets(): array
    {
        return [
            FlagingOnBoardingWidget::class,
            JobOpeningWidget::class,
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make()
                ->label('Dashboard')
                ->icon('heroicon-o-home')
                ->url(static::getUrl())
                ->isActiveWhen(fn () => request()->routeIs([
                    'filament.candidate.pages.dashboard',
                    'filament.candidate.resources.job-vacancies.edit',
                ])),
        ];
    }
}
