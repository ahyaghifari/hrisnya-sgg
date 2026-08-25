<?php

namespace Tests\Feature\Sikartap;

use App\Filament\Resources\Sikartap\Pages\ViewSikartap;
use App\Filament\Resources\Sikartap\RelationManagers\DocumentsRelationManager;
use App\Models\Master\Companies;
use App\Models\Role;
use App\Models\Sikartap\Sikartap;
use App\Models\Sikartap\SikartapDocumentAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sisi HRD dari verifikasi berkas (rewrite verifikasi_berkas.php legacy) - lihat
 * PengajuanKaryawanTetapPageTest untuk sisi upload karyawan.
 */
class SikartapResourceTest extends TestCase
{
    use RefreshDatabase;

    private ?Companies $company = null;

    /** HRD & pelamar harus 1 company - SikartapResource::getEloquentQuery() scoped per company
     * buat user yang tidak canBrowseAllCompanies() (lihat SikartapResource.php). */
    private function actingAsHrd(): User
    {
        $this->company = Companies::create(['code' => 'TST', 'name' => 'Test Company', 'location' => 'Test', 'is_active' => true]);
        $hrd = User::factory()->create(['company_id' => $this->company->id]);

        $role = Role::firstOrCreate(['name' => 'HRD'], ['guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'ViewAny:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Sikartap', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Update:Sikartap', 'guard_name' => 'web']),
        ]);
        $hrd->assignRole($role);

        $this->actingAs($hrd);

        return $hrd;
    }

    private function makeApplicant(): User
    {
        return User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_hrd_can_approve_single_document(): void
    {
        $this->actingAsHrd();

        $applicant = $this->makeApplicant();
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);
        $doc = SikartapDocumentAttachment::create([
            'sikartap_id' => $sikartap->id,
            'doc_type' => 'ktp_kk',
            'file_path' => 'sikartap/fake/ktp.pdf',
            'status' => 'pending',
        ]);

        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $sikartap,
            'pageClass' => ViewSikartap::class,
        ])->callTableAction('approve', $doc);

        $this->assertSame('approved', $doc->fresh()->status);
    }

    public function test_hrd_can_reject_single_document_with_note(): void
    {
        $this->actingAsHrd();

        $applicant = $this->makeApplicant();
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);
        $doc = SikartapDocumentAttachment::create([
            'sikartap_id' => $sikartap->id,
            'doc_type' => 'ktp_kk',
            'file_path' => 'sikartap/fake/ktp.pdf',
            'status' => 'pending',
        ]);

        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $sikartap,
            'pageClass' => ViewSikartap::class,
        ])->callTableAction('reject', $doc, data: ['catatan_penolakan' => 'Tidak jelas']);

        $doc->refresh();
        $this->assertSame('rejected', $doc->status);
        $this->assertSame('Tidak jelas', $doc->catatan_penolakan);
    }

    public function test_approve_all_requires_all_required_documents_uploaded(): void
    {
        $this->actingAsHrd();

        $applicant = $this->makeApplicant();
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);
        // Cuma upload 1 dari 6 dokumen wajib.
        SikartapDocumentAttachment::create([
            'sikartap_id' => $sikartap->id,
            'doc_type' => 'ktp_kk',
            'file_path' => 'sikartap/fake/ktp.pdf',
            'status' => 'pending',
        ]);

        $this->assertFalse($sikartap->fresh()->hasAllRequiredDocumentsUploaded());
    }

    public function test_approve_all_action_approves_all_uploaded_documents_and_application(): void
    {
        $this->actingAsHrd();

        $applicant = $this->makeApplicant();
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);

        foreach (['surat_permohonan', 'formulir_rekomendasi_atasan', 'formulir_rekomendasi_pejabat', 'penilaian_kinerja', 'ijazah_terakhir', 'ktp_kk'] as $type) {
            SikartapDocumentAttachment::create([
                'sikartap_id' => $sikartap->id,
                'doc_type' => $type,
                'file_path' => "sikartap/fake/{$type}.pdf",
                'status' => 'pending',
            ]);
        }

        $this->assertTrue($sikartap->fresh()->hasAllRequiredDocumentsUploaded());

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->callAction('approveAll');

        $sikartap->refresh();
        $this->assertSame('approved', $sikartap->status_berkas);
        $this->assertTrue($sikartap->berkas_lengkap);
        $this->assertSame(6, $sikartap->documents()->where('status', 'approved')->count());
    }

    public function test_reject_application_rejects_all_uploaded_documents(): void
    {
        $this->actingAsHrd();

        $applicant = $this->makeApplicant();
        $sikartap = Sikartap::create(['user_id' => $applicant->id]);
        SikartapDocumentAttachment::create([
            'sikartap_id' => $sikartap->id,
            'doc_type' => 'ktp_kk',
            'file_path' => 'sikartap/fake/ktp.pdf',
            'status' => 'pending',
        ]);

        Livewire::test(ViewSikartap::class, ['record' => $sikartap->id])
            ->callAction('rejectApplication', data: ['catatan_penolakan' => 'Berkas tidak lengkap']);

        $sikartap->refresh();
        $this->assertSame('rejected', $sikartap->status_berkas);
        $this->assertSame('rejected', $sikartap->documents()->first()->status);
        $this->assertSame('Berkas tidak lengkap', $sikartap->documents()->first()->catatan_penolakan);
    }
}
