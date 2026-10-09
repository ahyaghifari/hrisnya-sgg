<?php

namespace Tests\Feature\Coaching;

use App\Enum\CustomPermissionEnum;
use App\Enum\InvitationType;
use App\Livewire\Coaching\CoachingInvitationPage;
use App\Livewire\InvitationsCard;
use App\Livewire\InvitationViewPage;
use App\Models\Invitation;
use App\Models\Master\Companies;
use App\Models\User;
use App\Notifications\SpReportUpdated;
use App\Services\Coaching\CoachingInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Undangan coaching: coach mengundang coachee (tabel invitations, type COACHING). */
class CoachingInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private User $coachee;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Companies::create(['code' => 'COA', 'name' => 'Company COA', 'location' => 'Test', 'is_active' => true]);
        $perms = [CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:Coaching'];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->coach = User::factory()->create(['company_id' => $company->id]);
        $this->coach->givePermissionTo($perms); // akses penuh coaching
        $this->coachee = User::factory()->create(['company_id' => $company->id]);
        $this->outsider = User::factory()->create(['company_id' => $company->id]);
    }

    private function invite(): Invitation
    {
        return app(CoachingInvitationService::class)->create($this->coach, [
            'user_id' => $this->coachee->id, 'scheduled_at' => now()->addDays(2), 'location' => 'Ruang Meeting', 'note' => 'Evaluasi ketepatan waktu',
        ]);
    }

    public function test_coach_invites_coachee_who_is_notified_and_sees_widget(): void
    {
        Notification::fake();

        $invitation = $this->invite();

        $this->assertSame(InvitationType::COACHING, $invitation->type);
        $this->assertNull($invitation->supervisor_id);
        Notification::assertSentTo($this->coachee, SpReportUpdated::class);

        Livewire::actingAs($this->coachee)->test(InvitationsCard::class)
            ->assertSee('Undangan Coaching')->assertSee('Ruang Meeting')->assertSee('2 hari lagi');
        Livewire::actingAs($this->coachee)->test(InvitationViewPage::class, ['record' => $invitation])
            ->assertOk()->assertSee('Evaluasi ketepatan waktu')->assertSee('Terjadwal');
    }

    public function test_supervisor_is_invited_only_when_chosen(): void
    {
        Notification::fake();

        $supervisor = User::factory()->create(['company_id' => $this->coach->company_id]);
        $this->mock(\App\Services\SikartapReviewerResolver::class, fn ($mock) => $mock->shouldReceive('directSupervisorOf')->andReturn($supervisor));

        $service = app(CoachingInvitationService::class);
        $data = ['user_id' => $this->coachee->id, 'scheduled_at' => now()->addDay(), 'location' => 'Aula'];

        $without = $service->create($this->coach, $data);
        $this->assertNull($without->supervisor_id);
        Notification::assertNotSentTo($supervisor, SpReportUpdated::class);

        $with = $service->create($this->coach, $data + ['with_supervisor' => true]);
        $this->assertSame($supervisor->id, $with->supervisor_id);
        Notification::assertSentTo($supervisor, SpReportUpdated::class);

        Livewire::actingAs($supervisor)->test(InvitationsCard::class)
            ->assertSee('Undangan Coaching')->assertSee($this->coachee->name);
        Livewire::actingAs($supervisor)->test(InvitationViewPage::class, ['record' => $with])->assertOk()->assertSee('Atasan langsung');

        // Diubah jadi tanpa atasan: supervisor_id dikosongkan.
        $service->update($with, $this->coach, ['scheduled_at' => now()->addDays(2), 'location' => 'Aula', 'with_supervisor' => false]);
        $this->assertNull($with->fresh()->supervisor_id);
    }

    public function test_supervisor_equal_to_the_coach_is_not_invited_twice(): void
    {
        $this->mock(\App\Services\SikartapReviewerResolver::class, fn ($mock) => $mock->shouldReceive('directSupervisorOf')->andReturn($this->coach));

        $invitation = app(CoachingInvitationService::class)->create($this->coach, [
            'user_id' => $this->coachee->id, 'scheduled_at' => now()->addDay(), 'location' => 'Aula', 'with_supervisor' => true,
        ]);

        $this->assertNull($invitation->supervisor_id);
    }

    public function test_coaching_created_from_invitation_is_linked_and_closes_the_invitation(): void
    {
        Notification::fake();
        $invitation = $this->invite();

        $page = Livewire::actingAs($this->coach)->withQueryParams(['invitation_id' => $invitation->id])
            ->test(\App\Livewire\Coaching\CreateCoachingPage::class)
            ->assertSet('user_id', $this->coachee->id)
            ->assertSet('coach_by', $this->coach->id)
            ->assertSee('Dari undangan coaching');

        $page->set('title', 'Coaching ketepatan waktu')
            ->set('goals', 'g')->set('employee_condition', 'c')->set('solution', 's')->set('improvement_action', 'a')
            ->call('save')
            ->assertHasNoErrors();

        $coaching = \App\Models\Coaching\Coaching::firstOrFail();
        $this->assertSame($invitation->id, $coaching->invitation_id);
        $this->assertFalse($invitation->fresh()->isOpen());

        // Undangan selesai: hilang dari widget, tidak bisa diubah/dibatalkan, dan tidak bisa dipakai lagi.
        Livewire::actingAs($this->coachee)->test(InvitationsCard::class)->assertDontSee('Ruang Meeting');
        $this->assertFalse(app(CoachingInvitationService::class)->canManage($invitation->fresh(), $this->coach));
        Livewire::actingAs($this->coach)->test(CoachingInvitationPage::class, ['record' => $invitation->fresh()])
            ->assertSee('Coaching dibuat')->assertSee('Lihat Coaching');
        Livewire::actingAs($this->coach)->withQueryParams(['invitation_id' => $invitation->id])
            ->test(\App\Livewire\Coaching\CreateCoachingPage::class)->assertNotFound();
    }

    public function test_coaching_without_invitation_still_works_and_others_cannot_use_an_invitation(): void
    {
        Notification::fake();
        $invitation = $this->invite();

        $otherCoach = User::factory()->create(['company_id' => $this->coach->company_id]);
        $otherCoach->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);
        // tanpa akses penuh & bukan pembuat undangan
        Livewire::actingAs($otherCoach)->withQueryParams(['invitation_id' => $invitation->id])
            ->test(\App\Livewire\Coaching\CreateCoachingPage::class)->assertForbidden();

        Livewire::actingAs($this->coach)->withQueryParams([])->test(\App\Livewire\Coaching\CreateCoachingPage::class) // query param test sebelumnya tidak ikut
            ->set('user_id', $this->coachee->id)->set('coach_by', $this->coach->id)
            ->set('title', 'Tanpa undangan')->set('goals', 'g')->set('employee_condition', 'c')->set('solution', 's')->set('improvement_action', 'a')
            ->call('save')->assertHasNoErrors();

        $this->assertNull(\App\Models\Coaching\Coaching::firstOrFail()->invitation_id);
        $this->assertTrue($invitation->fresh()->isOpen());
    }

    public function test_user_without_coaching_access_cannot_invite(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(CoachingInvitationService::class)->create($this->outsider, [
            'user_id' => $this->coachee->id, 'scheduled_at' => now()->addDay(), 'location' => 'X',
        ]);
    }

    public function test_only_creator_or_full_access_can_cancel_and_cancel_hides_widget(): void
    {
        $invitation = $this->invite();
        $service = app(CoachingInvitationService::class);

        $this->assertFalse($service->canManage($invitation, $this->outsider));
        $this->assertTrue($service->canManage($invitation, $this->coach));

        $service->cancel($invitation, $this->coach, 'Coach berhalangan');
        $this->assertTrue($invitation->fresh()->isCancelled());

        Livewire::actingAs($this->coachee)->test(InvitationsCard::class)->assertDontSee('Ruang Meeting');
    }

    public function test_coaching_invitation_pages_create_edit_and_block_outsiders(): void
    {
        Livewire::actingAs($this->coach)->test(CoachingInvitationPage::class)
            ->set('user_id', $this->coachee->id)
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('location', 'Aula')
            ->set('note', 'Topik A')
            ->call('save')
            ->assertHasNoErrors();

        $invitation = Invitation::ofType(InvitationType::COACHING)->firstOrFail();

        Livewire::actingAs($this->coach)->test(CoachingInvitationPage::class, ['record' => $invitation])
            ->assertSee('Aula')->assertSee('Buat Coaching')
            ->call('edit')->set('location', 'Aula Baru')->call('save')->assertHasNoErrors();
        $this->assertSame('Aula Baru', $invitation->fresh()->location);

        Livewire::actingAs($this->outsider)->test(CoachingInvitationPage::class)->assertForbidden();
    }
}