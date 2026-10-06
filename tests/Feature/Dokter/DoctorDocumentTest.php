<?php

namespace Tests\Feature\Dokter;

use App\Filament\Dokter\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Dokter\Resources\Doctors\RelationManagers\DocumentsRelationManager;
use App\Models\Doctor\Doctor;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dokumen dokter: file tersimpan terenkripsi di disk privat, hanya bisa dibuka lewat route berizin.
 * Memakai koneksi `doctor` asli (DB dokter terpisah) - data tes dibersihkan di tearDown.
 */
class DoctorDocumentTest extends TestCase
{
    use RefreshDatabase;

    private ?Doctor $doctor = null;

    protected function tearDown(): void
    {
        $this->doctor?->documents()->withTrashed()->forceDelete();
        $this->doctor?->forceDelete();

        parent::tearDown();
    }

    public function test_upload_is_encrypted_at_rest_and_download_is_gated(): void
    {
        Storage::fake('local');
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $outsider = User::factory()->create();

        $this->doctor = Doctor::create(['nama' => 'Tes Dokumen']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('dokter');

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->doctor, 'pageClass' => EditDoctor::class])
            ->callTableAction('create', data: [
                'document_type' => 'KTP',
                'file' => UploadedFile::fake()->createWithContent('ktp.pdf', 'ISI-RAHASIA'),
                'status' => 'aktif',
            ])
            ->assertHasNoTableActionErrors();

        $document = $this->doctor->documents()->firstOrFail();
        $this->assertSame('ktp.pdf', $document->file_name);
        $this->assertStringNotContainsString('ISI-RAHASIA', Storage::disk('local')->get($document->file_path));

        $this->get(route('filament.dokter.dokumen.show', $document))->assertOk()->assertSee('ISI-RAHASIA');

        // User biasa (tanpa Admin/kelola_dokter) tidak boleh membuka dokumen.
        auth()->logout();
        $this->actingAs($outsider);
        $this->get(route('filament.dokter.dokumen.show', $document))->assertForbidden();
    }
}
