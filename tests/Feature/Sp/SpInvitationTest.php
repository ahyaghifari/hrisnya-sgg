<?php

namespace Tests\Feature\Sp;

use App\Enum\CustomPermissionEnum;
use App\Models\Master\Companies;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Sp\SpInvitationService;
use App\Services\Sp\SpReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\SpFixtures;
use Tests\TestCase;

/** Undangan klarifikasi: tahap wajib sebelum Berita Acara; bisa diubah/dibatalkan; 1 undangan = 1 BA. */
class SpInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $hc;
    private User $employee;
    private User $knower;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Companies::create(['code' => 'SPI', 'name' => 'Company SPI', 'location' => 'Test', 'is_active' => true]);
        foreach ([CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->hc = User::factory()->create(['company_id' => $company->id]);
        $this->hc->givePermissionTo(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value, 'Create:EmployeeSp');
        $this->employee = User::factory()->create(['company_id' => $company->id]);
        $this->knower = User::factory()->create(['company_id' => $company->id]);
    }

    private function report(array $data = []): \App\Models\Sp\SpReport
    {
        return app(SpReportService::class)->create($this->hc, $data + ['knows_by' => $this->knower->id, 'kejadian' => 'x', 'dasar_items' => ['d']]);
    }

    public function test_report_requires_an_open_invitation(): void
    {
        $this->expectException(ValidationException::class);

        $this->report();
    }

    public function test_one_invitation_makes_at_most_one_report(): void
    {
        $invitation = SpFixtures::invitation($this->hc, $this->employee);

        $report = $this->report(['invitation_id' => $invitation->id]);
        $this->assertSame($this->employee->id, $report->user_id);

        $this->expectException(ValidationException::class);
        $this->report(['invitation_id' => $invitation->id]);
    }

    public function test_cancelled_invitation_cannot_make_report(): void
    {
        $invitation = SpFixtures::invitation($this->hc, $this->employee);
        app(SpInvitationService::class)->cancel($invitation, $this->hc, 'jadwal bentrok');

        $this->assertTrue($invitation->fresh()->isCancelled());

        $this->expectException(ValidationException::class);
        $this->report(['invitation_id' => $invitation->id]);
    }

    public function test_invitation_can_be_rescheduled_until_report_exists(): void
    {
        $service = app(SpInvitationService::class);
        $invitation = $service->create($this->hc, ['user_id' => $this->employee->id, 'scheduled_at' => now()->addDays(2), 'location' => 'Ruang A']);

        $service->update($invitation, $this->hc, ['scheduled_at' => now()->addDays(3), 'location' => 'Ruang B']);
        $this->assertSame('Ruang B', $invitation->fresh()->location);

        $this->report(['invitation_id' => $invitation->id]);

        $this->expectException(ValidationException::class);
        $service->update($invitation->fresh(), $this->hc, ['scheduled_at' => now()->addDays(4), 'location' => 'Ruang C']);
    }

    public function test_pages_create_show_and_list_invitation(): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ViewAny:EmployeeSp', 'guard_name' => 'web']);
        $this->hc->givePermissionTo('ViewAny:EmployeeSp');

        \Livewire\Livewire::actingAs($this->hc)->test(\App\Livewire\Sp\SpInvitationPage::class)
            ->set('user_id', $this->employee->id)
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('location', 'Ruang HC')
            ->call('save')
            ->assertHasNoErrors();

        $invitation = Invitation::firstOrFail();

        \Livewire\Livewire::actingAs($this->hc)->test(\App\Livewire\Sp\SpInvitationPage::class, ['record' => $invitation])
            ->assertSee('Ruang HC')
            ->assertSee('Buat Berita Acara');

        \Livewire\Livewire::actingAs($this->hc)->test(\App\Livewire\Sp\SpPage::class)
            ->assertSee($this->employee->name)
            ->assertSee('Undangan');
    }

    public function test_invitee_and_supervisor_can_read_but_others_cannot(): void
    {
        $outsider = User::factory()->create(['company_id' => $this->employee->company_id]);
        $invitation = SpFixtures::invitation($this->hc, $this->employee);
        $invitation->update(['supervisor_id' => $this->knower->id, 'location' => 'Ruang HC']);

        foreach ([$this->employee, $this->knower, $this->hc] as $user) {
            \Livewire\Livewire::actingAs($user)->test(\App\Livewire\InvitationViewPage::class, ['record' => $invitation])
                ->assertOk()->assertSee('Ruang HC')->assertDontSee('Buat Berita Acara');
        }

        \Livewire\Livewire::actingAs($outsider)->test(\App\Livewire\InvitationViewPage::class, ['record' => $invitation])->assertForbidden();
    }

    public function test_home_widget_shows_upcoming_invitations_for_invitee_and_supervisor_only(): void
    {
        $supervisor = User::factory()->create(['company_id' => $this->employee->company_id]);
        $other = User::factory()->create(['company_id' => $this->employee->company_id]);

        $upcoming = SpFixtures::invitation($this->hc, $this->employee);
        $upcoming->update(['supervisor_id' => $supervisor->id, 'scheduled_at' => now()->addDays(3), 'location' => 'Ruang Rapat HC', 'note' => 'Klarifikasi keterlambatan Oktober']);

        // Dibatalkan dan sudah lewat tidak tampil.
        SpFixtures::invitation($this->hc, $this->employee)->update(['cancelled_at' => now(), 'location' => 'Batal-X']);
        SpFixtures::invitation($this->hc, $this->employee)->update(['scheduled_at' => now()->subDay(), 'location' => 'Lewat-X']);

        foreach ([$this->employee, $supervisor] as $user) {
            \Livewire\Livewire::actingAs($user)->test(\App\Livewire\InvitationsCard::class)
                ->assertSee('Undangan Klarifikasi')->assertSee('Ruang Rapat HC')->assertSee('Klarifikasi keterlambatan Oktober')->assertSee('3 hari lagi')
                ->assertDontSee('Batal-X')->assertDontSee('Lewat-X');
        }

        \Livewire\Livewire::actingAs($other)->test(\App\Livewire\InvitationsCard::class)->assertDontSee('Ruang Rapat HC');
    }

    public function test_cancel_requires_reason(): void
    {
        $invitation = SpFixtures::invitation($this->hc, $this->employee);

        $this->expectException(ValidationException::class);
        app(SpInvitationService::class)->cancel($invitation, $this->hc, '');
    }
}
