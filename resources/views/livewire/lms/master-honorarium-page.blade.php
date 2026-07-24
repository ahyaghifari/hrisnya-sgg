@use('App\Enum\LMS\TrainingCategoryEnum')
@php $activeColor = TrainingCategoryEnum::from($context)->color(); @endphp

<div>
    <x-page-header title="Honorarium" />

    <p style="font-size:12.5px;color:var(--text-muted);margin:6px 0 18px;max-width:70ch;">
        Variabel, kategori, dan range skor honorarium — khusus Dokter Spesialis & Biaya Pelatihan Eksternal
        (honor Pelatihan Umum/Sertifikasi/Dokter Umum sudah fixed, tidak dihitung di sini).
    </p>

    <style>
        .honorarium-layout { display:grid; grid-template-columns:1fr; gap:20px; align-items:start; }
        @media (min-width: 1024px) { .honorarium-layout { grid-template-columns:3fr 1fr; } }
        .btn-xs { padding:4px 10px !important; font-size:11px !important; border-radius:6px !important; }
    </style>

    {{-- Tab konteks — warna beda per konteks supaya tidak ketuker --}}
    <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;">
        @foreach ($this->scoredContexts as $case)
            @php $c = $case->color(); $isActive = $context === $case->value; @endphp
            <button
                type="button"
                wire:click="setContext('{{ $case->value }}')"
                style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:12.5px;font-weight:700;border-radius:10px;border:1.5px solid {{ $c['solid'] }};cursor:pointer;transition:.15s;
                    {{ $isActive ? "background:{$c['solid']};color:#fff;" : "background:{$c['soft']};color:{$c['text']};" }}"
            >
                <i class="fa-solid {{ $case->icon() }}"></i> {{ $case->label() }}
            </button>
        @endforeach
    </div>

    <div class="honorarium-layout">
        {{-- Variabel & Kategori (3/4) --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);border-left:4px solid {{ $activeColor['solid'] }};padding:18px;min-width:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                <div>
                    <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;">Variabel & Kategori</h3>
                    <p style="font-size:11px;color:var(--text-faint);margin:1px 0 0;">Tiap kategori punya skor acuan sendiri — total skornya dicocokkan ke tabel Honorarium di samping.</p>
                </div>
                @if ($this->canEditHonorarium)
                    <div style="display:flex;gap:6px;">
                        <button type="button" wire:click="openVariableModal" class="btn-xs" style="border:none;color:#fff;font-weight:600;cursor:pointer;background:{{ $activeColor['solid'] }};white-space:nowrap;">
                            <i class="fa-solid fa-plus"></i> Variabel
                        </button>
                        <button type="button" wire:click="openCategoryModal" class="btn-xs" style="border:1.5px solid {{ $activeColor['solid'] }};color:{{ $activeColor['text'] }};font-weight:600;cursor:pointer;background:{{ $activeColor['soft'] }};white-space:nowrap;">
                            <i class="fa-solid fa-plus"></i> Kategori
                        </button>
                    </div>
                @endif
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="background:{{ $activeColor['soft'] }};">
                            <th style="text-align:left;padding:8px 10px;white-space:nowrap;color:{{ $activeColor['text'] }};">Variabel</th>
                            <th style="text-align:left;padding:8px 10px;color:{{ $activeColor['text'] }};">Kategori</th>
                            <th style="text-align:center;padding:8px 10px;white-space:nowrap;color:{{ $activeColor['text'] }};">Skor Acuan</th>
                            <th style="text-align:center;padding:8px 10px;white-space:nowrap;color:{{ $activeColor['text'] }};">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->variables as $variable)
                            @forelse ($variable->categories as $category)
                                <tr wire:key="'category-'.{{ $category->id }}" style="border-bottom:1px solid var(--border-soft);">
                                    @if ($loop->first)
                                        <td rowspan="{{ $variable->categories->count() }}" style="padding:9px 10px;font-weight:600;white-space:nowrap;vertical-align:top;border-right:1px solid var(--border-soft);">
                                            {{ $variable->name }}
                                            @if ($variable->description)
                                                <div style="font-weight:400;font-size:11px;color:var(--text-faint);margin-top:2px;white-space:normal;">{{ $variable->description }}</div>
                                            @endif
                                            @if ($this->canEditHonorarium)
                                                <div style="margin-top:6px;display:flex;gap:4px;">
                                                    <button type="button" wire:click="openCategoryModal('{{ $variable->id }}')" class="btn-ghost btn-xs" title="Tambah kategori ke variabel ini">
                                                        <i class="fa-solid fa-plus"></i>
                                                    </button>
                                                    <button type="button" wire:click="deleteVariable('{{ $variable->id }}')" wire:confirm="Hapus variabel ini beserta kategorinya?" class="btn-ghost btn-xs" style="color:#dc2626;" title="Hapus variabel">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                    <td style="padding:7px 10px;">{{ $category->name }}</td>
                                    <td style="padding:7px 10px;text-align:center;font-weight:600;">{{ $category->score }}</td>
                                    <td style="padding:7px 10px;text-align:center;white-space:nowrap;">
                                        @if ($this->canEditHonorarium)
                                            <button type="button" wire:click="deleteCategory('{{ $category->id }}')" wire:confirm="Hapus kategori ini?" class="btn-ghost btn-xs" style="color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr wire:key="'variable-empty-'.{{ $variable->id }}" style="border-bottom:1px solid var(--border-soft);">
                                    <td style="padding:9px 10px;font-weight:600;white-space:nowrap;vertical-align:top;border-right:1px solid var(--border-soft);">
                                        {{ $variable->name }}
                                        @if ($variable->description)
                                            <div style="font-weight:400;font-size:11px;color:var(--text-faint);margin-top:2px;white-space:normal;">{{ $variable->description }}</div>
                                        @endif
                                        @if ($this->canEditHonorarium)
                                            <div style="margin-top:6px;display:flex;gap:4px;">
                                                <button type="button" wire:click="openCategoryModal('{{ $variable->id }}')" class="btn-ghost btn-xs" title="Tambah kategori ke variabel ini">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                <button type="button" wire:click="deleteVariable('{{ $variable->id }}')" wire:confirm="Hapus variabel ini?" class="btn-ghost btn-xs" style="color:#dc2626;" title="Hapus variabel">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                    <td colspan="3" style="padding:9px 10px;color:var(--text-faint);font-style:italic;">Belum ada kategori.</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="4" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada variabel untuk {{ TrainingCategoryEnum::from($context)->label() }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Range Skor -> Honorarium (1/4) --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);border-left:4px solid {{ $activeColor['solid'] }};padding:18px;min-width:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:14px;">
                <div>
                    <h3 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;">Honorarium</h3>
                    <p style="font-size:11px;color:var(--text-faint);margin:1px 0 0;">Total skor &rarr; nominal</p>
                </div>
                @if ($this->canEditHonorarium)
                    <button type="button" wire:click="openRangeModal" class="btn-xs" style="border:none;color:#fff;font-weight:600;cursor:pointer;background:{{ $activeColor['solid'] }};">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                @endif
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="background:{{ $activeColor['soft'] }};">
                            <th style="text-align:left;padding:7px 8px;white-space:nowrap;color:{{ $activeColor['text'] }};">Total Skor</th>
                            <th style="text-align:left;padding:7px 8px;white-space:nowrap;color:{{ $activeColor['text'] }};">Honorarium</th>
                            <th style="padding:7px 8px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->ranges as $range)
                            <tr wire:key="'range-'.{{ $range->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:7px 8px;font-weight:600;white-space:nowrap;">
                                    {{ $range->min_score == $range->max_score ? $range->min_score : $range->min_score.'–'.$range->max_score }}
                                </td>
                                <td style="padding:7px 8px;font-weight:600;white-space:nowrap;">Rp{{ number_format($range->honor_amount, 0, ',', '.') }}</td>
                                <td style="padding:7px 8px;text-align:center;white-space:nowrap;">
                                    @if ($this->canEditHonorarium)
                                        <button type="button" wire:click="deleteRange('{{ $range->id }}')" wire:confirm="Hapus range ini?" class="btn-ghost btn-xs" style="color:#dc2626;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="padding:18px 8px;text-align:center;color:var(--text-faint);font-size:11.5px;">Belum ada range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal Tambah Variabel --}}
    @if ($showVariableModal)
        <div class="modal-overlay" wire:click="closeVariableModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    Tambah Variabel — {{ TrainingCategoryEnum::from($context)->label() }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nama Variabel</label>
                        <input type="text" class="form-input" wire:model="newVariable.name" placeholder="mis. Lama Praktik">
                        @error('newVariable.name') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Deskripsi (opsional)</label>
                        <input type="text" class="form-input" wire:model="newVariable.description">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeVariableModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveVariable" style="padding:9px 18px;font-size:12.5px;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;background:{{ $activeColor['solid'] }};">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Tambah Kategori --}}
    @if ($showCategoryModal)
        <div class="modal-overlay" wire:click="closeCategoryModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    Tambah Kategori — {{ TrainingCategoryEnum::from($context)->label() }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Variabel</label>
                        <select class="form-input" wire:model="newCategory.variable_id">
                            <option value="">— Pilih Variabel —</option>
                            @foreach ($this->variables as $variable)
                                <option value="{{ $variable->id }}">{{ $variable->name }}</option>
                            @endforeach
                        </select>
                        @error('newCategory.variable_id') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" class="form-input" wire:model="newCategory.name" placeholder="mis. < 10 Tahun">
                        @error('newCategory.name') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Skor Acuan</label>
                        <input type="number" class="form-input" wire:model="newCategory.score">
                        @error('newCategory.score') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeCategoryModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveCategory" style="padding:9px 18px;font-size:12.5px;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;background:{{ $activeColor['solid'] }};">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Tambah Range --}}
    @if ($showRangeModal)
        <div class="modal-overlay" wire:click="closeRangeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    Tambah Range — {{ TrainingCategoryEnum::from($context)->label() }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="display:flex;gap:6px;">
                        <button type="button" wire:click="$set('rangeIsSingle', true)" class="btn-xs" style="flex:1;{{ $rangeIsSingle ? "border:none;color:#fff;background:{$activeColor['solid']};" : "border:1.5px solid {$activeColor['solid']};color:{$activeColor['text']};background:{$activeColor['soft']};" }}">
                            Skor Tunggal
                        </button>
                        <button type="button" wire:click="$set('rangeIsSingle', false)" class="btn-xs" style="flex:1;{{ ! $rangeIsSingle ? "border:none;color:#fff;background:{$activeColor['solid']};" : "border:1.5px solid {$activeColor['solid']};color:{$activeColor['text']};background:{$activeColor['soft']};" }}">
                            Rentang Skor
                        </button>
                    </div>

                    @if ($rangeIsSingle)
                        <div>
                            <label class="form-label">Skor</label>
                            <input type="number" class="form-input" wire:model="newRange.min_score" placeholder="mis. 5">
                            @error('newRange.min_score') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div style="display:flex;gap:10px;">
                            <div style="flex:1;">
                                <label class="form-label">Skor Min</label>
                                <input type="number" class="form-input" wire:model="newRange.min_score">
                                @error('newRange.min_score') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>

                            <div style="flex:1;">
                                <label class="form-label">Skor Max</label>
                                <input type="number" class="form-input" wire:model="newRange.max_score">
                                @error('newRange.max_score') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="form-label">Honorarium (Rp)</label>
                        <input type="number" class="form-input" wire:model="newRange.honor_amount">
                        @error('newRange.honor_amount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeRangeModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveRange" style="padding:9px 18px;font-size:12.5px;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;background:{{ $activeColor['solid'] }};">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
