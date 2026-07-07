<?php

namespace App\Livewire;

use App\Filament\Resources\Master\JobPositions\JobPositionResource;
use App\Models\Master\Companies;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class OrganizationChartWidget extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'livewire.organization-chart-widget';

    public ?string $companyId = null;
    public ?string $selectedParentId = null;
    public ?string $selectedLevel = null;
    public ?string $selectedNodeId = null;

    #[On('companySelected')]
    public function setCompany($companyId): void
    {
        $this->companyId = $companyId;
    }

    public function getCompanyProperty(): ?Companies
    {
        return $this->companyId
            ? Companies::with('jobPositions.pangkat', 'jobPositions.golongan', 'jobPositions.kelas')->find($this->companyId)
            : null;
    }

    public function getJobHierarchyProperty(): array
    {
        if (!$this->companyId) {
            return [];
        }

        // Ambil semua jabatan untuk company ini dengan relasi yang benar
        try {
            $jobs = \App\Models\Master\JobPositions::where('company_id', $this->companyId)
                ->where('is_draft', false)
                ->with(['pangkat', 'golongan', 'kelas', 'parent', 'children', 'orgStructure'])
                ->get();

            // Handle users relationship secara safe
            foreach ($jobs as $job) {
                $job->assignedUsers = collect(); // Default empty collection
                
                // Coba berbagai cara untuk mendapatkan users
                $relationshipMethods = ['employees', 'users', 'userJobs'];
                
                foreach ($relationshipMethods as $method) {
                    if (method_exists($job, $method)) {
                        try {
                            $job->load($method);
                            if ($method === 'userJobs' && $job->userJobs) {
                                $job->assignedUsers = $job->userJobs->pluck('user')->filter();
                            } elseif ($job->$method) {
                                $job->assignedUsers = $job->$method;
                            }
                            break; // Keluar dari loop jika berhasil
                        } catch (\Exception $e) {
                            continue; // Coba method berikutnya
                        }
                    }
                }
            }

            return $this->buildJobHierarchy($jobs);
        } catch (\Exception $e) {
            // Jika ada error, return empty array
            return [];
        }
    }

    public function getJobsWithoutOrgStructureProperty(): array
    {
        if (!$this->companyId) {
            return [];
        }

        try {
            // Ambil job positions yang benar-benar standalone:
            // org_structure_id = null, parent_id = null, dan tidak punya children
            $jobs = \App\Models\Master\JobPositions::where('company_id', $this->companyId)
                ->where('is_draft', false)
                ->whereNull('org_structure_id')
                ->whereNull('parent_id')
                ->whereDoesntHave('children', function ($q) {
                    $q->where('company_id', $this->companyId)
                      ->where('is_draft', false);
                })
                ->with(['pangkat', 'golongan', 'kelas'])
                ->get();

            // Handle users relationship secara safe
            foreach ($jobs as $job) {
                $job->assignedUsers = collect(); // Default empty collection
                
                // Coba berbagai cara untuk mendapatkan users
                $relationshipMethods = ['employees', 'users', 'userJobs'];
                
                foreach ($relationshipMethods as $method) {
                    if (method_exists($job, $method)) {
                        try {
                            $job->load($method);
                            if ($method === 'userJobs' && $job->userJobs) {
                                $job->assignedUsers = $job->userJobs->pluck('user')->filter();
                            } elseif ($job->$method) {
                                $job->assignedUsers = $job->$method;
                            }
                            break; // Keluar dari loop jika berhasil
                        } catch (\Exception $e) {
                            continue; // Coba method berikutnya
                        }
                    }
                }
            }

            return $jobs->map(function ($job) {
                return [
                    'job' => $job,
                    'children' => [] // Jobs tanpa org structure tidak punya children
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function buildJobHierarchy($jobs): array
    {
        // Kelompokkan berdasarkan parent_id
        $grouped = $jobs->groupBy('parent_id');
        
        // Ambil root jobs (parent_id = null)
        $rootJobs = $grouped->get(null, collect());

        // Exclude root jobs yang benar-benar standalone (tidak punya org_structure DAN tidak punya
        // children di koleksi ini) — mereka ditampilkan di jobsWithoutOrgStructure
        $rootJobs = $rootJobs->filter(function ($job) use ($grouped) {
            $hasOrgStructure = !is_null($job->org_structure_id);
            $hasChildren     = $grouped->has($job->id);
            return $hasOrgStructure || $hasChildren;
        });
        
        // Fungsi rekursif untuk membangun hierarki
        $buildTree = function ($parentJobs) use (&$buildTree, $grouped) {
            return $parentJobs->map(function ($job) use (&$buildTree, $grouped) {
                $children = $grouped->get($job->id, collect());
                return [
                    'job' => $job,
                    'children' => $children->isNotEmpty() ? $buildTree($children) : []
                ];
            })->toArray();
        };

        return $buildTree($rootJobs);
    }

    public function mount(): void
    {
        // Bisa ditambahkan logic untuk set default company jika diperlukan
    }

    public function openJobList(string $nodeId): void
    {
        $this->selectedNodeId = $nodeId;
        $this->dispatch('open-modal', id: 'job-list-slideover');
    }

    public function openAddJobModal(): void
    {
        $this->dispatch('open-modal', id: 'add-job-slideover');
    }

    public function createJob(): void
    {
        redirect()->to(JobPositionResource::getUrl('create'));
    }
}
