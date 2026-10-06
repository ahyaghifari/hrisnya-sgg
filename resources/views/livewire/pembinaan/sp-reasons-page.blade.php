<div class="p-4 md:p-6">
    <x-page-header title="Master Pelanggaran" subtitle="Dasar klarifikasi Berita Acara dan poin Surat Peringatan dipilih dari daftar ini" :back="route('pembinaan.pengaturan')" />

    <div class="form-card" style="margin-top:16px;">
        <form wire:submit="save" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;">
            <div style="flex:1;min-width:220px;">
                <input type="text" wire:model="name" class="form-input" placeholder="Nama pelanggaran, mis. Keterlambatan">
                @error('name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
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
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pelanggaran.</p>
        @endforelse
    </div>
</div>
