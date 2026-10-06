<?php

namespace Tests\Feature\Dokter;

use App\Filament\Dokter\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Dokter\Resources\Doctors\RelationManagers\CredentialingRelationManager;
use App\Models\AuditLog;
use App\Models\Doctor\Doctor;
use App\Models\Master\Companies;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Credentialing: PDF final tersimpan terenkripsi & tertaut ke case, perubahan status masuk audit log,
 * SK SPK dilacak masa berlakunya. Memakai koneksi `doctor` asli; data dibersihkan di tearDown.
 */
class DoctorCredentialingTest extends TestCase
{
    use RefreshDatabase;

    private ?Doctor $doctor = null;

    protected function tearDown(): void
    {
        $this->doctor?->forceDelete();

        parent::tearDown();
    }

    public function test_case_links_encrypted_pdf_logs_status_change_and_tracks_spk_expiry(): void
    {
        Storage::fake('local');
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $company = Companies::create(['code' => 'CRD', 'name' => 'RS Kredensial', 'location' => 'Test', 'is_active' => true]);

        $this->doctor = Doctor::create(['nama' => 'Dokter Credentialing']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('dokter');

        $component = Livewire::test(CredentialingRelationManager::class, ['ownerRecord' => $this->doctor, 'pageClass' => EditDoctor::class])
            ->callTableAction('create', data: [
                'rumah_sakit_id' => $company->id,
                'status' => 'pengajuan',
                'file' => [(string) Str::uuid() => UploadedFile::fake()->createWithContent('final.pdf', 'ISI-PDF-FINAL')],
            ])
            ->assertHasNoTableActionErrors();

        $case = $this->doctor->credentialingCases()->firstOrFail();
        $this->assertSame('CREDENTIALING', $case->document->document_type);
        $this->assertStringNotContainsString('ISI-PDF-FINAL', Storage::disk('local')->get($case->document->file_path));
        $this->assertNull($case->spk_status);

        $component->callTableAction('edit', $case, data: [
            'rumah_sakit_id' => $company->id,
            'status' => 'selesai',
            'spk_nomor' => 'SPK-1',
            'spk_berakhir' => now()->subDay()->toDateString(),
        ])->assertHasNoTableActionErrors();

        $this->assertSame('expired', $case->fresh()->spk_status);
        // 1x saat dibuat (- -> pengajuan), 1x saat status diubah (pengajuan -> selesai).
        $this->assertSame(2, AuditLog::where('module', 'doctor_credentialing')->count());
    }
}
