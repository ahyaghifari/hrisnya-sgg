<div>
<x-page-header title="Undangan Klarifikasi" subtitle="Anda diundang untuk klarifikasi" :back="route('dashboard')" />

@include('livewire.partials.invitation-detail', ['record' => $record])
</div>
