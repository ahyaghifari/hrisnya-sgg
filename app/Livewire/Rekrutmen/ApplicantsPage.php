<?php

namespace App\Livewire\Rekrutmen;

use App\Models\Job\JobOpenings;
use App\Services\Job\RekrutmenSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Daftar pelamar 1 lowongan — data live dari project Rekrutmen eksternal.
 * `$openingId` bisa id lowongan HRIS (`JobOpenings`) ATAU id lowongan Rekrutmen yang belum
 * terhubung ke HRIS (dibuat manual di sana) — dua-duanya bisa dikelola pelamarnya.
 * Aksi per-pelamar (seleksi/wawancara/MCU/dst) ada di ApplicantDetailPage, tidak tergantung
 * linkage lowongan.
 */
#[Layout('components.layout', ['title' => 'Daftar Pelamar'])]
class ApplicantsPage extends Component
{
    public ?JobOpenings $record = null;
    public string $openingId;

    #[Url]
    public string $status = '';

    public function mount(string $opening): void
    {
        abort_unless(Auth::user()->can('View:JobOpenings'), 403);

        $hris = JobOpenings::with(['jobPosition.orgStructure', 'company'])->find($opening);

        if ($hris) {
            abort_unless(
                Auth::user()->canFilterByCompany() || $hris->company_id === Auth::user()->company_id,
                403,
            );
            $this->record = $hris;
            $this->openingId = $hris->id;
        } else {
            // Lowongan Rekrutmen belum terhubung ke HRIS — tetap boleh kelola pelamarnya.
            $this->openingId = $opening;
        }
    }

    /** Ambil SEMUA pelamar 1x (tanpa filter status) — tab per-status filter di client, jadi
     * ganti tab instan + hitungan per tab gratis. */
    #[Computed]
    public function response(): array
    {
        return app(RekrutmenSyncService::class)->getJobOpeningApplications($this->openingId, null);
    }

    public function getApplicantsProperty(): array
    {
        $all = $this->response['data'];

        return $this->status === ''
            ? $all
            : array_values(array_filter($all, fn ($a) => ($a['status'] ?? null) === $this->status));
    }

    /** @return array<string,int> status => jumlah */
    public function getStatusCountsProperty(): array
    {
        $counts = [];
        foreach ($this->response['data'] as $a) {
            $s = $a['status'] ?? '?';
            $counts[$s] = ($counts[$s] ?? 0) + 1;
        }

        return $counts;
    }

    public function getHeaderTitleProperty(): string
    {
        return $this->record?->display_name
            ?: ($this->response['job_opening_title'] ?? 'Lowongan Rekrutmen');
    }

    public function getIsUnlinkedProperty(): bool
    {
        return $this->record === null;
    }

    public function getStatusOptionsProperty(): array
    {
        return [
            'applied' => 'Lamaran Baru', 'screening' => 'Seleksi Berkas', 'shortlisted' => 'Lolos Berkas / Tes',
            'interview' => 'Wawancara', 'offering' => 'Offering', 'mcu' => 'MCU',
            'onboarding' => 'Onboarding', 'passed' => 'Diterima', 'rejected' => 'Ditolak',
        ];
    }

    public function render()
    {
        return view('livewire.rekrutmen.applicants-page');
    }
}
