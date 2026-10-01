<?php

namespace Tests\Feature\MyStaff;

use App\Livewire\MyStaff\CoachingTimWidget;
use App\Livewire\MyStaff\CutiTimWidget;
use App\Livewire\MyStaff\KontrakOrientasiTimWidget;
use App\Livewire\MyStaff\PresensiTimWidget;
use App\Livewire\MyStaff\RingkasanTimWidget;
use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_widgets_render_after_load_with_subordinate_types(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test', 'location' => 'Test', 'is_active' => true]);
        $boss = JobPositions::create(['company_id' => $company->id, 'name' => 'Manager', 'planned_manpower' => 1]);
        $staff = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff', 'planned_manpower' => 1, 'parent_id' => $boss->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $employee = User::factory()->create(['company_id' => $company->id]);
        foreach ([[$manager, $boss], [$employee, $staff]] as [$u, $p]) {
            UserJobPositions::create(['user_id' => $u->id, 'job_position_id' => $p->id, 'start_date' => now()->subYear(), 'status' => 'active']);
        }
        $type = EmployeeTypes::create(['company_id' => $company->id, 'name' => 'Karyawan Tetap', 'category' => 'tetap']);
        UserProfiles::create(['user_id' => $employee->id, 'employee_type_id' => $type->id]);

        $this->actingAs($manager);

        Livewire::test(RingkasanTimWidget::class)->call('load')
            ->assertSee('Karyawan Tetap')->assertSee('100% dari tim');

        Livewire::test(PresensiTimWidget::class)->call('load')
            ->call('openModal', 'not_recorded')
            ->assertSee('Belum Presensi')->assertSee($employee->name);

        foreach ([PresensiTimWidget::class, CutiTimWidget::class, KontrakOrientasiTimWidget::class, CoachingTimWidget::class] as $widget) {
            Livewire::test($widget)->call('load')->assertDontSee('Memuat data tim');
        }
    }
}
