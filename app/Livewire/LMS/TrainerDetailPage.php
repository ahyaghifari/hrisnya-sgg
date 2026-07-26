<?php

namespace App\Livewire\LMS;

use App\Enum\CustomPermissionEnum;
use App\Models\LMS\LmsCourseFeedbacks;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\Trainer\Trainers;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail "Pemateri" — 3 tab: Profil, Pelatihan (course distinct yang pernah diisi lewat
 * relasi meetings, klik baris masuk ke detail pelatihan yang sudah ada/LmsCourseDetailPage),
 * dan Review (LmsCourseFeedbacks dari course-course itu — feedback level-course, bukan
 * per-meeting, karena memang begitu skema datanya).
 */
#[Layout('components.layout', ['title' => 'Detail Pemateri'])]
class TrainerDetailPage extends Component
{
    public Trainers $trainer;

    public string $tab = 'profil';

    public function mount(Trainers $trainer): void
    {
        abort_unless(Auth::user()->can(CustomPermissionEnum::SISTEM_LMS->value), 403);

        $trainer->load('user');

        $this->trainer = $trainer;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    private function courseIds()
    {
        return $this->trainer->meetings()->pluck('course_id')->unique()->values();
    }

    public function getCoursesProperty()
    {
        return LmsCourses::whereIn('id', $this->courseIds())
            ->orderByDesc('created_at')
            ->get();
    }

    public function getReviewsProperty()
    {
        return LmsCourseFeedbacks::whereIn('course_id', $this->courseIds())
            ->with(['user', 'course'])
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.lms.trainer-detail-page');
    }
}
