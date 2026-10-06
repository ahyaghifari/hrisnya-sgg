<?php

namespace Tests\Feature;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Models\Announcement;
use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User;
use App\Models\User\UserProfiles;
use App\Notifications\AnnouncementPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_saves_targets_then_publish_notifies_only_selected_positions(): void
    {
        $company = Companies::forceCreate(['id' => '01J2F0C8KQ9M7D4S8RBX6E1ZVP', 'code' => 'GLB', 'name' => 'Global', 'location' => 'Global', 'is_active' => true]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

        $dept = OrgStructure::forceCreate(['company_id' => $company->id, 'name' => 'Dept A', 'level' => 'DEPARTMENT']);
        $kept = JobPositions::forceCreate(['company_id' => $company->id, 'org_structure_id' => $dept->id, 'name' => 'Staf', 'status' => 'approved']);
        $skipped = JobPositions::forceCreate(['company_id' => $company->id, 'org_structure_id' => $dept->id, 'name' => 'Kepala', 'status' => 'approved']);

        $receiver = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::forceCreate(['user_id' => $receiver->id, 'job_position_id' => $kept->id]);
        $other = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        UserProfiles::forceCreate(['user_id' => $other->id, 'job_position_id' => $skipped->id]);

        $tree = Announcement::targetTree();
        $this->assertSame([$kept->id, $skipped->id], collect($tree['tree'][0]['leaves'])->sort()->values()->all() ?: []);

        Livewire::actingAs($admin)->test(CreateAnnouncement::class)
            ->fillForm(['title' => 'Libur', 'body' => 'Besok libur', 'job_position_ids' => [$kept->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $announcement = Announcement::firstOrFail();
        $this->assertSame('draft', $announcement->status);
        $this->assertSame([$kept->id], $announcement->jobPositions()->pluck('m_job_positions.id')->all());

        Notification::fake();
        $this->actingAs($admin);
        AnnouncementResource::publish($announcement);

        $this->assertSame('published', $announcement->fresh()->status);
        Notification::assertSentTo($receiver, AnnouncementPublished::class);
        Notification::assertNotSentTo($other, AnnouncementPublished::class);
    }
}
