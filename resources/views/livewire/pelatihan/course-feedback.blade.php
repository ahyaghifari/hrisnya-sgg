<div class="accent-{{ $this->accent }}"
    x-data
    x-on:feedback-submitted.window="
        const here = location.pathname;
        setTimeout(() => { if (location.pathname === here) Livewire.navigate($event.detail.url); }, 5000);
    ">
<x-page-header title="Umpan Balik" :back="route('pelatihan.show', ['course' => $course])" />

<div class="course-hero-body" style="margin-bottom:16px;">
    <span class="course-hero-eyebrow">{{ $course->category->name ?? 'Umum' }}</span>
    <p class="course-hero-title">{{ $course->title }}</p>
</div>

@if ($blocked)
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <i class="fa-solid fa-lock" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Belum bisa mengisi umpan balik</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Kamu belum terdaftar sebagai peserta pelatihan ini.</p>
    </div>
@elseif ($locked)
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <i class="fa-solid fa-lock" style="font-size:22px;color:var(--text-faint);margin-bottom:10px;"></i>
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Umpan balik belum dibuka</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Admin/pemateri belum membuka pengisian umpan balik ini.</p>
    </div>
@elseif ($alreadySubmitted)
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <i class="fa-solid fa-circle-check" style="font-size:24px;color:#047C54;margin-bottom:10px;"></i>
        <p style="font-size:14px;font-weight:700;color:var(--text-heading);margin-bottom:4px;">Terima kasih!</p>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Umpan balik kamu sudah tersimpan.</p>
    </div>
@else
    @error('scores') <p style="color:#dc2626;font-size:12.5px;margin-bottom:10px;">{{ $message }}</p> @enderror

    <style>
        .feedback-radio .dot { border-color: #94a3b8; }
        .feedback-radio input:checked ~ .dot { border-color: var(--accent, var(--brand)); }
    </style>
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
                            <span class="custom-radio feedback-radio">
                                <input type="radio"
                                    name="score-{{ $question->id }}"
                                    wire:click="rate('{{ $question->id }}', {{ $score }})"
                                    @checked(($scores[$question->id] ?? null) === $score)>
                                <span class="dot"></span>
                            </span>
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
@endif
</div>
