<div>
    <x-page-header title="Pertanyaan Evaluasi" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;justify-content:flex-end;">
            @if ($this->canCreateQuestion)
                <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Pertanyaan
                </button>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;width:70px;">Urutan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Kelompok</th>
                        <th style="text-align:left;padding:10px 14px;">Pertanyaan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aktif</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Jawaban</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->questions as $index => $question)
                        <tr wire:key="'question-'.{{ $question->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;flex-direction:column;gap:2px;">
                                    <button type="button" wire:click="moveUp('{{ $question->id }}')" @if ($index === 0) disabled @endif class="btn-ghost" style="padding:2px 8px;font-size:10px;line-height:1;" title="Naikkan">
                                        <i class="fa-solid fa-chevron-up"></i>
                                    </button>
                                    <button type="button" wire:click="moveDown('{{ $question->id }}')" @if ($index === $this->questions->count() - 1) disabled @endif class="btn-ghost" style="padding:2px 8px;font-size:10px;line-height:1;" title="Turunkan">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                @if ($question->group_label)
                                    <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $question->group_label }}</span>
                                @else
                                    <span style="color:var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td style="padding:9px 14px;">{{ $question->question }}</td>
                            <td style="padding:9px 14px;text-align:center;">
                                @if ($question->is_active)
                                    <i class="fa-solid fa-circle-check" style="color:#16a34a;"></i>
                                @else
                                    <i class="fa-solid fa-circle-xmark" style="color:var(--text-faint);"></i>
                                @endif
                            </td>
                            <td style="padding:9px 14px;text-align:center;">{{ $question->answers_count }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    @can('update', $question)
                                        <button type="button" wire:click="openEditModal('{{ $question->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    @endcan
                                    @can('delete', $question)
                                        @if ($question->answers_count === 0)
                                            <button type="button" wire:click="removeQuestion('{{ $question->id }}')" wire:confirm="Hapus pertanyaan ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada pertanyaan evaluasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah/Ubah Pertanyaan --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Pertanyaan' : 'Tambah Pertanyaan' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Kelompok</label>
                        <input type="text" class="form-input" wire:model="formGroupLabel" placeholder="mis. Instruktur, Materi, Fasilitas">
                        @error('formGroupLabel') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Pertanyaan</label>
                        <textarea class="form-input" rows="3" wire:model="formQuestion" placeholder="mis. Instruktur menguasai materi yang disampaikan"></textarea>
                        @error('formQuestion') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="display:flex;align-items:center;gap:8px;">
                        <label class="custom-checkbox">
                            <input type="checkbox" wire:model="formIsActive">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span class="form-label" style="margin:0;">Aktif</span>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
