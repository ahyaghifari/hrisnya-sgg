<?php

namespace App\Livewire\Rekrutmen;

use App\Exports\Rekrutmen\JobApplicantsExport;
use App\Models\Job\JobOpenings;
use App\Services\Job\RekrutmenSyncService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
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

    /** @var list<string> job_application_id yang dicentang untuk aksi massal */
    public array $selected = [];

    /** Status tujuan yang boleh dipakai aksi massal (portal tetap validasi transisi per item). */
    public const BULK_TARGETS = [
        'screening' => 'Pindahkan ke Seleksi Berkas',
        'shortlisted' => 'Loloskan Berkas',
        'rejected' => 'Tolak',
    ];

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

    // ── Aksi massal ──────────────────────────────────────────────────────────

    public function getCanDecideProperty(): bool
    {
        return Auth::user()->can('Update:JobOpenings');
    }

    /** id pelamar yang sedang tampil (ikut filter tab) — dasar "pilih semua". */
    public function getVisibleIdsProperty(): array
    {
        return array_map(fn ($a) => $a['job_application_id'], $this->applicants);
    }

    public function updatedStatus(): void
    {
        $this->selected = [];
    }

    public function toggleSelectAll(): void
    {
        $visible = $this->visibleIds;
        $this->selected = count(array_intersect($this->selected, $visible)) === count($visible) ? [] : $visible;
    }

    /**
     * Ubah status banyak pelamar sekaligus. Loop panggil RekrutmenSyncService::updateApplicationStatus
     * (portal yang validasi transisi + efek samping per item) — item yang gagal dilaporkan, sisanya
     * tetap jalan. ponytail: sinkron; untuk batch sangat besar sebaiknya jadi job antrian nanti.
     */
    public function bulkTransition(string $status): void
    {
        abort_unless($this->canDecide, 403);
        abort_unless(array_key_exists($status, self::BULK_TARGETS), 422);

        $ids = array_values(array_intersect($this->selected, $this->visibleIds));
        if ($ids === []) {
            Notification::make()->title('Belum ada pelamar dipilih')->warning()->send();

            return;
        }

        @set_time_limit(120);

        $svc = app(RekrutmenSyncService::class);
        $ok = 0;
        $failed = [];
        foreach ($ids as $id) {
            $res = $svc->updateApplicationStatus($id, $status);
            $res['ok'] ? $ok++ : $failed[] = $res['message'];
        }

        unset($this->response); // buang cache computed -> tabel refresh
        $this->selected = [];

        Notification::make()
            ->title("{$ok} pelamar diproses" . ($failed ? ', ' . count($failed) . ' gagal' : ''))
            ->body($failed ? collect($failed)->unique()->take(3)->implode(' — ') : null)
            ->{$failed ? 'warning' : 'success'}()
            ->send();
    }

    /** Export daftar pelamar (ikut filter tab yang sedang aktif) ke Excel. */
    public function exportExcel()
    {
        abort_unless(Auth::user()->can('View:JobOpenings'), 403);

        $title = $this->headerTitle;
        $suffix = $this->status ? '-' . ($this->statusOptions[$this->status] ?? $this->status) : '';
        $filename = 'Pelamar-' . Str::slug($title . $suffix) . '-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(
            new JobApplicantsExport($this->applicants, $this->statusOptions, $title),
            $filename,
        );
    }

    public function render()
    {
        return view('livewire.rekrutmen.applicants-page');
    }
}
