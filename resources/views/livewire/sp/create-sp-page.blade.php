<div>
<x-page-header title="Buat Surat Peringatan" subtitle="Terbitkan Surat Peringatan untuk karyawan" :back="route('sp')" />

<style>
    /* Pola sama .ot-type-group/.ot-type-btn di submit-overtime-request.blade.php (pilihan Jenis
    Lembur) - instruksi user 2026-09-25: Level SP pakai radio-icon yang sama, bukan <select>. */
    .sp-level-group { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .sp-level-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 16px 12px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--surface); color: var(--text-heading); font-family: inherit; font-size: 13.5px; font-weight: 700; text-align: center; cursor: pointer; transition: background 0.12s ease, border-color 0.12s ease; }
    .sp-level-btn i { font-size: 22px; }
    .sp-level-btn:hover { background: var(--surface-alt); }
    .sp-level-btn.is-active[data-level="sp1"] { background: linear-gradient(130deg, #eab308 0%, #a16207 100%); border-color: #a16207; color: #fff; }
    .sp-level-btn.is-active[data-level="sp2"] { background: linear-gradient(130deg, #f97316 0%, #c2410c 100%); border-color: #c2410c; color: #fff; }
    .sp-level-btn.is-active[data-level="sp3"] { background: linear-gradient(130deg, #ef4444 0%, #b91c1c 100%); border-color: #b91c1c; color: #fff; }
    @media (max-width: 767.98px) {
        .sp-level-group { gap: 8px; }
        .sp-level-btn { gap: 5px; padding: 11px 8px; font-size: 12px; border-radius: 10px; }
        .sp-level-btn i { font-size: 16px; }
    }
</style>

<form wire:submit="chooseConfirmSign">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-id-card" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Informasi Karyawan</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            @if ($this->canBrowseAllCompanies)
                <div>
                    <label class="form-label">Perusahaan <span style="color:var(--danger-text);">*</span></label>
                    <select wire:model.live="companyId" class="form-input">
                        <option value="">Pilih perusahaan</option>
                        @foreach ($this->companyOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="form-label">Karyawan <span style="color:var(--danger-text);">*</span></label>
                <select data-tom-select data-placeholder="Pilih karyawan" wire:model.live="user_id" class="form-input" @disabled(! $companyId)>
                    <option value="">Pilih karyawan</option>
                    @foreach ($this->employeeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('user_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Sumber dari Coaching (opsional)</label>
                <select wire:model="coaching_id" class="form-input" @disabled(! $user_id)>
                    <option value="">Manual (tanpa Coaching)</option>
                    @foreach ($this->coachingOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Isi kalau SP ini terbit sebagai tindak lanjut dari sesi Coaching tertentu.</p>
                @error('coaching_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-triangle-exclamation" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Surat Peringatan</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Nomor Surat <span style="color:var(--danger-text);">*</span></label>
                <input type="text" wire:model="nomor" class="form-input" placeholder="mis. 001/SP/HC-GA/IX/2026">
                @error('nomor') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Level SP <span style="color:var(--danger-text);">*</span></label>
                <div class="sp-level-group" role="radiogroup" aria-label="Level SP">
                    @foreach (\App\Enum\Sp\SpLevel::cases() as $level)
                        <button type="button" role="radio" aria-checked="{{ $sp_level === $level->value ? 'true' : 'false' }}"
                            data-level="{{ $level->value }}"
                            wire:click="$set('sp_level', '{{ $level->value }}')"
                            class="sp-level-btn {{ $sp_level === $level->value ? 'is-active' : '' }}">
                            <i class="fa-solid {{ $level->icon() }}"></i>
                            <span>{{ $level->getLabel() }}</span>
                        </button>
                    @endforeach
                </div>
                @error('sp_level') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Tanggal Mulai <span style="color:var(--danger-text);">*</span></label>
                <input type="date" wire:model.live="start_date" class="form-input">
                @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                @if ($this->endDatePreview)
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        <i class="fa-solid fa-circle-info"></i> Berlaku sampai: {{ \Illuminate\Support\Carbon::parse($this->endDatePreview)->translatedFormat('d F Y') }}
                    </p>
                @endif
            </div>

            <div>
                <label class="form-label">Mengetahui <span style="color:var(--danger-text);">*</span></label>
                <select data-tom-select data-placeholder="Pilih pimpinan/HC Supervisor/Manager" wire:model="knows_by" class="form-input">
                    <option value="">Pilih</option>
                    @foreach ($this->knowsByOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Pimpinan/HC Supervisor/Manager yang menyetujui penerbitan SP ini.</p>
                @error('knows_by') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Uraian/Kronologi <span style="color:var(--danger-text);">*</span></label>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($items as $index => $item)
                        <div wire:key="'sp-item-'.{{ $index }}" style="display:flex;gap:8px;align-items:flex-start;">
                            <div wire:ignore style="flex:1;">
                                <input type="hidden" id="trix-item-{{ $index }}" wire:model="items.{{ $index }}" value="{{ $item }}">
                                <trix-editor input="trix-item-{{ $index }}" style="min-height:70px;" placeholder="Tulis uraian/kronologi (nomor manual, mis. &quot;1. ...&quot;)"></trix-editor>
                            </div>
                            <button type="button" wire:click="removeItem({{ $index }})" class="btn-ghost" style="padding:8px 10px;color:var(--danger-text);">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        @error('items.' . $index) <p style="color:var(--danger-text);font-size:12px;margin-left:20px;">{{ $message }}</p> @enderror
                    @endforeach
                </div>
                <button type="button" wire:click="addItem" class="btn-ghost" style="margin-top:8px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Uraian
                </button>
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Berita Acara (Opsional)</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Dasar Klarifikasi</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-dasar-klarifikasi" wire:model="dasar_klarifikasi" value="{{ $dasar_klarifikasi }}">
                    <trix-editor input="trix-dasar-klarifikasi" style="min-height:100px;"></trix-editor>
                </div>
                @error('dasar_klarifikasi') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Kejadian</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-kejadian" wire:model="kejadian" value="{{ $kejadian }}">
                    <trix-editor input="trix-kejadian" style="min-height:100px;"></trix-editor>
                </div>
                @error('kejadian') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Klarifikasi Karyawan</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-subject-clarification" wire:model="subject_clarification" value="{{ $subject_clarification }}">
                    <trix-editor input="trix-subject-clarification" style="min-height:100px;"></trix-editor>
                </div>
                @error('subject_clarification') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Analisis</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-analysis" wire:model="analysis" value="{{ $analysis }}">
                    <trix-editor input="trix-analysis" style="min-height:100px;"></trix-editor>
                </div>
                @error('analysis') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Kesimpulan</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-kesimpulan" wire:model="kesimpulan" value="{{ $kesimpulan }}">
                    <trix-editor input="trix-kesimpulan" style="min-height:100px;"></trix-editor>
                </div>
                @error('kesimpulan') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Rencana Tindak Lanjut</label>
                <div wire:ignore>
                    <input type="hidden" id="trix-followup-plan" wire:model="followup_plan" value="{{ $followup_plan }}">
                    <trix-editor input="trix-followup-plan" style="min-height:100px;"></trix-editor>
                </div>
                @error('followup_plan') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
            <p style="font-size:11px;color:var(--text-muted);margin:0;">Berita Acara bersifat internal HR, tidak ditampilkan ke karyawan.</p>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('sp') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk" style="font-size:12px;"></i> Simpan</button>
    </div>
</form>

{{-- Konfirmasi password created_by SEBELUM SP disimpan & ditandatangani (TTE Fase 1, instruksi
user 2026-09-28, Pasal 59 ayat 3 butir 6 PP 71/2019 - persetujuan eksplisit, bukan cuma browser
confirm() dialog). knows_by konfirmasi terpisah lewat halaman /dokumen/tandatangani sendiri. --}}
@if ($confirmingSign)
    <div class="modal-overlay" wire:click="cancelConfirmSign">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Konfirmasi Tanda Tangan</h3>
                <button type="button" wire:click="cancelConfirmSign" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="display:flex;align-items:center;gap:12px;background:var(--info-soft-bg);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                <x-avatar :user="auth()->user()" :size="42" />
                <div style="min-width:0;">
                    <p style="font-size:10.5px;color:var(--info-soft-text);text-transform:uppercase;letter-spacing:0.03em;margin:0 0 2px;">Anda menandatangani sebagai</p>
                    <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ auth()->user()->name }}</p>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:0;">{{ auth()->user()->activeJobPosition?->jobPosition?->name ?? '-' }}</p>
                </div>
            </div>

            <p style="font-size:12px;color:var(--text-body);margin:0 0 12px;">Surat akan langsung diterima oleh karyawan bersangkutan. Masukkan password Anda untuk menyetujui dan menandatangani penerbitan SP ini.</p>

            <div style="margin-bottom:14px;">
                <label class="form-label">Password Anda</label>
                <input type="password" wire:model="signPassword" class="form-input" wire:keydown.enter="save" autofocus>
                @error('signPassword') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-check"></i> Setujui &amp; Terbitkan
                </button>
                <button type="button" wire:click="cancelConfirmSign" class="btn-ghost" style="flex:1;justify-content:center;">Batal</button>
            </div>
        </div>
    </div>
@endif
</div>
