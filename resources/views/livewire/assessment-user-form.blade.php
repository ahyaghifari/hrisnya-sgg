<div class="form-page-wrap">
    <div class="page-hero">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);font-size:11px;">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Penilaian Karyawan</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">{{ $assessmentUser->assessments->name }}</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">
                {{ $assessmentUser->target->name }}
                @if ($assessmentUser->assessments->start_date)
                    &middot; {{ \Carbon\Carbon::parse($assessmentUser->assessments->start_date)->translatedFormat('j F Y') }} - {{ \Carbon\Carbon::parse($assessmentUser->assessments->end_date)->translatedFormat('j F Y') }}
                @endif
            </p>
        </div>
    </div>

    @if ($assessmentUser->final_score !== null)
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--info-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-circle-check" style="color:var(--info-soft-text);font-size:14px;"></i>
            </div>
            <div>
                <p style="font-size:12.5px;font-weight:700;color:var(--info-soft-text);margin:0 0 2px;">Nilai Akhir: {{ number_format($assessmentUser->final_score, 2) }} &middot; {{ $assessmentUser->final_category }}</p>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0;">Penilaian sudah final, tidak bisa diubah lagi.</p>
            </div>
        </div>
    @elseif (! $canEdit)
        <div style="background:var(--warning-soft-bg);border:1px solid var(--warning-soft-border);border-radius:14px;padding:14px 18px;margin-bottom:20px;">
            <p style="font-size:12.5px;font-weight:600;color:var(--warning-soft-text);margin:0;">Menunggu atasan langsung mengisi nilai.</p>
        </div>
    @endif

    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-clipboard-list" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Indikator Penilaian</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                @forelse ($this->templateItems as $item)
                    <div style="border-bottom:1px solid var(--border-subtle);padding-bottom:14px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">
                            <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">{{ $item->name }}</p>
                            <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;">Bobot {{ number_format($item->weight_percent, 0) }}%</span>
                        </div>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="{{ $item->max_score }}"
                            class="form-input"
                            placeholder="Maks. {{ number_format($item->max_score, 0) }}"
                            wire:model="scores.{{ $item->id }}"
                            @disabled(! $canEdit)
                        >
                        @error('scores.' . $item->id) <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @empty
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada indikator penilaian di template ini.</p>
                @endforelse
            </div>
        </div>

        @if ($canEdit)
            <div style="display:flex;align-items:center;justify-content:flex-end;margin-top:20px;">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Simpan Penilaian</button>
            </div>
        @endif
    </form>
</div>
