<?php

namespace Tests\Feature\Kepegawaian;

use App\Models\Job\JobOpenings;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Services\JobPosition\ManPowerReqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Penambahan Kuota untuk jabatan yang lowongannya sedang berjalan / menunggu dibuka
 * harus menambah kuota lowongan itu (2026-08-29) — lowongan yang sudah ditutup tidak.
 */
class ManPowerReqQuotaBumpTest extends TestCase
{
    use RefreshDatabase;

    public function test_additional_quota_bumps_open_and_waiting_recruitments_only(): void
    {
        Http::fake(); // RecruitmentSync::sync() sekarang direct-DB & non-fatal — fake sisa jaga-jaga

        $company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $jp = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff IT', 'planned_manpower' => 1]);

        $waiting = JobOpenings::create(['company_id' => $company->id, 'job_position_id' => $jp->id, 'quota' => 2]); // opened_at null
        $open = JobOpenings::create(['company_id' => $company->id, 'job_position_id' => $jp->id, 'quota' => 1, 'is_active' => true, 'opened_at' => now()]);
        $closed = JobOpenings::create(['company_id' => $company->id, 'job_position_id' => $jp->id, 'quota' => 5, 'is_active' => false, 'opened_at' => now()]);

        $svc = app(ManPowerReqService::class);
        (new \ReflectionMethod($svc, 'bumpOpenRecruitmentsQuota'))->invoke($svc, $jp->id, 3);

        $this->assertSame(5, $waiting->fresh()->quota);   // 2 + 3
        $this->assertSame(4, $open->fresh()->quota);      // 1 + 3
        $this->assertSame(5, $closed->fresh()->quota);    // tidak disentuh
    }

    public function test_no_openings_no_error(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Co', 'location' => 'X', 'is_active' => true]);
        $jp = JobPositions::create(['company_id' => $company->id, 'name' => 'Staff IT', 'planned_manpower' => 1]);

        $svc = app(ManPowerReqService::class);
        (new \ReflectionMethod($svc, 'bumpOpenRecruitmentsQuota'))->invoke($svc, $jp->id, 3);

        $this->assertSame(0, JobOpenings::count());
    }
}
