@php
    $job = $node['job'];
    $blockedBy = $this->blockedJobPositions[$job->id] ?? null;
@endphp
<label style="margin-left:{{ $depth * 20 }}px;padding:5px 8px;display:flex;align-items:center;gap:9px;font-size:12.5px;border-radius:6px;{{ $blockedBy ? '' : 'cursor:pointer;' }}" {!! $blockedBy ? '' : 'onmouseover="this.style.background=\'var(--border-soft)\'" onmouseout="this.style.background=\'\'"' !!}>
    @if ($blockedBy)
        <i class="fa-solid fa-xmark" style="color:#dc2626;width:19px;text-align:center;flex-shrink:0;" title="Sudah pakai jadwal {{ $blockedBy }}"></i>
    @else
        <span class="custom-checkbox" style="--accent:#dc2626;">
            <input type="checkbox" wire:model="selectedJobPositionIds" value="{{ $job->id }}">
            <span class="box"><i class="fa-solid fa-check"></i></span>
        </span>
    @endif
    <i class="fa-solid fa-user" style="font-size:9.5px;color:var(--text-faint);flex-shrink:0;"></i>
    <span>{{ $job->name }}</span>
    @if ($blockedBy)
        <span style="font-size:10.5px;color:#dc2626;white-space:nowrap;">// {{ $blockedBy }}</span>
    @endif
</label>
