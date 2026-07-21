{{-- Isi 1 pertemuan: Detail Pertemuan + Materi + Quiz. Dipakai baik di dalam accordion
     (course multi-hari) maupun langsung tanpa collapse (course 1 hari). Butuh $meeting. --}}
<h5 style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Detail Pertemuan</h5>
@include('livewire.lms.partials.meeting-detail-form')
<div style="display:flex;justify-content:flex-end;margin-top:12px;">
    <button type="button" wire:click="saveMeeting" class="btn-primary" style="padding:7px 16px;font-size:12px;">Simpan Detail</button>
</div>

<div style="border-top:1px solid var(--border-soft);margin:18px 0;"></div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <h5 style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">Materi Pertemuan</h5>
    @if ($addingMaterialForMeetingId !== $meeting->id)
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
        <button type="button" wire:click="removeMaterial('{{ $material->id }}')" wire:confirm="Hapus materi ini?" class="btn-ghost" style="padding:4px 8px;font-size:11px;color:#dc2626;">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>
@empty
    <p style="font-size:12px;color:var(--text-faint);margin:0;">Belum ada materi.</p>
@endforelse

<div style="border-top:1px solid var(--border-soft);margin:18px 0;"></div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <h5 style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">Quiz</h5>
    @if ($addingQuizForMeetingId !== $meeting->id)
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

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button type="button" wire:click="closeAddQuizForm" class="btn-ghost" style="padding:6px 12px;font-size:11.5px;">Batal</button>
            <button type="button" wire:click="saveQuiz" class="btn-primary" style="padding:6px 14px;font-size:11.5px;">Simpan</button>
        </div>
    </div>
@endif

@forelse ($meeting->quizzes as $meetingQuiz)
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-soft);font-size:12.5px;">
        <span><i class="fa-solid fa-circle-question" style="color:var(--text-faint);"></i> {{ $meetingQuiz->quiz?->title ?? '-' }} <span style="color:var(--text-faint);">({{ $meetingQuiz->duration }} menit)</span></span>
        <button type="button" wire:click="removeQuiz('{{ $meetingQuiz->id }}')" wire:confirm="Hapus quiz ini?" class="btn-ghost" style="padding:4px 8px;font-size:11px;color:#dc2626;">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>
@empty
    <p style="font-size:12px;color:var(--text-faint);margin:0;">Belum ada quiz.</p>
@endforelse
