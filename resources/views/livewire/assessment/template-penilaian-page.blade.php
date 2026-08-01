<div>
    <x-page-header title="Template Penilaian" subtitle="Susunan indikator penilaian karyawan" :back="route('dashboard')" />

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
        <input type="text" class="form-input" style="max-width:280px;" placeholder="Cari nama template..." wire:model.live.debounce.400ms="search">
        <a href="{{ route('penilaian.template.create') }}" wire:navigate class="btn-primary"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tambah Template</a>
    </div>

    @php $templates = $this->templates; @endphp
    @if ($templates->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada template penilaian.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($templates as $template)
                <a href="{{ route('penilaian.template.edit', $template->id) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon rust"><i class="fa-solid fa-file-lines"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $template->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            {{ $template->assessmentType?->name ?? '-' }} &middot; {{ $template->template_items_count }} indikator
                        </p>
                    </div>
                    <span class="badge" style="background:{{ $template->is_active ? 'var(--success-soft-bg)' : 'var(--danger-soft-bg)' }};color:{{ $template->is_active ? 'var(--success-soft-text)' : 'var(--danger-soft-text)' }};font-size:11px;padding:3px 8px;border-radius:6px;">
                        {{ $template->is_active ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                </a>
            @endforeach
        </div>
        <div style="margin-top:16px;">{{ $templates->links() }}</div>
    @endif
</div>
