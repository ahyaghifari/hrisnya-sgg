<div>
<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Dashboard</h1>
    </div>
</div>

@include('livewire.partials.home-cards')
</div>
