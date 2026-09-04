<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Models\Rekrutmen\QuizAttempt;
use Filament\Pages\Page;

/**
 * Rincian 1 percobaan kuis (read-only) — port dari HasilTesDetailPage project `rekrutmen`.
 * Diakses `?attempt=<id>`, tak muncul di nav.
 */
class HasilTesDetailPage extends Page
{
    protected string $view = 'filament.rekrutmen.pages.hasil-tes-detail-page';

    protected static bool $shouldRegisterNavigation = false;

    public ?QuizAttempt $attempt = null;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('View:JobOpenings');
    }

    public function mount(): void
    {
        $id = request('attempt');

        $this->attempt = $id
            ? QuizAttempt::with(['quiz', 'jobApplication.jobOpening', 'answers.question'])->find($id)
            : null;

        abort_if(! $this->attempt, 404);
    }

    public function getTitle(): string
    {
        return 'Hasil Tes — ' . ($this->attempt?->quiz->name ?? '');
    }
}
