<?php

namespace App\Filament\Rekrutmen\Concerns;

use App\Filament\Rekrutmen\Pages\McuPage;
use App\Filament\Rekrutmen\Pages\OfferingLetterPage;
use App\Filament\Rekrutmen\Pages\SelesaiPage;
use App\Filament\Rekrutmen\Pages\TesOnlinePage;
use App\Filament\Rekrutmen\Pages\TtdOnboardingPage;
use App\Filament\Rekrutmen\Pages\WawancaraPage;
use Livewire\Attributes\Url;

/**
 * Tahap KANDIDAT Rekrutmen (Tes → Selesai) sebagai TAB di dalam konten halaman (di bawah
 * selektor Perusahaan + Lowongan). Pilihan perusahaan/lowongan disimpan di query string
 * (#[Url]) DAN session — jadi pindah tab (pindah halaman) tidak mereset pilihan.
 */
trait PipelineTabs
{
    #[Url]
    public ?string $companyId = null;

    #[Url]
    public ?string $jobOpeningId = null;

    /** Restore pilihan terakhir kalau tak ada di URL (mis. pindah tab). */
    public function mountPipelineTabs(): void
    {
        $this->companyId ??= session('rekrutmen.pipeline.companyId');
        $this->jobOpeningId ??= session('rekrutmen.pipeline.jobOpeningId');
    }

    /** Simpan pilihan tiap kali diubah, supaya kebawa ke tab berikutnya. */
    public function updatedPipelineTabs(string $name, $value): void
    {
        if ($name === 'companyId') {
            session(['rekrutmen.pipeline.companyId' => $value ?: null]);
            session()->forget('rekrutmen.pipeline.jobOpeningId');
        }

        if ($name === 'jobOpeningId') {
            session(['rekrutmen.pipeline.jobOpeningId' => $value ?: null]);
        }
    }

    /** @return list<array{label:string,icon:string,url:string,active:bool}> */
    public function pipelineTabs(): array
    {
        $params = array_filter([
            'companyId' => $this->companyId,
            'jobOpeningId' => $this->jobOpeningId,
        ]);

        $tabs = [
            [TesOnlinePage::class, 'Tes', 'heroicon-o-clipboard-document-check'],
            [WawancaraPage::class, 'Wawancara', 'heroicon-o-chat-bubble-left-right'],
            [OfferingLetterPage::class, 'Offering Letter', 'heroicon-o-document-text'],
            [McuPage::class, 'MCU', 'heroicon-o-heart'],
            [TtdOnboardingPage::class, 'TTD & Onboarding', 'heroicon-o-pencil-square'],
            [SelesaiPage::class, 'Selesai', 'heroicon-o-check-badge'],
        ];

        $out = [];

        foreach ($tabs as [$class, $label, $icon]) {
            if (! $class::canAccess()) {
                continue;
            }

            $out[] = [
                'label' => $label,
                'icon' => $icon,
                'url' => $class::getUrl($params),
                'active' => static::class === $class,
            ];
        }

        return $out;
    }
}
