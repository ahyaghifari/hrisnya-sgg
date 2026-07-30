<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Master\Companies;
use App\Models\User;
use App\Models\User\UserProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LogsFieldAuditChanges dipasang di User & UserProfiles supaya SETIAP field berubah (siapa pun
 * yang mengubah - self-service Livewire, admin Filament, dst) tercatat 1 baris audit_logs per
 * field, bukan cuma dari 1 jalur nulis tertentu (hook-nya di level Eloquent event).
 */
class LogsFieldAuditChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_watched_profile_field_writes_one_audit_log_row(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $profile = UserProfiles::create(['user_id' => $staff->id, 'no_nik' => 'lama']);

        $actor = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($actor);

        $profile->update(['no_nik' => 'baru']);

        $log = AuditLog::where('key', $staff->id . ':no_nik')->first();

        $this->assertNotNull($log);
        $this->assertSame('data_karyawan', $log->module);
        $this->assertSame('No. NIK', $log->label);
        $this->assertSame('lama', $log->old_value);
        $this->assertSame('baru', $log->new_value);
        $this->assertSame($actor->id, $log->user_id);
    }

    public function test_updating_multiple_watched_fields_writes_one_row_each(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $profile = UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        $profile->update(['nickname' => 'Budi', 'phone' => '0812xxxx']);

        $this->assertTrue(AuditLog::where('key', $staff->id . ':nickname')->exists());
        $this->assertTrue(AuditLog::where('key', $staff->id . ':phone')->exists());
    }

    public function test_updating_an_unwatched_field_writes_nothing(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $profile = UserProfiles::create(['user_id' => $staff->id]);

        $this->actingAs($staff);

        $profile->update(['profile_picture' => ['some/path.jpg']]);

        $this->assertSame(0, AuditLog::where('key', 'like', $staff->id . ':%')->count());
    }

    public function test_saving_without_actual_change_writes_nothing(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);
        $profile = UserProfiles::create(['user_id' => $staff->id, 'nickname' => 'Sama']);

        $this->actingAs($staff);

        $profile->update(['nickname' => 'Sama']);

        $this->assertSame(0, AuditLog::where('key', $staff->id . ':nickname')->count());
    }

    public function test_creating_a_profile_writes_nothing(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($staff);

        UserProfiles::create(['user_id' => $staff->id, 'nickname' => 'Baru']);

        $this->assertSame(0, AuditLog::where('key', 'like', $staff->id . ':%')->count());
    }

    public function test_updating_email_on_user_model_is_logged(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'email' => 'lama@syifaglobal.com']);

        $this->actingAs($staff);

        $staff->update(['email' => 'baru@syifaglobal.com']);

        $log = AuditLog::where('key', $staff->id . ':email')->first();

        $this->assertNotNull($log);
        $this->assertSame('Email', $log->label);
        $this->assertSame('lama@syifaglobal.com', $log->old_value);
        $this->assertSame('baru@syifaglobal.com', $log->new_value);
    }

    public function test_updating_password_is_not_logged(): void
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($staff);

        $staff->update(['password' => bcrypt('secret-baru')]);

        $this->assertSame(0, AuditLog::where('key', 'like', $staff->id . ':%')->count());
    }
}
