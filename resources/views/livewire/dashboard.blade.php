<div>
<x-page-header title="Beranda" size="lg" />

{{-- Wrapper kartu persetujuan/tanda tangan: kartunya render display:none kalau kosong, tapi margin-bottom 20px wrapper tetap
makan tempat -> jarak ekstra di atas welcome card. Margin cuma berlaku kalau ada anak yang tampil. --}}
<style>
    .dash-approvals { margin-bottom: 20px; }
    .dash-approvals:not(:has(> [wire\:id]:not([style*="display:none"]):not([style*="display: none"]))) { margin-bottom: 0; }
</style>
<div class="dash-approvals">
    <livewire:pending-approvals-card />
    <livewire:presensi.shift-swap-approvals-card />
    <livewire:document.pending-signatures-card />
</div>

@include('livewire.partials.home-cards')
</div>