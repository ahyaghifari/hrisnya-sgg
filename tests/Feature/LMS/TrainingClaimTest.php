<?php

namespace Tests\Feature\LMS;

use App\Livewire\Pelatihan\SubmitTrainingRequest;
use App\Livewire\Pelatihan\TrainingClaimDetail;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use App\Models\ApprovalManagement\MasterApprovalFlowSteps;
use App\Models\LMS\TrainingClaim;
use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrainingClaimTest extends TestCase
{
    use RefreshDatabase;

    private function makeFlow(Companies $company, User $approver): void
    {
        $flow = MasterApprovalFlows::create([
            'company_id' => $company->id,
            'module' => 'training_claim',
            'name' => 'Alur Pelatihan Terlaksana',
            'flow_type' => 'default',
            'is_active' => true,
        ]);

        MasterApprovalFlowSteps::create([
            'flow_id' => $flow->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_ref_id' => $approver->id,
            'approval_mode' => 'any',
            'scope_type' => 'global',
            'is_final' => true,
        ]);
    }

    public function test_jp_dihitung_dari_total_menit_semua_sesi_dibulatkan_sekali(): void
    {
        $sessions = [
            ['start_time' => '09:00', 'end_time' => '10:40'],
            ['start_time' => '13:00', 'end_time' => '14:20'],
        ];
        $this->assertSame(180, TrainingClaim::minutesOf($sessions));
        $this->assertSame(4, TrainingClaim::jpFor(180));

        // 20 + 20 menit: total 40/45 dibulatkan = 1 JP (kalau dibulatkan per sesi jadi 0).
        $short = [
            ['start_time' => '09:00', 'end_time' => '09:20'],
            ['start_time' => '09:00', 'end_time' => '09:20'],
        ];
        $this->assertSame(1, TrainingClaim::jpFor(TrainingClaim::minutesOf($short)));
    }

    public function test_pengajuan_terlaksana_masuk_alur_persetujuan_dan_hc_koreksi_jp_saat_approve(): void
    {
        Storage::fake('public');

        $company = new Companies();
        $company->fill(['code' => 'T2', 'name' => 'Test2', 'location' => 'Test', 'is_active' => true])->save();

        Permission::firstOrCreate(['name' => 'ajukan_pelatihan', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'kelola_klaim_pelatihan', 'guard_name' => 'web']);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->givePermissionTo('ajukan_pelatihan');
        $hc = User::factory()->create(['company_id' => $company->id]);
        $hc->givePermissionTo('kelola_klaim_pelatihan');
        $this->makeFlow($company, $hc);

        Livewire::actingAs($employee)
            ->test(SubmitTrainingRequest::class)
            ->assertSet('jenis', 'sudah')
            ->set('title', 'Seminar')
            ->set('certificate', UploadedFile::fake()->create('s.pdf', 100, 'application/pdf'))
            ->set('sessions', [
                ['date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '12:00'],
                ['date' => '2026-10-02', 'start_time' => '08:00', 'end_time' => '10:15'],
            ])
            ->call('submit')
            ->assertHasNoErrors();

        $claim = TrainingClaim::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('pending', $claim->status);
        $this->assertCount(2, $claim->sessions);
        $this->assertSame(7, $claim->jp); // 180 + 135 = 315 menit / 45
        $this->assertNotNull($claim->approvalSnapshot);
    }

    public function test_laporan_pelatihan_menampilkan_jp_dan_jam_eksternal_dan_menambah_total(): void
    {
        $company = new Companies();
        $company->fill(['code' => 'T4', 'name' => 'Test4', 'location' => 'Test', 'is_active' => true])->save();

        $baseline = \App\Models\LMS\LmsCourseBaselines::create([
            'company_id' => $company->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'min_hours' => 20,
            'min_courses' => 1,
            'is_active' => true,
        ]);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $viewer = User::factory()->create(['company_id' => $company->id]);
        Permission::firstOrCreate(['name' => 'lihat_laporan_pelatihan', 'guard_name' => 'web']);
        $viewer->givePermissionTo('lihat_laporan_pelatihan');

        $make = fn (string $status, int $jp, string $date) => tap(TrainingClaim::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'title' => 'X',
            'certificate_path' => 'x.pdf',
            'status' => $status,
            'jp' => $jp,
        ]), fn ($c) => $c->sessions()->create(['date' => $date, 'start_time' => '09:00', 'end_time' => '10:00']));

        $make('approved', 8, '2026-05-01');  // dihitung
        $make('approved', 4, '2025-05-01');  // di luar periode baseline
        $make('pending', 6, '2026-05-02');   // belum disetujui

        $report = Livewire::actingAs($viewer)
            ->test(\App\Livewire\LMS\TrainingReportPage::class)
            ->set('baselineId', $baseline->id);

        // Modal Detail: hanya klaim approved di periode baseline yang tampil.
        $report->call('openDetail', $employee->id)
            ->assertSee('Pelatihan Eksternal Terlaksana')
            ->assertSee('8 JP')
            ->assertDontSee('4 JP')
            ->assertDontSee('6 JP')
            ->call('closeDetail')
            ->assertDontSee('Pelatihan Eksternal Terlaksana');

        $matrix = $report->get('reportMatrix');

        $row = collect($matrix)->firstWhere('name', $employee->name);
        $this->assertSame(8, $row['external_jp']);
        $this->assertEquals(6.0, $row['external_hours']); // 8 JP x 45 menit / 60
        $this->assertEquals(6.0, $row['total_hours']);
        $this->assertEquals(14.0, $row['unfulfilled_hours']);
        $this->assertSame(1, $row['total_courses']); // hanya 1 pelatihan eksternal yang disetujui di periode

        // Tabel Filament admin (SQL sendiri) harus sama dengan matriks portal.
        $page = new \App\Filament\Resources\LMS\TrainingReport\Pages\TrainingReportPage();
        $page->baselineId = $baseline->id;
        $query = (new \ReflectionMethod($page, 'buildTableQuery'))->invoke($page);
        $record = $query->where('users.id', $employee->id)->first();
        $this->assertSame(8, (int) $record->external_jp);
        $this->assertEquals(6.0, (float) $record->total_training_hours);
        $this->assertSame(1, (int) $record->total_courses_count);
    }

    public function test_hc_approver_bisa_koreksi_jp_lalu_approve(): void
    {
        Storage::fake('public');

        $company = new Companies();
        $company->fill(['code' => 'T3', 'name' => 'Test3', 'location' => 'Test', 'is_active' => true])->save();

        Permission::firstOrCreate(['name' => 'ajukan_pelatihan', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'kelola_klaim_pelatihan', 'guard_name' => 'web']);

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->givePermissionTo('ajukan_pelatihan');
        $hc = User::factory()->create(['company_id' => $company->id]);
        $hc->givePermissionTo('kelola_klaim_pelatihan');
        $this->makeFlow($company, $hc);

        $this->actingAs($employee);
        $claim = app(\App\Services\LMS\TrainingClaimService::class)->store(
            [
                'title' => 'Workshop',
                'sessions' => [['date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '12:00']],
            ],
            UploadedFile::fake()->create('s.pdf', 100, 'application/pdf'),
        );
        $this->assertSame(4, $claim->jp);

        $component = Livewire::actingAs($hc)
            ->test(TrainingClaimDetail::class, ['record' => $claim])
            ->assertSee('Aksi Persetujuan')
            ->set('jp', 6)
            ->call('chooseAction', 'approve')
            ->set('note', 'Sesuai sertifikat')
            ->call('confirmAction')
            ->assertHasNoErrors();

        // Setelah disetujui, kotak aksi persetujuan harus hilang.
        $component->assertDontSee('Aksi Persetujuan');

        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame(6, $claim->jp);
        $this->assertSame($hc->id, $claim->reviewed_by);

        // HC tetap bisa mengubah jam & JP setelah disetujui; tercatat di riwayat & laporan ikut berubah.
        $component->call('startEdit')
            ->set('sessionsForm', [
                ['date' => '2026-10-03', 'start_time' => '09:00', 'end_time' => '10:30'],
                ['date' => '2026-10-04', 'start_time' => '09:00', 'end_time' => '12:00'],
            ])
            ->set('jp', 9)
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('editing', false);

        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame(9, $claim->jp);
        $this->assertCount(2, $claim->sessions);
        $this->assertSame('2026-10-03', $claim->sessions->first()->date->toDateString());
        $this->assertTrue($claim->approvalHistories()->where('action', 'updated')->exists());

        // User biasa (bukan HC) tidak boleh mengubah.
        Livewire::actingAs($employee)
            ->test(TrainingClaimDetail::class, ['record' => $claim])
            ->assertDontSee('Ubah')
            ->call('startEdit')
            ->assertForbidden();
    }
}
