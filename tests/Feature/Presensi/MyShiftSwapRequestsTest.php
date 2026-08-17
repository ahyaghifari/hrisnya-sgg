<?php

namespace Tests\Feature\Presensi;

use App\Livewire\Presensi\MyShiftSwapRequests;
use App\Models\Master\Companies;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyShiftSwapRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_only_shows_own_requests(): void
    {
        $company = Companies::create(['code' => 'LST', 'name' => 'RS List Test', 'location' => 'Test', 'is_active' => true]);
        $me = User::factory()->create(['company_id' => $company->id]);
        $someoneElse = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);

        ShiftSwapRequests::create([
            'company_id' => $company->id,
            'requester_id' => $me->id,
            'target_id' => $target->id,
            'req_schedule_id' => 'fake-schedule-id-1',
            'target_schedule_id' => null,
            'req_date' => '2026-08-20',
            'target_date' => '2026-08-20',
            'status' => 'in_review',
        ]);

        ShiftSwapRequests::create([
            'company_id' => $company->id,
            'requester_id' => $someoneElse->id,
            'target_id' => $target->id,
            'req_schedule_id' => 'fake-schedule-id-2',
            'target_schedule_id' => null,
            'req_date' => '2026-08-21',
            'target_date' => '2026-08-21',
            'status' => 'in_review',
        ]);

        $component = Livewire::actingAs($me)->test(MyShiftSwapRequests::class);

        $requests = $component->get('requests');

        $this->assertCount(1, $requests);
        $this->assertSame($me->id, $requests->first()->requester_id);
    }
}
