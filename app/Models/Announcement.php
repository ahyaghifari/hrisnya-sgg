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

    protected $fillable = ['company_id', 'title', 'summary', 'body', 'status', 'published_at', 'created_by', 'published_by'];

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
     * Pohon target buat form, mengikuti struktur organisasi master PER PERUSAHAAN: Perusahaan >
     * Departemen > Divisi > Sub Divisi > Jabatan. Di dalam satu unit, jabatan disusun menurut
     * atasannya (parent_id) selama atasannya ada di unit yang sama; kalau atasannya di unit lain
     * (atau tidak ada), jabatan itu jadi akar di unitnya. Tiap node membawa `leaves` (id jabatan
     * di bawahnya) supaya centang node = centang semua jabatannya. Jabatan tanpa unit org nempel
     * langsung di perusahaan. `users` = null untuk perusahaan/unit, jumlah karyawan untuk jabatan.
     *
     * @return array{tree: array, positions: array<string, array{name: string, path: string, users: int}>}
     */
    public static function targetTree(?string $onlyCompanyId = null): array
    {
        $companies = Companies::query()->when($onlyCompanyId, fn ($q) => $q->where('id', $onlyCompanyId))->orderBy('name')->get();

        $allOrgs = OrgStructure::query()->whereIn('company_id', $companies->pluck('id'))->orderBy('name')->get()->groupBy('company_id');
        $allPositions = JobPositions::query()
            ->whereIn('company_id', $companies->pluck('id'))->where('status', 'approved')->orderBy('name')->get()->groupBy('company_id');

        $userCounts = UserProfiles::query()
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->whereIn('job_position_id', $allPositions->flatten()->pluck('id'))
            ->selectRaw('job_position_id, count(*) as total')->groupBy('job_position_id')
            ->pluck('total', 'job_position_id');

        $positions = [];

        // Jabatan satu unit ($list), disusun menurut atasan yang ada di unit yang sama.
        $positionNodes = function ($list, string $path) use (&$positions, $userCounts) {
            $ids = $list->pluck('id')->all();
            $byParent = $list->groupBy('parent_id');
            $seen = [];

            $build = function ($position) use (&$build, &$positions, &$seen, $byParent, $userCounts, $path) {
                $seen[$position->id] = true;
                $positions[$position->id] = ['name' => $position->name, 'path' => $path, 'users' => (int) ($userCounts[$position->id] ?? 0)];

                $children = $byParent->get($position->id, collect())
                    ->reject(fn ($child) => isset($seen[$child->id])) // jaga-jaga rantai atasan melingkar
                    ->map($build)->values()->all();

                return [
                    'name' => $position->name,
                    'users' => $positions[$position->id]['users'],
                    'leaves' => [$position->id, ...self::leavesOf($children)],
                    'children' => $children,
                ];
            };

            $roots = $list->filter(fn ($p) => ! $p->parent_id || ! in_array($p->parent_id, $ids, true));

            return $roots->map($build)->values()->all();
        };

        $tree = $companies->map(function ($company) use ($allOrgs, $allPositions, $positionNodes) {
            $orgsByParent = $allOrgs->get($company->id, collect())->groupBy(fn ($o) => $o->parent_id ?? '');
            $positionsByOrg = $allPositions->get($company->id, collect())->groupBy(fn ($p) => $p->org_structure_id ?? '');

            $orgNodes = function (string $parentId, string $path) use (&$orgNodes, $orgsByParent, $positionsByOrg, $positionNodes) {
                return $orgsByParent->get($parentId, collect())->map(function ($org) use (&$orgNodes, $positionsByOrg, $positionNodes, $path) {
                    $here = $path . ' › ' . $org->name;
                    // Jabatan langsung di unit ini (mis. Kabag) di atas, baru sub-unitnya.
                    $children = [...$positionNodes($positionsByOrg->get($org->id, collect()), $here), ...$orgNodes($org->id, $here)];

                    return ['name' => $org->name, 'users' => null, 'leaves' => self::leavesOf($children), 'children' => $children];
                })->all();
            };

            // Jabatan setingkat perusahaan (mis. Direktur) di atas, baru departemen.
            $children = [...$positionNodes($positionsByOrg->get('', collect()), $company->name), ...$orgNodes('', $company->name)];

            return ['name' => $company->name, 'users' => null, 'leaves' => self::leavesOf($children), 'children' => $children];
        })->all();

        return ['tree' => $tree, 'positions' => $positions];
    }

    private static function leavesOf(array $children): array
    {
        return array_merge([], ...array_column($children, 'leaves'));
    }
}
