<?php

namespace Tests\Feature\Presensi;

use App\Livewire\ShiftSwapTargetResponseCard;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\Shift\ShiftSwapRequests;
use App\Models\User;
use App\Services\Shift\ShiftSwapRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftSwapTargetResponseCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeSwapRequest(Companies $company, User $requester, User $target): ShiftSwapRequests
    {
        return ShiftSwapRequests::create([
            'company_id' => $company->id,
            'requester_id' => $requester->id,
            'target_id' => $target->id,
            'req_schedule_id' => 'fake-schedule-id',
            'target_schedule_id' => null,
            'req_date' => '2026-08-20',
            'target_date' => '2026-08-20',
            'status' => 'in_review',
        ]);
    }

    public function test_card_empty_when_setting_off(): void
    {
        $company = Companies::create(['code' => 'CRD', 'name' => 'RS Card Test', 'location' => 'Test', 'is_active' => true]);
        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);
        $this->makeSwapRequest($company, $requester, $target);

        $component = Livewire::actingAs($target)->test(ShiftSwapTargetResponseCard::class);

        $this->assertCount(0, $component->get('pendingResponses'));
    }

    public function test_card_shows_only_own_pending_responses_when_setting_on(): void
    {
        $company = Companies::create(['code' => 'CRD2', 'name' => 'RS Card Test 2', 'location' => 'Test', 'is_active' => true]);
        Setting::create([
            'company_id' => $company->id,
            'module' => 'attendance',
            'key' => ShiftSwapRequestService::ASK_TARGET_RESPONSE_SETTING_KEY,
            'type' => 'boolean',
            'value' => '1',
            'label' => 'Minta Tanggapan Rekan buat Tukar Shift',
        ]);

        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);
        $otherTarget = User::factory()->create(['company_id' => $company->id]);

        $mine = $this->makeSwapRequest($company, $requester, $target);
        $this->makeSwapRequest($company, $requester, $otherTarget);

        $component = Livewire::actingAs($target)->test(ShiftSwapTargetResponseCard::class);
        $pending = $component->get('pendingResponses');

        $this->assertCount(1, $pending);
        $this->assertSame($mine->id, $pending->first()->id);
    }

    public function test_respond_removes_row_from_card(): void
    {
        $company = Companies::create(['code' => 'CRD3', 'name' => 'RS Card Test 3', 'location' => 'Test', 'is_active' => true]);
        Setting::create([
            'company_id' => $company->id,
            'module' => 'attendance',
            'key' => ShiftSwapRequestService::ASK_TARGET_RESPONSE_SETTING_KEY,
            'type' => 'boolean',
            'value' => '1',
            'label' => 'Minta Tanggapan Rekan buat Tukar Shift',
        ]);

        $requester = User::factory()->create(['company_id' => $company->id]);
        $target = User::factory()->create(['company_id' => $company->id]);
        $swap = $this->makeSwapRequest($company, $requester, $target);

        Livewire::actingAs($target)
            ->test(ShiftSwapTargetResponseCard::class)
            ->call('respond', $swap->id, 'accepted')
            ->assertCount('pendingResponses', 0);

        $this->assertSame('accepted', $swap->fresh()->target_response);
    }
}
