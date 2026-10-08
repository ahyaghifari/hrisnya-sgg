<div>
    <x-page-header title="Kuis" subtitle="Daftar kuis untuk pelatihan" />

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
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari judul kuis..." style="padding-left:38px;">
                    </div>
                </div>
            </div>

            @if ($this->canCreate)
                <a href="{{ route('pelatihan.kuis.create') }}" wire:navigate class="btn-primary" style="padding:9px 18px;font-size:12.5px;white-space:nowrap;">
                    <i class="fa-solid fa-plus"></i> Buat Kuis
                </a>
            @endif
        </div>

        @php $quizzes = $this->quizzes; @endphp

        {{-- MOBILE: card list --}}
        <div class="md:hidden">
            @if ($quizzes->isEmpty())
                <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada kuis yang cocok.</p>
                </div>
            @else
                <div class="pwa-list">
                    @foreach ($quizzes as $quiz)
                        <div wire:key="'quiz-m-'.{{ $quiz->id }}" class="pwa-list-row" style="cursor:pointer;" wire:click="openViewModal('{{ $quiz->id }}')">
                            <div class="pwa-tile-icon teal"><i class="fa-solid fa-list-check"></i></div>
                            <div style="flex:1;min-width:0;">
                                <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $quiz->title }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                    @if ($quiz->category?->name) {{ $quiz->category->name }} &middot; @endif
                                    {{ $quiz->questions_count }} soal &middot; Total skor {{ $quiz->total_score }}
                                </p>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="color:var(--text-faint);font-size:12px;"></i>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top:16px;">
                    {{ $quizzes->links('livewire.partials.pagination') }}
                </div>
            @endif
        </div>

        {{-- DESKTOP: tabel --}}
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $quizzes->isEmpty() ? 'display:none;' : '' }}">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Judul Kuis</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Kategori</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Soal</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Total Skor</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quizzes as $quiz)
                            <tr wire:key="'quiz-d-'.{{ $quiz->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;font-weight:600;max-width:420px;">{{ $quiz->title }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $quiz->category?->name ?? '-' }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $quiz->questions_count }} soal</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $quiz->total_score }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;">
                                        <button type="button" wire:click="openViewModal('{{ $quiz->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-eye"></i> Lihat
                                        </button>
                                        @if ($this->canUpdate)
                                            <a href="{{ route('pelatihan.kuis.edit', $quiz) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </a>
                                            <a href="{{ route('pelatihan.kuis.soal', $quiz) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-list-check"></i> Kelola Soal
                                            </a>
                                        @endif
                                        @if ($this->canDelete && $quiz->meeting_usages_count === 0)
                                            <button type="button" wire:click="removeQuiz('{{ $quiz->id }}')" wire:confirm="Hapus kuis ini? Tindakan tidak bisa dibatalkan." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
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

            @if ($quizzes->isEmpty())
                <div style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada kuis yang cocok.</p>
                </div>
            @endif

            <div style="margin-top:16px;">
                {{ $quizzes->links('livewire.partials.pagination') }}
            </div>
        </div>
    </div>

    {{-- Modal View (read-only) --}}
    @if ($viewingId && $this->viewingQuiz)
        @php $v = $this->viewingQuiz; @endphp
        <div class="modal-overlay" wire:click="closeViewModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">{{ $v->title }}</h3>
                    <button type="button" wire:click="closeViewModal" style="border:none;background:transparent;color:var(--text-muted);cursor:pointer;font-size:18px;padding:0 4px;">&times;</button>
                </div>

                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
                    @if ($v->category?->name)
                        <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);">{{ $v->category->name }}</span>
                    @endif
                    <span class="badge" style="background:rgba(4,124,84,0.12);color:var(--brand-success);">Total skor {{ $v->total_score }}</span>
                </div>

                <p class="form-label" style="margin-bottom:8px;">Daftar Soal ({{ $v->questions->count() }})</p>
                <div style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;">
                    @forelse ($v->questions as $i => $question)
                        <div style="padding:10px 12px;background:var(--surface-alt);border-radius:8px;">
                            <p style="font-size:12px;color:var(--text-faint);margin:0 0 4px;">Soal #{{ $i + 1 }}</p>
                            <div style="font-size:13px;color:var(--text-body);">{!! \Illuminate\Support\Str::limit(strip_tags($question->question), 200) !!}</div>
                        </div>
                    @empty
                        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada soal di kuis ini.</p>
                    @endforelse
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeViewModal" class="btn-ghost">Tutup</button>
                    @if ($this->canUpdate)
                        <a href="{{ route('pelatihan.kuis.soal', $v) }}" wire:navigate class="btn-primary">
                            <i class="fa-solid fa-list-check"></i> Kelola Soal
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
