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

    public function test_use_teams_defaults_off_and_kepala_ruangan_can_toggle(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsKepalaRuangan();

        $this->assertFalse($schedule->fresh()->use_teams);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->assertSee('belum memakai Regu')
            ->call('activateTeams');

        $this->assertTrue($schedule->fresh()->use_teams);

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule->fresh()])
            ->assertDontSee('belum memakai Regu')
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
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsKepalaRuangan();
        $team = ScheduleShiftTeam::create(['schedule_id' => $schedule->id, 'name' => 'Regu A']);
        $pastPeriod = now()->subMonthNoOverflow()->format('Y-m');

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('period', $pastPeriod)
            ->set("assignForm.{$employee->id}", $team->id)
            ->call('assignMember', $employee->id)
            ->assertForbidden();

        $this->assertNull(ScheduleShiftTeamMember::forPeriod($schedule->id, $employee->id, $pastPeriod));
    }

    public function test_viewer_without_permission_cannot_create_team(): void
    {
        [$schedule] = $this->makeScheduleWithEmployee();
        $user = $this->actingAsViewerOnly();

        Livewire::actingAs($user)
            ->test(ScheduleShiftTeamTab::class, ['schedule' => $schedule])
            ->set('teamFormName', 'Regu A')
            ->call('saveTeam')
            ->assertForbidden();

        $this->assertDatabaseMissing('schedule_shift_teams', ['schedule_id' => $schedule->id, 'name' => 'Regu A']);
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
        $component->assertSeeInOrder(['Regu A', 'Yudi (Regu A juga)', 'Zaki (Regu A)', 'Regu B', 'Andi (Regu B)', 'Tanpa Regu', 'Budi (Tanpa Regu)']);

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

        // $a & $b sama regu (harus ke-merge), $c tanpa regu — urutan ini niru urutan yang
        // dihasilkan ScheduleShiftRosterTab::getEmployeesProperty() (sudah dikelompokkan).
        $export = new ScheduleShiftRosterExport(
            $schedule,
            collect([$a, $b, $c]),
            [$date],
            [],
            'label',
            [$a->id => 'Regu A', $b->id => 'Regu A'],
        );

        $rows = $export->array();

        // "Regu" paling kiri, "Karyawan" di sebelahnya — sama posisi kayak tabel Detail
        // Penjadwalan. Header tanggal ikut nama hari (2 baris), sama kayak header tabel.
        $this->assertSame(['Regu', 'Karyawan', $date->format('d')."\n".$date->translatedFormat('D')], $rows[0]);
        $this->assertSame(['Regu A', 'A', ''], $rows[1]);
        $this->assertSame(['Regu A', 'B', ''], $rows[2]);
        $this->assertSame(['Tanpa Regu', 'C', ''], $rows[3]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);
        $export->styles($sheet);

        // 2 baris judul disisipkan di styles() -> data geser ke baris 4 (A) & 5 (B) -> merge A4:A5.
        // $c sendirian, tidak ke-merge.
        $this->assertContains('A4:A5', $sheet->getMergeCells());
        $this->assertNotContains('A6:A6', $sheet->getMergeCells());

        // Freeze pane geser ke kolom C (Regu + Karyawan dibekukan) begitu ada kolom Regu.
        $this->assertSame('C4', $sheet->getFreezePane());
    }

    public function test_export_omits_team_column_when_use_teams_inactive(): void
    {
        [$schedule, $employee] = $this->makeScheduleWithEmployee();
        $date = now()->startOfMonth();

        $export = new ScheduleShiftRosterExport($schedule, collect([$employee]), [$date], [], 'label', [$employee->id => 'Regu A']);

        $this->assertSame(['Karyawan', $date->format('d')."\n".$date->translatedFormat('D')], $export->array()[0]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($export->array());
        $export->styles($sheet);

        // Tanpa kolom Regu, freeze pane cukup di kolom B (cuma Karyawan yang dibekukan).
        $this->assertSame('B4', $sheet->getFreezePane());
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
}
