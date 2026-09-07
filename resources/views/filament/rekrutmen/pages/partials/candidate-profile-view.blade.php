@php
$fmt = fn ($v) => match (true) {
    is_bool($v) => $v ? 'Ya' : 'Tidak',
    $v === null || $v === '' => '—',
    default => $v,
};
$dateFmt = fn ($v) => $v ? \Carbon\Carbon::parse($v)->translatedFormat('j F Y') : '—';
@endphp

<x-filament::section heading="Data Diri Kandidat" icon="heroicon-m-identification" collapsible>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-6">
        @foreach([
            'Nama Lengkap' => $candidate->name,
            'Nama Panggilan' => $candidate->nickname,
            'NIK' => $candidate->nik,
            'No. KK' => $candidate->no_kk,
            'Golongan Darah' => $candidate->blood_type,
            'Tinggi/Berat Badan' => $candidate->body_height_weight,
            'Agama' => $candidate->religion,
            'Kewarganegaraan' => $candidate->nationality,
            'Status Pernikahan' => $candidate->status_marital,
            'Tanggal Menikah' => $candidate->marriage_date ? $dateFmt($candidate->marriage_date) : null,
            'Nama Ibu Kandung' => $candidate->mother_name,
        ] as $label => $value)
        <div>
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $label }}</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($value) }}</div>
        </div>
        @endforeach
    </div>

    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Alamat Sesuai KTP</div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-6">
        @foreach([
            'Alamat' => $candidate->ktp_address, 'Kelurahan/Desa' => $candidate->village,
            'Kecamatan' => $candidate->district, 'Kota/Kabupaten' => $candidate->city,
            'Provinsi' => $candidate->province, 'Akun Media Sosial' => $candidate->social_media_accounts,
        ] as $label => $value)
        <div>
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $label }}</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($value) }}</div>
        </div>
        @endforeach
    </div>

    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">BPJS &amp; Rekening</div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-6">
        @foreach([
            'No. BPJS Kesehatan' => $candidate->no_bpjskes, 'Fasilitas Kesehatan' => $candidate->faskes,
            'No. BPJS Ketenagakerjaan' => $candidate->no_bpjstk, 'Perusahaan pada BPJS TK' => $candidate->company_bpjstk,
            'No. Rekening BSI' => $candidate->bsi_bank_number,
        ] as $label => $value)
        <div>
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $label }}</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($value) }}</div>
        </div>
        @endforeach
    </div>

    @if($candidate->familyMembers->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Data Keluarga</div>
    <div class="flex flex-col gap-2 mb-6">
        @foreach($candidate->familyMembers as $f)
        <div class="text-sm rounded-lg border border-gray-100 dark:border-white/5 p-3">
            <strong class="text-gray-900 dark:text-gray-100">{{ $f->relation }}</strong> — {{ $f->name }} ({{ $f->gender }}){{ $f->birth_date ? ', lahir ' . $dateFmt($f->birth_date) : '' }}
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $f->education }} · {{ $f->job }} · {{ $f->phone }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @if($candidate->educations->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Riwayat Pendidikan</div>
    <div class="flex flex-col gap-2 mb-6">
        @foreach($candidate->educations as $e)
        <div class="text-sm rounded-lg border border-gray-100 dark:border-white/5 p-3">
            <strong class="text-gray-900 dark:text-gray-100">{{ $e->level }}</strong> — {{ $e->school_name }} ({{ $e->major }})
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">IPK {{ $fmt($e->gpa) }} · {{ $e->start_year }}–{{ $e->end_year }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @if($candidate->trainings->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Riwayat Pelatihan</div>
    <div class="flex flex-col gap-2 mb-6">
        @foreach($candidate->trainings as $t)
        <div class="text-sm rounded-lg border border-gray-100 dark:border-white/5 p-3">
            <strong class="text-gray-900 dark:text-gray-100">{{ $t->type }}</strong> — {{ $t->institution }}
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $t->material }} · {{ $t->duration }} · {{ $t->location }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @if($candidate->organizations->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Pengalaman Organisasi</div>
    <div class="flex flex-col gap-2 mb-6">
        @foreach($candidate->organizations as $o)
        <div class="text-sm rounded-lg border border-gray-100 dark:border-white/5 p-3">
            <strong class="text-gray-900 dark:text-gray-100">{{ $o->organization_name }}</strong> — {{ $o->position }}
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $o->field }} · {{ $o->duration }} · {{ $o->location }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @if($candidate->workExperiences->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Pengalaman Kerja</div>
    <div class="flex flex-col gap-2 mb-6">
        @foreach($candidate->workExperiences as $w)
        <div class="text-sm rounded-lg border border-gray-100 dark:border-white/5 p-3">
            <strong class="text-gray-900 dark:text-gray-100">{{ $w->company_name }}</strong> — {{ $w->position }}
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                {{ $w->business_type }} · {{ $dateFmt($w->start_date) }} – {{ $w->is_current ? 'Sekarang' : $dateFmt($w->end_date) }}
                @if($w->salary) · Gaji terakhir Rp {{ number_format($w->salary, 0, ',', '.') }} @endif
            </div>
            @if($w->resign_reason)<div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Alasan resign: {{ $w->resign_reason }}</div>@endif
        </div>
        @endforeach
    </div>
    @endif

    @if($candidate->references->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Referensi</div>
    <div class="flex flex-col gap-2 mb-6">
        @foreach($candidate->references as $r)
        <div class="text-sm rounded-lg border border-gray-100 dark:border-white/5 p-3">
            <strong class="text-gray-900 dark:text-gray-100">{{ $r->name }}</strong> ({{ $r->relation }}) — {{ $r->job }}
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $r->phone }} · {{ $r->address }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @php $addInfo = $candidate->additionalInfo; @endphp
    @if($addInfo)
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Informasi Tambahan</div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-6">
        @foreach([
            'Bersedia Referensi Dihubungi' => $addInfo->allow_reference_check,
            'Memiliki Riwayat Penyakit' => $addInfo->has_disease,
            'Catatan Penyakit' => $addInfo->disease_notes,
            'Hobi' => $addInfo->hobbies,
            'Bahasa yang Dikuasai' => $addInfo->languages,
            'Fasilitas Terakhir' => $addInfo->last_facilities,
            'Ekspektasi Gaji & Fasilitas' => $addInfo->expected_salary_facilities,
            'Tersedia Mulai Bekerja' => $addInfo->available_start_date ? $dateFmt($addInfo->available_start_date) : null,
        ] as $label => $value)
        <div>
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $label }}</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($value) }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @php $selfAssess = $candidate->selfAssessment; @endphp
    @if($selfAssess)
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Penilaian Diri</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm mb-6">
        <div>
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Kelebihan</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($selfAssess->strengths) }}</div>
        </div>
        <div>
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Kekurangan</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($selfAssess->weaknesses) }}</div>
        </div>
        <div>
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Tingkat Kepuasan Karier</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($selfAssess->satisfaction_level) }}</div>
        </div>
        <div class="sm:col-span-2">
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Alasan</div>
            <div class="text-gray-900 dark:text-gray-100">{{ $fmt($selfAssess->reason) }}</div>
        </div>
    </div>
    @endif

    @if($candidate->documents->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Dokumen</div>
    <div class="flex flex-col gap-1.5 mb-6">
        @foreach($candidate->documents as $doc)
        <div class="text-sm flex items-center gap-2 text-gray-500 dark:text-gray-400">
            <x-heroicon-m-document class="w-4 h-4" />
            {{ $doc->document_label ?: $doc->doc_type }}
            <span class="text-xs italic">(unduh belum tersedia)</span>
        </div>
        @endforeach
    </div>
    @endif

    @if($candidate->agreements->isNotEmpty())
    <div class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2 mt-6">Persetujuan</div>
    <div class="flex flex-wrap gap-2">
        @foreach($candidate->agreements as $ag)
        <x-filament::badge color="success" icon="heroicon-m-check">{{ $ag->title ?? $ag->jobOpeningAgreement?->title ?? $ag->companyAgreement?->title ?? 'Persetujuan' }}</x-filament::badge>
        @endforeach
    </div>
    @endif

</x-filament::section>
