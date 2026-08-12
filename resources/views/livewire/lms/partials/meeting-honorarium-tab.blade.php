{{-- Tab "Penilaian dan Gaji Pemateri". Pola picker sama seperti tab Pertemuan: course 1 hari
     (isSingleDayCourse) langsung tampil form (sudah di-preload via selectHonorMeeting() di mount()),
     course multi-pertemuan pilih dulu pertemuan mana (beda pertemuan bisa beda pemateri & beda
     hasil hitungan). Jalur form tergantung LmsCategories::training_category->usesScoredHonor():
     scored (Dokter Spesialis/Eksternal) = pilih kategori per variabel lms_honorarium_variables,
     fixed (Umum/Sertifikasi Dokter Umum/tanpa kategori) = input nominal langsung. --}}
<div style="display:flex;flex-direction:column;gap:14px;">
    @if (! $this->isSingleDayCourse)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:14px 18px;">
            <label class="form-label" style="margin-bottom:8px;display:block;">Pilih Pertemuan</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @forelse ($this->meetings as $meeting)
                    <button type="button" wire:click="selectHonorMeeting('{{ $meeting->id }}')"
                        class="badge" style="cursor:pointer;border:1px solid {{ $selectedHonorMeetingId === $meeting->id ? 'var(--brand-success)' : 'var(--border-soft)' }};background:{{ $selectedHonorMeetingId === $meeting->id ? 'rgba(4,124,84,0.12)' : 'var(--surface-muted)' }};color:{{ $selectedHonorMeetingId === $meeting->id ? 'var(--brand-success)' : 'var(--text-muted)' }};">
                        {{ $meeting->title }}
                        @if ($meeting->trainer?->name) &middot; {{ $meeting->trainer->name }} @endif
                    </button>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada pertemuan.</p>
                @endforelse
            </div>
        </div>
    @endif

    @php $meeting = $this->selectedHonorMeeting; @endphp

    @if (! $meeting)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-faint);">
            Pilih pertemuan dulu untuk mengisi penilaian dan gaji pemateri.
        </div>
    @elseif (! $meeting->trainer_id)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-faint);">
            Pertemuan ini belum punya pemateri — pilih pemateri dulu di tab Pertemuan.
        </div>
    @else
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <div>
                    <p style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ $meeting->trainer->name }}</p>
                    <p style="font-size:11.5px;color:var(--text-faint);margin:2px 0 0;">{{ $meeting->title }}</p>
                </div>
                @if ($this->existingHonorResult)
                    <span class="badge" style="background:rgba(4,124,84,0.12);color:var(--brand-success);">
                        Tersimpan: Rp{{ number_format($this->existingHonorResult->honor_amount, 0, ',', '.') }}
                    </span>
                @endif
            </div>

            @if ($this->usesScoredHonor)
                {{-- Jalur scored: 1 kategori per variabel (radio-like, sama pola dengan Bank Soal). --}}
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 12px;">
                    Kategori pelatihan "{{ $this->honorContext->label() }}" — honor dihitung dari jumlah skor kategori yang dipilih di tiap variabel.
                </p>

                <div style="display:flex;flex-direction:column;gap:14px;">
                    @forelse ($this->honorVariables as $variable)
                        <div wire:key="'honor-var-'.{{ $variable->id }}">
                            <label class="form-label">{{ $variable->name }}</label>
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                @foreach ($variable->categories as $category)
                                    <label style="display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--text-body);cursor:pointer;">
                                        <span class="custom-radio">
                                            <input type="radio" name="honor-var-{{ $variable->id }}"
                                                wire:model.live="honorCategorySelections.{{ $variable->id }}"
                                                value="{{ $category->id }}">
                                            <span class="dot"></span>
                                        </span>
                                        {{ $category->name }}
                                        <span style="color:var(--text-faint);">(skor {{ $category->score }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">
                            Belum ada variabel honorarium untuk konteks "{{ $this->honorContext->label() }}" — atur dulu di halaman Master Honorarium.
                        </p>
                    @endforelse
                </div>
                @error('honorCategorySelections') <p style="color:var(--danger-text);font-size:12px;margin-top:8px;">{{ $message }}</p> @enderror

                @if ($this->honorVariables->isNotEmpty())
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border-soft);flex-wrap:wrap;">
                        <div>
                            <p style="font-size:11px;color:var(--text-faint);margin:0;">Total Skor &middot; Gaji Pemateri</p>
                            <p style="font-size:14.5px;font-weight:700;color:var(--text-heading);margin:2px 0 0;">
                                {{ $this->honorPreviewTotalScore }} &middot; Rp{{ number_format($this->honorPreviewAmount, 0, ',', '.') }}
                            </p>
                        </div>
                        @if ($this->canEditDetail)
                            <button type="button" wire:click="saveHonorScored" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                                <i class="fa-solid fa-save"></i> Simpan Penilaian
                            </button>
                        @endif
                    </div>
                @endif
            @else
                {{-- Jalur fixed: kategori pelatihan tidak dihitung skor, admin input nominal langsung. --}}
                <div style="max-width:280px;">
                    <label class="form-label">Gaji Pemateri (Rp)</label>
                    <input type="number" wire:model="honorFixedAmount" class="form-input" min="0" placeholder="0">
                    @error('honorFixedAmount') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                @if ($this->canEditDetail)
                    <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                        <button type="button" wire:click="saveHonorFixed" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                            <i class="fa-solid fa-save"></i> Simpan Gaji
                        </button>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
