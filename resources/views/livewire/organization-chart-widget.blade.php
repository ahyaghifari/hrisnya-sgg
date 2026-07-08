<div>
@if ($this->company)
<x-filament::section>
    <div class="org-zoom-viewport relative w-full rounded-xl border border-gray-200 bg-gray-50/50 overflow-hidden" style="height: 70vh;" data-scale="0.5" data-pan-x="0" data-pan-y="0">
        <div class="org-zoom-toolbar absolute top-3 right-3 z-20 flex items-center gap-1 bg-white rounded-lg shadow-md border border-gray-200 p-1">
            <button type="button" class="org-zoom-btn" data-zoom-action="out" title="Perkecil">
                <x-heroicon-m-minus class="w-4 h-4" />
            </button>
            <span class="org-zoom-level px-1.5 text-xs font-semibold text-gray-500 select-none w-11 text-center">50%</span>
            <button type="button" class="org-zoom-btn" data-zoom-action="in" title="Perbesar">
                <x-heroicon-m-plus class="w-4 h-4" />
            </button>
            <div class="w-px h-5 bg-gray-200 mx-0.5"></div>
            <button type="button" class="org-zoom-btn" data-zoom-action="reset" title="Reset tampilan">
                <x-heroicon-m-arrows-pointing-out class="w-4 h-4" />
            </button>
        </div>

        <div class="org-zoom-pan absolute inset-0 cursor-grab overflow-hidden">
            <div class="org-chart-container inline-flex flex-col items-center p-4 transition-transform duration-100" style="transform-origin: 0 0; transform: scale(0.5);">
            {{-- LEVEL: COMPANY --}}
            <div class="flex flex-col items-center w-full">
                <div class="flex justify-center w-full">
                    <div class="org-card px-8 py-4 rounded-xl shadow-lg ring-1 ring-gray-900/5 bg-primary-600 text-white min-w-[250px] text-center transform transition hover:-translate-y-1 hover:shadow-xl z-10 relative">
                        <h3 class="text-lg font-bold">{{ $this->company->name }}</h3>
                    </div>
                </div>

                {{-- Jobs tanpa org structure (org_structure_id = null) --}}
                @if(count($this->jobsWithoutOrgStructure) > 0)
                    {{-- LEVEL: JOBS WITHOUT ORG STRUCTURE --}}
                    <div class="org-level-without-org flex justify-center gap-8 relative mt-8">
                        @foreach($this->jobsWithoutOrgStructure as $jobWithoutOrgData)
                            @include('livewire.partials.job-node', ['jobData' => $jobWithoutOrgData, 'level' => 0])
                        @endforeach
                    </div>
                @endif

                @if(count($this->jobHierarchy) > 0)
                    {{-- Garis ke bawah --}}
                    <div class="w-[2px] h-8 bg-gray-300 mx-auto relative z-0"></div>

                    {{-- LEVEL: ROOT JOBS --}}
                    <div class="org-level flex justify-center gap-8 relative" data-level="root-jobs">
                        @foreach($this->jobHierarchy as $rootJobData)
                            @include('livewire.partials.job-node', ['jobData' => $rootJobData, 'level' => 1])
                        @endforeach
                    </div>
                @else
                    <div class="w-[2px] h-8 bg-gray-300 mx-auto relative z-0"></div>
                    <div class="py-8 text-center text-gray-500">
                        <p class="text-sm">Belum ada struktur jabatan yang didefinisikan untuk perusahaan ini.</p>
                        <p class="text-xs text-gray-400 mt-2">Silakan tambahkan jabatan melalui menu Master Data.</p>
                    </div>

                    <div class="mt-6">
                        <x-filament::button
                            color="primary"
                            wire:click="createJob()"
                        >
                            Tambah Jabatan
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </div>
        </div>
    </div>
</x-filament::section>
@else
<x-filament::section>
    <x-slot name="heading">Tidak ada data</x-slot>
    <x-slot name="description">Silakan pilih perusahaan di atas.</x-slot>
</x-filament::section>
@endif

<style>
.org-zoom-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    color: #4b5563;
    transition: background-color 0.15s;
}
.org-zoom-btn:hover {
    background-color: #f3f4f6;
    color: #111827;
}
.org-zoom-pan.is-panning {
    cursor: grabbing;
}

.org-level {
    position: relative;
}

/* Garis horizontal connector di org-level dengan lebih dari 1 child */
.org-level:has(> .org-branch:nth-child(2))::before {
    content: '';
    position: absolute;
    top: 0;
    left: var(--line-left, 0);
    width: var(--line-width, 100%);
    height: 2px;
    background-color: #d1d5db; /* gray-300 */
    z-index: 0;
}

@media (max-width: 1024px) {
    .org-level {
        flex-direction: column;
        gap: 1.5rem;
        align-items: center;
    }
    .org-level::before {
        display: none !important;
    }
    .org-branch > .org-line-up {
        display: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function drawOrgChartConnections() {
        const levels = document.querySelectorAll('.org-level');
        
        levels.forEach(level => {
            const branches = level.querySelectorAll(':scope > .org-branch');
            
            if (branches.length <= 1) {
                level.style.setProperty('--line-display', 'none');
                return;
            }
            
            const firstLineUp = branches[0].querySelector('.org-line-up');
            const lastLineUp = branches[branches.length - 1].querySelector('.org-line-up');
            
            if (!firstLineUp || !lastLineUp) return;
            
            const levelRect = level.getBoundingClientRect();
            const firstRect = firstLineUp.getBoundingClientRect();
            const lastRect = lastLineUp.getBoundingClientRect();
            
            const leftPos = firstRect.left + (firstRect.width / 2) - levelRect.left;
            const rightPos = lastRect.left + (lastRect.width / 2) - levelRect.left;
            
            level.style.setProperty('--line-left', leftPos + 'px');
            level.style.setProperty('--line-width', (rightPos - leftPos) + 'px');
        });
    }
    
    window.__drawOrgChartConnections = drawOrgChartConnections;

    setTimeout(drawOrgChartConnections, 150);

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(drawOrgChartConnections, 250);
    });

    if (typeof Livewire !== 'undefined') {
        Livewire.hook('message.processed', () => {
            setTimeout(drawOrgChartConnections, 150);
            window.HSStaticMethods?.autoInit('overlay');
        });
    }
    
    const observer = new MutationObserver(function(mutations) {
        setTimeout(drawOrgChartConnections, 100);
    });
    
    const chartContainer = document.querySelector('.org-chart-container');
    if (chartContainer) {
        observer.observe(chartContainer, {
            childList: true,
            subtree: true
        });
    }
});
</script>

<script>
(function () {
    function getViewport(el) {
        return el.closest('.org-zoom-viewport');
    }

    function getState(viewport) {
        return {
            scale: parseFloat(viewport.dataset.scale || '0.5'),
            panX: parseFloat(viewport.dataset.panX || '0'),
            panY: parseFloat(viewport.dataset.panY || '0'),
        };
    }

    function applyState(viewport, state) {
        state.scale = Math.min(Math.max(state.scale, 0.05), 2);
        viewport.dataset.scale = state.scale;
        viewport.dataset.panX = state.panX;
        viewport.dataset.panY = state.panY;

        const chart = viewport.querySelector('.org-chart-container');
        if (chart) {
            chart.style.transform = `translate(${state.panX}px, ${state.panY}px) scale(${state.scale})`;
        }

        const label = viewport.querySelector('.org-zoom-level');
        if (label) {
            label.textContent = Math.round(state.scale * 100) + '%';
        }

        if (window.__drawOrgChartConnections) {
            window.__drawOrgChartConnections();
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.org-zoom-btn');
        if (!btn) return;
        const viewport = getViewport(btn);
        if (!viewport) return;

        const state = getState(viewport);
        const action = btn.dataset.zoomAction;

        if (action === 'in') state.scale += 0.15;
        else if (action === 'out') state.scale -= 0.15;
        else if (action === 'reset') {
            state.scale = 0.5;
            state.panX = 0;
            state.panY = 0;
        }

        applyState(viewport, state);
    });

    document.addEventListener('wheel', function (e) {
        const viewport = getViewport(e.target);
        if (!viewport) return;

        e.preventDefault();
        const state = getState(viewport);
        state.scale += e.deltaY < 0 ? 0.08 : -0.08;
        applyState(viewport, state);
    }, { passive: false });

    let drag = null;

    document.addEventListener('mousedown', function (e) {
        const pan = e.target.closest('.org-zoom-pan');
        if (!pan || e.button !== 0) return;
        const viewport = getViewport(pan);
        if (!viewport) return;

        const state = getState(viewport);
        drag = {
            pan,
            viewport,
            startX: e.clientX,
            startY: e.clientY,
            originX: state.panX,
            originY: state.panY,
        };
        pan.classList.add('is-panning');
    });

    document.addEventListener('mousemove', function (e) {
        if (!drag) return;
        const state = getState(drag.viewport);
        state.panX = drag.originX + (e.clientX - drag.startX);
        state.panY = drag.originY + (e.clientY - drag.startY);
        applyState(drag.viewport, state);
    });

    document.addEventListener('mouseup', function () {
        if (!drag) return;
        drag.pan.classList.remove('is-panning');
        drag = null;
    });
})();
</script>

</div>
