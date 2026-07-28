<div>
@if ($notFound)
    <div class="course-session-card" style="text-align:center;padding:32px 16px;">
        <i class="fa-solid fa-circle-exclamation" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Kuis tidak ditemukan</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Link ini tidak valid atau pelatihannya belum dipublikasikan.</p>
    </div>
@elseif ($closed)
    <div class="course-session-card" style="text-align:center;padding:32px 16px;">
        <i class="fa-solid fa-lock" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Kuis sudah ditutup</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Pengisian kuis ini sudah lewat tanggal pertemuan pelatihannya.</p>
    </div>
@else
    <div class="course-session-card" style="margin-bottom:16px;border-top:8px solid #042c6c;">
        <p style="font-size:18px;font-weight:800;color:var(--text-heading);margin:0 0 6px;">{{ $meetingQuiz->quiz->title }}</p>
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">{{ $meetingQuiz->meeting->course->title }}</p>
        <p style="font-size:12.5px;color:var(--text-body);line-height:1.6;margin:0;padding-top:12px;border-top:1px solid var(--border-soft);">
            Kerjakan kuis ini untuk menguji pemahaman Anda terhadap materi pelatihan. Pastikan identitas yang diisi benar sebelum mulai mengerjakan.
        </p>
    </div>

    @if ($step === 'identity')
        <div class="course-session-card">
            <p class="form-label" style="margin-bottom:14px;font-size:13.5px;">Isi data diri sebelum mengerjakan kuis</p>

            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label class="form-label">Nama <span style="color:#dc2626;">*</span></label>
                    <input type="text" wire:model="name" class="form-input" placeholder="Nama lengkap">
                    @error('name') <p style="color:#dc2626;font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">NIP <span style="color:#dc2626;">*</span></label>
                    <input type="text" wire:model="nip" class="form-input">
                    @error('nip') <p style="color:#dc2626;font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Perusahaan <span style="color:#dc2626;">*</span></label>
                    <select wire:model="company_id" class="form-input">
                        <option value="">-- Pilih Perusahaan --</option>
                        @foreach ($this->companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('company_id') <p style="color:#dc2626;font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Unit / Divisi / Jabatan <span style="color:#dc2626;">*</span></label>
                    <input type="text" wire:model="unit" class="form-input" placeholder="Contoh: Perawat - IGD">
                    @error('unit') <p style="color:#dc2626;font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="button" wire:click="startQuiz" wire:loading.attr="disabled" wire:target="startQuiz" class="pwa-action-btn navy" style="width:100%;padding:14px;border:none;color:#fff;font-weight:700;font-size:14px;margin-top:16px;">
                <span wire:loading.remove wire:target="startQuiz">Mulai Kuis</span>
                <span wire:loading wire:target="startQuiz"><i class="fa-solid fa-spinner fa-spin"></i> Memuat...</span>
            </button>
        </div>
    @elseif ($step === 'quiz')
        @error('answers') <p style="color:#dc2626;font-size:12.5px;margin-bottom:10px;">{{ $message }}</p> @enderror

        @if (empty($questions))
            <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada soal pilihan ganda di kuis ini.</p>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
                @foreach ($questions as $question)
                    <div class="course-session-card">
                        <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin-bottom:12px;">
                            {{ $question['no'] }}. {{ $question['question_text'] }}
                        </p>

                        <div style="display:flex;flex-direction:column;gap:8px;">
                            @foreach ($question['options'] as $option)
                                <button type="button"
                                    wire:click="selectAnswer('{{ $question['id'] }}', '{{ $option['id'] }}')"
                                    class="quiz-option {{ ($answers[$question['id']] ?? null) === $option['id'] ? 'is-selected' : '' }}">
                                    {{ $option['text'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="pwa-action-btn navy" style="width:100%;padding:14px;border:none;color:#fff;font-weight:700;font-size:14px;">
                <span wire:loading.remove wire:target="submit">Kirim Jawaban</span>
                <span wire:loading wire:target="submit"><i class="fa-solid fa-spinner fa-spin"></i> Mengirim...</span>
            </button>
        @endif
    @else
        <div class="course-session-card" style="text-align:center;padding:32px 16px;">
            <i class="fa-solid fa-circle-check" style="font-size:24px;color:#047C54;margin-bottom:10px;"></i>
            <p style="font-size:14px;font-weight:700;color:var(--text-heading);margin-bottom:4px;">Terima kasih!</p>
            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Jawaban kamu sudah tersimpan.</p>
        </div>
    @endif
@endif
</div>
