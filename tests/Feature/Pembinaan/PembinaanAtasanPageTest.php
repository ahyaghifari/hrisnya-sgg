<?php

namespace Tests\Feature\Pembinaan;

use App\Livewire\Pembinaan\PembinaanAtasanPage;
use App\Models\Coaching\Coaching;
use App\Models\Counseling\Counseling;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman TAMBAHAN "Pembinaan (Sebagai Atasan)" (instruksi user 2026-08-17) - gate literal
 * hasSubordinates(), TIDAK butuh permission modul SISTEM_PEMBINAAN_KARYAWAN sama sekali, dan
 * cuma nampilin record yang coach_by/counselor_id-nya = user yang login (bukan company-wide,
 * beda dari CoachingPage/CounselingManagePage).
 */
class PembinaanAtasanPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeManagerWithSubordinate(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $managerPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staffPosition = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $managerPosition->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        UserJobPositions::create([
            'user_id' => $manager->id, 'job_position_id' => $managerPosition->id,
            'start_date' => now()->subYear(), 'status' => 'active',
        ]);

        $subordinate = User::factory()->create(['company_id' => $company->id]);
        UserJobPositions::create([
            'user_id' => $subordinate->id, 'job_position_id' => $staffPosition->id,
            'start_date' => now()->subYear(), 'status' => 'active',
        ]);

        return [$manager, $subordinate, $company];
    }

    public function test_user_without_subordinates_is_forbidden_even_if_coach_or_counselor(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $someone = User::factory()->create(['company_id' => $company->id]);

        Coaching::create([
            'company_id' => $company->id, 'user_id' => $someone->id, 'coach_by' => $user->id,
            'title' => 'Coaching', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);

        Livewire::actingAs($user)
            ->test(PembinaanAtasanPage::class)
            ->assertForbidden();
    }

    public function test_manager_with_subordinates_can_open_page_without_any_module_permission(): void
    {
        [$manager, , $company] = $this->makeManagerWithSubordinate();

        Livewire::actingAs($manager)
            ->test(PembinaanAtasanPage::class)
            ->assertOk();
    }

    public function test_coaching_tab_only_shows_records_where_user_is_the_coach(): void
    {
        [$manager, $subordinate, $company] = $this->makeManagerWithSubordinate();
        $otherCoach = User::factory()->create(['company_id' => $company->id]);

        Coaching::create([
            'company_id' => $company->id, 'user_id' => $subordinate->id, 'coach_by' => $manager->id,
            'title' => 'Coaching milik saya', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);
        Coaching::create([
            'company_id' => $company->id, 'user_id' => $subordinate->id, 'coach_by' => $otherCoach->id,
            'title' => 'Coaching orang lain', 'improvement_due_date' => now()->addWeek(), 'status' => 'in_progress',
        ]);

        Livewire::actingAs($manager)
            ->test(PembinaanAtasanPage::class)
            ->assertSee('Coaching milik saya')
            ->assertDontSee('Coaching orang lain');
    }

    public function test_counseling_tab_only_shows_records_where_user_is_the_counselor(): void
    {
        [$manager, $subordinate, $company] = $this->makeManagerWithSubordinate();
        $otherCounselor = User::factory()->create(['company_id' => $company->id]);

        Counseling::create([
            'user_id' => $subordinate->id, 'counselor_id' => $manager->id,
            'issue' => 'Keluhan A', 'status' => 'pending', 'is_secret' => false,
        ]);
        Counseling::create([
            'user_id' => $subordinate->id, 'counselor_id' => $otherCounselor->id,
            'issue' => 'Keluhan B', 'status' => 'pending', 'is_secret' => false,
        ]);

        Livewire::actingAs($manager)
            ->test(PembinaanAtasanPage::class)
            ->set('activeTab', 'counseling')
            ->assertSee('Keluhan A')
            ->assertDontSee('Keluhan B');
    }
}
