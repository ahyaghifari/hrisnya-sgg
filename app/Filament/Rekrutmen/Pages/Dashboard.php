<?php

namespace App\Filament\Rekrutmen\Pages;

use App\Filament\Rekrutmen\Resources\JobOpenings\JobOpeningResource;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\JobApplication;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Dasbor panel Rekrutmen — ganti dashboard bawaan Filament: ringkasan jumlah kandidat per
 * tahap + kartu aksi cepat ke tiap halaman pipeline. Angka discope ke perusahaan user kalau
 * dia tak pegang LIHAT_PERUSAHAAN_LAIN.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dasbor';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.rekrutmen.pages.dashboard')->viewData([
                'stats' => $this->getStageStats(),
                'links' => $this->getQuickLinks(),
            ]),
        ]);
    }

    /** @return list<array{label:string,count:int,color:string}> */
    protected function getStageStats(): array
    {
        $user = filament()->auth()->user();
        $ownId = $user?->canFilterByCompany()
            ? null
            : Company::idForHrisCompany($user?->company_id);

        $counts = JobApplication::query()
            ->when($ownId, fn ($q) => $q->whereHas('jobOpening', fn ($j) => $j->where('company_id', $ownId)))
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $map = [
            'applied' => ['Lamaran Masuk', '#64748b'],
            'screening' => ['Peninjauan Berkas', '#0284c7'],
            'shortlisted' => ['Lolos Berkas / Tes', '#047C54'],
            'interview' => ['Wawancara', '#0369a1'],
            'offering' => ['Offering Letter', '#d97706'],
            'mcu' => ['MCU', '#7c3aed'],
            'onboarding' => ['TTD & Onboarding', '#c2410c'],
            'passed' => ['Diterima', '#16a34a'],
        ];

        return collect($map)
            ->map(fn ($cfg, $status) => [
                'label' => $cfg[0],
                'count' => (int) ($counts[$status] ?? 0),
                'color' => $cfg[1],
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label:string,description:string,icon:string,url:string}> */
    protected function getQuickLinks(): array
    {
        $pages = [
            [ApplicantsPage::class, 'Daftar Pelamar', 'Tinjau berkas & mulai peninjauan', 'heroicon-o-users', '#0284c7'],
            [TesOnlinePage::class, 'Tes', 'Aktifkan tes & catat hasil', 'heroicon-o-clipboard-document-check', '#0369a1'],
            [WawancaraPage::class, 'Wawancara', 'Jadwalkan & catat hasil wawancara', 'heroicon-o-chat-bubble-left-right', '#7c3aed'],
            [OfferingLetterPage::class, 'Offering Letter', 'Unggah surat & lanjutkan tahap', 'heroicon-o-document-text', '#d97706'],
            [McuPage::class, 'MCU', 'Jadwal MCU & keputusan fit/unfit', 'heroicon-o-heart', '#db2777'],
            [TtdOnboardingPage::class, 'TTD & Onboarding', 'Tandai kandidat selesai', 'heroicon-o-pencil-square', '#c2410c'],
            [SelesaiPage::class, 'Selesai', 'Buat akun karyawan HRIS', 'heroicon-o-check-badge', '#16a34a'],
        ];

        $links = [];
        foreach ($pages as [$class, $label, $desc, $icon, $color]) {
            if ($class::canAccess()) {
                $links[] = ['label' => $label, 'description' => $desc, 'icon' => $icon, 'color' => $color, 'url' => $class::getUrl()];
            }
        }

        if (JobOpeningResource::canAccess()) {
            $links[] = [
                'label' => 'Lowongan',
                'description' => 'Kelola & publish lowongan',
                'icon' => 'heroicon-o-briefcase',
                'color' => '#042c6c',
                'url' => JobOpeningResource::getUrl('index'),
            ];
        }

        return $links;
    }
}
