<div class="p-4 md:p-6">
    <x-page-header title="Atur Pengguna Jadwal" :subtitle="$schedule->name" :back="route('presensi.jadwal')" />

    <div style="background:var(--surface);border-radius:14px;box-shadow:var(--shadow-card);padding:20px;margin-top:16px;width:100%;">
        <div style="display:flex;flex-wrap:wrap;gap:28px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-soft);">
            <div>
                <p style="font-size:10.5px;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;color:var(--text-faint);margin:0 0 3px;">Jadwal</p>
                <p style="font-size:19px;font-weight:800;color:var(--text-heading);margin:0;">{{ $schedule->name }}</p>
            </div>
            <div>
                <p style="font-size:10.5px;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;color:var(--text-faint);margin:0 0 3px;">Perusahaan</p>
                <p style="font-size:19px;font-weight:800;color:var(--text-heading);margin:0;">{{ $schedule->company?->name ?? '-' }}</p>
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-soft);">
            <div>
                <label class="form-label">Tanggal Diterapkan *</label>
                <input type="date" class="form-input" style="max-width:220px;" wire:model="effectiveFrom">
                @error('effectiveFrom') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <p style="font-size:11.5px;color:var(--text-muted);margin:0;max-width:420px;">Centang jabatan yang memakai jadwal <strong style="color:var(--text-heading);">{{ $schedule->name }}</strong>. Jabatan bertanda <i class="fa-solid fa-xmark" style="color:#dc2626;"></i> sudah memakai jadwal lain — tidak bisa dipilih di sini.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">
            @forelse ($this->orgTree as $node)
                <div style="border:1px solid var(--border-soft);border-radius:10px;padding:12px 14px;">
                    @include('livewire.presensi.partials.org-tree-node', ['node' => $node, 'depth' => 0])
                </div>
            @empty
                <p style="font-size:12px;color:var(--text-faint);margin:8px 0;">Belum ada struktur organisasi untuk perusahaan ini.</p>
            @endforelse
        </div>

        @if ($this->functionalJobs->isNotEmpty())
            <div style="margin-top:20px;">
                <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;display:flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-user-tag" style="font-size:11px;color:var(--text-faint);"></i> Jabatan Fungsional
                </p>
                <div style="border:1px solid var(--border-soft);border-radius:10px;padding:10px 14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                    @foreach ($this->functionalJobs as $job)
                        @php $blockedBy = $this->blockedJobPositions[$job->id] ?? null; @endphp
                        <label style="padding:5px 8px;display:flex;align-items:center;gap:9px;font-size:12.5px;border-radius:6px;{{ $blockedBy ? '' : 'cursor:pointer;' }}" {!! $blockedBy ? '' : 'onmouseover="this.style.background=\'var(--border-soft)\'" onmouseout="this.style.background=\'\'"' !!}>
                            @if ($blockedBy)
                                <i class="fa-solid fa-xmark" style="color:#dc2626;width:19px;text-align:center;flex-shrink:0;" title="Sudah pakai jadwal {{ $blockedBy }}"></i>
                            @else
                                <span class="custom-checkbox" style="--accent:#dc2626;">
                                    <input type="checkbox" wire:model="selectedJobPositionIds" value="{{ $job->id }}">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </span>
                            @endif
                            <span>{{ $job->name }}</span>
                            @if ($blockedBy)
                                <span style="font-size:10.5px;color:#dc2626;white-space:nowrap;">// {{ $blockedBy }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
            <a href="{{ route('presensi.jadwal') }}" wire:navigate class="btn-ghost">Lewati</a>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary">
                <span wire:loading.remove wire:target="save"><i class="fa-solid fa-floppy-disk"></i> Simpan</span>
                <span wire:loading wire:target="save"><i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...</span>
            </button>
        </div>
    </div>
</div>
