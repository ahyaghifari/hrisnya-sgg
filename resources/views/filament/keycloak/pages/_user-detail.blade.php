@php($full = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))

<div style="display:flex;flex-direction:column;gap:1rem;font-size:.875rem">
    <div style="display:grid;grid-template-columns:9rem 1fr;gap:.4rem 1rem">
        <div style="color:#6b7280">Username</div>
        <div style="font-weight:600">{{ $user['username'] ?? '—' }}</div>

        <div style="color:#6b7280">Nama</div>
        <div>{{ $full ?: '—' }}</div>

        <div style="color:#6b7280">Email</div>
        <div>{{ $user['email'] ?? '—' }}</div>

        <div style="color:#6b7280">Status</div>
        <div>
            @if ($user['enabled'] ?? false)
                <span style="color:#047857;font-weight:600">Aktif</span>
            @else
                <span style="color:#b91c1c;font-weight:600">Nonaktif</span>
            @endif
        </div>

        <div style="color:#6b7280">NIP</div>
        <div>{{ $nip ?: '—' }}</div>

        <div style="color:#6b7280">Karyawan HRIS</div>
        <div>
            @if ($hrisName)
                <span style="color:#047857">{{ $hrisName }}</span>
            @else
                <span style="color:#9ca3af">— tidak tersambung —</span>
            @endif
        </div>
    </div>

    <div>
        <div style="color:#6b7280;margin-bottom:.35rem">Grup ({{ count($groups) }})</div>
        @if (empty($groups))
            <div style="color:#9ca3af">Belum masuk grup mana pun.</div>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                @foreach ($groups as $g)
                    <span style="display:inline-flex;align-items:center;padding:.15rem .5rem;border-radius:999px;background:rgba(0,102,204,.12);color:#0b4a91;font-size:.8125rem">
                        {{ $g['path'] ?? $g['name'] }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>
