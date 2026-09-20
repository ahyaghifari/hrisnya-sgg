<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Enum\ScheduleType;
use App\Exports\Presensi\ScheduleShiftRosterExport;
use App\Livewire\Presensi\ScheduleShiftTeamTab;
use App\Models\Master\Companies;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftTeam;
use App\Models\Shift\ScheduleShiftTeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleShiftTeamTabTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function scheduleShiftTeamPermissions(): array
    {
        return ['ViewAny:ScheduleShiftTeam', 'View:ScheduleShiftTeam', 'Create:ScheduleShiftTeam', 'Update:ScheduleShiftTeam', 'Delete:ScheduleShiftTeam'];
    }

    private function actingAsKepalaRuangan(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        foreach ($this->scheduleShiftTeamPermissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Kepala Ruangan Team Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, ...$this->scheduleShiftTeamPermissions(), 'View:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsScheduleOwner(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        foreach ($this->scheduleShiftTeamPermissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Schedule Owner Team Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, ...$this->scheduleShiftTeamPermissions(), 'View:Schedule', 'Update:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Bisa lihat jadwal (tab Regu ikut kebuka, sama siblingnya), tapi TANPA permission
     * ScheduleShiftTeam apa pun — buat verifikasi Policy beneran menolak aksi kelola. */
    private function actingAsViewerOnly(): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Viewer Only Team Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'View:Schedule']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeScheduleWithEmployee(): array
    {
        $company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Shift Regu',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
        ]);
        $employee = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi Santoso']);
        ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $employee->id, 'effective_from' => now()->startOfMonth()]);

        return [$schedule, $employee];
    }

    /** Saklar aktif/nonaktif Tim cuma untuk pemegang Update:Schedule - pengelola Tim biasa
     * (Create:ScheduleShiftTeam) tidak melihat tombolnya dan aksinya ditolak. */
    public function test_team_manager_without_update_schedule_cannot_toggle_teams(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsKepalaRuangan();

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->assertDontSee('Aktifkan Tim')
            ->call('activateTeams')
            ->assertForbidden();

        $this->assertFalse($schedule->fresh()->use_teams);

        $schedule->update(['use_teams' => true]);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule->fresh()])
            ->assertSee('Tambah Tim')
            ->assertDontSee('Nonaktifkan')
            ->call('deactivateTeams')
            ->assertForbidden();

        $this->assertTrue($schedule->fresh()->use_teams);
    }

    public function test_use_teams_defaults_off_and_schedule_owner_can_toggle(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsScheduleOwner();

        $this->assertFalse($schedule->fresh()->use_teams);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->assertSee('belum memakai Tim')
            ->call('activateTeams');

        $this->assertTrue($schedule->fresh()->use_teams);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule->fresh()])
            ->assertDontSee('belum memakai Tim')
            ->call('deactivateTeams');

        $this->assertFalse($schedule->fresh()->use_teams);
    }

    public function test_kepala_ruangan_can_create_team_via_modal(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsKepalaRuangan();

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->call('openCreateTeamModal')
            ->assertSet('showTeamModal', true)
            ->assertSet('editingTeamId', null)
            ->set('teamFormName', 'Regu A')
            ->set('teamFormColor', '#ff0000')
            ->call('saveTeam')
            ->assertHasNoErrors()
            ->assertSet('showTeamModal', false);

        $this->assertDatabaseHas('schedule_shift_teams', ['schedule_id' => $schedule->id, 'name' => 'Regu A', 'color' => '#ff0000']);
    }

    /** Role punya Create/Update:ScheduleShiftTeam tapi TIDAK Delete - tombol edit muncul (boleh),
     * tombol hapus TIDAK boleh muncul (2026-09-14, laporan user: tombol hapus kelihatan padahal
     * izin Delete belum di-grant - dulu edit & hapus dua-duanya cuma dicek canManage/Create,
     * bukan permission masing-masing). */
    public function test_role_without_delete_permission_does_not_see_delete_button(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:ScheduleShiftTeam', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Create:ScheduleShiftTeam', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:ScheduleShiftTeam', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'No Delete Team Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'View:Schedule', 'ViewAny:ScheduleShiftTeam', 'Create:ScheduleShiftTeam', 'Update:ScheduleShiftTeam']);
        $user = User::factory()->create();
        $user->assignRole($role);

        [$schedule] = $this->makeScheduleWithEmployee();
        $schedule->update(['use_teams' => true]);
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->assertSeeHtml('openEditTeamModal')
            ->assertDontSeeHtml('deleteTeam(');

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->call('deleteTeam', $team->id)
            ->assertForbidden();

        $this->assertDatabaseHas('schedule_shift_teams', ['id' => $team->id]);
    }

    public function test_kepala_ruangan_can_edit_team_via_modal(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsKepalaRuangan();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu Lama', 'color' => '#000000']);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->call('openEditTeamModal', $team->id)
            ->assertSet('editingTeamId', $team->id)
            ->assertSet('teamFormName', 'Regu Lama')
            ->set('teamFormName', 'Regu Baru')
            ->call('saveTeam')
            ->assertHasNoErrors();

        $this->assertSame('Regu Baru', $team->fresh()->name);
    }

    public function test_assign_member_current_month_resolves_immediately(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsKepalaRuangan();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set("assignForm.{$employee->id}", $team->id)
            ->call('assignMember', $employee->id);

        $resolved = ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, now()->format('Y-m'));
        $this->assertSame($team->id, $resolved?->team_id);
    }

    public function test_changing_current_month_does_not_touch_next_month(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsScheduleOwner();

        $teamA = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $teamB = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu B']);

        $currentPeriod = now()->format('Y-m');
        $nextPeriod = now()->addMonthNoOverflow()->format('Y-m');

        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $teamA, $currentPeriod, $user->id);
        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $teamB, $nextPeriod, $user->id);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('period', $currentPeriod)
            ->set("assignForm.{$employee->id}", $teamB->id)
            ->call('assignMember', $employee->id);

        // Bulan berjalan ikut berubah ke Regu B...
        $this->assertSame($teamB->id, ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $currentPeriod)?->team_id);
        // ...tapi bulan depan yang sudah diatur duluan TIDAK ikut berubah.
        $this->assertSame($teamB->id, ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $nextPeriod)?->team_id);
    }

    public function test_kepala_ruangan_cannot_edit_past_month(): void
    {
        // Diperjalankan ke bulan sesudah floor 2026-09 (ScheduleShiftTeamTab::MIN_PERIOD) - biar
        // "bulan lalu" beneran mengetes gerbang canEditPeriod, bukan malah kena clamp floor
        // (instruksi user 2026-09-13: periode di bawah 2026-09 tidak boleh dibuka sama sekali).
        \Illuminate\Support\Carbon::setTestNow('2026-11-15');

        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsKepalaRuangan();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Tim A']);
        $pastPeriod = now()->subMonthNoOverflow()->format('Y-m');

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('period', $pastPeriod)
            ->set("assignForm.{$employee->id}", $team->id)
            ->call('assignMember', $employee->id)
            ->assertForbidden();

        $this->assertNull(ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $pastPeriod));

        \Illuminate\Support\Carbon::setTestNow();
    }

    /** actingAsViewerOnly() tidak punya permission ScheduleShiftTeam apa pun - sejak 2026-09-21
     * tab Tim terbuka untuk siapa pun yang boleh melihat jadwal (ViewAny:ScheduleShiftTeam tidak
     * dicek lagi), tapi semua aksi kelola tetap ditolak. */
    public function test_viewer_without_permission_can_open_team_tab_but_not_manage(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $schedule->update(['use_teams' => true]);
        ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $user = $this->actingAsViewerOnly();

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->assertOk()
            ->assertSee('Regu A')
            ->assertDontSee('Tambah Tim');

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('teamFormName', 'Regu B')
            ->call('saveTeam')
            ->assertForbidden();

        $this->assertDatabaseMissing('schedule_shift_teams', ['schedule_id' => $schedule->id, 'name' => 'Regu B']);
    }

    /** Role yang PUNYA ViewAny/View:ScheduleShiftTeam tapi TIDAK Create/Update/Delete - bisa
     * buka tab & lihat daftar Tim beserta jatah shift-nya (read-only, instruksi user 2026-09-14),
     * tapi tombol kelola (Tambah/Edit/Hapus) tidak muncul dan aksinya ditolak kalau di-craft. */
    public function test_view_only_role_can_see_teams_but_cannot_manage(): void
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::SISTEM_ABSENSI->value, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Schedule', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:ScheduleShiftTeam', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:ScheduleShiftTeam', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'View Only Team Test', 'guard_name' => 'web']);
        $role->givePermissionTo([CustomPermissionEnum::SISTEM_ABSENSI->value, 'View:Schedule', 'ViewAny:ScheduleShiftTeam', 'View:ScheduleShiftTeam']);
        $user = User::factory()->create();
        $user->assignRole($role);

        [$schedule] = $this->makeScheduleWithEmployee();
        $schedule->update(['use_teams' => true]);
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);

        $component = Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->assertOk()
            ->assertSee('Regu A')
            ->assertDontSee('Tambah Tim');

        $this->assertFalse($component->instance()->canManage);

        // Instance BARU per aksi - Testable Livewire tidak bisa dipakai lanjut ->call() lagi
        // sesudah 1 aksi kena forbidden (snapshot-nya jadi tidak valid buat request berikutnya).
        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('teamFormName', 'Regu B')
            ->call('saveTeam')
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->call('deleteTeam', $team->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('schedule_shift_teams', ['schedule_id' => $schedule->id, 'name' => 'Regu B']);
        $this->assertDatabaseHas('schedule_shift_teams', ['id' => $team->id]);
    }

    public function test_viewer_without_permission_cannot_delete_team(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $viewer = $this->actingAsViewerOnly();

        Livewire::actingAs($viewer)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->call('deleteTeam', $team->id)
            ->assertForbidden();

        $this->assertDatabaseHas('schedule_shift_teams', ['id' => $team->id]);
    }

    /** Ganti tim langsung dari tabel Detail Penjadwalan (tombol di samping nama): bulan berjalan +
     * bulan depan boleh, 2+ bulan ke depan dan bulan lampau tidak, dan wajib Create:ScheduleShiftTeam. */
    public function test_roster_tab_can_change_team_for_current_and_next_month_only(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-15');

        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $schedule->update(['use_teams' => true]);
        $user = $this->actingAsKepalaRuangan();
        $teamA = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $teamB = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu B']);
        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $teamA, '2026-10', $user->id);

        $roster = fn (string $period) => Livewire::actingAs($user)
            ->test(\App\Livewire\Presensi\ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->set('period', $period);

        // Bulan berjalan: tombol muncul, ganti ke Regu B.
        $roster('2026-10')
            ->assertSeeHtml('wire:click="openTeamPicker(\'' . $employee->id . '\')"')
            ->call('openTeamPicker', $employee->id)
            ->assertSee('Ganti Tim')
            ->call('setMemberTeam', $teamB->id);
        $this->assertSame($teamB->id, ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, '2026-10')->team_id);

        // Bulan depan: boleh; "Tanpa Tim" melepas keanggotaan bulan itu.
        $roster('2026-11')
            ->call('openTeamPicker', $employee->id)
            ->call('setMemberTeam', '');
        $this->assertNull(ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, '2026-11'));

        // 2 bulan ke depan: tombol hilang dan aksinya ditolak.
        $roster('2026-12')
            ->assertDontSeeHtml('openTeamPicker')
            ->call('openTeamPicker', $employee->id)
            ->assertForbidden();

        // Bulan lampau: ditolak juga.
        $roster('2026-09')
            ->assertDontSeeHtml('openTeamPicker')
            ->call('openTeamPicker', $employee->id)
            ->assertForbidden();

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_roster_tab_change_team_requires_create_team_permission_and_not_edit_mode(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $schedule->update(['use_teams' => true]);
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);

        // Tanpa Create:ScheduleShiftTeam (viewer biasa): tombol tidak ada, aksi ditolak.
        Livewire::actingAs($this->actingAsViewerOnly())
            ->test(\App\Livewire\Presensi\ScheduleShiftRosterTab::class, ['schedule' => $schedule])
            ->assertDontSeeHtml('openTeamPicker')
            ->call('openTeamPicker', $employee->id)
            ->assertForbidden();

        // Pemegang izin, tapi selama mode isi jadwal (editMode) tombolnya dimatikan.
        $user = $this->actingAsKepalaRuangan();
        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Presensi\ScheduleShiftRosterTab::class, ['schedule' => $schedule]);
        $this->assertTrue($component->instance()->canChangeTeam);
        $component->set('editMode', true);
        $this->assertFalse($component->instance()->canChangeTeam);
        $this->assertNull(ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, now()->format('Y-m')));
    }

    /** Urutan roster: tanggal bergabung terlama dulu (di dalam tim maupun tidak), yang belum
     * punya tanggal di paling bawah, nama (tanpa peduli huruf besar/kecil) sebagai pembeda. */
    public function test_roster_orders_by_joining_date_oldest_first_with_and_without_teams(): void
    {
        $company = Companies::create(['code' => 'TSJ', 'name' => 'Test Join Order', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create(['company_id' => $company->id, 'name' => 'Jadwal Join', 'schedule_type' => ScheduleType::SHIFT->value, 'use_template' => false]);
        $viewer = $this->actingAsKepalaRuangan();

        $mk = function (string $name, ?string $joined) use ($company, $schedule) {
            $u = User::factory()->create(['company_id' => $company->id, 'name' => $name]);
            \App\Models\User\UserProfiles::create(['user_id' => $u->id, 'joining_date' => $joined]);
            ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $u->id, 'effective_from' => now()->startOfMonth()]);

            return $u;
        };

        $baru = $mk('Andi Baru', '2024-06-01');
        $lama = $mk('Zaki Lama', '2015-03-10');
        $tengah = $mk('Budi Tengah', '2020-01-15');
        $kosong = $mk('Citra Tanpa Tanggal', null);

        $names = fn () => Livewire::actingAs($viewer)
            ->test(\App\Livewire\Presensi\ScheduleShiftRosterTab::class, ['schedule' => $schedule->fresh()])
            ->instance()->employees->pluck('name')->all();

        $expected = ['Zaki Lama', 'Budi Tengah', 'Andi Baru', 'Citra Tanpa Tanggal'];
        $this->assertSame($expected, $names());

        // Dengan Tim: urut nama tim dulu, di dalam tim tetap urut tanggal bergabung; tanpa tim di akhir.
        $schedule->update(['use_teams' => true]);
        $teamA = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $period = now()->format('Y-m');
        ScheduleShiftTeamMember::setForPeriod($schedule, $baru, $teamA, $period, $viewer->id);
        ScheduleShiftTeamMember::setForPeriod($schedule, $lama, $teamA, $period, $viewer->id);

        $this->assertSame(['Zaki Lama', 'Andi Baru', 'Budi Tengah', 'Citra Tanpa Tanggal'], $names());
    }

    public function test_roster_tab_groups_rows_by_team_when_use_teams_active(): void
    {
        $company = Companies::create(['code' => 'TSG', 'name' => 'Test Company Grouping', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Grouping',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
            'use_teams' => true,
        ]);
        $user = $this->actingAsKepalaRuangan();

        // Sengaja bikin karyawan dengan nama alfabetis KEBALIK urutan regu yang diinginkan,
        // supaya kalau grouping tidak jalan (masih sort by name doang) test ini ketahuan gagal.
        $zaki = User::factory()->create(['company_id' => $company->id, 'name' => 'Zaki (Regu A)']);
        $yudi = User::factory()->create(['company_id' => $company->id, 'name' => 'Yudi (Regu A juga)']);
        $andi = User::factory()->create(['company_id' => $company->id, 'name' => 'Andi (Regu B)']);
        $budi = User::factory()->create(['company_id' => $company->id, 'name' => 'Budi (Tanpa Regu)']);

        foreach ([$zaki, $yudi, $andi, $budi] as $emp) {
            ScheduleAssignment::create(['company_id' => $company->id, 'schedule_id' => $schedule->id, 'user_id' => $emp->id, 'effective_from' => now()->startOfMonth()]);
        }

        $teamA = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $teamB = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu B']);
        ScheduleShiftTeamMember::setForPeriod($schedule, $zaki, $teamA, now()->format('Y-m'), $user->id);
        ScheduleShiftTeamMember::setForPeriod($schedule, $yudi, $teamA, now()->format('Y-m'), $user->id);
        ScheduleShiftTeamMember::setForPeriod($schedule, $andi, $teamB, now()->format('Y-m'), $user->id);
        // $budi sengaja tidak diatur reginya sama sekali.

        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Presensi\ScheduleShiftRosterTab::class, ['schedule' => $schedule]);

        $names = $component->instance()->employees->pluck('name')->values()->all();

        // Urut per nama regu (Regu A, Regu B), lalu yang tanpa regu paling akhir; dalam 1 regu
        // urut nama karyawan (Yudi sebelum Zaki).
        $this->assertSame(['Yudi (Regu A juga)', 'Zaki (Regu A)', 'Andi (Regu B)', 'Budi (Tanpa Regu)'], $names);

        // Baris pemisah nama regu (colspan penuh) dirender di atas anggotanya, urut per grup -
        // BUKAN kolom (instruksi user: kolom bikin susah bandingkan antar regu).
        $component->assertSeeInOrder(['Regu A', 'Yudi (Regu A juga)', 'Zaki (Regu A)', 'Regu B', 'Andi (Regu B)', 'Tanpa Tim', 'Budi (Tanpa Regu)']);

        // Cuma 1 baris pemisah (colspan) per grup regu - 3 grup (Regu A, Regu B, Tanpa Regu),
        // bukan diulang tiap anggota (Regu A 2 anggota tapi baris pemisahnya cuma sekali).
        $this->assertSame(3, substr_count($component->html(), 'colspan="'));
    }

    public function test_export_puts_team_column_first_with_merged_cells(): void
    {
        $company = Companies::create(['code' => 'TSX', 'name' => 'Test Company Export', 'location' => 'Test', 'is_active' => true]);
        $schedule = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Jadwal Export',
            'schedule_type' => ScheduleType::SHIFT->value,
            'use_template' => false,
            'use_teams' => true,
        ]);
        $a = User::factory()->create(['company_id' => $company->id, 'name' => 'A']);
        $b = User::factory()->create(['company_id' => $company->id, 'name' => 'B']);
        $c = User::factory()->create(['company_id' => $company->id, 'name' => 'C']);
        $date = now()->startOfMonth();

        // $a & $b sama tim (1 banner buat berdua), $c tanpa tim (banner "Tanpa Tim" baru) —
        // urutan ini niru urutan yang dihasilkan ScheduleShiftRosterTab::getEmployeesProperty()
        // (sudah dikelompokkan). 2026-09-15: kolom "Tim" terpisah diganti BARIS banner (merge
        // penuh lebar tabel), sama pola dengan baris pemisah di tabel Detail Penjadwalan web -
        // bukan kolom + merge vertikal lagi.
        $export = new ScheduleShiftRosterExport(
            $schedule,
            collect([$a, $b, $c]),
            [$date],
            [],
            'label',
            [$a->id => 'Regu A', $b->id => 'Regu A'],
        );

        $rows = $export->array();

        // Layout 2026-09-21: tabel legenda di kolom A-D, E kosong, grid roster mulai kolom F
        // (index 5) - baris 1-2 judul legenda, baris 3 header, baris 4.. data. Cuma "Karyawan" +
        // tanggal di grid - tidak ada kolom Tim (mode Baris).
        $grid = fn (int $i) => array_slice($rows[$i], 5);
        $this->assertSame(['Karyawan', $date->format('d')."\n".$date->translatedFormat('D')], $grid(2));
        $this->assertSame(['Regu A', ''], $grid(3));
        $this->assertSame(['A', ''], $grid(4));
        $this->assertSame(['B', ''], $grid(5));
        $this->assertSame(['Tanpa Tim', ''], $grid(6));
        $this->assertSame(['C', ''], $grid(7));

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);
        $export->styles($sheet);

        // Banner "Regu A" di baris sheet 4, "Tanpa Tim" di baris 7 - dua-duanya merge PENUH lebar
        // grid (F:G, cuma 1 kolom tanggal di test ini), bukan cuma kolom nama.
        $this->assertContains('F4:G4', $sheet->getMergeCells());
        $this->assertContains('F7:G7', $sheet->getMergeCells());

        // Freeze pane A4 - baris judul+header saja, tidak ada kolom yang dibekukan (legenda lebar).
        $this->assertSame('A4', $sheet->getFreezePane());
    }

    public function test_export_omits_team_separator_rows_when_use_teams_inactive(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $date = now()->startOfMonth();

        $export = new ScheduleShiftRosterExport($schedule, collect([$employee]), [$date], [], 'label', [$employee->id => 'Regu A']);

        $rows = $export->array();

        // use_teams false -> $teamNames diabaikan sepenuhnya, tidak ada baris banner sama sekali.
        // 2 baris judul + header + 1 karyawan (legenda cuma header + baris Libur, lebih pendek).
        $this->assertSame(['Karyawan', $date->format('d')."\n".$date->translatedFormat('D')], array_slice($rows[2], 5));
        $this->assertSame(['Budi Santoso', ''], array_slice($rows[3], 5));
        $this->assertCount(4, $rows);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);
        $export->styles($sheet);

        $this->assertSame('A4', $sheet->getFreezePane());
    }

    public function test_deleting_team_removes_membership_via_cascade(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsScheduleOwner();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $team, now()->format('Y-m'), $user->id);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->call('deleteTeam', $team->id);

        $this->assertDatabaseMissing('schedule_shift_teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('schedule_shift_team_members', ['team_id' => $team->id]);
    }

    /** Bulan depan yang belum punya keanggotaan sama sekali LANGSUNG kesimpen ke DB begitu
     * halaman dibuka (bukan cuma prefill draft yang nunggu klik Simpan) - instruksi user
     * 2026-09-16, mewarisi Tim dari bulan berjalan. */
    public function test_opening_next_month_auto_persists_team_from_current_month(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsScheduleOwner();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);

        $currentPeriod = now()->format('Y-m');
        $nextPeriod = now()->addMonthNoOverflow()->format('Y-m');

        ScheduleShiftTeamMember::setForPeriod($schedule, $employee, $team, $currentPeriod, $user->id);

        $this->assertNull(ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $nextPeriod));

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('period', $nextPeriod)
            ->assertSet("assignForm.{$employee->id}", $team->id);

        $this->assertSame(
            $team->id,
            ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $nextPeriod)?->team_id
        );
    }

    /** Karyawan yang BELUM punya tim bulan berjalan sama sekali TIDAK ikut dibuatkan baris
     * kosong buat bulan depan - carry-over cuma jalan kalau memang ada sumbernya. */
    public function test_opening_next_month_does_not_create_row_when_current_month_has_no_team(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsScheduleOwner();
        $nextPeriod = now()->addMonthNoOverflow()->format('Y-m');

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('period', $nextPeriod)
            ->assertSet("assignForm.{$employee->id}", '');

        $this->assertNull(ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $nextPeriod));
    }
}
