<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Tab Harian Rekap Presensi: filter tanggal (default hari ini, tidak boleh melewati hari ini). */
class RekapPresensiPageHarianDateTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(Companies $company): User
    {
        Permission::firstOrCreate(['name' => CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value, 'guard_name' => 'web']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value);

        return $user;
    }

    public function test_harian_tab_defaults_to_today_and_can_show_another_date(): void
    {
        $company = Companies::create(['code' => 'HRN', 'name' => 'Test Harian', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->viewer($company);
        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Staff Harian', 'nip' => 'HRN001']);

        $yesterday = now()->subDay()->toDateString();
        AttendanceRecords::create([
            'nip' => $staff->nip, 'user_id' => $staff->id, 'date' => $yesterday,
            'check_in_time' => '08:01:00', 'check_out_time' => '16:02:00', 'status' => 'present', 'source' => 'import',
        ]);

        $component = Livewire::actingAs($viewer)->test(RekapPresensiPage::class)
            ->assertSet('harianDate', now()->toDateString());

        // Hari ini: belum ada presensi siapa pun.
        $this->assertSame(0, $component->get('todayStats')['present']);

        // Pilih kemarin: data kemarin tampil, ringkasan ikut berubah.
        $component->set('harianDate', $yesterday);
        $this->assertSame(1, $component->get('todayStats')['present']);
        $component->assertSee('08:01')->assertSee('16:02');

        // Tombol kembali ke hari ini.
        $component->assertSee('Hari Ini')->call('resetHarianDate')
            ->assertSet('harianDate', now()->toDateString());
        $this->assertSame(0, $component->get('todayStats')['present']);
    }

    /** Filter status (kartu Terlambat dst) harus ikut di query string, kalau tidak hilang saat pindah halaman
     * (link paginasi = navigasi ke URL baru, bukan wire:click). */
    public function test_status_filter_survives_pagination_navigation(): void
    {
        $company = Companies::create(['code' => 'HRN3', 'name' => 'Test Harian 3', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->viewer($company);

        // 20 karyawan terlambat hari ini -> lebih dari 15 baris per halaman.
        foreach (range(1, 20) as $i) {
            $u = User::factory()->create(['company_id' => $company->id, 'name' => sprintf('Telat %02d', $i), 'nip' => 'TLT'.$i]);
            AttendanceRecords::create([
                'nip' => $u->nip, 'user_id' => $u->id, 'date' => now()->toDateString(),
                'check_in_time' => '09:30:00', 'status' => 'late', 'late_minutes' => 90, 'source' => 'import',
            ]);
        }

        $component = Livewire::actingAs($viewer)->test(RekapPresensiPage::class)->call('setStatusFilter', 'late');
        $this->assertSame('late', $component->get('statusFilter'));

        // Halaman berikutnya = request baru dengan query string dari link paginasi.
        Livewire::withQueryParams(['statusFilter' => 'late', 'page' => 2])
            ->actingAs($viewer)->test(RekapPresensiPage::class)
            ->assertSet('statusFilter', 'late');
    }

    public function test_harian_date_cannot_be_in_the_future_or_invalid(): void
    {
        $company = Companies::create(['code' => 'HRN2', 'name' => 'Test Harian 2', 'location' => 'Test', 'is_active' => true]);
        $viewer = $this->viewer($company);
        $today = now()->toDateString();

        $component = Livewire::actingAs($viewer)->test(RekapPresensiPage::class);

        $component->set('harianDate', now()->addDays(3)->toDateString())->assertSet('harianDate', $today);
        $component->set('harianDate', 'bukan-tanggal')->assertSet('harianDate', $today);
        $component->set('harianDate', '2026-02-31')->assertSet('harianDate', $today);

        // Dari query string (?tanggal=...) juga disanitasi.
        Livewire::withQueryParams(['tanggal' => now()->addYear()->toDateString()])
            ->actingAs($viewer)->test(RekapPresensiPage::class)
            ->assertSet('harianDate', $today);
    }
}
