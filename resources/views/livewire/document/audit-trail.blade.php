{{-- Riwayat audit dokumen: bukti tanda tangan + semua kejadian (termasuk percobaan gagal). --}}
<div class="card-surface" style="margin:8px 0 14px;">
    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">
        <i class="fa-solid fa-clipboard-list" style="color:var(--brand);"></i> Riwayat Audit Dokumen
    </p>
    <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">
        No. {{ $this->document->document_number ?: '-' }} &middot; {{ $this->document->title }}
    </p>

    {{-- Tanda tangan --}}
    <p style="font-size:10.5px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:var(--text-muted);margin:0 0 8px;">Tanda Tangan</p>
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
        @forelse ($this->document->signatures->sortBy('signed_at') as $sig)
            <div style="border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12.5px;">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <strong>{{ $sig->signer_name }}</strong>
                    <span style="color:#15803d;"><i class="fa-solid fa-circle-check"></i> {{ $sig->signed_at->translatedFormat('d M Y, H:i:s') }}</span>
                </div>
                <div style="color:var(--text-muted);font-size:11.5px;margin-top:2px;">
                    {{ $this->roleLabel($sig->signer_role) }} &middot; {{ $sig->signer_position ?: '-' }}
                </div>
                <div style="color:var(--text-muted);font-size:11px;margin-top:4px;word-break:break-all;">
                    IP {{ $sig->ip_address ?: '-' }}
                    @if ($sig->verification) &middot; Kode {{ $sig->verification->verification_code }} @endif
                    <br>Hash isi: <span style="font-family:monospace;">{{ $sig->document_hash }}</span>
                </div>
            </div>
        @empty
            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada yang menandatangani.</p>
        @endforelse
    </div>

    {{-- Kejadian --}}
    <p style="font-size:10.5px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:var(--text-muted);margin:0 0 8px;">Kejadian</p>
    <div style="display:flex;flex-direction:column;">
        @forelse ($this->events as $event)
            @php $failed = $event->event === 'signature_failed'; @endphp
            <div wire:key="audit-{{ $event->id }}" style="display:flex;gap:10px;padding:8px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                <div style="width:8px;height:8px;border-radius:50%;margin-top:6px;flex-shrink:0;background:{{ $failed ? '#dc2626' : ($event->event === 'document_signed' ? '#16a34a' : 'var(--brand)') }};"></div>
                <div style="min-width:0;flex:1;font-size:12.5px;">
                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        <span style="font-weight:600;{{ $failed ? 'color:#dc2626;' : '' }}">{{ $event->label() }}</span>
                        <span style="color:var(--text-muted);font-size:11.5px;">{{ $event->created_at->translatedFormat('d M Y, H:i:s') }}</span>
                    </div>
                    <div style="color:var(--text-muted);font-size:11.5px;margin-top:1px;">
                        {{ $event->actor?->name ?? 'Sistem' }}
                        @if (! empty($event->meta['role'])) &middot; peran {{ $this->roleLabel($event->meta['role']) }} @endif
                        @if (($event->meta['reason'] ?? null) === 'password_salah') &middot; password salah @elseif (! empty($event->meta['reason'])) &middot; {{ $event->meta['reason'] }} @endif
                    </div>
                    @if ($event->ip_address || $event->user_agent)
                        <div style="color:var(--text-faint);font-size:11px;margin-top:1px;word-break:break-all;" @if ($event->user_agent) title="{{ $event->user_agent }}" @endif>
                            IP {{ $event->ip_address ?: '-' }}@if ($event->user_agent) &middot; {{ \Illuminate\Support\Str::limit($event->user_agent, 70) }} @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada kejadian tercatat.</p>
        @endforelse
    </div>
</div>
