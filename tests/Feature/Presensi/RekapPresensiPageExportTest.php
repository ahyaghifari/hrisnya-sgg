<?php

namespace Tests\Feature\Presensi;

use App\Enum\CustomPermissionEnum;
use App\Exports\Presensi\RekapPresensiExport;
use App\Livewire\Presensi\RekapPresensiPage;
use App\Models\Attendance\AttendanceRecords;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Export Excel Rekap Presensi harus mengikuti mode + filter yang aktif (bukan cuma halaman 1). */
class RekapPresensiPageExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_harian_export_follows_status_filter_without_pagination(): void
    {
        Excel::fake();
        Permission::firstOrCreate(['name' => CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value, 'guard_name' => 'web']);
        $company = Companies::create(['code' => 'EXP', 'name' => 'Test Export', 'location' => 'Test', 'is_active' => true]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        $viewer->givePermissionTo(CustomPermissionEnum::VIEW_REPORT_DATA_ABSENSI->value);

        // 20 terlambat (> 15 per halaman) + 1 hadir.
        foreach (range(1, 20) as $i) {
            $u = User::factory()->create(['company_id' => $company->id, 'nip' => 'EXL'.$i]);
            AttendanceRecords::create(['nip' => $u->nip, 'user_id' => $u->id, 'date' => now()->toDateString(), 'check_in_time' => '09:30:00', 'status' => 'late', 'late_minutes' => 90, 'source' => 'import']);
        }
        $ok = User::factory()->create(['company_id' => $company->id, 'nip' => 'EXP1']);
        AttendanceRecords::create(['nip' => $ok->nip, 'user_id' => $ok->id, 'date' => now()->toDateString(), 'check_in_time' => '08:00:00', 'status' => 'present', 'source' => 'import']);

        Livewire::actingAs($viewer)->test(RekapPresensiPage::class)
            ->call('setStatusFilter', 'late')
            ->call('exportExcel');

        Excel::assertDownloaded(
            'rekap-presensi-harian-'.now()->toDateString().'.xlsx',
            fn (RekapPresensiExport $export) => count($export->array()) === 20 && $export->array()[0][2] === 'Terlambat',
        );
    }
}
