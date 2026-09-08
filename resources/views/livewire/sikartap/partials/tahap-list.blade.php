@php
    /** @var string $tahap 'tes' | 'wawancara' */
    $isTes = $tahap === 'tes';
    $jadwal = $this->jadwal;
    $labelTahap = $isTes ? 'Tes' : 'Wawancara';

    $gate = \App\Livewire\Sikartap\Concerns\ManagesSikartapTesWawancara::class;
    $schedulable = fn ($item) => $isTes ? $gate::canScheduleTest($item) : $gate::canScheduleInterview($item);
    $schedule = fn ($item) => $isTes ? $item->testSchedule : $item->interviewSchedule;
    $statusVal = fn ($item) => $isTes ? $item->status_tes : $item->status_wawancara;

    $statusBadge = fn (?string $status) => match ($status) {
        'lulus', 'disarankan' => ['✅', 'Lulus / Disarankan'],
        'tidak_lulus', 'tidak_disarankan' => ['❌', 'Tidak lulus / Tidak disarankan'],
        default => ['⏳', 'Belum ada hasil'],
    };
@endphp

<div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
    @if ($canFilterByCompany)
        <div style="min-width:220px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                <option value="">Semua perusahaan</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div style="min-width:220px;flex:1;">
        <label class="form-label">Cari</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP..." style="padding-left:38px;">
        </div>
    </div>
</div>

@if ($this->canUpdate && count($selected) > 0)
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span style="font-size:13px;font-weight:600;">{{ count($selected) }} dipilih</span>
        <button type="button" wire:click="openScheduleModal" class="btn-primary" style="padding:6px 14px;font-size:12px;">
            <i class="fa-solid fa-calendar-plus"></i> Jadwalkan {{ $labelTahap }} (massal)
        </button>
        <button type="button" wire:click="$set('selected', [])" class="btn-ghost" style="padding:6px 12px;font-size:12px;">Batal pilih</button>
    </div>
@endif

@if ($jadwal->isEmpty())
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">
            {{ $canFilterByCompany && ! $companyId ? 'Pilih perusahaan dulu.' : 'Belum ada pengajuan pada tahap ini.' }}
        </p>
    </div>
@else
    {{-- MOBILE --}}
    <div class="md:hidden">
        <div class="pwa-list">
            @foreach ($jadwal as $item)
                @php $s = $schedule($item); [$emoji, $stLabel] = $statusBadge($statusVal($item)); @endphp
                <div class="pwa-list-row" style="align-items:flex-start;gap:10px;">
                    @if ($this->canUpdate && $schedulable($item))
                        <label class="custom-checkbox" style="--accent:#7c3aed;flex-shrink:0;margin-top:2px;">
                            <input type="checkbox" wire:model.live="selected" value="{{ $item->id }}">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                    @endif
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $item->user->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $item->user->nip }}</p>
                        @if ($s)
                            <p style="font-size:12px;color:var(--text-body);margin-top:6px;">
                                <i class="fa-solid fa-calendar-day" style="color:var(--text-faint);"></i>
                                {{ \Carbon\Carbon::parse($isTes ? $s->tanggal_tes : $s->tanggal_wawancara)->translatedFormat('j M Y') }},
                                {{ $isTes ? $s->waktu_tes : $s->waktu_wawancara }} &middot; {{ $isTes ? $s->tempat_tes : $s->tempat_wawancara }}
                            </p>
                            <p style="font-size:12px;color:var(--text-body);margin-top:2px;">{{ $emoji }} {{ $stLabel }}</p>
                            @if ($isTes && ($item->nilai_tes !== null || $item->nilai_psikotes !== null))
                                <p style="font-size:12px;color:var(--text-body);margin-top:2px;">Nilai tes {{ $item->nilai_tes ?? '–' }} / psikotes {{ $item->nilai_psikotes ?? '–' }}</p>
                            @endif
                        @endif
                        @if ($this->canUpdate)
                            <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                                @if ($schedulable($item))
                                    <button type="button" wire:click="scheduleRow('{{ $item->id }}')" class="btn-ghost" style="padding:4px 10px;font-size:11px;">{{ $s ? 'Ubah jadwal' : 'Jadwalkan' }}</button>
                                @endif
                                @if ($s)
                                    <button type="button" wire:click="openResultModal('{{ $item->id }}')" class="btn-ghost" style="padding:4px 10px;font-size:11px;">Isi hasil</button>
                                @endif
                                <a href="{{ route('sikartap.pengajuan.show', $item) }}" wire:navigate class="btn-ghost" style="padding:4px 10px;font-size:11px;">Detail</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div style="margin-top:16px;">{{ $jadwal->links('livewire.partials.pagination') }}</div>
    </div>

    {{-- DESKTOP --}}
    <div class="hidden md:block">
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="width:34px;padding:10px 8px 10px 14px;"></th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jadwal {{ $labelTahap }}</th>
                        @if ($isTes)
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Nilai</th>
                        @endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Hasil</th>
                        <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jadwal as $item)
                        @php $s = $schedule($item); [$emoji, $stLabel] = $statusBadge($statusVal($item)); @endphp
                        <tr wire:key="row-{{ $item->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 8px 9px 14px;text-align:center;">
                                @if ($this->canUpdate && $schedulable($item))
                                    <label class="custom-checkbox" style="--accent:#7c3aed;">
                                        <input type="checkbox" wire:model.live="selected" value="{{ $item->id }}">
                                        <span class="box"><i class="fa-solid fa-check"></i></span>
                                    </label>
                                @endif
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span style="font-weight:600;">{{ $item->user->name }}</span>
                                <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $item->user->nip }}</span>
                            </td>
                            <td style="padding:9px 14px;">
                                @if ($s)
                                    {{ \Carbon\Carbon::parse($isTes ? $s->tanggal_tes : $s->tanggal_wawancara)->translatedFormat('j M Y') }},
                                    {{ $isTes ? $s->waktu_tes : $s->waktu_wawancara }}
                                    <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $isTes ? $s->tempat_tes : $s->tempat_wawancara }}</span>
                                @else
                                    <span style="color:var(--text-faint);font-style:italic;">Belum dijadwalkan</span>
                                @endif
                            </td>
                            @if ($isTes)
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @if ($item->nilai_tes !== null || $item->nilai_psikotes !== null)
                                        <span title="Nilai tes">{{ $item->nilai_tes ?? '–' }}</span>
                                        <span style="color:var(--text-faint);"> / </span>
                                        <span title="Nilai psikotes">{{ $item->nilai_psikotes ?? '–' }}</span>
                                        <span style="display:block;font-size:10px;color:var(--text-faint);">tes / psikotes</span>
                                    @else
                                        <span style="color:var(--text-faint);">-</span>
                                    @endif
                                </td>
                            @endif
                            <td style="padding:9px 14px;text-align:center;">
                                @if ($s)
                                    <span style="font-size:15px;" title="{{ $stLabel }}">{{ $emoji }}</span>
                                @else
                                    <span style="color:var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td style="padding:9px 14px;text-align:right;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    @if ($this->canUpdate && $schedulable($item))
                                        <button type="button" wire:click="scheduleRow('{{ $item->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="{{ $s ? 'Ubah jadwal' : 'Jadwalkan' }}">
                                            <i class="fa-solid fa-calendar-day"></i>
                                        </button>
                                    @endif
                                    @if ($this->canUpdate && $s)
                                        <button type="button" wire:click="openResultModal('{{ $item->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Isi hasil">
                                            <i class="fa-solid fa-clipboard-check"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('sikartap.pengajuan.show', $item) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;">{{ $jadwal->links('livewire.partials.pagination') }}</div>
    </div>
@endif

{{-- MODAL --}}
@if ($activePanel === 'schedule' || $activePanel === 'result')
    <div wire:click.self="closeModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;">
        @php $wideModal = ! $isTes && $activePanel === 'schedule'; @endphp
        <div style="background:var(--surface);border-radius:14px;box-shadow:var(--shadow-card);padding:20px;width:100%;max-width:{{ $wideModal ? '640px' : '460px' }};max-height:90vh;overflow-y:auto;">
            @if ($activePanel === 'schedule')
                <h3 style="font-size:15px;font-weight:700;margin:0 0 4px;">Jadwal {{ $labelTahap }}</h3>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Dipasang ke {{ count($selected) }} orang terpilih yang lolos tahap ini.</p>
                <div style="display:grid;gap:10px;">
                    @if ($isTes)
                        <div>
                            <label class="form-label">Tanggal</label>
                            <input type="date" wire:model="testScheduleForm.tanggal_tes" class="form-input">
                            @error('testScheduleForm.tanggal_tes') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Waktu</label>
                            <input type="time" wire:model="testScheduleForm.waktu_tes" class="form-input">
                            @error('testScheduleForm.waktu_tes') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Tempat</label>
                            <input type="text" wire:model="testScheduleForm.tempat_tes" class="form-input">
                            @error('testScheduleForm.tempat_tes') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Keterangan</label>
                            <textarea wire:model="testScheduleForm.keterangan" class="form-input" rows="2"></textarea>
                        </div>
                    @else
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
                            <div>
                                <label class="form-label">Tanggal</label>
                                <input type="date" wire:model="interviewScheduleForm.tanggal_wawancara" class="form-input">
                                @error('interviewScheduleForm.tanggal_wawancara') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Waktu</label>
                                <input type="time" wire:model="interviewScheduleForm.waktu_wawancara" class="form-input">
                                @error('interviewScheduleForm.waktu_wawancara') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Tempat</label>
                            <input type="text" wire:model="interviewScheduleForm.tempat_wawancara" class="form-input">
                            @error('interviewScheduleForm.tempat_wawancara') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
                            <div>
                                <label class="form-label">Pewawancara 1</label>
                                <input type="text" wire:model="interviewScheduleForm.pewawancara" class="form-input">
                                @error('interviewScheduleForm.pewawancara') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Pewawancara 2</label>
                                <input type="text" wire:model="interviewScheduleForm.pewawancara_2" class="form-input">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Keterangan</label>
                            <textarea wire:model="interviewScheduleForm.keterangan" class="form-input" rows="2"></textarea>
                        </div>
                    @endif
                </div>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="button" wire:click="{{ $isTes ? 'saveTestSchedule' : 'saveInterviewSchedule' }}" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">💾 Simpan</button>
                    <button type="button" wire:click="closeModal" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                </div>
            @else
                <h3 style="font-size:15px;font-weight:700;margin:0 0 14px;">Hasil {{ $labelTahap }}</h3>
                <div style="display:grid;gap:10px;">
                    @if ($isTes)
                        <div>
                            <label class="form-label">Nilai Tes</label>
                            <input type="number" step="0.01" wire:model="testResultForm.nilai_tes" class="form-input">
                            @error('testResultForm.nilai_tes') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Nilai Psikotes</label>
                            <input type="number" step="0.01" wire:model="testResultForm.nilai_psikotes" class="form-input">
                            @error('testResultForm.nilai_psikotes') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select wire:model="testResultForm.status_tes" class="form-input">
                                <option value="">— pilih —</option>
                                <option value="lulus">Lulus</option>
                                <option value="tidak_lulus">Tidak lulus</option>
                            </select>
                            @error('testResultForm.status_tes') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Catatan</label>
                            <textarea wire:model="testResultForm.catatan_tes" class="form-input" rows="2"></textarea>
                        </div>
                    @else
                        <div>
                            <label class="form-label">Status</label>
                            <select wire:model="interviewResultForm.status_wawancara" class="form-input">
                                <option value="">— pilih —</option>
                                <option value="disarankan">Disarankan</option>
                                <option value="tidak_disarankan">Tidak disarankan</option>
                            </select>
                            @error('interviewResultForm.status_wawancara') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Catatan</label>
                            <textarea wire:model="interviewResultForm.catatan_wawancara" class="form-input" rows="2"></textarea>
                        </div>
                    @endif
                </div>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="button" wire:click="{{ $isTes ? 'saveTestResult' : 'saveInterviewResult' }}" class="btn-primary" style="padding:8px 16px;font-size:12.5px;">💾 Simpan</button>
                    <button type="button" wire:click="closeModal" class="btn-ghost" style="padding:8px 16px;font-size:12.5px;">Batal</button>
                </div>
            @endif
        </div>
    </div>
@endif
