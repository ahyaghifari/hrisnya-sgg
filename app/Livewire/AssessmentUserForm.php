<?php

namespace App\Livewire;

use App\Enum\CustomPermissionEnum;
use App\Models\Assessment\AssessmentScores;
use App\Models\Assessment\AssessmentUsers;
use App\Services\Assessment\AssessmentService;
use App\Services\OrgHierarchyService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Isi/lihat nilai penilaian karyawan dari portal (bukan Filament). Cakupan pertama sengaja
 * cuma indikator generik (template_item.type === null) — item Orientasi/MCQ/Skala Sederhana
 * (App\Enum\AssessmentTemplateItemType) belum digarap di sini, sama seperti gap yang sudah
 * ada di form admin (lihat knowledge/markdown/analisis-assessment-penilaian-karyawan.md).
 */
class AssessmentUserForm extends Component
{
    public AssessmentUsers $assessmentUser;
    public bool $canEdit = false;
    public bool $isSelf = false;

    /** @var array<string, string|null> keyed by template_item_id */
    public array $scores = [];

    public function mount(AssessmentUsers $assessmentUser): void
    {
        $this->isSelf = Auth::id() === $assessmentUser->target_id;
        $isManager = Auth::user()->canAccessKaryawanMenu()
            && app(OrgHierarchyService::class)->subordinates(Auth::user())->whereKey($assessmentUser->target_id)->exists();
        // HC/pengelola Sistem Penilaian Karyawan boleh BUKA (lihat) record siapa pun buat
        // pantau progress dari halaman "Daftar Penilaian" terpusat — tapi TIDAK otomatis boleh
        // ISI nilai (lihat $canEdit di bawah, tetap cuma atasan langsung, biar integritas nilai
        // tetap dari orang yang benar-benar menilai).
        $canView = Auth::user()->can(CustomPermissionEnum::SISTEM_PENILAIAN_KARYAWAN->value);

        abort_unless($this->isSelf || $isManager || $canView, 403);
        // Jadwal (Assessments) ATAU karyawan targetnya (User) bisa sudah soft-deleted setelah
        // baris AssessmentUsers ini dibuat — relasi belongsTo/hasOne otomatis exclude yang
        // trashed, jangan lanjut proses kalau sudah yatim, daripada crash baca properti null.
        abort_if(! $assessmentUser->assessments || ! $assessmentUser->target, 404, 'Data penilaian ini sudah tidak tersedia.');

        app(AssessmentService::class)->ensureAssessmentScoresExist($assessmentUser);

        $this->assessmentUser = $assessmentUser->load(['target', 'assessments.template.templateItems', 'scores']);
        $this->canEdit = $isManager && is_null($this->assessmentUser->final_score);

        $this->scores = $this->templateItems
            ->mapWithKeys(fn ($item) => [$item->id => $this->assessmentUser->scores->firstWhere('template_item_id', $item->id)?->score])
            ->toArray();
    }

    public function getTemplateItemsProperty()
    {
        return $this->assessmentUser->assessments->template->templateItems
            ->whereNull('type')
            ->sortBy('sort_order')
            ->values();
    }

    protected function rules(): array
    {
        $maxScores = $this->templateItems->pluck('max_score', 'id');

        return collect($this->scores)
            ->keys()
            ->mapWithKeys(fn ($id) => ["scores.{$id}" => ['nullable', 'numeric', 'min:0', 'max:' . $maxScores[$id]]])
            ->toArray();
    }

    public function submit(): void
    {
        abort_unless($this->canEdit, 403);

        $data = $this->validate();

        foreach ($data['scores'] as $templateItemId => $score) {
            AssessmentScores::updateOrCreate(
                ['assessment_user_id' => $this->assessmentUser->id, 'template_item_id' => $templateItemId],
                ['score' => $score === '' ? null : $score]
            );
        }

        // calculateUserAssessmentScore juga menulis ulang weighted_score tiap item, jadi tidak
        // perlu dihitung manual di sini (lihat AssessmentService::calculateUserAssessmentScore).
        $result = app(AssessmentService::class)->calculateUserAssessmentScore($this->assessmentUser->id);

        if ($result['success']) {
            Notification::make()
                ->success()
                ->title('Penilaian tersimpan')
                ->body('Nilai akhir: ' . $result['final_score'] . ' - ' . $result['final_category'])
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('Gagal menghitung nilai akhir')
                ->body($result['error'] ?? 'Terjadi kesalahan')
                ->send();
        }

        $this->assessmentUser->refresh();
        $this->canEdit = false;
    }

    public function render()
    {
        return view('livewire.assessment-user-form')->layout('components.layout', ['title' => 'Penilaian Karyawan']);
    }
}
