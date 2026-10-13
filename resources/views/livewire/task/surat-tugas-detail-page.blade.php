<div>
    <x-page-header title="Surat Perintah Tugas" :back="route('tugas')" />

    <div class="form-card">
        @php
            // label => [nilai, icon, warna]
            $rows = [
                'Nomor' => [$surat->nomor ?: '-', 'fa-hashtag', '#6366f1'],
                'Ditugaskan' => [$surat->peserta->map(fn ($p) => $p->displayName())->filter()->implode(', '), 'fa-users', '#0d9488'],
                'Tugas / Keperluan' => [$surat->tugas, 'fa-clipboard-list', '#d4a017'],
                'Tanggal' => [$surat->periodeLabel(), 'fa-calendar-days', '#2563eb'],
                'Waktu' => [$surat->waktu, 'fa-clock', '#7c3aed'],
                'Tempat' => [$surat->tempat, 'fa-location-dot', '#dc2626'],
                'Transportasi' => [$surat->transportasi, 'fa-car', '#0891b2'],
                'Lain-lain' => [$surat->lain_lain, 'fa-ellipsis', '#64748b'],
                'Pemberi Tugas' => [trim($surat->nama_ttd . ($surat->jabatan_ttd ? ' · ' . $surat->jabatan_ttd : '')), 'fa-signature', '#b45309'],
            ];
        @endphp

        @foreach (array_filter($rows, fn ($r) => filled($r[0])) as $label => [$value, $icon, $color])
            <div style="display:flex;gap:16px;padding:10px 0;border-bottom:1px solid var(--border-soft);flex-wrap:wrap;">
                <div style="width:180px;font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid {{ $icon }}" style="width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:13px;color:{{ $color }};background:{{ $color }}1a;"></i>
                    {{ $label }}
                </div>
                <div style="flex:1;min-width:200px;font-size:14px;font-weight:600;color:var(--text-heading);">{!! nl2br(e($value)) !!}</div>
            </div>
        @endforeach

        <div style="margin-top:20px;">
            <a href="{{ route('surat-tugas.preview', $surat) }}" target="_blank" class="btn-primary">
                <i class="fa-solid fa-file-pdf"></i> Lihat PDF
            </a>
        </div>
    </div>
</div>
