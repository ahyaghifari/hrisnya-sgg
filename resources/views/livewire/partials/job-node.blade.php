{{-- Job Node Component --}}
<div class="org-branch flex flex-col items-center relative shrink-0" data-branch="job-{{ $level }}">
    <div class="org-line-up w-[2px] h-8 bg-gray-300 relative z-0"></div>
    
    @php
        $bgClass = match($level) {
            0, 1 => 'bg-emerald-600',
            2 => 'bg-violet-600',
            default => 'bg-rose-600',
        };
        $borderClass = match($level) {
            0, 1 => 'border-emerald-700',
            2 => 'border-violet-700',
            default => 'border-rose-700',
        };
    @endphp

    <div class="org-card w-64 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 relative z-10 flex flex-col overflow-hidden border {{ $borderClass }} bg-white cursor-default">
        @php
            $actualCount = 0;
            try {
                if (isset($jobData['job']->assignedUsers) && $jobData['job']->assignedUsers) {
                    $actualCount = $jobData['job']->assignedUsers->count();
                } elseif (isset($jobData['job']->employees) && $jobData['job']->employees) {
                    $actualCount = $jobData['job']->employees->count();
                } elseif (isset($jobData['job']->userJobs) && $jobData['job']->userJobs) {
                    $actualCount = $jobData['job']->userJobs->count();
                } elseif (isset($jobData['job']->users) && $jobData['job']->users) {
                    $actualCount = $jobData['job']->users->count();
                }
            } catch (\Exception $e) {
                $actualCount = 0;
            }
            
            $plannedCount = $jobData['job']->manpower ?? 0;
        @endphp
        
        {{-- Card Header --}}
        <div class="px-4 py-3 {{ $bgClass }} text-white text-center">
            <h{{ $level + 3 }} class="font-bold text-sm leading-snug tracking-tight">{{ $jobData['job']->name }}</h{{ $level + 3 }}>
            @if($jobData['job']->orgStructure && $jobData['job']->orgStructure->name)
                <div class="text-[10px] font-medium opacity-90 mt-1 uppercase tracking-wide">
                    {{ $jobData['job']->orgStructure->name }}
                </div>
            @endif
        </div>
        
        {{-- Card Body --}}
        <div class="p-3 text-xs text-gray-600 flex flex-col gap-2">
            @if(($jobData['job']->pangkat && $jobData['job']->pangkat->name) ||
                ($jobData['job']->pangkat && $jobData['job']->pangkat->golongan) ||
                ($jobData['job']->kelas && $jobData['job']->kelas->name))
                <div class="bg-gray-50 rounded-lg p-2.5 space-y-1.5 border border-gray-100">
                    @if($jobData['job']->pangkat && $jobData['job']->pangkat->name)
                        <div class="flex justify-between items-center group">
                            <span class="font-medium text-gray-500 group-hover:text-primary-600 transition-colors">Pangkat</span>
                            <span class="font-semibold text-gray-900 text-right">{{ $jobData['job']->pangkat->name }}</span>
                        </div>
                    @endif
                    @if($jobData['job']->pangkat && $jobData['job']->pangkat->golongan)
                        <div class="flex justify-between items-center group">
                            <span class="font-medium text-gray-500 group-hover:text-primary-600 transition-colors">Golongan</span>
                            <span class="font-semibold text-gray-900 text-right">{{ $jobData['job']->pangkat->golongan }}</span>
                        </div>
                    @endif
                    @if($jobData['job']->kelas && $jobData['job']->kelas->name)
                        <div class="flex justify-between items-center group">
                            <span class="font-medium text-gray-500 group-hover:text-primary-600 transition-colors">Kelas</span>
                            <span class="font-semibold text-gray-900 text-right">{{ $jobData['job']->kelas->name }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex items-center justify-between px-1.5 mt-1">
                <span class="font-medium text-gray-500">Manpower</span>
                <div class="flex items-center gap-1.5 font-bold">
                    <span class="text-gray-900">{{ $actualCount }}</span>
                    <span class="text-gray-300 font-normal">/</span>
                    <span class="text-gray-900">{{ $plannedCount }}</span>
                </div>
            </div>

            {{-- Button Lihat Detail --}}
            <button
                type="button"
                wire:click="$dispatch('open-job-detail', { jobId: '{{ $jobData['job']->id }}' })"
                class="mt-1.5 w-full flex items-center justify-center gap-1.5 py-2 text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-colors font-semibold border border-transparent hover:border-primary-100"
            >
                <x-heroicon-m-information-circle class="w-4 h-4" />
                <span>Lihat Detail</span>
            </button>
        </div>
    </div>

    {{-- Jika ada children --}}
    @if(count($jobData['children']) > 0)
        <div class="org-line-down w-[2px] h-8 bg-gray-300 relative z-0"></div>

        <div class="org-level flex justify-center gap-8 relative" data-level="job-level-{{ $level + 1 }}">
            @foreach($jobData['children'] as $childJobData)
                @include('livewire.partials.job-node', ['jobData' => $childJobData, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>