<?php

namespace App\Livewire\LMS;

use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCategories;
use App\Models\LMS\LmsCourses;
use App\Models\Master\Competencies;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form Tambah Pelatihan portal — REDESIGN, bukan port dari admin `LmsCoursesForm`
 * (5 tab: Detail/Jadwal/Pertemuan/Peserta/Umpan Balik, ratusan baris). Portal cuma ambil
 * field detail+jadwal (satu form, tanpa tab), direplikasi dari `detailSchema()`/`jadwalSchema()`
 * admin, minus field "Sumber Pelatihan" (dipaksa 'internal' terus, sesuai keputusan kategori
 * yang juga khusus internal).
 *
 * Jadwal disederhanakan jadi toggle "1 hari" (pola sama seperti SubmitLeaveRequest
 * is_one_day/toggleOneDay) alih-alih Repeater Pertemuan admin yang penuh field
 * trainer/materi/quiz — di baliknya tetap membuat 1 baris `LmsMeetings` (judul dipaksa
 * sama dengan judul pelatihan) supaya `LmsCourses::start_date/end_date` (yang di-derive
 * dari meeting lewat `syncDatesFromMeetings()`) tetap konsisten. Manajemen pertemuan detail
 * (trainer, materi, quiz) masih lewat admin sampai ada kebutuhan portalnya.
 */
#[Layout('components.layout', ['title' => 'Tambah Pelatihan'])]
class CreateLmsCourse extends Component
{
    use WithFileUploads;

    public ?string $category_id = null;
    public string $code = '';
    public string $title = '';
    public string $type_nakes = 'all';
    public int $minimum_score = 0;
    public ?string $due_date = null;
    public array $competencyIds = [];
    public $thumbnail = null;

    public bool $is_one_day = true;
    public ?string $start_date = null;
    public ?string $end_date = null;

    public bool $requires_feedback = true;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('create', LmsCourses::class), 403);
    }

    public function toggleOneDay(): void
    {
        $this->is_one_day = ! $this->is_one_day;
    }

    public function toggleRequiresFeedback(): void
    {
        $this->requires_feedback = ! $this->requires_feedback;
    }

    public function getCategoryOptionsProperty()
    {
        return LmsCategories::orderBy('name')->pluck('name', 'id');
    }

    public function getCompetencyOptionsProperty()
    {
        return Competencies::orderBy('name')->pluck('name', 'id');
    }

    protected function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:lms_categories,id'],
            'code' => ['required', 'string', 'max:100', 'unique:lms_courses,code'],
            'title' => ['required', 'string', 'max:255'],
            'type_nakes' => ['required', 'in:nakes,non-nakes,all'],
            'minimum_score' => ['required', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'competencyIds' => ['required', 'array', 'min:1'],
            'competencyIds.*' => ['exists:m_competencies,id'],
            'thumbnail' => ['nullable', 'image', 'max:10240'],
            'is_one_day' => ['required', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required_if:is_one_day,false', 'nullable', 'date', 'after_or_equal:start_date'],
            'requires_feedback' => ['required', 'boolean'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $thumbnailPath = $this->thumbnail?->store('lms/thumbnails', 'public');

        $course = LmsCourses::create([
            'company_id' => Auth::user()->company_id,
            'category_id' => $validated['category_id'],
            'code' => $validated['code'],
            'title' => $validated['title'],
            'type_nakes' => $validated['type_nakes'],
            'source_type' => 'internal',
            'minimum_score' => $validated['minimum_score'],
            'due_date' => $validated['due_date'],
            'thumbnail' => $thumbnailPath,
            'type' => 'seasonal',
            'status' => LsmCourseStatus::Draft->value,
            'requires_feedback' => $validated['requires_feedback'],
        ]);

        $course->competencies()->sync($validated['competencyIds']);

        $course->meetings()->create([
            'title' => $validated['title'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['is_one_day'] ? $validated['start_date'] : $validated['end_date'],
        ]);

        // LmsCourses::boot() nyinkronin start_date/end_date dari meetings tiap 'saving' — pas
        // create() di atas, meeting belum ada jadi hasilnya null. save() ulang di sini
        // memicu sync itu lagi, sekarang meeting-nya sudah ada.
        $course->save();

        Notification::make()
            ->title('Pelatihan berhasil disimpan sebagai draft')
            ->success()
            ->send();

        $this->redirect(route('pelatihan.kelola.show', $course), navigate: true);
    }

    public function render()
    {
        return view('livewire.lms.create-lms-course');
    }
}
