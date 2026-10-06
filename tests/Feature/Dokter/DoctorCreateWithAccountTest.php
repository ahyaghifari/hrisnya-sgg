<?php

namespace Tests\Feature\Dokter;

use App\Filament\Dokter\Pages\Beranda;
use App\Filament\Dokter\Resources\Doctors\Pages\CreateDoctor;
use App\Models\Doctor\Doctor;
use App\Models\Master\Companies;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tambah dokter = sekaligus buat akun HRIS (users + profile is_dokter) dan menautkannya (hris_user_id).
 * Memakai koneksi `doctor` asli; data dokter dibersihkan di tearDown (users ikut RefreshDatabase).
 */
class DoctorCreateWithAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Doctor::withTrashed()->where('nama', 'Dokter Uji Akun')->forceDelete();

        parent::tearDown();
    }

    public function test_create_doctor_also_creates_linked_hris_account_and_shows_on_beranda(): void
    {
        Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $company = Companies::create(['code' => 'DKT', 'name' => 'RS Uji', 'location' => 'Test', 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('dokter');

        Storage::fake('local');

        Livewire::test(CreateDoctor::class)
            ->fillForm([
                'nama' => 'Dokter Uji Akun',
                'jenis_kelamin' => 'P',
                'nip' => 'DKT-001',
                'email' => 'dokter.uji@example.test',
                'password' => 'rahasia123',
                'company_id' => $company->id,
                'dokumen' => [[
                    'document_type' => 'KTP',
                    // State FileUpload di UI berbentuk array (uuid => file).
                    'file' => [(string) \Illuminate\Support\Str::uuid() => UploadedFile::fake()->createWithContent('ktp.pdf', 'ISI-DOKUMEN-AWAL')],
                ]],
                'lisensi' => [[
                    'jenis' => 'STR',
                    'nomor' => 'STR-123',
                    'tanggal_berakhir' => now()->addYear()->toDateString(),
                    'file' => [(string) \Illuminate\Support\Str::uuid() => UploadedFile::fake()->createWithContent('str.pdf', 'ISI-STR')],
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $doctor = Doctor::where('nama', 'Dokter Uji Akun')->firstOrFail();
        $user = User::withoutGlobalScopes()->where('email', 'dokter.uji@example.test')->firstOrFail();

        $this->assertSame($user->id, $doctor->hris_user_id);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertTrue((bool) $user->profile->is_dokter);
        $this->assertSame('perempuan', $user->profile->gender);

        // Dokumen yang diunggah saat tambah dokter tersimpan terenkripsi & tertaut ke dokter.
        $document = $doctor->documents()->firstOrFail();
        $this->assertSame('ktp.pdf', $document->file_name);
        $this->assertStringNotContainsString('ISI-DOKUMEN-AWAL', Storage::disk('local')->get($document->file_path));

        // Lisensi yang diisi saat tambah dokter tersimpan, file scan-nya jadi dokumen yang tertaut.
        $license = $doctor->licenses()->firstOrFail();
        $this->assertSame('STR-123', $license->nomor);
        $this->assertSame('str.pdf', $license->document->file_name);
        $this->assertSame('STR', $license->document->document_type);

        // Dokter disembunyikan dari daftar karyawan biasa (global scope 'dokter').
        $this->assertFalse(User::where('email', 'dokter.uji@example.test')->exists());

        Livewire::test(Beranda::class)
            ->assertSee('Dokter Uji Akun')
            ->call('buka', $doctor->id)
            ->assertSee('dokter.uji@example.test')
            ->assertSee('Edit');
    }
}
