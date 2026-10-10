<?php

namespace Tests\Feature\LMS;

use App\Enum\CourseUserStatus;
use App\Enum\CustomPermissionEnum;
use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCourses;
use App\Models\LMS\LmsCourseUsers;
use App\Models\User;
use App\Services\LMS\CourseUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CourseUserNotCompletedTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $permission = Permission::findOrCreate(CustomPermissionEnum::EDIT_DETAIL_KURSUS_LMS->value, 'web');
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function participant(): LmsCourseUsers
    {
        $course = LmsCourses::create(['code' => 'NC-1', 'title' => 'Kursus', 'status' => LsmCourseStatus::Published->value]);

        return LmsCourseUsers::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'status' => CourseUserStatus::IN_PROGRESS->value,
            'progress' => 0,
            'score' => 0,
            'enrolled_at' => now(),
        ]);
    }

    public function test_mark_not_completed_stores_reason_and_unmark_clears_it(): void
    {
        $service = app(CourseUserService::class);
        $participant = $this->participant();

        $service->markNotCompleted($participant, $this->actor(), 'Tidak hadir');

        $participant->refresh();
        $this->assertSame(CourseUserStatus::NOT_COMPLETED->value, $participant->status);
        $this->assertSame('Tidak hadir', $participant->not_completed_reason);

        $service->unmarkCompleted($participant, $this->actor());

        $participant->refresh();
        $this->assertSame(CourseUserStatus::APPROVED->value, $participant->status);
        $this->assertNull($participant->not_completed_reason);
    }

    public function test_reason_is_required(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(CourseUserService::class)->markNotCompleted($this->participant(), $this->actor(), '   ');
    }
}
