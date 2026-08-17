<?php

namespace Tests\Feature\Portal;

use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCourseFeedbacks;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsFeedbackQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(string $code, bool $feedbackUnlocked = true): LmsCourses
    {
        return LmsCourses::create([
            'code' => $code,
            'title' => 'Kursus Umpan Balik',
            'status' => LsmCourseStatus::Published->value,
            'requires_feedback' => true,
            'feedback_unlocked' => $feedbackUnlocked,
        ]);
    }

    private function approveUserForCourse(User $user, LmsCourses $course): void
    {
        LmsCourseUsers::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'progress' => 0,
            'score' => 0,
            'enrolled_at' => now(),
        ]);
    }

    public function test_user_not_enrolled_sees_blocked_message(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse('FB01');

        $response = $this->actingAs($user)->get('/pelatihan/umpan-balik/' . $course->id);

        $response->assertOk();
        $response->assertSee('Belum bisa mengisi umpan balik');
    }

    public function test_locked_feedback_shows_locked_message_even_for_enrolled_user(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse('FB02', feedbackUnlocked: false);
        $this->approveUserForCourse($user, $course);

        $response = $this->actingAs($user)->get('/pelatihan/umpan-balik/' . $course->id);

        $response->assertOk();
        $response->assertSee('Umpan balik belum dibuka');
    }

    public function test_enrolled_user_can_submit_feedback_and_cannot_resubmit(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse('FB03');
        $this->approveUserForCourse($user, $course);
        $question = LmsFeedbackQuestion::create(['question' => 'Bagaimana kualitas pemateri?', 'is_active' => true, 'sort_order' => 1]);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Pelatihan\CourseFeedback::class, ['course' => $course])
            ->call('rate', $question->id, 5)
            ->call('submit');

        $this->assertSame(1, LmsCourseFeedbacks::where('course_id', $course->id)->count());

        $response = $this->actingAs($user)->get('/pelatihan/umpan-balik/' . $course->id);

        $response->assertOk();
        $response->assertSee('Terima kasih!');
        $response->assertDontSee('Kirim Umpan Balik');
    }
}
