@props(['userId' => null, 'compact' => false])

{{-- Ringkasan pengajuan absensi: Sisa Cuti Tahunan + Jumlah Izin + Jumlah Lembur (+ Extra Shift utk karyawan berjadwal Shift), periode ini.
Dipakai bersama oleh Beranda (home-cards, 2 variasi ukuran lewat $compact) dan tab Absensi di
Profil Saya (data milik $userId). Isi 2 komponen Livewire yang sama persis; pembungkus/grid
tetap urusan pemakai. $userId null = user yang login. --}}
@php $suffix = ($compact ? 'compact' : 'full') . '-' . ($userId ?? 'me'); @endphp
{{-- Pembungkus (.bento-side-stats-compact / .bento-side-stats / .attendance-summary-grid) dikasih
kelas .summary-cols-4 oleh pemakai kalau ada 4 kartu (karyawan berjadwal Shift = ada kartu Extra
Shift): HP (compact) 2 kolom, >=768px 2 kolom, >=1024px 4 kolom. Tanpa kelas ini tetap 3 kolom. --}}
<style>
    .bento-side-stats-compact.summary-cols-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .bento-side-stats.summary-cols-4,
    .attendance-summary-grid.summary-cols-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 1024px) {
        .bento-side-stats.summary-cols-4,
        .attendance-summary-grid.summary-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
</style>
<livewire:leave-balance-card
    type-keyword="tahunan"
    label="Sisa Cuti Tahunan"
    icon="fa-sun"
    color="var(--brand)"
    icon-bg="rgba(4,44,108,0.1)"
    grid-class=""
    :user-id="$userId"
    :compact="$compact"
    :key="'leave-balance-tahunan-' . $suffix"
/>
<livewire:attendance-stat-cards :user-id="$userId" :compact="$compact" :key="'attendance-stat-cards-' . $suffix" />
