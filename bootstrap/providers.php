<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    // Panel /rekrutmen — admin/pipeline rekrutmen (shared DB dgn project `rekrutmen`).
    // Repurpose dari panel `candidate` yang dulu nonaktif. Lihat RekrutmenPanelProvider.
    App\Providers\Filament\RekrutmenPanelProvider::class,
];
