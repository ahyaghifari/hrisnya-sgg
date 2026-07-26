@use('App\Enum\QuestionType')

<div>
    <x-page-header title="Tambah Bank Soal" subtitle="Buat banyak soal sekaligus" :back="route('pelatihan.bank-soal')" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- Kategori shared untuk semua soal di batch ini --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;">
            <div style="max-width:360px;">
                <label class="form-label">Kategori (berlaku untuk semua soal di bawah) *</label>
                <div wire:ignore>
                    <select wire:model="categoryId" data-tom-select data-placeholder="Pilih kategori" class="form-input" required>
                        <option value="">Pilih kategori</option>
                        @foreach ($this->categoryOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('categoryId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Daftar soal (repeater) --}}
        <div style="display:flex;flex-direction:column;gap:14px;">
            @foreach ($soalRows as $i => $row)
                <div wire:key="'soal-row-'.{{ $i }}" style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;border:1px solid var(--border-soft);">
                    {{-- Header row: nomor + tombol hapus --}}
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;display:flex;align-items:center;gap:8px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:rgba(4,124,84,0.12);color:var(--brand-success);border-radius:50%;font-size:11px;font-weight:700;">{{ $i + 1 }}</span>
                            Soal #{{ $i + 1 }}
                        </h3>
                        @if (count($soalRows) > 1)
                            <button type="button" wire:click="removeSoalRow({{ $i }})" wire:confirm="Hapus soal #{{ $i + 1 }} dari form?" style="border:none;background:transparent;color:#dc2626;cursor:pointer;padding:6px 8px;font-size:13px;font-weight:600;">
                                <i class="fa-solid fa-trash"></i> Hapus
                            </button>
                        @endif
                    </div>

                    <div style="display:flex;flex-direction:column;gap:12px;">
                        {{-- Tipe soal --}}
                        <div style="max-width:240px;">
                            <label class="form-label">Tipe Soal *</label>
                            <select wire:model.live="soalRows.{{ $i }}.type" class="form-input">
                                @foreach (QuestionType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                                @endforeach
                            </select>
                            @error("soalRows.{$i}.type") <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>

                        {{-- Pertanyaan (Trix rich editor) --}}
                        <div>
                            <label class="form-label">Pertanyaan *</label>
                            @php
                                $trixInputId = 'trix-create-' . $i . '-' . uniqid();
                            @endphp
                            {{-- wire:ignore WAJIB — Trix generate toolbar sendiri yang tidak boleh
                                di-morph Livewire (morph.updated nembak terus dan menghancurkan toolbar). --}}
                            <div wire:ignore>
                                <input type="hidden" id="{{ $trixInputId }}" wire:model="soalRows.{{ $i }}.question" value="{{ $row['question'] }}">
                                <trix-editor input="{{ $trixInputId }}" style="min-height:100px;"></trix-editor>
                            </div>
                            @error("soalRows.{$i}.question") <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>

                        {{-- Opsi (MultipleChoice) atau Skor (Essay) --}}
                        @if ($row['type'] === QuestionType::MultipleChoice->value)
                            <div>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                    <label class="form-label" style="margin:0;">Opsi Jawaban *</label>
                                    @if (count($row['options']) < 10)
                                        <button type="button" wire:click="addOption({{ $i }})" class="btn-ghost" style="padding:4px 10px;font-size:11.5px;">
                                            <i class="fa-solid fa-plus"></i> Tambah Opsi
                                        </button>
                                    @endif
                                </div>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    @foreach ($row['options'] as $j => $opt)
                                        <div wire:key="'soal-'.{{ $i }}.'-opt-'.{{ $j }}" style="display:flex;align-items:center;gap:10px;">
                                            <textarea wire:model="soalRows.{{ $i }}.options.{{ $j }}.text" class="form-input" rows="1" placeholder="Teks opsi..." style="flex:1;min-height:38px;resize:vertical;"></textarea>
                                            {{-- Radio "Benar" di kanan input — native radio (di-group per row via `name`)
                                                supaya browser sendiri yang menjamin cuma 1 opsi ter-check per soal. --}}
                                            <label class="custom-checkbox" style="--accent:var(--brand-success);flex-shrink:0;" title="Tandai sebagai jawaban benar">
                                                <input type="radio" name="correct-{{ $i }}" @checked(! empty($opt['is_correct'])) wire:click="setCorrectOption({{ $i }}, {{ $j }})">
                                                <span class="box"><i class="fa-solid fa-check"></i></span>
                                            </label>
                                            <span style="font-size:11px;color:var(--text-muted);flex-shrink:0;">Benar</span>
                                            @if (count($row['options']) > 2)
                                                <button type="button" wire:click="removeOption({{ $i }}, {{ $j }})" style="border:none;background:transparent;color:#dc2626;cursor:pointer;padding:6px 8px;font-size:14px;flex-shrink:0;" title="Hapus opsi">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            @endif
                                        </div>
                                        @error("soalRows.{$i}.options.{$j}.text") <p style="font-size:11px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                                    @endforeach
                                </div>
                                @error("soalRows.{$i}.options") <p style="font-size:11px;color:#dc2626;margin-top:6px;">{{ $message }}</p> @enderror
                                <p style="font-size:11px;color:var(--text-faint);margin-top:6px;">Centang opsi yang merupakan jawaban benar. Minimal 2 opsi, maksimal 10.</p>
                            </div>
                        @else
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:400px;">
                                <div>
                                    <label class="form-label">Skor Minimal</label>
                                    <input type="number" wire:model="soalRows.{{ $i }}.min_score" class="form-input" min="0" max="100" placeholder="0">
                                    @error("soalRows.{$i}.min_score") <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Skor Maksimal *</label>
                                    <input type="number" wire:model="soalRows.{{ $i }}.max_score" class="form-input" min="1" max="100" placeholder="100">
                                    @error("soalRows.{$i}.max_score") <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tombol tambah soal (di tengah) --}}
        <div style="display:flex;justify-content:center;">
            <button type="button" wire:click="addSoalRow" class="btn-primary" style="padding:10px 22px;font-size:12.5px;">
                <i class="fa-solid fa-plus"></i> Tambah Soal
            </button>
        </div>

        {{-- Tombol batal + simpan --}}
        <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px;">
            <div style="display:flex;gap:8px;">
                <a href="{{ route('pelatihan.bank-soal') }}" wire:navigate class="btn-ghost" style="padding:10px 18px;font-size:12.5px;">
                    Batal
                </a>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:10px 22px;font-size:12.5px;">
                    <span wire:loading.remove wire:target="save"><i class="fa-solid fa-save"></i> Simpan Semua ({{ count($soalRows) }} soal)</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>
</div>
