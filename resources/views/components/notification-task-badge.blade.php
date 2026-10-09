@props(['data' => []])
{{-- Penanda notifikasi yang berkaitan dengan Tugas (individu, Board, atau permintaan review) - dibedakan dari notifikasi harian lain (presensi, persetujuan, dst). --}}
@if (($data['request_type'] ?? null) === 'task')
    <span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;padding:1px 7px;margin-bottom:3px;border-radius:99px;background:var(--brand-soft);color:var(--brand);">
        <i class="fa-solid fa-list-check" style="font-size:9px;"></i> {{ ! empty($data['project_id']) ? 'Tugas · Board' : 'Tugas' }}
    </span>
@endif
