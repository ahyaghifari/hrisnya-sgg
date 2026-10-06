@php
    $disabled = $isDisabled();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            positions: @js($positions),
            get selected() { return (this.state ?? []).filter(id => this.positions[id]); },
            has(leaves) { return leaves.length > 0 && leaves.every(id => this.selected.includes(id)); },
            some(leaves) { return leaves.some(id => this.selected.includes(id)); },
            toggle(leaves, on) {
                const set = new Set(this.selected);
                leaves.forEach(id => on ? set.add(id) : set.delete(id));
                this.state = [...set];
            },
            get preview() {
                return this.selected.map(id => ({ id, ...this.positions[id] }))
                    .sort((a, b) => (a.path + a.name).localeCompare(b.path + b.name));
            },
            get totalUsers() { return this.preview.reduce((sum, p) => sum + p.users, 0); },
        }"
        style="display: grid; gap: 16px;"
    >
        <div style="max-height: 420px; overflow: auto; padding: 8px 12px; border: 1px solid rgba(128,128,128,.3); border-radius: 8px;">
            @forelse ($tree as $node)
                @include('filament.forms.announcement-target-node', ['node' => $node, 'depth' => 0])
            @empty
                <p style="opacity: .6;">Belum ada perusahaan.</p>
            @endforelse
        </div>

        <div style="padding: 8px 12px; border: 1px solid rgba(128,128,128,.3); border-radius: 8px;">
            <p style="font-weight: 600; margin-bottom: 6px;">
                Preview penerima:
                <span x-text="preview.length"></span> jabatan,
                <span x-text="totalUsers"></span> karyawan aktif
            </p>
            <p x-show="!preview.length" style="opacity: .6;">Belum ada jabatan dipilih.</p>
            <ul style="max-height: 260px; overflow: auto; display: grid; gap: 2px;">
                <template x-for="p in preview" :key="p.id">
                    <li style="font-size: 13px;">
                        <span style="opacity: .6;" x-text="p.path + ' › '"></span><span style="font-weight: 500;" x-text="p.name"></span>
                        <span style="opacity: .6;" x-text="'(' + p.users + ')'"></span>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</x-dynamic-component>
