@php $org = $node['org']; @endphp
<div style="margin-left:{{ $depth * 18 }}px;padding:{{ $depth === 0 ? '0 0 6px' : '8px 0 4px' }};display:flex;align-items:center;gap:7px;color:{{ $depth === 0 ? 'var(--text-heading)' : 'var(--text-muted)' }};font-size:{{ $depth === 0 ? '13px' : '12px' }};font-weight:700;{{ $depth > 0 ? 'border-top:1px dashed var(--border-soft);' : '' }}">
    <i class="fa-solid fa-sitemap" style="font-size:{{ $depth === 0 ? '12px' : '10px' }};color:var(--text-faint);"></i> {{ $org->name }}
</div>

@foreach ($node['jobs'] as $jobNode)
    @include('livewire.presensi.partials.job-tree-node', ['node' => $jobNode, 'depth' => $depth + 1])
@endforeach

@foreach ($node['children'] as $childOrg)
    @include('livewire.presensi.partials.org-tree-node', ['node' => $childOrg, 'depth' => $depth + 1])
@endforeach
