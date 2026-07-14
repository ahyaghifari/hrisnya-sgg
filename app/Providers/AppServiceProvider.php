<?php

namespace App\Providers;

use App\Filament\Candidate\Pages\Auth\LoginResponse as AuthLoginResponse;
use App\Filament\Widgets\ShiftCalendarWidget;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponse::class, AuthLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ponytail: config('livewire.class_namespace') is customized to App\Livewire, which breaks
        // reverse name->class resolution for Filament widgets outside that namespace (they render fine
        // on mount but 500 on any livewire/update round-trip). Explicit alias sidesteps the broken lookup.
        Livewire::component('app.filament.widgets.shift-calendar-widget', ShiftCalendarWidget::class);
    }
}
