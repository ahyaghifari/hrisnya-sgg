<?php

namespace Tests\Feature;

use App\Livewire\OrganizationStructure;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\User;
use App\Models\User\UserJobPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel detail jabatan (redesign 2026-09-27, ganti x-filament::section bawaan jadi komponen
 * situs sendiri) - cuma smoke test render, blade error (typo var(), class salah) tidak
 * ketangkep php -l, harus benar-benar dirender sekali.
 */
class OrganizationStructureDetailPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_detail_panel_renders_holder_avatar_and_children(): void
    {
        $company = Companies::create(['code' => 'ORG', 'name' => 'Org Co', 'location' => 'Test', 'is_active' => true]);

        $parentJob = JobPositions::create(['company_id' => $company->id, 'name' => 'Kepala Divisi', 'planned_manpower' => 1]);
        $childJob = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff Divisi', 'planned_manpower' => 1, 'parent_id' => $parentJob->id]);

        $holder = User::factory()->create(['name' => 'Budi Santoso', 'company_id' => $company->id]);
        UserJobPositions::create(['user_id' => $holder->id, 'job_position_id' => $parentJob->id, 'start_date' => now(), 'status' => 'active']);

        $viewer = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($viewer);

        Livewire::test(OrganizationStructure::class)
            ->call('openJobDetail', $parentJob->id)
            ->assertOk()
            ->assertSee('Kepala Divisi')
            ->assertSee('Budi Santoso')
            ->assertSee('BS') // inisial x-avatar, holder belum upload foto
            ->assertSee('Staff Divisi');
    }
}
