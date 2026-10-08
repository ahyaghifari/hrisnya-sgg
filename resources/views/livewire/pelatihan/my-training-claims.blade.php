<div>
<x-page-header title="Pengajuan Pelatihan" :back="route('pelatihan')" />

@include('livewire.pelatihan.partials.mode-tabs', ['active' => 'sudah', 'list' => true])

@if (auth()->user()->can(\App\Enum\CustomPermissionEnum::AJUKAN_PELATIHAN->value))
    <div style="margin-bottom:20px;">
        <a href="{{ route('pelatihan.pengajuan') }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus" style="font-size:12px;"></i> Ajukan Pelatihan
        </a>
    </div>
@endif

<span class="pwa-section-title">Riwayat Pelatihan Terlaksana</span>

@php
    $claims = $this->claims;
    $badge = ['pending' => 'badge-pending', 'approved' => 'badge-approved', 'rejected' => 'badge-rejected'];
    $label = ['pending' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
@endphp

<div class="md:hidden" style="margin-top:10px;">
    <div class="pwa-list">
        @forelse ($claims as $claim)
            <a href="{{ route('pelatihan.klaim.show', $claim) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div class="pwa-tile-icon neutral"><i class="fa-solid fa-certificate"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $claim->title }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $claim->created_at?->translatedFormat('j F Y') }} &middot; JP {{ $claim->jp ?? '-' }}</p>
                    <span class="badge {{ $badge[$claim->status] ?? '' }}" style="margin-top:4px;">{{ $label[$claim->status] ?? $claim->status }}</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        @empty
            <div style="padding:32px 16px;text-align:center;font-size:13px;color:var(--text-muted);">Belum ada klaim.</div>
        @endforelse
    </div>
</div>

<div class="hidden md:block" style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:10px;">
    <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
        <thead>
            <tr style="border-bottom:1.5px solid var(--border);">
                <th style="text-align:left;padding:10px 14px;">Pelatihan</th>
                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jam Diinput</th>
                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">JP Disetujui</th>
                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($claims as $claim)
                @php $minutes = \App\Models\LMS\TrainingClaim::minutesOf($claim->sessions); @endphp
                <tr wire:key="claim-{{ $claim->id }}" style="border-bottom:1px solid var(--border-soft);">
                    <td style="padding:9px 14px;">
                        <div style="font-weight:600;">{{ $claim->title }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">
                            {{ $claim->sessions->count() }} hari &middot; {{ $claim->created_at?->translatedFormat('j F Y') }}
                            &middot; <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($claim->certificate_path) }}" target="_blank" style="color:var(--brand);">Sertifikat</a>
                        </div>
                        @if ($claim->review_note)
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Catatan HC: {{ $claim->review_note }}</div>
                        @endif
                    </td>
                    <td style="padding:9px 14px;white-space:nowrap;">{{ intdiv($minutes, 60) }} jam {{ $minutes % 60 }} menit</td>
                    <td style="padding:9px 14px;text-align:center;">{{ $claim->jp ?? '-' }}</td>
                    <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                        <span class="badge {{ $badge[$claim->status] ?? '' }}">{{ $label[$claim->status] ?? $claim->status }}</span>
                    </td>
                    <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                        <a href="{{ route('pelatihan.klaim.show', $claim) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Detail"><i class="fa-solid fa-arrow-right"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"style="padding:32px 16px;text-align:center;color:var(--text-muted);">Belum ada klaim.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $claims->links('livewire.partials.pagination') }}</div>
</div>
