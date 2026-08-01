<div class="form-page-wrap">
    <x-page-header title="Edit Jadwal Penilaian" :back="route('penilaian.jadwal')" />

    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-calendar-check" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Detail Jadwal</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                @include('livewire.assessment.partials.jadwal-form-fields')
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;margin-top:20px;">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan Perubahan</button>
        </div>
    </form>
</div>
