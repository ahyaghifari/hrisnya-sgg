@php $isCreate = false; $locked = $this->isLocked; @endphp
<div>
<x-page-header title="Edit Jabatan" :subtitle="$record->name" :back="route('kepegawaian.jabatan')" />

@if ($locked)
    <div style="background:var(--warning-soft-bg,rgba(217,119,6,0.1));border:1px solid var(--warning-soft-border,rgba(217,119,6,0.3));border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-lock" style="color:#d97706;"></i>
        <p style="font-size:12.5px;color:var(--text-body);margin:0;">Jabatan ini sudah diajukan/diproses (status: <strong>{{ $record->status }}</strong>) — semua field terkunci, cuma bisa dilihat.</p>
    </div>
@endif

<form wire:submit="save">
    @include('livewire.kepegawaian.partials.jabatan-form-fields')

    @unless ($locked)
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan</button>
        </div>
    @endunless
</form>
</div>
