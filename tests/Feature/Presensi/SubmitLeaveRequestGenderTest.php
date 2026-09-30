<?php

namespace Tests\Feature\Presensi;

use App\Livewire\SubmitLeaveRequest;
use App\Enum\Attendance\LeaveTypeCategoryEnum;
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
    }

    private function loginAs(string $gender): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        UserProfiles::create(['user_id' => $user->id, 'gender' => $gender]);
        $this->be($user);

        return $user;
    }

    /** Cuti Haid DIMATIKAN sementara dari dropdown (instruksi user 2026-09-30) - tidak ikut
     * muncul di sini walau karyawannya perempuan. Lihat SubmitLeaveRequest::getLeaveTypesProperty(). */
    public function test_female_sees_all_leave_types_except_haid(): void
    {
        $this->loginAs('perempuan');

        $names = Livewire::test(SubmitLeaveRequest::class)->get('leaveTypes')->map(fn ($t) => $t->label());

        $this->assertEqualsCanonicalizing(['Cuti Tahunan', 'Cuti Melahirkan', 'Cuti Keguguran'], $names->all());
    }

    /** Cuti Haid ditolak di server juga, bukan cuma disembunyikan dari dropdown - jaga-jaga
     * request yang di-craft manual (pola sama dengan guard female-only). */
    public function test_haid_is_rejected_even_for_female(): void
    {
        $this->loginAs('perempuan');
        $haid = LeaveTypeCategoryEnum::HAID;

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', $haid->value)
            ->set('start_date', now()->toDateString())
            ->call('submit');

        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_male_does_not_see_female_only_leave_types(): void
    {
        $this->loginAs('laki_laki');

        $names = Livewire::test(SubmitLeaveRequest::class)->get('leaveTypes')->map(fn ($t) => $t->label());

        $this->assertSame(['Cuti Tahunan'], $names->values()->all());
    }

    public function test_male_submitting_female_only_type_is_rejected(): void
    {
        $this->loginAs('laki_laki');
        $haid = LeaveTypeCategoryEnum::HAID;

        Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', $haid->value)
            ->set('start_date', now()->toDateString())
            ->call('submit');

        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_special_leave_requires_attachment(): void
    {
        $this->loginAs('perempuan');
        // Keguguran dipakai sebagai contoh "cuti khusus" di sini (bukan Haid - lagi dimatikan
        // sementara, lihat test_haid_is_rejected_even_for_female).
        $keguguran = LeaveTypeCategoryEnum::KEGUGURAN;

        $component = Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', $keguguran->value)
            ->set('start_date', now()->toDateString());

        $this->assertTrue($component->get('requiresAttachment'));

        $component->call('submit')->assertHasErrors(['attachments' => 'required']);

        $this->assertDatabaseCount('attendance_requests', 0);
    }

    public function test_annual_leave_does_not_require_attachment(): void
    {
        $this->loginAs('perempuan');
        $tahunan = LeaveTypeCategoryEnum::TAHUNAN;

        $component = Livewire::test(SubmitLeaveRequest::class)
            ->set('leave_type', $tahunan->value)
            ->set('start_date', now()->toDateString());

        $this->assertFalse($component->get('requiresAttachment'));

        $component->call('submit')->assertHasNoErrors('attachments');
    }
}
