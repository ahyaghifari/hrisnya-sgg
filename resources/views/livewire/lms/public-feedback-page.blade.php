<div>
@if ($notFound)
    <div class="course-session-card" style="text-align:center;padding:32px 16px;">
        <i class="fa-solid fa-circle-exclamation" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Umpan balik tidak ditemukan</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Link ini tidak valid atau pelatihannya belum dipublikasikan.</p>
    </div>
@elseif ($closed)
    <div class="course-session-card" style="text-align:center;padding:32px 16px;">
        <i class="fa-solid fa-lock" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Umpan balik sudah ditutup</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Pengisian umpan balik ini sudah lewat tanggal pelaksanaan pelatihannya.</p>
    </div>
@else
    <div class="course-session-card" style="margin-bottom:16px;border-top:8px solid #042c6c;">
        <p style="font-size:18px;font-weight:800;color:var(--text-heading);margin:0 0 6px;">Evaluasi Pelatihan / Fasilitator</p>
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">{{ $course->title }}</p>
        <p style="font-size:12.5px;color:var(--text-body);line-height:1.6;margin:0;padding-top:12px;border-top:1px solid var(--border-soft);">
            Kuesioner ini bertujuan untuk menilai kualitas pelatihan dan fasilitator. Masukan Anda sangat berarti untuk peningkatan program ke depan. Jawaban bersifat rahasia dan hanya digunakan untuk evaluasi internal.
        </p>
    </div>

    @if ($step === 'form')
        <div class="course-session-card" style="margin-bottom:16px;">
            <p class="form-label" style="margin-bottom:14px;font-size:13.5px;">Isi data diri</p>

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
        </div>

        @error('scores') <p style="color:#dc2626;font-size:12.5px;margin-bottom:10px;">{{ $message }}</p> @enderror

        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;">
            @foreach ($questions as $question)
                @php $isUnanswered = $errors->has('scores') && ! isset($scores[$question->id]); @endphp
                <div class="course-session-card" @style(["border-color:#dc2626" => $isUnanswered])>
                    <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin-bottom:16px;">
                        {{ $question->question }} <span style="color:#dc2626;">*</span>
                    </p>

                    <div style="display:flex;justify-content:space-between;gap:4px;">
                        @foreach ([1, 2, 3, 4, 5] as $score)
                            <label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;flex:1;">
                                <input type="radio"
                                    name="score-{{ $question->id }}"
                                    wire:click="rate('{{ $question->id }}', {{ $score }})"
                                    @checked(($scores[$question->id] ?? null) === $score)
                                    style="width:18px;height:18px;accent-color:#042c6c;cursor:pointer;">
                                @if ($score === 1)
                                    <span style="font-size:10.5px;color:var(--text-muted);text-align:center;">Kurang Baik</span>
                                @elseif ($score === 5)
                                    <span style="font-size:10.5px;color:var(--text-muted);text-align:center;">Sangat Baik</span>
                                @else
                                    <span style="font-size:10.5px;color:var(--text-faint);">&nbsp;</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="course-session-card" style="margin-bottom:16px;">
            <label class="form-label">Catatan tambahan (opsional)</label>
            <textarea wire:model="feedback" class="form-input" rows="3" placeholder="Tulis masukan kamu..."></textarea>
        </div>

        <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="pwa-action-btn navy" style="width:100%;padding:14px;border:none;color:#fff;font-weight:700;font-size:14px;">
            <span wire:loading.remove wire:target="submit">Kirim Umpan Balik</span>
            <span wire:loading wire:target="submit"><i class="fa-solid fa-spinner fa-spin"></i> Mengirim...</span>
        </button>
    @else
        <div class="course-session-card" style="text-align:center;padding:32px 16px;">
            <i class="fa-solid fa-circle-check" style="font-size:24px;color:#047C54;margin-bottom:10px;"></i>
            <p style="font-size:14px;font-weight:700;color:var(--text-heading);margin-bottom:4px;">Terima kasih!</p>
            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Umpan balik kamu sudah tersimpan.</p>
        </div>
    @endif
@endif
</div>
