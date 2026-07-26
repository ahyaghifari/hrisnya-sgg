@use('App\Enum\QuestionType')

<div>
    <x-page-header title="Bank Soal" subtitle="Daftar soal pilihan ganda & esai" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        {{-- Filter bar: kategori + search + tombol tambah --}}
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between;">
            <div style="display:flex;flex-wrap:wrap;gap:12px;flex:1;min-width:0;">
                <div style="min-width:200px;">
                    <label class="form-label">Kategori</label>
                    <div wire:ignore>
                        <select wire:model.live="categoryId" data-tom-select data-placeholder="Semua kategori" class="form-input">
                            <option value="">Semua kategori</option>
                            @foreach ($this->categoryOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="min-width:220px;flex:1;">
                    <label class="form-label">Cari</label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari teks soal..." style="padding-left:38px;">
                    </div>
                </div>
            </div>

            @if ($this->canCreate)
                <a href="{{ route('pelatihan.bank-soal.create') }}" wire:navigate class="btn-primary" style="padding:9px 18px;font-size:12.5px;white-space:nowrap;">
                    <i class="fa-solid fa-plus"></i> Tambah Soal
                </a>
            @endif
        </div>

        @php $soal = $this->soal; @endphp

        {{-- MOBILE: card list --}}
        <div class="md:hidden">
            @if ($soal->isEmpty())
                <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada soal yang cocok.</p>
                </div>
            @else
                <div class="pwa-list">
                    @foreach ($soal as $item)
                        @php
                            $plainQuestion = trim(strip_tags($item->question));
                            $preview = \Illuminate\Support\Str::limit($plainQuestion, 100);
                            $typeLabel = QuestionType::tryFrom($item->type)?->getLabel() ?? ucfirst($item->type);
                            $optCount = $item->options->count();
                        @endphp
                        <div wire:key="'soal-m-'.{{ $item->id }}" class="pwa-list-row" style="cursor:pointer;" wire:click="openViewModal('{{ $item->id }}')">
                            <div class="pwa-tile-icon teal"><i class="fa-solid fa-clipboard-list"></i></div>
                            <div style="flex:1;min-width:0;">
                                <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $preview ?: '(Soal kosong)' }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                    @if ($item->category?->name) {{ $item->category->name }} &middot; @endif
                                    Digunakan di {{ $item->quizzes_count }} kuis
                                </p>
                                <div style="margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;">
                                    <span class="badge" style="background:rgba(4,124,84,0.12);color:var(--brand-success);">{{ $typeLabel }}</span>
                                    @if ($item->type === QuestionType::MultipleChoice->value)
                                        <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $optCount }} opsi</span>
                                    @else
                                        <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">Skor {{ $item->min_score }}-{{ $item->max_score }}</span>
                                    @endif
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="color:var(--text-faint);font-size:12px;"></i>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top:16px;">
                    {{ $soal->links('livewire.partials.pagination') }}
                </div>
            @endif
        </div>

        {{-- DESKTOP: tabel --}}
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $soal->isEmpty() ? 'display:none;' : '' }}">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Soal</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tipe</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Kategori</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Opsi/Skor</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Digunakan</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($soal as $item)
                            @php
                                $plainQuestion = trim(strip_tags($item->question));
                                $preview = \Illuminate\Support\Str::limit($plainQuestion, 150);
                                $typeLabel = QuestionType::tryFrom($item->type)?->getLabel() ?? ucfirst($item->type);
                                $optCount = $item->options->count();
                            @endphp
                            <tr wire:key="'soal-d-'.{{ $item->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;font-weight:600;max-width:420px;">{{ $preview ?: '(Soal kosong)' }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">
                                    <span class="badge" style="background:rgba(4,124,84,0.12);color:var(--brand-success);">{{ $typeLabel }}</span>
                                </td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $item->category?->name ?? '-' }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @if ($item->type === QuestionType::MultipleChoice->value)
                                        {{ $optCount }} opsi
                                    @else
                                        {{ $item->min_score }}-{{ $item->max_score }}
                                    @endif
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;color:var(--text-muted);">
                                    {{ $item->quizzes_count }} kuis
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;">
                                        <button type="button" wire:click="openViewModal('{{ $item->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-eye"></i> Lihat
                                        </button>
                                        @if ($this->canUpdate)
                                            <button type="button" wire:click="openEditModal('{{ $item->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </button>
                                        @endif
                                        @if ($this->canDelete && $item->quizzes_count === 0)
                                            <button type="button" wire:click="removeSoal('{{ $item->id }}')" wire:confirm="Hapus soal ini? Tindakan tidak bisa dibatalkan." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($soal->isEmpty())
                <div style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada soal yang cocok.</p>
                </div>
            @endif

            <div style="margin-top:16px;">
                {{ $soal->links('livewire.partials.pagination') }}
            </div>
        </div>
    </div>

    {{-- Modal View (read-only) --}}
    @if ($viewingId && $this->viewingSoal)
        @php
            $v = $this->viewingSoal;
            $vTypeLabel = QuestionType::tryFrom($v->type)?->getLabel() ?? ucfirst($v->type);
        @endphp
        <div class="modal-overlay" wire:click="closeViewModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Detail Soal</h3>
                    <button type="button" wire:click="closeViewModal" style="border:none;background:transparent;color:var(--text-muted);cursor:pointer;font-size:18px;padding:0 4px;">&times;</button>
                </div>

                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
                    <span class="badge" style="background:rgba(4,124,84,0.12);color:var(--brand-success);">{{ $vTypeLabel }}</span>
                    @if ($v->category?->name)
                        <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $v->category->name }}</span>
                    @endif
                </div>

                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 12px;">
                    Dibuat {{ $v->created_at?->translatedFormat('d M Y') }} &middot; Digunakan di {{ $v->quizzes_count ?? $v->quizzes->count() }} kuis
                </p>

                <div style="padding:12px;background:var(--surface-alt);border-radius:8px;margin-bottom:14px;">
                    <div style="font-size:13px;color:var(--text-body);line-height:1.6;">{!! $v->question !!}</div>
                </div>

                @if ($v->type === QuestionType::MultipleChoice->value)
                    <div style="margin-bottom:12px;">
                        <p class="form-label" style="margin-bottom:8px;">Opsi Jawaban</p>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            @foreach ($v->options as $opt)
                                <div style="display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border-radius:6px;background:{{ $opt->is_correct ? 'rgba(4,124,84,0.08)' : 'var(--surface-alt)' }};border:1px solid {{ $opt->is_correct ? 'rgba(4,124,84,0.3)' : 'var(--border-soft)' }};">
                                    @if ($opt->is_correct)
                                        <i class="fa-solid fa-check" style="color:var(--brand-success);font-size:12px;margin-top:3px;"></i>
                                    @else
                                        <i class="fa-solid fa-circle" style="color:var(--text-faint);font-size:8px;margin-top:6px;"></i>
                                    @endif
                                    <span style="font-size:12.5px;color:var(--text-body);flex:1;">{{ $opt->option_text }}</span>
                                </div>
                            @endforeach
                            @if ($v->options->isEmpty())
                                <p style="font-size:12px;color:var(--text-muted);margin:0;">Belum ada opsi jawaban.</p>
                            @endif
                        </div>
                    </div>
                @else
                    <div style="display:flex;gap:12px;margin-bottom:12px;">
                        <div style="flex:1;">
                            <p class="form-label" style="margin-bottom:4px;">Skor Minimal</p>
                            <p style="font-size:13px;color:var(--text-body);">{{ $v->min_score }}</p>
                        </div>
                        <div style="flex:1;">
                            <p class="form-label" style="margin-bottom:4px;">Skor Maksimal</p>
                            <p style="font-size:13px;color:var(--text-body);">{{ $v->max_score }}</p>
                        </div>
                    </div>
                @endif

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeViewModal" class="btn-ghost">Tutup</button>
                    @if ($this->canUpdate)
                        <button type="button" wire:click="openEditModal('{{ $v->id }}')" class="btn-primary">
                            <i class="fa-solid fa-pen"></i> Ubah
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Edit --}}
    @if ($showEditModal)
        <div class="modal-overlay" wire:click="closeEditModal">
            <div class="modal-box modal-box-xl" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Ubah Soal</h3>
                    <button type="button" wire:click="closeEditModal" style="border:none;background:transparent;color:var(--text-muted);cursor:pointer;font-size:18px;padding:0 4px;">&times;</button>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label class="form-label">Kategori *</label>
                            <div wire:ignore>
                                <select wire:model="formCategoryId" data-tom-select data-placeholder="Pilih kategori" class="form-input" required>
                                    <option value="">Pilih kategori</option>
                                    @foreach ($this->categoryOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('formCategoryId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Tipe Soal *</label>
                            <select wire:model.live="formType" class="form-input">
                                @foreach (QuestionType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                                @endforeach
                            </select>
                            @error('formType') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Pertanyaan *</label>
                        {{-- Trix editor: input attribute link ke <input type="hidden"> via id.
                            wire:model di hidden input terima value, dan app.js dispatch "input" event
                            saat trix-change nembak supaya Livewire pick up (lihat app.js wireTrixEditor).
                            Wrapper wire:ignore WAJIB — Trix generate toolbar sendiri yang tidak boleh
                            di-morph Livewire (morph.updated nembak terus dan menghancurkan toolbar). --}}
                        <div wire:ignore>
                            <input type="hidden" id="trix-edit-{{ $editingId }}" wire:model="formQuestion" value="{{ $formQuestion }}">
                            <trix-editor input="trix-edit-{{ $editingId }}" style="min-height:120px;"></trix-editor>
                        </div>
                        @error('formQuestion') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($formType === QuestionType::MultipleChoice->value)
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <label class="form-label" style="margin:0;">Opsi Jawaban *</label>
                                @if (count($formOptions) < 10)
                                    <button type="button" wire:click="addOption" class="btn-ghost" style="padding:4px 10px;font-size:11.5px;">
                                        <i class="fa-solid fa-plus"></i> Tambah Opsi
                                    </button>
                                @endif
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach ($formOptions as $i => $opt)
                                    <div wire:key="'opt-edit-'.{{ $i }}" style="display:flex;align-items:center;gap:10px;">
                                        <textarea wire:model="formOptions.{{ $i }}.text" class="form-input" rows="1" placeholder="Teks opsi..." style="flex:1;min-height:38px;resize:vertical;"></textarea>
                                        {{-- Radio "Benar" di kanan input — native radio (di-group via `name`) supaya
                                            browser sendiri yang menjamin cuma 1 opsi ter-check. --}}
                                        <label class="custom-checkbox" style="--accent:var(--brand-success);flex-shrink:0;" title="Tandai sebagai jawaban benar">
                                            <input type="radio" name="correct-edit-{{ $editingId }}" @checked(! empty($opt['is_correct'])) wire:click="setCorrectOption({{ $i }})">
                                            <span class="box"><i class="fa-solid fa-check"></i></span>
                                        </label>
                                        <span style="font-size:11px;color:var(--text-muted);flex-shrink:0;">Benar</span>
                                        @if (count($formOptions) > 2)
                                            <button type="button" wire:click="removeOption({{ $i }})" style="border:none;background:transparent;color:#dc2626;cursor:pointer;padding:6px 8px;font-size:14px;flex-shrink:0;" title="Hapus opsi">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                    @error("formOptions.{$i}.text") <p style="font-size:11px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                                @endforeach
                            </div>
                            <p style="font-size:11px;color:var(--text-faint);margin-top:6px;">Centang opsi yang merupakan jawaban benar. Minimal 2 opsi.</p>
                        </div>
                    @else
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label class="form-label">Skor Minimal</label>
                                <input type="number" wire:model="formMinScore" class="form-input" min="0" max="100" placeholder="0">
                                @error('formMinScore') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Skor Maksimal *</label>
                                <input type="number" wire:model="formMaxScore" class="form-input" min="1" max="100" placeholder="100">
                                @error('formMaxScore') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeEditModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveEdit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveEdit">
                        <span wire:loading.remove wire:target="saveEdit"><i class="fa-solid fa-save"></i> Simpan</span>
                        <span wire:loading wire:target="saveEdit">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
