<div>
    <x-page-header title="Daftar Penilaian" subtitle="Progress penilaian karyawan lintas jadwal" :back="route('dashboard')" />

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <select class="form-input" style="max-width:240px;" wire:model.live="jadwalId">
                <option value="">Semua jadwal</option>
                @foreach ($this->jadwalOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <input type="text" class="form-input" style="max-width:220px;" placeholder="Cari nama karyawan..." wire:model.live.debounce.400ms="search">
        </div>
        <a href="{{ route('penilaian.daftar.tambah') }}" wire:navigate class="btn-primary"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tambah Penilaian</a>
    </div>

    @php $rows = $this->rows; @endphp
    @if ($rows->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada karyawan yang ditambahkan ke jadwal penilaian.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($rows as $row)
                <a href="{{ route('assessment.show', $row->id) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon gold"><i class="fa-solid fa-user"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $row->target?->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row->assessments?->name }}</p>
                    </div>
                    <span class="badge" style="background:{{ $row->final_score !== null ? 'var(--success-soft-bg)' : 'var(--warning-soft-bg)' }};color:{{ $row->final_score !== null ? 'var(--success-soft-text)' : 'var(--warning-soft-text)' }};font-size:11px;padding:3px 8px;border-radius:6px;white-space:nowrap;">
                        {{ $row->final_score !== null ? number_format($row->final_score, 2) . ' · ' . $row->final_category : 'Belum diisi' }}
                    </span>
                </a>
            @endforeach
        </div>
        <div style="margin-top:16px;">{{ $rows->links() }}</div>
    @endif
</div>
