<?php

namespace App\Livewire\Presensi;

use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Atur Pengguna Jadwal" — niru fingerspot.io "Atur Kantor dan Jabatan", lihat
 * knowledge/absensi/ATUR JABATAN DARI JADWAL*.png. Tree sideways: struktur organisasi
 * (OrgStructure, rekursif) jadi batang, jabatan (JobPositions, rekursif lewat parent_id)
 * menempel di tiap simpul struktur yang org_structure_id-nya cocok. Menulis ke
 * `schedule_assignments` (dual-target user/jabatan, cuma jabatan yang dipakai di sini —
 * assignment per-user individual belum ada UI-nya, lihat App\Models\Shift\ScheduleAssignment).
 *
 * Halaman ini dituju otomatis setelah CreateSchedulePage::save(), dan lewat tombol "Kelola"
 * di SchedulesPage untuk jadwal yang sudah ada.
 */
#[Layout('components.layout', ['title' => 'Atur Pengguna Jadwal'])]
class ScheduleAssignmentsPage extends Component
{
    public Schedule $schedule;

    public string $effectiveFrom;

    /** @var array<int, string> job_position_id yang dicentang */
    public array $selectedJobPositionIds = [];

    public function mount(Schedule $schedule): void
    {
        abort_unless(Auth::user()->can('update', $schedule), 403);

        $this->schedule = $schedule;
        $this->effectiveFrom = now()->toDateString();

        $this->selectedJobPositionIds = ScheduleAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->whereNotNull('job_position_id')
            ->pluck('job_position_id')
            ->all();
    }

    /**
     * Jabatan yang sudah punya assignment AKTIF ke schedule LAIN — ditandai X (tidak bisa
     * dipilih di sini, niru fingerspot.io), bukan checkbox. Dicek "aktif hari ini", bukan
     * terhadap effectiveFrom yang lagi dipilih (simplifikasi sengaja versi awal).
     *
     * @return array<string, string> job_position_id => nama schedule lain
     */
    public function getBlockedJobPositionsProperty(): array
    {
        $today = now()->toDateString();

        return ScheduleAssignment::query()
            ->where('company_id', $this->schedule->company_id)
            ->where('schedule_id', '!=', $this->schedule->id)
            ->whereNotNull('job_position_id')
            ->where('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $today))
            ->with('schedule')
            ->get()
            ->mapWithKeys(fn (ScheduleAssignment $a) => [$a->job_position_id => $a->schedule->name])
            ->all();
    }

    /**
     * Jabatan ditempel ke simpul struktur langsung lewat `org_structure_id` — TIDAK disaring
     * lewat `parent_id` seperti percobaan pertama. `parent_id` di JobPositions itu rantai
     * pelaporan (siapa atasan siapa, kebanyakan berujung ke Direktur), konsep BEDA dari
     * penempatan departemen — kalau cuma jabatan "akar" (parent_id null) yang ditempel, hampir
     * semua jabatan nyata (yang parent_id-nya terisi ke Direktur) tidak pernah muncul sama
     * sekali di tree ini, ditemukan lewat tes browser langsung.
     *
     * @return array<int, array{org: OrgStructure, children: array, jobs: array}>
     */
    public function getOrgTreeProperty(): array
    {
        $companyId = $this->schedule->company_id;

        $orgStructures = OrgStructure::where('company_id', $companyId)->get();
        $jobs = JobPositions::where('company_id', $companyId)->where('is_draft', false)->whereNotNull('org_structure_id')->get();

        $jobsByOrg = $jobs->groupBy('org_structure_id');
        $orgByParent = $orgStructures->groupBy('parent_id');

        $buildOrgTree = function ($structures) use (&$buildOrgTree, $orgByParent, $jobsByOrg) {
            return $structures->map(fn (OrgStructure $org) => [
                'org' => $org,
                'children' => $buildOrgTree($orgByParent->get($org->id, collect())),
                'jobs' => $jobsByOrg->get($org->id, collect())->sortBy('name')->map(fn (JobPositions $job) => ['job' => $job])->values()->all(),
            ])->values()->all();
        };

        return $buildOrgTree($orgByParent->get(null, collect()));
    }

    /** Jabatan tanpa penempatan struktur sama sekali (org_structure_id null) — "Jabatan
     * Fungsional" di fingerspot.io. `parent_id` (rantai pelaporan) tidak relevan di sini. */
    public function getFunctionalJobsProperty()
    {
        return JobPositions::where('company_id', $this->schedule->company_id)
            ->where('is_draft', false)
            ->whereNull('org_structure_id')
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $this->validate([
            'effectiveFrom' => 'required|date',
        ], [], ['effectiveFrom' => 'tanggal diterapkan']);

        DB::transaction(function () {
            // Yang di-uncheck di sesi ini -> lepas dari jadwal ini. Yang tetap/baru dicentang ->
            // upsert. Tidak menyentuh assignment ke schedule LAIN sama sekali (itu di luar scope
            // form ini, dan memang di-blok checkbox-nya lewat blockedJobPositions).
            ScheduleAssignment::where('schedule_id', $this->schedule->id)
                ->whereNotNull('job_position_id')
                ->whereNotIn('job_position_id', $this->selectedJobPositionIds)
                ->delete();

            foreach ($this->selectedJobPositionIds as $jobPositionId) {
                ScheduleAssignment::updateOrCreate(
                    ['schedule_id' => $this->schedule->id, 'job_position_id' => $jobPositionId],
                    [
                        'company_id' => $this->schedule->company_id,
                        'effective_from' => $this->effectiveFrom,
                        'created_by' => Auth::id(),
                    ]
                );
            }
        });

        Notification::make()
            ->title('Pengguna jadwal berhasil disimpan')
            ->success()
            ->send();

        $this->redirect(route('presensi.jadwal'), navigate: true);
    }

    public function render()
    {
        return view('livewire.presensi.schedule-assignments-page');
    }
}
