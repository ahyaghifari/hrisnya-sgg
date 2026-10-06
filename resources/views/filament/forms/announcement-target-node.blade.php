@php($leaves = $node["leaves"])
@if ($leaves)
@if (isset($node['children'][0]))
    <details @if ($depth === 0) open @endif style="margin-left: {{ $depth ? 18 : 0 }}px; margin-top: 4px;">
        <summary style="cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 2px 0;">
            <input type="checkbox" @disabled($disabled)
                :checked="has(@js($leaves))"
                x-effect="$el.indeterminate = !has(@js($leaves)) && some(@js($leaves))"
                @click.stop
                @change="toggle(@js($leaves), $event.target.checked)">
            <span style="font-weight: {{ $depth < 2 ? 600 : 500 }};">{{ $node['name'] }}</span>
            <span style="font-size: 12px; opacity: .6;">({{ count($leaves) }} jabatan)</span>
        </summary>
        @foreach ($node['children'] as $child)
            @include('filament.forms.announcement-target-node', ['node' => $child, 'depth' => $depth + 1])
        @endforeach
    </details>
@else
    <label style="margin-left: {{ 18 * $depth + 18 }}px; margin-top: 4px; display: flex; align-items: center; gap: 8px;">
        <input type="checkbox" @disabled($disabled)
            :checked="has(@js($leaves))"
            @change="toggle(@js($leaves), $event.target.checked)">
        <span>{{ $node['name'] }}</span>
        <span style="font-size: 12px; opacity: .6;">({{ $node['users'] }} karyawan)</span>
    </label>
@endif
@endif
