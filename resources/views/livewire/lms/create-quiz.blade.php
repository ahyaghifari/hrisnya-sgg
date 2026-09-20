@use('App\Enum\QuestionType')

<div>
    <x-page-header
        title="{{ $editingQuizId ? 'Ubah Kuis' : 'Buat Kuis' }}"
        subtitle="Pilih kategori, lalu pilih soal dari Bank Soal"
        :back="route('pelatihan.kuis')"
    />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- Kategori & Judul --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Kategori *</label>
                    <div wire:ignore>
                        <select wire:model.live="categoryId" data-tom-select data-placeholder="Pilih kategori" class="form-input" required>
                            <option value="">Pilih kategori</option>
                            @foreach ($this->categoryOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('categoryId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Judul Kuis *</label>
                    <input type="text" wire:model="title" class="form-input" placeholder="Judul kuis...">
                    @error('title') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @if (! $categoryId)
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-faint);">
                Pilih kategori dulu untuk mulai memilih soal.
            </div>
        @else
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
                {{-- Pilih dari Bank Soal --}}
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:8px;">
                        <label class="form-label" style="margin:0;">Pilih dari Bank Soal</label>
                        @if ($this->canCreateQuestion)
                            <button type="button" wire:click="openNewQuestionModal" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;white-space:nowrap;">
                                <i class="fa-solid fa-plus"></i> Buat Soal Baru
                            </button>
                        @endif
                    </div>

                    <div style="position:relative;margin-bottom:10px;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                        <input type="search" wire:model.live.debounce.300ms="questionSearch" class="form-input" placeholder="Cari soal..." style="padding-left:38px;">
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px;max-height:420px;overflow-y:auto;">
                        @forelse ($this->availableQuestions as $question)
                            <div wire:key="'avail-'.{{ $question->id }}" style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--surface-alt);border-radius:8px;">
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

                {{-- Repeater soal terpilih (bisa diurutkan) --}}
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
                    <label class="form-label" style="margin-bottom:10px;display:block;">Soal Terpilih ({{ count($selectedQuestionIds) }})</label>
                    @error('selectedQuestionIds') <p style="font-size:11px;color:#dc2626;margin-bottom:8px;">{{ $message }}</p> @enderror

                    <div style="display:flex;flex-direction:column;gap:8px;max-height:420px;overflow-y:auto;">
                        @forelse ($this->selectedQuestions as $index => $question)
                            <div wire:key="'sel-'.{{ $question->id }}" style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--surface-alt);border-radius:8px;">
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
        @endif

        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('pelatihan.kuis') }}" wire:navigate class="btn-ghost" style="padding:10px 18px;font-size:12.5px;">Batal</a>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:10px 22px;font-size:12.5px;">
                <span wire:loading.remove wire:target="save"><i class="fa-solid fa-save"></i> {{ $editingQuizId ? 'Simpan Perubahan' : 'Simpan Kuis' }}</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </div>

    {{-- Modal "+ Buat Soal Baru" — 1 form soal, persis 1 baris repeater CreateBankSoal --}}
    @if ($showNewQuestionModal)
        <div class="modal-overlay" wire:click="closeNewQuestionModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Buat Soal Baru</h3>
                    <button type="button" wire:click="closeNewQuestionModal" style="border:none;background:transparent;color:var(--text-muted);cursor:pointer;font-size:18px;padding:0 4px;">&times;</button>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="max-width:240px;">
                        <label class="form-label">Tipe Soal *</label>
                        <select wire:model.live="newType" class="form-input">
                            @foreach (QuestionType::creatable() as $type)
                                <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                            @endforeach
                        </select>
                        @error('newType') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Pertanyaan *</label>
                        @php $trixInputId = 'trix-quiz-new-' . uniqid(); @endphp
                        <div wire:ignore>
                            <input type="hidden" id="{{ $trixInputId }}" wire:model="newQuestion" value="{{ $newQuestion }}">
                            <trix-editor input="{{ $trixInputId }}" style="min-height:100px;"></trix-editor>
                        </div>
                        @error('newQuestion') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($newType === QuestionType::MultipleChoice->value)
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <label class="form-label" style="margin:0;">Opsi Jawaban *</label>
                                @if (count($newOptions) < 10)
                                    <button type="button" wire:click="addNewOption" class="btn-ghost" style="padding:4px 10px;font-size:11.5px;">
                                        <i class="fa-solid fa-plus"></i> Tambah Opsi
                                    </button>
                                @endif
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach ($newOptions as $j => $opt)
                                    <div wire:key="'new-opt-'.{{ $j }}" style="display:flex;align-items:center;gap:10px;">
                                        <textarea wire:model="newOptions.{{ $j }}.text" class="form-input" rows="1" placeholder="Teks opsi..." style="flex:1;min-height:38px;resize:vertical;"></textarea>
                                        <label class="custom-checkbox" style="--accent:var(--brand-success);flex-shrink:0;" title="Tandai sebagai jawaban benar">
                                            <input type="radio" name="new-correct" @checked(! empty($opt['is_correct'])) wire:click="setNewCorrectOption({{ $j }})">
                                            <span class="box"><i class="fa-solid fa-check"></i></span>
                                        </label>
                                        <span style="font-size:11px;color:var(--text-muted);flex-shrink:0;">Benar</span>
                                        @if (count($newOptions) > 2)
                                            <button type="button" wire:click="removeNewOption({{ $j }})" style="border:none;background:transparent;color:#dc2626;cursor:pointer;padding:6px 8px;font-size:14px;flex-shrink:0;" title="Hapus opsi">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        @endif
                                    </div>
                                    @error("newOptions.{$j}.text") <p style="font-size:11px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                                @endforeach
                            </div>
                            @error('newOptions') <p style="font-size:11px;color:#dc2626;margin-top:6px;">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label class="form-label">Skor Minimal</label>
                                <input type="number" wire:model="newMinScore" class="form-input" min="0" max="100" placeholder="0">
                            </div>
                            <div>
                                <label class="form-label">Skor Maksimal *</label>
                                <input type="number" wire:model="newMaxScore" class="form-input" min="1" max="100" placeholder="100">
                                @error('newMaxScore') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeNewQuestionModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveNewQuestion" class="btn-primary">
                        <i class="fa-solid fa-save"></i> Simpan & Tambahkan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
