<?php

namespace Tests\Feature\Presensi;

use App\Livewire\SubmitLeaveRequest;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Cuti haid/melahirkan/keguguran (LeaveTypeCategoryEnum::isFemaleOnly) cuma buat perempuan. */
class SubmitLeaveRequestGenderTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);

        LeaveTypes::create(['company_id' => $this->company->id, 'name' => 'Cuti Tahunan', 'category' => 'tahunan', 'quota_days' => 12, 'reset_period' => 'yearly', 'is_paid' => true]);
        LeaveTypes::create(['company_id' => $this->company->id, 'name' => 'Cuti Haid', 'category' => 'haid', 'quota_days' => 2, 'reset_period' => 'monthly', 'is_paid' => true]);
        LeaveTypes::create(['company_id' => $this->company->id, 'name' => 'Cuti Melahirkan', 'category' => 'melahirkan', 'quota_days' => 90, 'reset_period' => 'none', 'is_paid' => true]);
    }

    private function loginAs(string $gender): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $user->id, 'gender' => $gender]);
        $this->be($user);

        return $user;
    }

    public function test_female_sees_all_leave_types(): void
    {
        $this->loginAs('perempuan');

        $names = Livewire::test(SubmitLeaveRequest::class)->get('leaveTypes')->pluck('name');

        $this->assertEqualsCanonicalizing(['Cuti Tahunan', 'Cuti Haid', 'Cuti Melahirkan'], $names->all());
    }

    public function test_male_does_not_see_female_only_leave_types(): void
    {
        $this->loginAs('laki_laki');

        $names = Livewire::test(SubmitLeaveRequest::class)->get('leaveTypes')->pluck('name');

        $this->assertSame(['Cuti Tahunan'], $names->values()->all());
    }

    public function test_male_submitting_female_only_type_is_rejected(): void
    {
        $this->loginAs('laki_laki');
        $haid = LeaveTypes::where('category', 'haid')->first();

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $haid->id)
            ->set('start_date', now()->toDateString())
            ->call('submit');

        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_special_leave_requires_attachment(): void
    {
        $this->loginAs('perempuan');
        $haid = LeaveTypes::where('category', 'haid')->first();

        $component = Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $haid->id)
            ->set('start_date', now()->toDateString());

        $this->assertTrue($component->get('requiresAttachment'));

        $component->call('submit')->assertHasErrors(['attachments' => 'required']);

        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_annual_leave_does_not_require_attachment(): void
    {
        $this->loginAs('perempuan');
        $tahunan = LeaveTypes::where('category', 'tahunan')->first();

        $component = Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type_id', $tahunan->id)
            ->set('start_date', now()->toDateString());

        $this->assertFalse($component->get('requiresAttachment'));

        $component->call('submit')->assertHasNoErrors('attachments');
    }
}
