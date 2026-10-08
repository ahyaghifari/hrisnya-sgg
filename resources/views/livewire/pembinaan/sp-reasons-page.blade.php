<div>
    <x-page-header title="Master Pelanggaran & Dasar" subtitle="Poin Surat Peringatan dan dasar klarifikasi Berita Acara dipilih dari daftar ini" :back="route('pembinaan.pengaturan')" />

    @php $isDasar = $kind === \App\Models\Sp\SpReason::KIND_DASAR; @endphp
    <div style="display:flex;gap:8px;margin-top:16px;">
        <button type="button" wire:click="$set('kind', 'pelanggaran')" class="{{ $isDasar ? 'btn-ghost' : 'btn-primary' }}"><i class="fa-solid fa-triangle-exclamation" style="font-size:12px;"></i> Pelanggaran (SP)</button>
        <button type="button" wire:click="$set('kind', 'dasar')" class="{{ $isDasar ? 'btn-primary' : 'btn-ghost' }}"><i class="fa-solid fa-scale-balanced" style="font-size:12px;"></i> Dasar Klarifikasi (BA)</button>
    </div>

    <div class="form-card" style="margin-top:16px;">
        <form wire:submit="save" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;">
            <div style="flex:1;min-width:220px;">
                <input type="text" wire:model="name" class="form-input" placeholder="{{ $isDasar ? 'Nama dasar, mis. Rekap presensi' : 'Nama pelanggaran, mis. Keterlambatan' }}">
                @error('name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            @unless ($isDasar)
                <div style="min-width:150px;">
                    <select wire:model="level" class="form-input">
                        <option value="">Level SP</option>
                        @foreach (\App\Enum\Sp\SpLevel::cases() as $lvl)
                            <option value="{{ $lvl->value }}">{{ $lvl->getLabel() }}</option>
                        @endforeach
                    </select>
                    @error('level') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            @endunless
            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> {{ $editingId ? 'Simpan' : 'Tambah' }}</button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="btn-ghost">Batal</button>
            @endif
        </form>
    </div>

    <div class="form-card" style="margin-top:12px;">
        @forelse ($this->reasons as $reason)
            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                <span style="flex:1;font-size:13.5px;font-weight:600;color:{{ $reason->is_active ? 'var(--text-heading)' : 'var(--text-faint)' }};">
                    @unless ($isDasar)
                        @php $lc = ['sp1' => ['#fef9c3', '#a16207'], 'sp2' => ['#ffedd5', '#c2410c'], 'sp3' => ['#fee2e2', '#b91c1c']][$reason->sp_level?->value] ?? ['#f1f5f9', '#64748b']; @endphp
                        <span class="badge" style="background:{{ $lc[0] }};color:{{ $lc[1] }};margin-right:6px;">{{ $reason->sp_level?->getLabel() ?? 'Semua level' }}</span>
                    @endunless
                    {{ $reason->name }}
                    @unless ($reason->company_id) <span class="badge badge-cancelled" style="margin-left:6px;">Bawaan</span> @endunless
                </span>
                @if ($reason->company_id === auth()->user()->company_id)
                    <button type="button" wire:click="edit('{{ $reason->id }}')" class="btn-ghost" style="font-size:12px;"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" wire:click="toggle('{{ $reason->id }}')" class="btn-secondary" style="font-size:12px;padding:4px 10px;">
                        {{ $reason->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                @else
                    <span style="font-size:12px;color:var(--text-muted);">{{ $reason->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                @endif
            </div>
        @empty
            <p style="font-size:13px;color:var(--text-muted);margin:0;">{{ $isDasar ? 'Belum ada dasar klarifikasi.' : 'Belum ada pelanggaran.' }}</p>
        @endforelse
    </div>
</div>
