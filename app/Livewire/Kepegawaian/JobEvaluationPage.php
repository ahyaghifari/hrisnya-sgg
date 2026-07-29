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
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_KEPEGAWAIAN->value), 403);

        $this->companyId = Auth::user()->canFilterByCompany()
            ? (Auth::user()->company_id ?? Companies::orderBy('code')->value('id'))
            : Auth::user()->company_id;

        $this->loadJe();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function selectCompany(string $companyId): void
    {
        abort_unless(Auth::user()->canFilterByCompany(), 403);

        $this->companyId = $companyId;
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
            ->orderBy('name')
            ->get();
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

    public function saveJe(): void
    {
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
