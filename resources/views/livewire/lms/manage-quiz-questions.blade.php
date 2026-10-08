<div>
    <x-page-header
        title="Kelola Soal"
        subtitle="{{ $this->quiz->title }}"
        :back="route('pelatihan.kuis')"
    />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
            {{-- Pilih dari Bank Soal --}}
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:8px;">
                    <label class="form-label" style="margin:0;">Pilih dari Bank Soal</label>
                    @if ($this->canCreateQuestion)
                        <a href="{{ route('pelatihan.bank-soal.create') }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11.5px;white-space:nowrap;">
                            <i class="fa-solid fa-plus"></i> Buat Soal Baru
                        </a>
                    @endif
                </div>

                <div style="position:relative;margin-bottom:10px;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                    <input type="search" wire:model.live.debounce.300ms="questionSearch" class="form-input" placeholder="Cari soal..." style="padding-left:38px;">
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;max-height:420px;overflow-y:auto;">
                    @forelse ($this->availableQuestions as $question)
                        <div wire:key="avail-{{ $question->id }}" style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--surface-alt);border-radius:8px;">
                            <div style="flex:1;min-width:0;font-size:12.5px;color:var(--text-body);">
                                {{ \Illuminate\Support\Str::limit(strip_tags($question->question), 90) }}
                            </div>
                            <button type="button" wire:click="addQuestion('{{ $question->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;flex-shrink:0;">
                                <i class="fa-solid fa-plus"></i> Pilih
                            </button>
                        </div>
                    @empty
                        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Tidak ada soal tersedia di kategori ini.</p>
                    @endforelse
                </div>
            </div>

            {{-- Soal terpilih (bisa diurutkan) --}}
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
                <label class="form-label" style="margin-bottom:10px;display:block;">Soal Terpilih ({{ count($selectedQuestionIds) }})</label>
                @error('selectedQuestionIds') <p style="font-size:11px;color:#dc2626;margin-bottom:8px;">{{ $message }}</p> @enderror

                <div style="display:flex;flex-direction:column;gap:8px;max-height:420px;overflow-y:auto;">
                    @forelse ($this->selectedQuestions as $index => $question)
                        <div wire:key="sel-{{ $question->id }}" style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--surface-alt);border-radius:8px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:rgba(4,124,84,0.12);color:var(--brand-success);border-radius:50%;font-size:11px;font-weight:700;flex-shrink:0;">{{ $index + 1 }}</span>
                            <div style="flex:1;min-width:0;font-size:12.5px;color:var(--text-body);">
                                {{ \Illuminate\Support\Str::limit(strip_tags($question->question), 80) }}
                            </div>
                            <div style="display:flex;gap:2px;flex-shrink:0;">
                                <button type="button" wire:click="moveQuestionUp({{ $index }})" style="border:none;background:transparent;color:var(--text-muted);cursor:pointer;padding:4px 6px;" title="Naik">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <button type="button" wire:click="moveQuestionDown({{ $index }})" style="border:none;background:transparent;color:var(--text-muted);cursor:pointer;padding:4px 6px;" title="Turun">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                                <button type="button" wire:click="removeQuestion('{{ $question->id }}')" style="border:none;background:transparent;color:#dc2626;cursor:pointer;padding:4px 6px;" title="Hapus dari kuis">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    @empty
                        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada soal dipilih.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('pelatihan.kuis') }}" wire:navigate class="btn-ghost" style="padding:10px 18px;font-size:12.5px;">Batal</a>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:10px 22px;font-size:12.5px;">
                <span wire:loading.remove wire:target="save"><i class="fa-solid fa-save"></i> Simpan Soal</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </div>
</div>
