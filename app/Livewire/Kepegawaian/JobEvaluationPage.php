<?php

namespace App\Livewire\Kepegawaian;

use App\Enum\CustomPermissionEnum;
use App\Enum\JobPositionReqStatus;
use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Job Evaluation per Jabatan — satu-satunya tempat edit `job_evaluation_score` (dipakai
 * JasaPelayananCalculator sebagai pembagi rasio Jasa Pelayanan, lihat halaman Omset di
 * Keuangan yang cuma menampilkannya read-only). Tab "Saat Ini" (tabel seperti Excel,
 * No/Nama Jabatan/JE Score) + tab "Riwayat" (siapa & kapan mengubah, dari AuditLog —
 * JobPositions::auditWatchedAttribute() = job_evaluation_score).
 */
#[Layout('components.layout', ['title' => 'Job Evaluation'])]
class JobEvaluationPage extends Component
{
    public ?string $companyId = null;
    public string $tab = 'saat-ini';

    /** @var array<string,string> job_position_id => job_evaluation_score */
    public array $jeValues = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::LIHAT_JOB_EVALUATION->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadJe();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    // Gerbang di selectCompany() saja tidak cukup - Livewire property bisa di-$set langsung
    // dari request yang di-craft (lewat hook updated*, bukan lewat method public), jadi
    // companyId perlu divalidasi ulang di sini juga.
    public function updatedCompanyId(): void
    {
        if (! Auth::user()->canFilterByCompany()) {
            $this->companyId = Auth::user()->company_id;
        }

        // Wajib — <select> pakai wire:model.live="companyId". Tanpa ini jeValues tetap
        // isi company sebelumnya sementara tabel sudah pindah company, jadi semua input
        // JE tampil kosong walau nilainya ADA di DB (laporan bug 2026-09-02).
        $this->loadJe();
    }

    public function getCompaniesProperty()
    {
        return Companies::orderBy('code')->get();
    }

    public function getJobPositionsProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return JobPositions::query()
            ->where('company_id', $this->companyId)
            ->where('status', JobPositionReqStatus::APPROVED->value)
            ->with(['pangkat', 'orgStructure.parent'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Dikelompokkan per Pangkat supaya jabatan setingkat gampang dibandingkan JE-nya (mis. semua
     * "Koordinator" satu blok) - urutan grup ikut urutan Pangkat dibuat (id ULID, sortable per
     * waktu), yang di semua company sejauh ini sudah konsisten dari level tertinggi ke terendah
     * (Direktur/Manager/... Pelaksana). Jabatan tanpa pangkat_id dikumpulkan ke grup "Tanpa
     * Pangkat" di paling akhir. Di dalam tiap grup Pangkat, dipecah lagi per Divisi (org
     * structure level 2, lihat JobPositions::getDivisionAttribute()) supaya jabatan setingkat
     * dari divisi yang sama gampang dibandingkan - "Tanpa Divisi" di akhir tiap grup Pangkat.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, JobPositions>>>
     */
    public function getGroupedJobPositionsProperty()
    {
        $positions = $this->jobPositions;

        $pangkatOrder = $positions->pluck('pangkat')->filter()->unique('id')->sortBy('id')->pluck('name', 'id');

        $byPangkatId = $positions->groupBy(fn (JobPositions $p) => $p->pangkat_id ?? '');

        return $pangkatOrder->keys()->push('')
            ->mapWithKeys(fn ($pangkatId) => [
                ($pangkatId === '' ? 'Tanpa Pangkat' : $pangkatOrder[$pangkatId]) => $byPangkatId->get($pangkatId, collect()),
            ])
            ->filter(fn ($group) => $group->isNotEmpty())
            ->map(fn ($group) => $this->groupByDivision($group));
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, JobPositions>> */
    private function groupByDivision($positions)
    {
        $divisionOrder = $positions->pluck('division')->filter()->unique('id')->sortBy('name')->pluck('name', 'id');

        $byDivisionId = $positions->groupBy(fn (JobPositions $p) => $p->division?->id ?? '');

        return $divisionOrder->keys()->push('')
            ->mapWithKeys(fn ($divisionId) => [
                ($divisionId === '' ? 'Tanpa Divisi' : $divisionOrder[$divisionId]) => $byDivisionId->get($divisionId, collect()),
            ])
            ->filter(fn ($group) => $group->isNotEmpty());
    }

    public function getTotalJeProperty(): int
    {
        return (int) collect($this->jeValues)->sum(fn ($v) => is_numeric($v) ? (int) $v : 0);
    }

    private function loadJe(): void
    {
        $this->jeValues = $this->jobPositions
            ->mapWithKeys(fn (JobPositions $p) => [$p->id => (string) ($p->job_evaluation_score ?? '')])
            ->all();
    }

    public function getHistoryProperty()
    {
        if (! $this->companyId) {
            return collect();
        }

        return AuditLog::query()
            ->where('module', 'kepegawaian')
            ->where('key', 'like', 'job_evaluation_score:%')
            ->where('company_id', $this->companyId)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    public function getCanManageProperty(): bool
    {
        return Auth::user()->can(CustomPermissionEnum::KELOLA_JOB_EVALUATION->value);
    }

    public function saveJe(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate([
            'jeValues.*' => $this->nonNegativeIntegerRule(),
        ], [], ['jeValues.*' => 'JE score']);

        foreach ($this->jeValues as $jobPositionId => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            JobPositions::where('id', $jobPositionId)->update(['job_evaluation_score' => $value]);
        }

        \Filament\Notifications\Notification::make()
            ->title('JE Jabatan berhasil disimpan')
            ->success()
            ->send();
    }

    private function nonNegativeIntegerRule(): array
    {
        return [function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === '' || $value === null) {
                return;
            }

            if (! is_numeric($value) || (int) $value != $value || (int) $value < 0) {
                $fail('Nilai harus berupa angka bulat 0 atau lebih besar.');
            }
        }];
    }

    public function render()
    {
        return view('livewire.kepegawaian.job-evaluation-page');
    }
}
