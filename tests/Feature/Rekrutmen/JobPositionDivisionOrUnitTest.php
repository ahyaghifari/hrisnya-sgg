<?php

namespace Tests\Feature\Rekrutmen;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturannya: divisi kalau ada, kalau tidak departemennya. Jabatan yang duduk langsung di departemen
 * memang wajar - itu pimpinan puncak departemen itu, jadi tidak punya divisi di atasnya.
 *
 * getDivisionAttribute() sendiri null untuk kasus itu: dia naik 1 tingkat ke parent kalau
 * org_structure jabatannya bukan level Divisi, padahal DEPARTMENT di master ini parent_id-nya null
 * semua (Keuangan, HCGA, Finance, ...). getDivisionOrUnitNameAttribute() menutup celah itu, dan
 * hasilnya mengisi job_openings.division_name = token {divisi} di naskah Persetujuan Onboarding.
 */
class JobPositionDivisionOrUnitTest extends TestCase
{
    use RefreshDatabase;

    private Companies $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Companies::create(['code' => 'C1', 'name' => 'Company 1', 'location' => 'Jakarta']);
    }

    private function org(string $name, string $level, ?string $parentId = null): OrgStructure
    {
        return OrgStructure::create([
            'company_id' => $this->company->id, 'name' => $name, 'level' => $level, 'parent_id' => $parentId,
        ]);
    }

    private function position(string $name, ?string $orgId): JobPositions
    {
        return JobPositions::create([
            'company_id' => $this->company->id, 'name' => $name, 'org_structure_id' => $orgId, 'status' => 'approved',
        ]);
    }

    public function test_a_sub_division_resolves_to_its_parent_division(): void
    {
        $division = $this->org('IT', 'DIVISION');
        $position = $this->position('IT Programmer Staff', $this->org('Programmer', 'SUB_DIVISION', $division->id)->id);

        $this->assertSame('IT', $position->division_or_unit_name);
    }

    public function test_a_position_sitting_directly_on_a_division_uses_that_division(): void
    {
        $position = $this->position('Manager IT', $this->org('IT', 'DIVISION')->id);

        $this->assertSame('IT', $position->division_or_unit_name);
    }

    /**
     * Inti perbaikannya: pimpinan puncak departemen (Kabid/Kabag/Manager) tidak punya divisi di
     * atasnya, jadi nama departemennya yang dipakai - bukan null seperti accessor lama.
     */
    public function test_a_department_head_falls_back_to_the_department_name(): void
    {
        $position = $this->position('Finance Manager', $this->org('Finance', 'DEPARTMENT')->id);

        $this->assertNull($position->division, 'Accessor lama memang null di kasus ini.');
        $this->assertSame('Finance', $position->division_or_unit_name);
    }

    /**
     * Di atas semua departemen (mis. Presiden Director) tidak ada struktur yang bisa dipakai. Praktis
     * tidak pernah direkrut lewat portal, tapi kalau toh ada, divisinya diisi manual di form lowongan.
     */
    public function test_a_position_without_any_org_structure_stays_null(): void
    {
        $this->assertNull($this->position('Office Boy', null)->division_or_unit_name);
    }
}
