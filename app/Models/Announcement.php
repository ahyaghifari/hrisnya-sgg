<?php

namespace App\Models;

use App\Models\Master\Companies;
use App\Models\Master\JobPositions;
use App\Models\Master\OrgStructure;
use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasUlids;

    protected $fillable = ['company_id', 'title', 'body', 'status', 'published_at', 'created_by', 'published_by'];

    protected $casts = ['published_at' => 'datetime'];

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function jobPositions(): BelongsToMany
    {
        return $this->belongsToMany(JobPositions::class, 'announcement_job_position', 'announcement_id', 'job_position_id');
    }

    /** Karyawan aktif yang jabatannya termasuk target pengumuman ini. */
    public function recipients()
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('users.id', UserProfiles::query()
                ->whereIn('job_position_id', $this->jobPositions()->select('m_job_positions.id'))
                ->select('user_id'));
    }

    /**
     * Pohon target buat form: Perusahaan > Departemen > Divisi > Sub Divisi > Jabatan. Tiap node
     * membawa `leaves` (id jabatan di bawahnya) supaya centang node = centang semua jabatannya.
     * Jabatan tanpa struktur org nempel langsung di perusahaan.
     *
     * @return array{tree: array, positions: array<string, array{name: string, path: string, users: int}>}
     */
    public static function targetTree(?string $onlyCompanyId = null): array
    {
        $companies = Companies::query()->when($onlyCompanyId, fn ($q) => $q->where('id', $onlyCompanyId))->orderBy('name')->get();
        $companyIds = $companies->pluck('id');

        $orgsByParent = OrgStructure::query()->whereIn('company_id', $companyIds)->orderBy('name')->get()->groupBy(fn ($o) => $o->parent_id ?? '');
        $positionsByOrg = JobPositions::query()
            ->whereIn('company_id', $companyIds)->where('status', 'approved')->orderBy('name')->get()
            ->groupBy(fn ($p) => $p->org_structure_id ?? '');

        $userCounts = UserProfiles::query()
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->whereIn('job_position_id', $positionsByOrg->flatten()->pluck('id'))
            ->selectRaw('job_position_id, count(*) as total')->groupBy('job_position_id')
            ->pluck('total', 'job_position_id');

        $positions = [];

        $positionNodes = function (string $orgId, string $path) use ($positionsByOrg, $userCounts, &$positions) {
            return $positionsByOrg->get($orgId, collect())->map(function ($p) use ($path, $userCounts, &$positions) {
                $positions[$p->id] = ['name' => $p->name, 'path' => $path, 'users' => (int) ($userCounts[$p->id] ?? 0)];

                return ['name' => $p->name, 'users' => $positions[$p->id]['users'], 'leaves' => [$p->id], 'children' => []];
            })->all();
        };

        $orgNodes = function (string $parentId, string $path) use (&$orgNodes, $orgsByParent, $positionNodes) {
            return $orgsByParent->get($parentId, collect())->map(function ($org) use (&$orgNodes, $positionNodes, $path) {
                $here = $path . ' › ' . $org->name;
                $children = [...$orgNodes($org->id, $here), ...$positionNodes($org->id, $here)];

                return ['name' => $org->name, 'leaves' => self::leavesOf($children), 'children' => $children];
            })->all();
        };

        $tree = $companies->map(function ($company) use ($orgNodes, $positionNodes) {
            $children = [...$orgNodes('', $company->name), ...$positionNodes('', $company->name)];

            return ['name' => $company->name, 'leaves' => self::leavesOf($children), 'children' => $children];
        })->all();

        return ['tree' => $tree, 'positions' => $positions];
    }

    private static function leavesOf(array $children): array
    {
        return array_merge([], ...array_column($children, 'leaves'));
    }
}
