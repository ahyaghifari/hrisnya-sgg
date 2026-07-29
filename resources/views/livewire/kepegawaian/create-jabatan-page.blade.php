@php $isCreate = true; $locked = false; @endphp
<div>
<x-page-header title="Ajukan Jabatan Baru" subtitle="Struktur organisasi, detail, job evaluation, dan analisis beban kerja" :back="route('kepegawaian.jabatan')" />

<form wire:submit="submit">
    @include('livewire.kepegawaian.partials.jabatan-form-fields')

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('kepegawaian.jabatan') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <div style="display:flex;gap:10px;">
            <button type="button" wire:click="saveDraft" class="btn-ghost"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Draft</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan</button>
        </div>
    </div>
</form>
</div>
