<div x-data="{ tab: 'detail' }">
<x-page-header title="Detail Kandidat" :subtitle="$applicant->candidate?->name" :back="route('kepegawaian.lowongan.show', $applicant->jobVacancy)" />

@php
    $candidate = $applicant->candidate;
    $field = function (string $label, $value, bool $border = true) {
        $borderStyle = $border ? 'border-bottom:1px solid var(--border-soft);' : '';
        $valueHtml = ($value !== null && $value !== '')
            ? '<span style="font-size:13px;font-weight:600;color:var(--text-body);text-align:right;">' . e($value) . '</span>'
            : '<span style="font-size:12px;font-style:italic;color:var(--text-muted);text-align:right;">-</span>';

        return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:8px 0;' . $borderStyle . '">'
            . '<span style="font-size:12px;color:var(--text-muted);flex-shrink:0;">' . e($label) . '</span>'
            . $valueHtml
            . '</div>';
    };
    $sectionCard = fn (string $icon, string $title) => '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">'
        . '<div style="flex-shrink:0;width:30px;height:30px;border-radius:8px;background:rgba(4,44,108,0.08);display:flex;align-items:center;justify-content:center;">'
        . '<i class="fa-solid ' . $icon . '" style="color:var(--brand);font-size:12px;"></i></div>'
        . '<h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">' . e($title) . '</h4></div>';
@endphp

<div class="section-nav">
    <button type="button" @click="tab = 'detail'" class="section-nav-btn navy" :class="{ 'is-active': tab === 'detail' }">
        <i class="fa-solid fa-user-circle"></i> Detail Kandidat
    </button>
    <button type="button" @click="tab = 'proses'" class="section-nav-btn green" :class="{ 'is-active': tab === 'proses' }">
        <i class="fa-solid fa-clipboard-list"></i> Proses Rekrutmen
    </button>
</div>

<!-- ===================== TAB: DETAIL KANDIDAT ===================== -->
<div x-show="tab === 'detail'">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
        {!! $sectionCard('fa-document-text', 'Informasi Lamaran') !!}
        {!! $field('Posisi yang Dilamar', $applicant->jobVacancy?->jobPosition?->name) !!}
        {!! $field('Unit', $applicant->jobVacancy?->jobPosition?->orgStructure?->name) !!}
        {!! $field('Perusahaan', $applicant->jobVacancy?->company?->name) !!}
        {!! $field('Tahap Saat Ini', ucfirst(str_replace('_', ' ', $applicant->current_stage ?? ''))) !!}
        {!! $field('Status', \App\Enum\Recruitment\ProcessStatus::tryFrom($applicant->status)?->getLabel()) !!}
        {!! $field('Tanggal Melamar', $applicant->applied_at?->translatedFormat('d F Y H:i')) !!}
        {!! $field('Tanggal Selesai', $applicant->finished_at?->translatedFormat('d F Y H:i'), false) !!}
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
        {!! $sectionCard('fa-user', 'Informasi Pribadi') !!}
        {!! $field('Nama Lengkap', $candidate?->name) !!}
        {!! $field('Email', $candidate?->email) !!}
        {!! $field('No. Telepon', $candidate?->phone_number) !!}
        {!! $field('NIK', $candidate?->nik) !!}
        {!! $field('No. KK', $candidate?->no_kk) !!}
        {!! $field('Jenis Kelamin', $candidate?->gender === 'male' ? 'Laki-laki' : ($candidate?->gender === 'female' ? 'Perempuan' : $candidate?->gender)) !!}
        {!! $field('Tempat, Tanggal Lahir', trim(($candidate?->birth_place ?? '') . ', ' . ($candidate?->birth_date?->translatedFormat('d F Y') ?? ''), ', ')) !!}
        {!! $field('Status Pernikahan', $candidate?->status_marital ? ucfirst($candidate->status_marital) : null, false) !!}
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
        {!! $sectionCard('fa-map-pin', 'Alamat') !!}
        {!! $field('Alamat KTP', $candidate?->ktp_address) !!}
        {!! $field('Alamat Domisili', $candidate?->domicile_address) !!}
        {!! $field('Kelurahan', $candidate?->village) !!}
        {!! $field('Kecamatan', $candidate?->district) !!}
        {!! $field('Kota/Kabupaten', $candidate?->city) !!}
        {!! $field('Provinsi', $candidate?->province, false) !!}
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
        {!! $sectionCard('fa-graduation-cap', 'Pendidikan Terakhir & BPJS/Bank') !!}
        {!! $field('Pendidikan Terakhir', $candidate?->education) !!}
        {!! $field('Jurusan', $candidate?->major) !!}
        {!! $field('Universitas/Institusi', $candidate?->university) !!}
        {!! $field('Tahun Lulus', $candidate?->graduation_year) !!}
        {!! $field('No. BPJS Kesehatan', $candidate?->no_bpjskes) !!}
        {!! $field('No. BPJS Ketenagakerjaan', $candidate?->no_bpjstk) !!}
        {!! $field('No. Rekening BSI', $candidate?->bsi_bank_number, false) !!}
    </div>

    @if ($candidate?->documents->isNotEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
            {!! $sectionCard('fa-folder-open', 'Dokumen Kandidat') !!}
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach ($candidate->documents as $doc)
                    <a href="{{ asset(\Illuminate\Support\Facades\Storage::url($doc->file_path)) }}" target="_blank" style="font-size:12.5px;color:var(--brand);text-decoration:none;display:flex;align-items:center;gap:6px;">
                        <i class="fa-solid fa-link"></i> {{ $doc->document_label }} &ndash; {{ basename($doc->file_path) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($candidate?->workExperiences->isNotEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
            {!! $sectionCard('fa-briefcase', 'Pengalaman Kerja') !!}
            @foreach ($candidate->workExperiences as $exp)
                <div style="{{ !$loop->last ? 'border-bottom:1px solid var(--border-soft);padding-bottom:10px;margin-bottom:10px;' : '' }}">
                    {!! $field('Perusahaan', $exp->company_name) !!}
                    {!! $field('Posisi', $exp->position) !!}
                    {!! $field('Periode', trim(($exp->start_date?->format('Y') ?? '') . ' - ' . ($exp->end_date?->format('Y') ?? 'Sekarang')), false) !!}
                </div>
            @endforeach
        </div>
    @endif

    @if ($candidate?->educations->isNotEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
            {!! $sectionCard('fa-school', 'Riwayat Pendidikan Formal') !!}
            @foreach ($candidate->educations as $edu)
                <div style="{{ !$loop->last ? 'border-bottom:1px solid var(--border-soft);padding-bottom:10px;margin-bottom:10px;' : '' }}">
                    {!! $field('Tingkat', $edu->level) !!}
                    {!! $field('Sekolah/Universitas', $edu->school_name) !!}
                    {!! $field('Jurusan', $edu->major, false) !!}
                </div>
            @endforeach
        </div>
    @endif

    @if ($candidate?->familyMembers->isNotEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
            {!! $sectionCard('fa-people-roof', 'Hubungan Keluarga') !!}
            @foreach ($candidate->familyMembers as $member)
                <div style="{{ !$loop->last ? 'border-bottom:1px solid var(--border-soft);padding-bottom:10px;margin-bottom:10px;' : '' }}">
                    {!! $field('Nama', $member->name) !!}
                    {!! $field('Hubungan', $member->relation) !!}
                    {!! $field('Pekerjaan', $member->job, false) !!}
                </div>
            @endforeach
        </div>
    @endif

    @if ($candidate?->references->isNotEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
            {!! $sectionCard('fa-comments', 'Referensi') !!}
            @foreach ($candidate->references as $ref)
                <div style="{{ !$loop->last ? 'border-bottom:1px solid var(--border-soft);padding-bottom:10px;margin-bottom:10px;' : '' }}">
                    {!! $field('Nama', $ref->name) !!}
                    {!! $field('Pekerjaan', $ref->job) !!}
                    {!! $field('Nomor Telepon', $ref->phone, false) !!}
                </div>
            @endforeach
        </div>
    @endif

    @if ($candidate?->additionalInfos || $candidate?->selfAssessments)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            {!! $sectionCard('fa-star', 'Informasi Tambahan & Penilaian Diri') !!}
            {!! $field('Hobi/Aktivitas', $candidate?->additionalInfos?->hobbies) !!}
            {!! $field('Ekspektasi Gaji & Fasilitas', $candidate?->additionalInfos?->expected_salary_facilities) !!}
            {!! $field('Kelebihan', $candidate?->selfAssessments?->strengths) !!}
            {!! $field('Kekurangan', $candidate?->selfAssessments?->weaknesses, false) !!}
        </div>
    @endif
</div>

<!-- ===================== TAB: PROSES REKRUTMEN ===================== -->
<div x-show="tab === 'proses'">

    @if ($applicant->approval_status !== null)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
            {!! $sectionCard('fa-user-tie', 'Approval Direktur') !!}
            {!! $field('Status Approval', match ($applicant->approval_status) {
                'requested' => 'Diajukan ke Direktur',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => 'Menunggu Proses',
            }) !!}
            {!! $field('Disetujui/Ditolak Oleh', $applicant->approver?->name) !!}
            {!! $field('Tanggal', $applicant->approved_at?->translatedFormat('d F Y H:i')) !!}
            {!! $field('Catatan', $applicant->metadata['approval_notes'] ?? null) !!}
            @if ($applicant->approval_status === 'approved')
                {!! $field('Catatan Persetujuan Direktur', $applicant->metadata['director_approval_notes'] ?? null, false) !!}
            @elseif ($applicant->approval_status === 'rejected')
                {!! $field('Alasan Penolakan Direktur', $applicant->metadata['director_rejection_notes'] ?? null, false) !!}
            @endif

            @if ($this->canApproveOrRejectCandidate)
                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft);" x-data="{ mode: null }">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                        <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="btn-primary"><i class="fa-solid fa-check"></i> Setujui Kandidat</button>
                        <button type="button" @click="mode = mode === 'reject' ? null : 'reject'" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);"><i class="fa-solid fa-xmark"></i> Tolak Kandidat</button>
                    </div>
                    <div x-show="mode === 'approve'">
                        <textarea wire:model="directorNotes" class="form-input" rows="2" placeholder="Catatan approval (opsional)..."></textarea>
                        <button type="button" wire:click="approveCandidate" wire:confirm="Setujui kandidat ini?" class="btn-primary" style="margin-top:8px;">Konfirmasi Setujui</button>
                    </div>
                    <div x-show="mode === 'reject'">
                        <textarea wire:model="directorNotes" class="form-input" rows="2" placeholder="Alasan penolakan (opsional)..."></textarea>
                        <button type="button" wire:click="rejectCandidate" wire:confirm="Tolak kandidat ini?" class="btn-ghost" style="color:var(--danger-text);margin-top:8px;">Konfirmasi Tolak</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
            {!! $sectionCard('fa-clipboard-list', 'Status Rekrutmen Saat Ini - ' . ucfirst(str_replace('_', ' ', $applicant->status))) !!}
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                @if ($this->canProcessRecruitment)
                    <button type="button" wire:click="processRecruitment" wire:confirm="Proses lamaran ini?" class="btn-primary"><i class="fa-solid fa-check-double"></i> Proses Rekrutmen</button>
                @endif
                @if ($this->canAddRecruitmentProcess)
                    <button type="button" wire:click="toggleAddProcessForm" class="btn-primary"><i class="fa-solid fa-plus-circle"></i> Lanjutkan Tahapan Rekrutmen</button>
                @endif
            </div>
        </div>

        @if ($showAddProcessForm)
            <div style="background:var(--surface-alt);border-radius:12px;padding:14px;margin-bottom:14px;">
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Tipe <span style="color:var(--danger-text);">*</span></label>
                        <select wire:model="process_type" class="form-input">
                            <option value="">Pilih tipe proses</option>
                            @foreach ($this->processTypes as $pt)
                                <option value="{{ $pt->value }}">{{ $pt->getLabel() }}</option>
                            @endforeach
                        </select>
                        @error('process_type') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Proses <span style="color:var(--danger-text);">*</span></label>
                        <input type="text" wire:model="process_name" class="form-input" placeholder="Contoh: Wawancara HRD, Tes Teknis">
                        @error('process_name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2" style="gap:12px;">
                        <div>
                            <label class="form-label">Jadwal Pelaksanaan</label>
                            <input type="date" wire:model="scheduled_at" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Batas Waktu Penyelesaian</label>
                            <input type="date" wire:model="maximum_completion_at" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Link Terkait</label>
                        <input type="text" wire:model="link" class="form-input" placeholder="Link tes online / dokumen">
                    </div>
                    <div>
                        <label class="form-label">Lampiran</label>
                        <input type="file" wire:model="attachments" multiple class="form-input">
                        <div wire:loading wire:target="attachments" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
                    </div>
                    <div>
                        <label class="form-label">Catatan</label>
                        <textarea wire:model="notes" class="form-input" rows="2"></textarea>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" wire:click="submitAddProcess" class="btn-primary">Simpan Tahapan</button>
                        <button type="button" wire:click="toggleAddProcessForm" class="btn-ghost">Batal</button>
                    </div>
                </div>
            </div>
        @endif

        @forelse ($applicant->recruitmentProcesses as $process)
            @php
                $ps = \App\Enum\Recruitment\RecruitmentProcessStatus::tryFrom($process->status);
            @endphp
            <div style="border:1px solid var(--border-soft);border-radius:12px;padding:14px;margin-bottom:10px;">
                <div class="grid grid-cols-1 md:grid-cols-3" style="gap:12px;">
                    <div style="grid-column:span 2;">
                        {!! $field('Nama Proses', $process->process_name) !!}
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                            <span style="font-size:12px;color:var(--text-muted);">Status Proses</span>
                            <span class="badge" style="background:var(--surface-alt);color:var(--text-body);">{{ $ps?->getLabel() ?? $process->status }}</span>
                        </div>
                        {!! $field('Jadwal Pelaksanaan', isset($process->metadata['scheduled_at']) ? \Illuminate\Support\Carbon::parse($process->metadata['scheduled_at'])->translatedFormat('d F Y') : null) !!}
                        {!! $field('Link Terkait', $process->link) !!}
                        {!! $field('Catatan', $process->notes, false) !!}
                    </div>
                    <div>
                        @if ($process->score !== null)
                            {!! $field('Skor', $process->score) !!}
                        @endif
                        @if ($process->feedback)
                            {!! $field('Catatan Penilaian', $process->feedback) !!}
                        @endif
                        @if (($process->metadata['results_summary'] ?? null))
                            {!! $field('Ringkasan MCU', $process->metadata['results_summary']) !!}
                        @endif
                    </div>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                    @if ($this->canAddScore($process))
                        <button type="button" wire:click="toggleScoreForm('{{ $process->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;"><i class="fa-solid fa-star"></i> Tambah Penilaian</button>
                    @endif
                    @if ($this->canConfirmReschedule($process))
                        <button type="button" wire:click="confirmReschedule('{{ $process->id }}')" wire:confirm="Konfirmasi penjadwalan ulang?" class="btn-ghost" style="padding:5px 10px;font-size:11px;"><i class="fa-solid fa-calendar-days"></i> Konfirmasi Reschedule</button>
                    @endif
                    @if ($this->canMarkMcuCompleted($process))
                        <button type="button" wire:click="toggleMcuForm('{{ $process->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;"><i class="fa-solid fa-check-circle"></i> Tandai MCU Selesai</button>
                    @endif
                    @if ($this->canDeleteProcess($process))
                        <button type="button" wire:click="deleteProcess('{{ $process->id }}')" wire:confirm="Hapus tahapan ini?" class="btn-ghost" style="padding:5px 10px;font-size:11px;color:var(--danger-text);"><i class="fa-solid fa-trash"></i> Hapus</button>
                    @endif
                </div>

                @if ($scoringProcessId === $process->id)
                    <div style="background:var(--surface-alt);border-radius:10px;padding:12px;margin-top:10px;">
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <div>
                                <label class="form-label">Status</label>
                                <select wire:model="scoreStatus" class="form-input">
                                    <option value="">Pilih status</option>
                                    <option value="{{ \App\Enum\Recruitment\RecruitmentProcessStatus::COMPLETED->value }}">Lulus</option>
                                    <option value="{{ \App\Enum\Recruitment\RecruitmentProcessStatus::FAILED->value }}">Tidak Lulus</option>
                                </select>
                                @error('scoreStatus') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Skor</label>
                                <input type="number" wire:model="scoreValue" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">Catatan Penilaian</label>
                                <textarea wire:model="scoreFeedback" class="form-input" rows="2"></textarea>
                            </div>
                            <div style="display:flex;gap:10px;">
                                <button type="button" wire:click="submitScore" class="btn-primary">Simpan Penilaian</button>
                                <button type="button" wire:click="toggleScoreForm('{{ $process->id }}')" class="btn-ghost">Batal</button>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($mcuProcessId === $process->id)
                    <div style="background:var(--surface-alt);border-radius:10px;padding:12px;margin-top:10px;">
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <div>
                                <label class="form-label">Ringkasan Hasil MCU <span style="color:var(--danger-text);">*</span></label>
                                <textarea wire:model="mcuResultsSummary" class="form-input" rows="2"></textarea>
                                @error('mcuResultsSummary') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Lampiran Hasil MCU</label>
                                <input type="file" wire:model="mcuAttachment" class="form-input">
                                <div wire:loading wire:target="mcuAttachment" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
                            </div>
                            <div style="display:flex;gap:10px;">
                                <button type="button" wire:click="submitMcu" class="btn-primary">Simpan</button>
                                <button type="button" wire:click="toggleMcuForm('{{ $process->id }}')" class="btn-ghost">Batal</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p style="font-size:13px;color:var(--text-muted);">Belum ada tahapan rekrutmen.</p>
        @endforelse
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 14px;text-align:center;">Aksi Lamaran</h4>
        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;">
            @if ($this->canRequestApproval)
                <button type="button" wire:click="toggleRequestApprovalForm" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Request Approval ke Direktur</button>
            @endif
            @if ($this->canRejectApplication)
                <button type="button" wire:click="rejectApplication" wire:confirm="Tolak lamaran kandidat ini? Tindakan ini tidak dapat dibatalkan." class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);"><i class="fa-solid fa-xmark"></i> Tolak Kandidat</button>
            @endif
            @if ($this->canAcceptApplication)
                <button type="button" wire:click="toggleAcceptForm" class="btn-primary"><i class="fa-solid fa-check"></i> Terima Kandidat</button>
            @endif
        </div>

        @if ($showRequestApprovalForm)
            <div style="background:var(--surface-alt);border-radius:10px;padding:14px;margin-top:14px;max-width:480px;margin-left:auto;margin-right:auto;">
                <label class="form-label">Catatan Approval</label>
                <textarea wire:model="requestApprovalNotes" class="form-input" rows="2" placeholder="Catatan untuk direktur (opsional)..."></textarea>
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="button" wire:click="submitRequestApproval" wire:confirm="Kirim request approval kandidat ini ke direktur?" class="btn-primary">Kirim</button>
                    <button type="button" wire:click="toggleRequestApprovalForm" class="btn-ghost">Batal</button>
                </div>
            </div>
        @endif

        @if ($showAcceptForm)
            <div style="background:var(--surface-alt);border-radius:10px;padding:14px;margin-top:14px;max-width:480px;margin-left:auto;margin-right:auto;">
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div>
                        <label class="form-label">NIP Karyawan <span style="color:var(--danger-text);">*</span></label>
                        <input type="text" wire:model="nip" class="form-input">
                        @error('nip') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Tipe Nakes <span style="color:var(--danger-text);">*</span></label>
                        <select wire:model="type_nakes" class="form-input">
                            <option value="">Pilih tipe</option>
                            <option value="nakes">Nakes</option>
                            <option value="non_nakes">Non Nakes</option>
                        </select>
                        @error('type_nakes') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Tipe Karyawan <span style="color:var(--danger-text);">*</span></label>
                        <select wire:model="employee_type_id" class="form-input">
                            <option value="">Pilih tipe karyawan</option>
                            @foreach ($this->employeeTypeOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('employee_type_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Tanggal Bergabung <span style="color:var(--danger-text);">*</span></label>
                        <input type="date" wire:model="joined_at" class="form-input">
                        @error('joined_at') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Role (Aplikasi Sistem) <span style="color:var(--danger-text);">*</span></label>
                        <select wire:model="role_id" class="form-input">
                            <option value="">Pilih role</option>
                            @foreach ($this->roleOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('role_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" wire:click="submitAccept" wire:confirm="Terima kandidat ini? Data akan otomatis diproses ke sistem karyawan." class="btn-primary">Terima Kandidat</button>
                        <button type="button" wire:click="toggleAcceptForm" class="btn-ghost">Batal</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
</div>
