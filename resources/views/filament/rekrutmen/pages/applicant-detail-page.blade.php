<x-filament-panels::page>

@php
$application = $this->application;
$answers = $application->answersMap();
$co = $application->jobOpening->company;
$brand = '#042c6c';
$statusMap = [
    'applied' => ['label' => 'Lamaran Masuk', 'color' => 'gray'],
    'screening' => ['label' => 'Peninjauan Berkas', 'color' => 'info'],
    'shortlisted' => ['label' => 'Lolos Seleksi Berkas', 'color' => 'warning'],
    'interview' => ['label' => 'Wawancara', 'color' => 'warning'],
    'offering' => ['label' => 'Offering Letter', 'color' => 'warning'],
    'mcu' => ['label' => 'Medical Check Up', 'color' => 'warning'],
    'onboarding' => ['label' => 'TTD & Onboarding', 'color' => 'success'],
    'passed' => ['label' => 'Diterima', 'color' => 'success'],
    'rejected' => ['label' => 'Tidak Dilanjutkan', 'color' => 'danger'],
];
$s = $statusMap[$application->status] ?? ['label' => $application->status, 'color' => 'gray'];
@endphp

<div class="-mt-2 mb-2">
    <a href="{{ route('filament.rekrutmen.pages.applicants-page') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 no-underline">
        <x-heroicon-m-arrow-left class="w-4 h-4" /> Kembali ke Daftar Pelamar
    </a>
</div>

<div class="relative overflow-hidden rounded-xl text-white px-6 py-5"
     style="background: linear-gradient(135deg, {{ $brand }} 0%, {{ $brand }}bb 100%);">
    <div class="relative z-[1] flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="text-xs font-bold uppercase tracking-widest text-white/60 mb-1">Melamar untuk</div>
            <div class="text-xl font-extrabold tracking-tight">{{ $application->jobOpening->title }}</div>
            <div class="text-sm text-white/75 mt-1">{{ $co?->name ?? $application->jobOpening->company_name }}</div>
            <div class="text-xs text-white/50 mt-2">Dikirim {{ $application->created_at?->translatedFormat('j F Y, H:i') }} WIB</div>
        </div>
        <x-filament::badge :color="$s['color']" size="lg">{{ $s['label'] }}</x-filament::badge>
    </div>
</div>

@php $employeeMatches = $application->formerEmployeeMatches(); @endphp
@if($employeeMatches->isNotEmpty())
<div class="rounded-xl px-5 py-4 bg-amber-50 border border-amber-200 flex items-start gap-3">
    <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
    <div class="text-sm text-amber-800">
        <div class="font-bold mb-1.5">NIK pelamar ini cocok dengan data karyawan di HRIS:</div>
        <ul class="space-y-1 list-disc list-inside">
            @foreach($employeeMatches as $emp)
                <li>
                    <strong>{{ $emp->company?->name ?? '—' }}</strong>
                    — {{ $emp->profile?->jobPosition?->name ?? 'jabatan tidak diketahui' }}
                    @if($emp->trashed())
                        <span class="text-red-700 font-semibold">(sudah keluar{{ $emp->deleted_at ? ', ' . $emp->deleted_at->translatedFormat('j F Y') : '' }})</span>
                    @else
                        <span class="text-emerald-700 font-semibold">(masih aktif bekerja)</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<x-filament::section heading="Riwayat Status" icon="heroicon-m-clock" collapsible collapsed>
    @php $logs = $application->statusLogs->sortByDesc('changed_at'); @endphp
    @if($logs->isEmpty())
        <p class="text-sm text-gray-400">Belum ada riwayat.</p>
    @else
        <div class="flex flex-col gap-3">
            @foreach($logs as $log)
            <div class="flex items-start gap-3">
                <x-filament::badge :color="$statusMap[$log->to_status]['color'] ?? 'gray'" size="sm">
                    {{ $statusMap[$log->to_status]['label'] ?? $log->to_status }}
                </x-filament::badge>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $log->changed_at?->translatedFormat('j F Y, H:i') }} WIB · oleh
                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $log->actorName() }}</span>
                    @if($log->from_status)
                        <span class="ml-1 text-xs text-gray-400">(dari {{ $statusMap[$log->from_status]['label'] ?? $log->from_status }})</span>
                    @endif
                    @if($log->notes)
                        <div class="mt-0.5 text-xs italic">"{{ $log->notes }}"</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif
</x-filament::section>

@if($application->candidate)
    @include('filament.rekrutmen.pages.partials.candidate-profile-view', ['candidate' => $application->candidate])
@endif

@if($co?->formSections->isNotEmpty())
    @foreach($co->formSections as $section)
    <x-filament::section :heading="$section->title" icon="heroicon-m-document-text">
        <dl class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach($section->fields as $field)
            @php $val = $answers[$field->name] ?? null; $isEmpty = $val === null || $val === ''; @endphp
            <div class="py-3 first:pt-0 last:pb-0 flex flex-col gap-1">
                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $field->label }}</dt>
                <dd>
                    @if($field->type === 'file')
                        @if($isEmpty)
                            <span class="text-sm text-gray-400">— belum diupload —</span>
                        @else
                            <a href="{{ route('rekrutmen.pelamar.dokumen', [$application->id, $field->name]) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                                <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" /> Buka berkas
                            </a>
                        @endif
                    @elseif($field->type === 'date' && !$isEmpty)
                        <span class="text-sm text-gray-950 dark:text-white">{{ \Carbon\Carbon::parse($val)->translatedFormat('j F Y') }}</span>
                    @elseif($field->type === 'textarea' && !$isEmpty)
                        <span class="text-sm text-gray-950 dark:text-white whitespace-pre-line leading-relaxed">{{ $val }}</span>
                    @else
                        <span class="text-sm {{ $isEmpty ? 'text-gray-400' : 'text-gray-950 dark:text-white' }}">{{ $isEmpty ? '—' : $val }}</span>
                    @endif
                </dd>
            </div>
            @endforeach
        </dl>
    </x-filament::section>
    @endforeach
@endif

@if(! in_array($application->status, ['rejected', 'passed']))
<x-filament::section heading="Tidak Dilanjutkan" icon="heroicon-m-x-circle" collapsible collapsed>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
        Tandai tidak dilanjutkan di luar alur normal — mis. kandidat mengundurkan diri, bukan gagal di salah satu tahap.
    </p>
    {{ $this->tidakDilanjutkanAction }}
</x-filament::section>
@endif

</x-filament-panels::page>
