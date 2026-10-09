<div>
<x-page-header :title="$record->type->label()" subtitle="Detail undangan untuk Anda" :back="route('dashboard')" />

@include('livewire.partials.invitation-detail', ['record' => $record])
</div>
