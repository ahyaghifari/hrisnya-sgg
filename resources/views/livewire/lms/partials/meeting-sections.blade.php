{{-- Isi 1 pertemuan: Detail Pertemuan + Materi + Quiz. Dipakai baik di dalam accordion
     (course multi-hari) maupun langsung tanpa collapse (course 1 hari). Butuh $meeting. --}}
<h5 style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Detail Pertemuan</h5>
@include('livewire.lms.partials.meeting-detail-form')
@if ($this->canEditDetail)
    <div style="display:flex;justify-content:flex-end;margin-top:12px;">
        <button type="button" wire:click="saveMeeting" class="btn-primary" style="padding:7px 16px;font-size:12px;">Simpan Detail</button>
    </div>
@endif

<div style="border-top:1px solid var(--border-soft);margin:18px 0;"></div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <h5 style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">Materi Pertemuan</h5>
    @if ($addingMaterialForMeetingId !== $meeting->id && $this->canEditDetail)
        <button type="button" wire:click="openAddMaterialForm('{{ $meeting->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
            <i class="fa-solid fa-plus"></i> Materi
        </button>
    @endif
</div>

@if ($addingMaterialForMeetingId === $meeting->id)
    <div style="background:var(--surface-alt);border-radius:10px;padding:14px;margin-bottom:12px;">
        <label class="form-label">Judul Materi</label>
        <input type="text" wire:model="materialTitle" class="form-input">
        @error('materialTitle') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror

        <label class="form-label" style="margin-top:10px;display:block;">Berkas (PDF, DOCX, PPTX)</label>
        <label class="upload-zone" style="display:block;">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:24px;color:var(--text-faint);margin-bottom:6px;display:block;"></i>
            <p style="font-size:12.5px;font-weight:600;color:var(--text-body);margin:0;">Klik untuk upload</p>
            <input type="file" style="display:none;" accept=".pdf,.doc,.docx,.ppt,.pptx" wire:model="materialFile">
        </label>
        @if ($materialFile)
            <div style="font-size:12px;color:var(--text-body);margin-top:6px;"><i class="fa-solid fa-file"></i> {{ $materialFile->getClientOriginalName() }}</div>
        @endif
        @error('materialFile') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button type="button" wire:click="closeAddMaterialForm" class="btn-ghost" style="padding:6px 12px;font-size:11.5px;">Batal</button>
            <button type="button" wire:click="saveMaterial" class="btn-primary" style="padding:6px 14px;font-size:11.5px;">Simpan</button>
        </div>
    </div>
@endif

@forelse ($meeting->materials as $material)
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-soft);font-size:12.5px;">
        <span><i class="fa-solid fa-file-lines" style="color:var(--text-faint);"></i> {{ $material->title }}</span>
        @if ($this->canEditDetail)
            <button type="button" wire:click="removeMaterial('{{ $material->id }}')" wire:confirm="Hapus materi ini?" class="btn-ghost" style="padding:4px 8px;font-size:11px;color:#dc2626;">
                <i class="fa-solid fa-trash"></i>
            </button>
        @endif
    </div>
@empty
    <p style="font-size:12px;color:var(--text-faint);margin:0;">Belum ada materi.</p>
@endforelse

<div style="border-top:1px solid var(--border-soft);margin:18px 0;"></div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <h5 style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">Quiz</h5>
    @if ($addingQuizForMeetingId !== $meeting->id && $this->canEditDetail)
        <button type="button" wire:click="openAddQuizForm('{{ $meeting->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
            <i class="fa-solid fa-plus"></i> Quiz
        </button>
    @endif
</div>

@if ($addingQuizForMeetingId === $meeting->id)
    <div style="background:var(--surface-alt);border-radius:10px;padding:14px;margin-bottom:12px;">
        <label class="form-label">Pilih Kuis</label>
        <select wire:model="quizId" class="form-input">
            <option value="">Pilih kuis...</option>
            @foreach ($this->quizOptions as $id => $qTitle)
                <option value="{{ $id }}">{{ $qTitle }}</option>
            @endforeach
        </select>
        <p style="font-size:11px;color:var(--text-faint);margin-top:4px;">Kuis difilter otomatis dari kategori pelatihan ini.</p>
        @error('quizId') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror

        <label class="form-label" style="margin-top:10px;">Durasi (menit)</label>
        <input type="number" wire:model="quizDuration" class="form-input" min="1">
        @error('quizDuration') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror

        <div style="display:flex;align-items:center;gap:8px;margin-top:12px;">
            <label class="custom-checkbox" style="width:20px;height:20px;">
                <input type="checkbox" wire:model="quizIsGraduationRequirement">
                <span class="box"><i class="fa-solid fa-check"></i></span>
            </label>
            <span style="font-size:12px;color:var(--text-body);">Kuis penentu kelulusan (skor kuis ini ikut menentukan lulus/tidaknya peserta)</span>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button type="button" wire:click="closeAddQuizForm" class="btn-ghost" style="padding:6px 12px;font-size:11.5px;">Batal</button>
            <button type="button" wire:click="saveQuiz" class="btn-primary" style="padding:6px 14px;font-size:11.5px;">Simpan</button>
        </div>
    </div>
@endif

@forelse ($meeting->quizzes as $meetingQuiz)
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-soft);font-size:12.5px;">
        <span>
            <i class="fa-solid fa-circle-question" style="color:var(--text-faint);"></i> {{ $meetingQuiz->quiz?->title ?? '-' }} <span style="color:var(--text-faint);">({{ $meetingQuiz->duration }} menit)</span>
            @if ($meetingQuiz->is_graduation_requirement)
                <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:6px;">Penentu Kelulusan</span>
            @endif
        </span>
        <div style="display:flex;gap:6px;">
            <button type="button" wire:click="showQuizResultsSummary('{{ $meetingQuiz->id }}')" class="btn-ghost" style="padding:4px 8px;font-size:11px;">
                <i class="fa-solid fa-chart-simple"></i> Rangkuman
            </button>
            <button type="button" wire:click="showQuizResultsList('{{ $meetingQuiz->id }}')" class="btn-ghost" style="padding:4px 8px;font-size:11px;">
                <i class="fa-solid fa-list"></i> Hasil Peserta
            </button>
            @if ($meetingQuiz->public_token)
                <button type="button" wire:click="showQuizQr('{{ $meetingQuiz->id }}')" class="btn-ghost" style="padding:4px 8px;font-size:11px;">
                    <i class="fa-solid fa-qrcode"></i> Lihat QR
                </button>
            @endif
            @if ($this->canEditDetail && ! $meetingQuiz->results()->exists())
                <button type="button" wire:click="removeQuiz('{{ $meetingQuiz->id }}')" wire:confirm="Hapus quiz ini?" class="btn-ghost" style="padding:4px 8px;font-size:11px;color:#dc2626;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            @endif
        </div>
    </div>
@empty
    <p style="font-size:12px;color:var(--text-faint);margin:0;">Belum ada quiz.</p>
@endforelse

<div style="border-top:1px solid var(--border-soft);margin:18px 0;"></div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <h5 style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">Absensi Peserta</h5>
    <div style="display:flex;gap:6px;">
        @if ($attendanceViewMeetingId === $meeting->id)
            <button type="button" wire:click="closeAttendanceView" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
                <i class="fa-solid fa-xmark"></i> Tutup
            </button>
        @else
            <button type="button" wire:click="openAttendanceView('{{ $meeting->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
                <i class="fa-solid fa-eye"></i> Lihat
            </button>
        @endif

        @if ($attendanceMeetingId === $meeting->id)
            <button type="button" wire:click="closeAttendanceMode" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
                <i class="fa-solid fa-xmark"></i> Tutup Isi Absensi
            </button>
        @else
            <button type="button" wire:click="openAttendanceMode('{{ $meeting->id }}')" class="btn-ghost" @disabled(! $this->canFillAttendance) style="padding:5px 10px;font-size:11px;" title="{{ $this->canFillAttendance ? '' : 'Pelatihan harus dipublish dulu sebelum absensi bisa diisi' }}">
                <i class="fa-solid fa-clipboard-check"></i> Isi Absensi
            </button>
        @endif

        <button type="button" wire:click="exportAttendance('{{ $meeting->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
            <i class="fa-solid fa-file-excel"></i> Export Excel
        </button>
    </div>
</div>

@if ($attendanceViewMeetingId === $meeting->id)
    <div style="background:var(--surface-alt);border-radius:10px;padding:14px;overflow-x:auto;margin-bottom:14px;">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Nama Peserta</th>
                    <th style="text-align:center;padding:8px 10px;white-space:nowrap;">Absen Masuk</th>
                    <th style="text-align:center;padding:8px 10px;white-space:nowrap;">Absen Pulang</th>
                    <th style="text-align:left;padding:8px 10px;">Keterangan Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->attendanceViewRows as $row)
                    <tr style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:8px 10px;font-weight:600;white-space:nowrap;">{{ $row->name }}</td>
                        <td style="padding:8px 10px;text-align:center;font-size:15px;">{{ $row->masuk ? '✅' : '❌' }}</td>
                        <td style="padding:8px 10px;text-align:center;font-size:15px;">{{ $row->pulang ? '✅' : '❌' }}</td>
                        <td style="padding:8px 10px;color:var(--text-muted);">{{ $row->keterangan ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding:16px;text-align:center;color:var(--text-faint);">Belum ada peserta.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@if ($attendanceMeetingId === $meeting->id)
    <div style="background:var(--surface-alt);border-radius:10px;padding:14px;">
        <input type="search" wire:model.live.debounce.300ms="attendanceSearch" class="form-input" placeholder="Cari nama peserta..." style="width:100%;margin-bottom:12px;">

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Nama Peserta</th>
                        <th style="text-align:center;padding:8px 10px;white-space:nowrap;">Absen Masuk</th>
                        <th style="text-align:center;padding:8px 10px;white-space:nowrap;">Absen Pulang</th>
                        <th style="text-align:left;padding:8px 10px;">Keterangan Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->attendanceParticipants as $participant)
                        <tr wire:key="'attendance-'.{{ $participant->user_id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:8px 10px;font-weight:600;white-space:nowrap;">{{ $participant->user?->name ?? '-' }}</td>
                            <td style="padding:8px 10px;text-align:center;">
                                <label class="custom-checkbox" style="width:24px;height:24px;">
                                    <input type="checkbox" wire:model="attendanceForm.{{ $participant->user_id }}.masuk">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                            </td>
                            <td style="padding:8px 10px;text-align:center;">
                                <label class="custom-checkbox" style="width:24px;height:24px;">
                                    <input type="checkbox" wire:model="attendanceForm.{{ $participant->user_id }}.pulang">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                            </td>
                            <td style="padding:8px 10px;">
                                <input type="text" class="form-input" style="font-size:12px;padding:6px 10px;" placeholder="mis. ada tugas..." wire:model="attendanceForm.{{ $participant->user_id }}.keterangan">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding:16px;text-align:center;color:var(--text-faint);">{{ trim($attendanceSearch) !== '' ? 'Tidak ada peserta yang cocok.' : 'Belum ada peserta.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
            <button type="button" wire:click="saveAttendance" class="btn-primary" style="padding:7px 16px;font-size:12px;">Simpan Absensi</button>
        </div>
    </div>
@endif
