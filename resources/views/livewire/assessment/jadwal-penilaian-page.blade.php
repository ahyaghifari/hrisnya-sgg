<div>
    <x-page-header title="Jadwal Penilaian" subtitle="Periode penilaian karyawan berjalan" :back="route('dashboard')" />

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
        <input type="text" class="form-input" style="max-width:280px;" placeholder="Cari nama jadwal..." wire:model.live.debounce.400ms="search">
        <a href="{{ route('penilaian.jadwal.create') }}" wire:navigate class="btn-primary"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tambah Jadwal</a>
    </div>

    @php $assessments = $this->assessments; @endphp
    @if ($assessments->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada jadwal penilaian.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($assessments as $assessment)
                <a href="{{ route('penilaian.jadwal.edit', $assessment->id) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon gold"><i class="fa-solid fa-calendar-check"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $assessment->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            {{ $assessment->template?->name ?? '-' }}
                            @if ($assessment->jobPosition) &middot; {{ $assessment->jobPosition->name }} @endif
                            &middot; {{ $assessment->user_assessments_count }} penilaian dibuat
                        </p>
                        @if ($assessment->start_date)
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                {{ \Carbon\Carbon::parse($assessment->start_date)->translatedFormat('j M Y') }} - {{ \Carbon\Carbon::parse($assessment->end_date)->translatedFormat('j M Y') }}
                            </p>
                        @endif
                    </div>
                    <span class="badge" style="background:{{ $assessment->status === 'active' ? 'var(--success-soft-bg)' : 'var(--danger-soft-bg)' }};color:{{ $assessment->status === 'active' ? 'var(--success-soft-text)' : 'var(--danger-soft-text)' }};font-size:11px;padding:3px 8px;border-radius:6px;">
                        {{ $assessment->status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                </a>
            @endforeach
        </div>
        <div style="margin-top:16px;">{{ $assessments->links() }}</div>
    @endif
</div>
