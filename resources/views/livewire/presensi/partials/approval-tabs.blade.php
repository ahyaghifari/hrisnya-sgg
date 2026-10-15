{{-- Tab status rekap (trait FiltersByApprovalTab): Disetujui (default) | Dalam proses approval. --}}
<div style="display:inline-flex;gap:2px;padding:3px;margin-bottom:12px;background:var(--surface-alt);border-radius:8px;">
    @foreach (['approved' => 'Disetujui', 'in_review' => 'Dalam Proses Approval'] as $key => $label)
        <button type="button" wire:click="setTab('{{ $key }}')"
                style="padding:6px 14px;font-size:12px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $tab === $key ? 'var(--surface)' : 'transparent' }};color:{{ $tab === $key ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $tab === $key ? 'var(--shadow-card)' : 'none' }};">{{ $label }}</button>
    @endforeach
</div>
