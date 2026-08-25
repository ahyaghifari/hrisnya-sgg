<div>
    @php
        $statusBadge = fn (?string $status) => match ($status) {
            'approved', 'fit' => ['badge-approved', '✅ Disetujui'],
            'lulus', 'disarankan' => ['badge-approved', '✅ Selesai'],
            'rejected', 'tidak_lulus', 'tidak_disarankan', 'unfit' => ['badge-rejected', '❌ Ditolak'],
            default => ['badge-pending', '⏳ Menunggu'],
        };

        $renderField = function (string $label, $value, bool $showBorder = true) {
            $borderStyle = $showBorder ? 'border-bottom:1px solid var(--border-soft);' : '';
            $valueHtml = $value instanceof \Illuminate\Support\HtmlString || (is_string($value) && str_starts_with(trim($value), '<'))
                ? (string) $value
                : ($value !== null && $value !== ''
                    ? '<span style="font-size:13.5px;font-weight:600;color:var(--text-body);text-align:right;">' . e($value) . '</span>'
                    : '<span style="font-size:12.5px;font-style:italic;color:var(--text-muted);text-align:right;">-</span>');

            return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:10px 0;' . $borderStyle . '">'
                . '<span style="font-size:12.5px;color:var(--text-muted);flex-shrink:0;">' . e($label) . '</span>'
                . $valueHtml
                . '</div>';
        };

        $sectionHeader = function (string $icon, string $title, string $emoji = '') {
            return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">'
                . '<div style="flex-shrink:0;width:32px;height:32px;border-radius:9px;background:rgba(124,58,237,0.1);display:flex;align-items:center;justify-content:center;">'
                . '<i class="fa-solid ' . $icon . '" style="color:#7c3aed;font-size:13px;"></i>'
                . '</div>'
                . '<h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">' . ($emoji ? $emoji . ' ' : '') . e($title) . '</h4>'
                . '</div>';
        };

        $badgeHtml = fn (?string $status) => (function () use ($statusBadge, $status) {
            [$color, $label] = $statusBadge($status);
            return '<span class="badge ' . $color . '">' . e($label) . '</span>';
        })();
    @endphp

    <div class="page-hero violet" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('sikartap.dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Dashboard Sikartap</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Detail</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">{{ $sikartap->user->name }}</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $sikartap->user->nip }} &middot; {{ $sikartap->user->company?->name }}</p>
        </div>
    </div>

    @if ($sikartap->is_diskualifikasi)
        <div style="background:var(--danger-soft-bg,#fef2f2);border:1px solid var(--danger-soft-border,#fecaca);border-radius:14px;padding:16px 18px;margin-bottom:24px;">
            <p style="font-size:13px;font-weight:700;color:var(--danger-text);margin:0 0 4px;">⚠️ Pelamar Didiskualifikasi</p>
            <p style="font-size:12.5px;color:var(--danger-text);margin:0;">{{ $sikartap->alasan_diskualifikasi }}</p>
        </div>
    @endif

    {{-- Dokumen Administratif — full width, daftar panjang, tidak cocok masuk kolom sempit.
         Collapsed by default begitu tahap berkas sudah lewat (approved/rejected, bukan pending
         lagi) - masih bisa di-expand manual buat lihat lagi, cuma tidak makan tempat di layar
         begitu HRD lanjut ke tahap berikutnya. --}}
    <div x-data="{ open: {{ $sikartap->status_berkas === 'pending' ? 'true' : 'false' }} }" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:6px;">
            <button type="button" @click="open = !open" class="collapse-toggle" :aria-expanded="open" title="Klik untuk tampilkan/sembunyikan">
                {!! $sectionHeader('fa-folder-open', 'Dokumen Administratif', '📑') !!}
                <span style="font-size:11.5px;font-weight:600;color:var(--text-muted);" x-text="open ? 'Sembunyikan' : 'Lihat selengkapnya'"></span>
                <span class="collapse-chevron" :style="open ? 'transform:rotate(180deg);' : ''">
                    <i class="fa-solid fa-chevron-down"></i>
                </span>
            </button>
            @if ($this->canUpdate && $sikartap->status_berkas !== 'approved')
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    @if ($sikartap->hasAllRequiredDocumentsUploaded())
                        <button type="button" wire:click="approveAllBerkas" wire:confirm="Setujui semua dokumen dan lanjut ke proses tes?" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">
                            ✓ Setujui Semua &amp; Lanjut Proses
                        </button>
                    @endif
                    <button type="button" wire:click="openPanel('rejectApplication')" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;color:var(--danger-text);border-color:var(--danger-soft-border);">
                        🚫 Tolak Pengajuan
                    </button>
                </div>
            @endif
        </div>

        <div x-show="open" x-transition>
        @if ($activePanel === 'rejectApplication')
            <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin:12px 0;">
                <label class="form-label">Catatan Penolakan</label>
                <p style="font-size:11px;color:var(--text-muted);margin:0 0 6px;">Semua dokumen yang sudah diupload akan ditolak dengan catatan yang sama.</p>
                <textarea wire:model="applicationRejectNote" class="form-input" rows="3"></textarea>
                @error('applicationRejectNote') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="button" wire:click="confirmRejectApplication" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);">🚫 Tolak Pengajuan</button>
                    <button type="button" wire:click="closePanel" class="btn-ghost">Batal</button>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3" style="gap:10px;margin-top:8px;">
            @foreach ($this->documentTypes as $docType)
                @php
                    $doc = $sikartap->documents->firstWhere('doc_type', $docType);
                    $docStatus = $doc?->status ?? null;
                    [$docColor, $docLabel] = $docStatus ? $statusBadge($docStatus) : ['badge-cancelled', 'Belum Upload'];
                    $uploaded = (bool) $doc?->file_path;
                @endphp
                <div style="border:1px solid var(--border-soft);border-radius:10px;padding:12px;">
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-body);margin:0 0 6px;">
                        {{ $docType->getLabel() }}
                        @if ($docType->isRequired())
                            <span style="color:var(--danger-text);">*</span>
                        @endif
                    </p>

                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="badge {{ $docColor }}">{{ $docLabel }}</span>
                        @if ($uploaded)
                            <a href="{{ $this->fileUrl($doc->file_path) }}" target="_blank" class="btn-ghost" style="padding:4px 10px;font-size:11px;">
                                👁️ Lihat
                            </a>
                            @if ($rejectingDocId !== $doc->id && $this->canUpdate)
                                @if ($doc->status !== 'approved')
                                    <button type="button" wire:click="approveDocument('{{ $doc->id }}')" class="btn-ghost" style="padding:4px 10px;font-size:11px;color:var(--brand-success,#047c54);border-color:rgba(4,124,84,0.3);">
                                        ✓ Setujui
                                    </button>
                                @endif
                                @if ($doc->status !== 'rejected')
                                    <button type="button" wire:click="startRejectDocument('{{ $doc->id }}')" class="btn-ghost" style="padding:4px 10px;font-size:11px;color:var(--danger-text);border-color:var(--danger-soft-border);">
                                        ✗ Tolak
                                    </button>
                                @endif
                            @endif
                        @endif
                    </div>

                    {{-- Belum upload = belum ada apa pun buat diputuskan, jadi tidak perlu
                         tombol approve/reject/catatan sama sekali. --}}
                    @if ($uploaded)
                        @if ($doc->status === 'rejected' && $doc->catatan_penolakan)
                            <p style="font-size:12px;color:var(--danger-text);margin:6px 0 0;">{{ $doc->catatan_penolakan }}</p>
                        @endif

                        @if ($rejectingDocId === $doc->id)
                            <div style="margin-top:8px;">
                                <textarea wire:model="documentRejectNote" class="form-input" rows="2" placeholder="Catatan penolakan..."></textarea>
                                @error('documentRejectNote') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                                <div style="display:flex;gap:8px;margin-top:6px;">
                                    <button type="button" wire:click="confirmRejectDocument" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:var(--danger-text);border-color:var(--danger-soft-border);">✗ Tolak</button>
                                    <button type="button" wire:click="$set('rejectingDocId', null)" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">Batal</button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
        </div>
    </div>

    {{-- 2 kolom: kiri = data karyawan & status ringkas, kanan = tahap seleksi (Tes/Wawancara/MCU) --}}
    <div class="grid grid-cols-1 md:grid-cols-2" style="gap:24px;align-items:start;">
        <div style="display:flex;flex-direction:column;gap:24px;">
            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                {!! $sectionHeader('fa-user', 'Informasi Karyawan', '👤') !!}
                {!! $renderField('Nama Karyawan', $sikartap->user->name) !!}
                {!! $renderField('NIP', $sikartap->user->nip) !!}
                {!! $renderField('Perusahaan', $sikartap->user->company?->name) !!}
                {!! $renderField('Diajukan', $sikartap->created_at->translatedFormat('d F Y'), false) !!}
            </div>

            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                {!! $sectionHeader('fa-list-check', 'Status Pengajuan', '📋') !!}
                {!! $renderField('Berkas', $badgeHtml($sikartap->status_berkas)) !!}
                {!! $renderField('Tes', $badgeHtml($sikartap->status_tes)) !!}
                @if ($sikartap->requiresIdeInovasi())
                    {!! $renderField('Ide Inovasi', $badgeHtml($sikartap->ideInovasi?->status_atasan ?? 'pending')) !!}
                @endif
                @if ($sikartap->requiresMcu())
                    {!! $renderField('MCU', $badgeHtml($sikartap->status_mcu)) !!}
                @endif
                {!! $renderField('Keputusan Direktur', $badgeHtml($sikartap->status_keputusan_direktur), false) !!}
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:24px;">
            {{-- Tes --}}
            @if ($sikartap->status_berkas === 'approved')
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:6px;">
                        {!! $sectionHeader('fa-pen-ruler', 'Tes Bidang & Psikotes', '📝') !!}
                        @if ($this->canUpdate)
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button type="button" wire:click="openPanel('testSchedule')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                    <i class="fa-solid fa-calendar"></i> {{ $sikartap->testSchedule ? 'Ubah Jadwal' : 'Jadwalkan' }}
                                </button>
                                @if ($sikartap->testSchedule)
                                    <button type="button" wire:click="openPanel('testResult')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                        <i class="fa-solid fa-pen"></i> Input Hasil
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($sikartap->testSchedule)
                        {!! $renderField('Jadwal', \Carbon\Carbon::parse($sikartap->testSchedule->tanggal_tes)->translatedFormat('j F Y') . ', ' . $sikartap->testSchedule->waktu_tes) !!}
                        {!! $renderField('Tempat', $sikartap->testSchedule->tempat_tes) !!}
                    @endif
                    @if ($sikartap->nilai_tes !== null)
                        {!! $renderField('Nilai Tes Bidang', $sikartap->nilai_tes) !!}
                        {!! $renderField('Nilai Psikotes', $sikartap->nilai_psikotes) !!}
                    @endif
                    {!! $renderField('Catatan', $sikartap->catatan_tes, false) !!}

                    @if ($activePanel === 'testSchedule')
                        <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:12px;">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
                                <div><label class="form-label">Tanggal</label><input type="date" wire:model="testScheduleForm.tanggal_tes" class="form-input"></div>
                                <div><label class="form-label">Waktu</label><input type="time" wire:model="testScheduleForm.waktu_tes" class="form-input"></div>
                                <div><label class="form-label">Tempat</label><input type="text" wire:model="testScheduleForm.tempat_tes" class="form-input"></div>
                            </div>
                            <label class="form-label" style="margin-top:10px;display:block;">Keterangan</label>
                            <textarea wire:model="testScheduleForm.keterangan" class="form-input" rows="2"></textarea>
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button type="button" wire:click="saveTestSchedule" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">💾 Simpan</button>
                                <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                            </div>
                        </div>
                    @endif

                    @if ($activePanel === 'testResult')
                        <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:12px;">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
                                <div><label class="form-label">Nilai Tes Bidang</label><input type="number" wire:model="testResultForm.nilai_tes" class="form-input"></div>
                                <div><label class="form-label">Nilai Psikotes</label><input type="number" wire:model="testResultForm.nilai_psikotes" class="form-input"></div>
                                <div>
                                    <label class="form-label">Status</label>
                                    <select wire:model="testResultForm.status_tes" class="form-input">
                                        <option value="">-- Pilih --</option>
                                        <option value="lulus">Lulus</option>
                                        <option value="tidak_lulus">Tidak Lulus</option>
                                    </select>
                                </div>
                            </div>
                            <label class="form-label" style="margin-top:10px;display:block;">Catatan</label>
                            <textarea wire:model="testResultForm.catatan_tes" class="form-input" rows="2"></textarea>
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button type="button" wire:click="saveTestResult" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">💾 Simpan</button>
                                <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Wawancara --}}
            @if ($sikartap->status_tes === 'lulus')
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:6px;">
                        {!! $sectionHeader('fa-comments', 'Wawancara', '🎤') !!}
                        @if ($this->canUpdate)
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button type="button" wire:click="openPanel('interviewSchedule')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                    <i class="fa-solid fa-calendar"></i> {{ $sikartap->interviewSchedule ? 'Ubah Jadwal' : 'Jadwalkan' }}
                                </button>
                                @if ($sikartap->interviewSchedule)
                                    <button type="button" wire:click="openPanel('interviewResult')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                        <i class="fa-solid fa-pen"></i> Input Hasil
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($sikartap->interviewSchedule)
                        {!! $renderField('Jadwal', \Carbon\Carbon::parse($sikartap->interviewSchedule->tanggal_wawancara)->translatedFormat('j F Y') . ', ' . $sikartap->interviewSchedule->waktu_wawancara) !!}
                        {!! $renderField('Tempat', $sikartap->interviewSchedule->tempat_wawancara) !!}
                        {!! $renderField('Pewawancara', trim($sikartap->interviewSchedule->pewawancara . ($sikartap->interviewSchedule->pewawancara_2 ? ' & ' . $sikartap->interviewSchedule->pewawancara_2 : ''))) !!}
                    @endif
                    {!! $renderField('Catatan', $sikartap->catatan_wawancara, false) !!}

                    @if ($activePanel === 'interviewSchedule')
                        <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:12px;">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
                                <div><label class="form-label">Tanggal</label><input type="date" wire:model="interviewScheduleForm.tanggal_wawancara" class="form-input"></div>
                                <div><label class="form-label">Waktu</label><input type="time" wire:model="interviewScheduleForm.waktu_wawancara" class="form-input"></div>
                                <div><label class="form-label">Tempat</label><input type="text" wire:model="interviewScheduleForm.tempat_wawancara" class="form-input"></div>
                                <div><label class="form-label">Pewawancara 1</label><input type="text" wire:model="interviewScheduleForm.pewawancara" class="form-input"></div>
                                <div><label class="form-label">Pewawancara 2</label><input type="text" wire:model="interviewScheduleForm.pewawancara_2" class="form-input"></div>
                            </div>
                            <label class="form-label" style="margin-top:10px;display:block;">Keterangan</label>
                            <textarea wire:model="interviewScheduleForm.keterangan" class="form-input" rows="2"></textarea>
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button type="button" wire:click="saveInterviewSchedule" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">💾 Simpan</button>
                                <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                            </div>
                        </div>
                    @endif

                    @if ($activePanel === 'interviewResult')
                        <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:12px;">
                            <label class="form-label">Status</label>
                            <select wire:model="interviewResultForm.status_wawancara" class="form-input">
                                <option value="">-- Pilih --</option>
                                <option value="disarankan">Disarankan</option>
                                <option value="tidak_disarankan">Tidak Disarankan</option>
                            </select>
                            <label class="form-label" style="margin-top:10px;display:block;">Catatan</label>
                            <textarea wire:model="interviewResultForm.catatan_wawancara" class="form-input" rows="2"></textarea>
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button type="button" wire:click="saveInterviewResult" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">💾 Simpan</button>
                                <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Ide Inovasi --}}
            @if ($sikartap->status_wawancara === 'disarankan' && $sikartap->requiresIdeInovasi())
                @php $ideInovasi = $sikartap->ideInovasi; @endphp
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    {!! $sectionHeader('fa-lightbulb', 'Ide Inovasi', '💡') !!}

                    @if (! $ideInovasi)
                        <p style="font-size:12px;color:var(--text-muted);">Karyawan belum mengupload berkas ide inovasi.</p>
                    @else
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;">
                            <a href="{{ $this->fileUrl($ideInovasi->file_ide_inovasi) }}" target="_blank" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                <i class="fa-solid fa-file"></i> Lihat Berkas
                            </a>
                            <span class="badge {{ $badgeColor = match ($ideInovasi->status_atasan) { 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', default => 'badge-pending' } }}">
                                {{ match ($ideInovasi->status_atasan) { 'approved' => '✅ Disetujui Atasan', 'rejected' => '❌ Ditolak Atasan', default => '⏳ Menunggu Atasan' } }}
                            </span>
                        </div>

                        @if ($ideInovasi->nilai_akhir_atasan !== null)
                            {!! $renderField('Nilai Akhir', $ideInovasi->nilai_akhir_atasan . ' (' . $ideInovasi->klasifikasi_atasan . ')') !!}
                        @endif
                        {!! $renderField('Catatan Atasan', $ideInovasi->catatan_atasan, false) !!}

                        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;margin-top:10px;border-top:1px solid var(--border-soft);">
                            <span style="font-size:12px;color:var(--text-muted);">Direktur <span style="color:var(--text-faint);">(opsional)</span></span>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="badge {{ match ($ideInovasi->status_direktur) { 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', default => 'badge-pending' } }}">
                                    {{ match ($ideInovasi->status_direktur) { 'approved' => '✅ Disetujui', 'rejected' => '❌ Ditolak', default => '⏳ Menunggu' } }}
                                </span>
                                @if ($this->canReviewIdeInovasiDirektur)
                                    <button type="button" wire:click="openPanel('ideInovasiDirektur')" class="btn-ghost" style="padding:4px 10px;font-size:11px;">Nilai</button>
                                @endif
                            </div>
                        </div>

                        @if ($activePanel === 'ideInovasiDirektur')
                            <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:10px;">
                                <label class="form-label">Catatan (wajib kalau tolak)</label>
                                <textarea wire:model="ideInovasiNote" class="form-input" rows="2"></textarea>
                                @error('ideInovasiNote') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                                <div style="display:flex;gap:10px;margin-top:10px;">
                                    <button type="button" wire:click="approveIdeInovasiDirektur" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">✅ Setujui</button>
                                    <button type="button" wire:click="rejectIdeInovasiDirektur" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">❌ Tolak</button>
                                    <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                                </div>
                            </div>
                        @endif

                        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;margin-top:10px;border-top:1px solid var(--border-soft);">
                            <span style="font-size:12px;color:var(--text-muted);">Komisaris <span style="color:var(--text-faint);">(opsional)</span></span>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="badge {{ match ($ideInovasi->status_komisaris) { 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', default => 'badge-pending' } }}">
                                    {{ match ($ideInovasi->status_komisaris) { 'approved' => '✅ Disetujui', 'rejected' => '❌ Ditolak', default => '⏳ Menunggu' } }}
                                </span>
                                @if ($this->canReviewIdeInovasiKomisaris)
                                    <button type="button" wire:click="openPanel('ideInovasiKomisaris')" class="btn-ghost" style="padding:4px 10px;font-size:11px;">Nilai</button>
                                @endif
                            </div>
                        </div>

                        @if ($activePanel === 'ideInovasiKomisaris')
                            <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:10px;">
                                <label class="form-label">Catatan (wajib kalau tolak)</label>
                                <textarea wire:model="ideInovasiNote" class="form-input" rows="2"></textarea>
                                @error('ideInovasiNote') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                                <div style="display:flex;gap:10px;margin-top:10px;">
                                    <button type="button" wire:click="approveIdeInovasiKomisaris" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">✅ Setujui</button>
                                    <button type="button" wire:click="rejectIdeInovasiKomisaris" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">❌ Tolak</button>
                                    <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            {{-- MCU --}}
            @if ($sikartap->status_wawancara === 'disarankan' && $sikartap->requiresMcu())
                @php
                    $mcuField = function (string $label, $value) {
                        $display = ($value === null || $value === '') ? '-' : $value;
                        return '<div style="display:flex;justify-content:space-between;gap:10px;padding:5px 0;font-size:12px;">'
                            . '<span style="color:var(--text-muted);">' . e($label) . '</span>'
                            . '<span style="font-weight:600;color:var(--text-body);text-align:right;">' . (is_bool($value) ? ($value ? 'Ya' : 'Tidak') : e($display)) . '</span>'
                            . '</div>';
                    };
                @endphp
                <div x-data="{ mcuDetail: false }" style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:6px;">
                        {!! $sectionHeader('fa-heart-pulse', 'Medical Check-Up', '🏥') !!}
                        @if ($this->canUpdate && $sikartap->mcuDoctorExamination)
                            <button type="button" wire:click="openPanel('mcuDecision')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                <i class="fa-solid fa-heart"></i> {{ $sikartap->status_mcu === 'pending' ? 'Putuskan Hasil' : 'Ubah Hasil' }}
                            </button>
                        @endif
                    </div>

                    {!! $renderField('Perawat', $sikartap->mcuNurseAssessment ? 'TD ' . $sikartap->mcuNurseAssessment->tekanan_darah . ' · EKG ' . ($sikartap->mcuNurseAssessment->hasil_ekg ?? '-') : null) !!}
                    {!! $renderField('Laboratorium', $sikartap->mcuLabResult ? 'HBsAg ' . ($sikartap->mcuLabResult->hbsag ?? '-') . ' · HIV ' . ($sikartap->mcuLabResult->hiv ?? '-') : null) !!}
                    {!! $renderField('Radiologi', $sikartap->mcuRadiologyResult ? 'Thorax ' . ($sikartap->mcuRadiologyResult->foto_thorax ?? '-') : null) !!}
                    {!! $renderField('Dokter', $sikartap->mcuDoctorExamination?->rekomendasi) !!}
                    {!! $renderField('Catatan', $sikartap->catatan_mcu, false) !!}

                    <button type="button" @click="mcuDetail = !mcuDetail" class="collapse-toggle" style="margin-top:10px;">
                        <span style="font-size:11.5px;font-weight:600;color:var(--text-muted);" x-text="mcuDetail ? 'Sembunyikan detail lengkap' : 'Lihat detail lengkap hasil MCU'"></span>
                        <span class="collapse-chevron" :style="mcuDetail ? 'transform:rotate(180deg);' : ''">
                            <i class="fa-solid fa-chevron-down"></i>
                        </span>
                    </button>

                    <div x-show="mcuDetail" x-transition style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
                        @if ($sikartap->mcuNurseAssessment)
                            @php $n = $sikartap->mcuNurseAssessment; @endphp
                            <div style="border:1px solid var(--border-soft);border-radius:10px;padding:12px;">
                                <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">🩺 Asesmen Perawat</p>
                                {!! $mcuField('Tinggi / Berat', $n->tinggi_badan . ' cm / ' . $n->berat_badan . ' kg') !!}
                                {!! $mcuField('Tekanan Darah', $n->tekanan_darah) !!}
                                {!! $mcuField('Nadi / Suhu / Nafas', $n->nadi . 'x · ' . $n->suhu . '°C · ' . $n->pernafasan . 'x') !!}
                                {!! $mcuField('Keluhan Kesehatan', $n->keluhan_kesehatan) !!}
                                {!! $mcuField('Riwayat Penyakit Keluarga', collect(['Hipertensi' => $n->rpk_hipertensi, 'Stroke' => $n->rpk_stroke, 'Jantung' => $n->rpk_jantung, 'Diabetes' => $n->rpk_diabetes])->filter()->keys()->implode(', ') ?: ($n->rpk_lainnya ?: '-')) !!}
                                {!! $mcuField('Riwayat Penyakit Dahulu', $n->riwayat_penyakit_dahulu) !!}
                                {!! $mcuField('Riwayat Operasi', $n->riwayat_operasi) !!}
                                {!! $mcuField('Alergi', collect(['Makanan' => $n->alergi_makanan, 'Obat' => $n->alergi_obat, 'Debu' => $n->alergi_debu])->filter()->keys()->implode(', ') ?: ($n->alergi_lainnya ?: '-')) !!}
                                {!! $mcuField('Kebiasaan', collect(['Olahraga' => $n->kebiasaan_olahraga, 'Merokok' => $n->kebiasaan_merokok, 'Alkohol' => $n->kebiasaan_alkohol, 'Obat' => $n->kebiasaan_obat])->filter()->keys()->implode(', ') ?: ($n->kebiasaan_lainnya ?: '-')) !!}
                                {!! $mcuField('Hasil EKG', $n->hasil_ekg) !!}
                                {!! $mcuField('Keterangan EKG', $n->keterangan_ekg) !!}
                                {!! $mcuField('Catatan', $n->catatan) !!}
                                @if ($n->file_ekg)
                                    <p style="margin-top:6px;"><a href="{{ $this->fileUrl($n->file_ekg) }}" target="_blank" style="font-size:11.5px;color:var(--primary-text);">📥 Lihat file EKG</a></p>
                                @endif
                            </div>
                        @endif

                        @if ($sikartap->mcuLabResult)
                            @php $l = $sikartap->mcuLabResult; @endphp
                            <div style="border:1px solid var(--border-soft);border-radius:10px;padding:12px;">
                                <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">🧪 Laboratorium</p>
                                {!! $mcuField('Hemoglobin', $l->hemoglobin) !!}
                                {!! $mcuField('Leukosit', $l->leukosit) !!}
                                {!! $mcuField('Eritrosit', $l->eritrosit) !!}
                                {!! $mcuField('Trombosit', $l->trombosit) !!}
                                {!! $mcuField('Hematokrit', $l->hematokrit) !!}
                                {!! $mcuField('MCV / MCH / MCHC', trim(($l->mcv ?? '-') . ' / ' . ($l->mch ?? '-') . ' / ' . ($l->mchc ?? '-'))) !!}
                                {!! $mcuField('Basofil / Eosinofil', trim(($l->basofil ?? '-') . ' / ' . ($l->eosinofil ?? '-'))) !!}
                                {!! $mcuField('Stab / Segmen', trim(($l->stab ?? '-') . ' / ' . ($l->segmen ?? '-'))) !!}
                                {!! $mcuField('Limfosit / Monosit', trim(($l->limfosit ?? '-') . ' / ' . ($l->monosit ?? '-'))) !!}
                                {!! $mcuField('GDS / GDA', trim(($l->gds ?? '-') . ' / ' . ($l->gda ?? '-'))) !!}
                                {!! $mcuField('HBsAg', $l->hbsag) !!}
                                {!! $mcuField('HIV', $l->hiv) !!}
                                {!! $mcuField('Kesimpulan', $l->kesimpulan) !!}
                                @if ($l->file_path)
                                    <p style="margin-top:6px;"><a href="{{ $this->fileUrl($l->file_path) }}" target="_blank" style="font-size:11.5px;color:var(--primary-text);">📥 Lihat berkas hasil lab</a></p>
                                @endif
                            </div>
                        @endif

                        @if ($sikartap->mcuRadiologyResult)
                            @php $r = $sikartap->mcuRadiologyResult; @endphp
                            <div style="border:1px solid var(--border-soft);border-radius:10px;padding:12px;">
                                <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">🩻 Radiologi</p>
                                {!! $mcuField('Jenis Pemeriksaan', $r->jenis_pemeriksaan) !!}
                                {!! $mcuField('Foto Thorax', $r->foto_thorax) !!}
                                {!! $mcuField('Jantung', $r->jantung) !!}
                                {!! $mcuField('Paru Kanan', $r->paru_kanan) !!}
                                {!! $mcuField('Paru Kiri', $r->paru_kiri) !!}
                                {!! $mcuField('Diafragma', $r->diafragma) !!}
                                {!! $mcuField('Tulang', $r->tulang) !!}
                                {!! $mcuField('Kesimpulan', $r->kesimpulan) !!}
                                @if ($r->file_path)
                                    <p style="margin-top:6px;"><a href="{{ $this->fileUrl($r->file_path) }}" target="_blank" style="font-size:11.5px;color:var(--primary-text);">📥 Lihat berkas hasil radiologi</a></p>
                                @endif
                            </div>
                        @endif

                        @if ($sikartap->mcuDoctorExamination)
                            @php $d = $sikartap->mcuDoctorExamination; @endphp
                            <div style="border:1px solid var(--border-soft);border-radius:10px;padding:12px;">
                                <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">🩺 Pemeriksaan Dokter</p>
                                {!! $mcuField('Kulit / Rambut', trim(($d->kulit ?? '-') . ' / ' . ($d->rambut ?? '-'))) !!}
                                {!! $mcuField('Visus Mata', $d->visus_mata) !!}
                                {!! $mcuField('Buta Warna', $d->buta_warna) !!}
                                {!! $mcuField('Lapang Pandang', $d->lapang_pandang) !!}
                                {!! $mcuField('THT', trim(($d->hidung ?? '-') . ' / ' . ($d->telinga ?? '-') . ' / ' . ($d->tonsil ?? '-') . ' / ' . ($d->faring ?? '-'))) !!}
                                {!! $mcuField('Gigi', $d->gigi) !!}
                                {!! $mcuField('Kelenjar Gondok', $d->kelenjar_gondok) !!}
                                {!! $mcuField('Suara Pernafasan', $d->suara_pernafasan) !!}
                                {!! $mcuField('Ronchi / Wheezing', trim(($d->ronchi ?? '-') . ' / ' . ($d->wheezing ?? '-'))) !!}
                                {!! $mcuField('Lainnya (Paru)', $d->lainnya_paru) !!}
                                {!! $mcuField('Jantung', $d->jantung) !!}
                                {!! $mcuField('Hati / Limpa', trim(($d->hati ?? '-') . ' / ' . ($d->limpa ?? '-'))) !!}
                                {!! $mcuField('Epigastrium / Ginjal', trim(($d->epigastrium ?? '-') . ' / ' . ($d->ginjal ?? '-'))) !!}
                                {!! $mcuField('Lainnya (Abdomen)', $d->lainnya_abdomen) !!}
                                {!! $mcuField('Ekstremitas', $d->ekstremitas) !!}
                                {!! $mcuField('Neurologi', $d->neurologi) !!}
                                {!! $mcuField('Diagnosa', $d->diagnosa) !!}
                                {!! $mcuField('Rekomendasi', $d->rekomendasi) !!}
                                {!! $mcuField('Catatan', $d->catatan) !!}
                            </div>
                        @endif
                    </div>

                    @if ($activePanel === 'mcuDecision')
                        <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:12px;">
                            <label class="form-label">Status MCU</label>
                            <select wire:model="mcuDecisionForm.status_mcu" class="form-input">
                                <option value="">-- Pilih --</option>
                                <option value="fit">Fit</option>
                                <option value="unfit">Unfit</option>
                            </select>
                            <label class="form-label" style="margin-top:10px;display:block;">Catatan</label>
                            <textarea wire:model="mcuDecisionForm.catatan_mcu" class="form-input" rows="2"></textarea>
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button type="button" wire:click="saveMcuDecision" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">💾 Simpan</button>
                                <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Keputusan Direktur — WAJIB lewat Approval Engine, timeline+riwayat sama pola pengajuan absensi --}}
    @if ($sikartap->isEligibleForDirectorDecision() || $sikartap->approvalSnapshot)
        <div style="margin-top:24px;">
            @if ($sikartap->approvalSnapshot)
                <x-approval-timeline :timeline="$this->timeline" title="Status Keputusan Direktur" />
            @endif

            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:24px;">
                {!! $sectionHeader('fa-user-tie', 'Keputusan Direktur', '👔') !!}

                @if (! $sikartap->approvalSnapshot)
                    @if ($this->canUpdate)
                        <div style="margin-top:10px;">
                            <button type="button" wire:click="ajukanKeputusanDirektur" wire:confirm="Pengajuan akan masuk ke alur approval Direktur sesuai flow perusahaan." class="btn-primary" style="padding:8px 16px;font-size:12.5px;">
                                📨 Ajukan ke Direktur
                            </button>
                        </div>
                    @endif
                @else
                    {!! $renderField('Status Approval', $sikartap->approvalSnapshot->status, ! $this->canApproveDirektur) !!}

                    @if ($this->canApproveDirektur)
                        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
                            <button type="button" wire:click="openPanel('approveDirektur')" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">
                                ✓ Setujui
                            </button>
                            <button type="button" wire:click="openPanel('rejectDirektur')" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;color:var(--danger-text);border-color:var(--danger-soft-border);">
                                ✗ Tolak
                            </button>
                        </div>
                    @endif

                    @if ($activePanel === 'approveDirektur')
                        <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:12px;">
                            <label class="form-label">Catatan</label>
                            <textarea wire:model="directorNote" class="form-input" rows="3"></textarea>
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button type="button" wire:click="confirmApproveDirektur" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">✓ Setujui</button>
                                <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                            </div>
                        </div>
                    @endif

                    @if ($activePanel === 'rejectDirektur')
                        <div style="background:var(--surface-alt,rgba(0,0,0,.03));border-radius:10px;padding:14px;margin-top:12px;">
                            <label class="form-label">Catatan Penolakan</label>
                            <textarea wire:model="directorNote" class="form-input" rows="3"></textarea>
                            @error('directorNote') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <button type="button" wire:click="confirmRejectDirektur" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;color:var(--danger-text);border-color:var(--danger-soft-border);">✗ Tolak</button>
                                <button type="button" wire:click="closePanel" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            @if ($sikartap->approvalSnapshot)
                <div style="margin-top:24px;">
                    <x-approval-history-list :histories="$sikartap->approvalHistories" />
                </div>
            @endif
        </div>
    @endif
</div>
