{{-- Dipakai 2x: form "Tambah Pertemuan" & form "Detail Pertemuan" tiap accordion. Tipe
     Absensi sengaja tidak ada field-nya (dipaksa 'offline' di saveMeeting()). Tanggal Mulai/
     Selesai & Judul Pertemuan cuma muncul kalau course-nya lebih dari 1 hari
     (isSingleDayCourse) — course 1 hari cuma py 1 pertemuan, judulnya otomatis ikut judul
     pelatihan, langsung ke Pemateri (wajib dipilih). Jam Mulai/Selesai SELALU muncul, baik
     1 hari maupun banyak pertemuan. --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
    @if (! $this->isSingleDayCourse)
        <div style="grid-column:1 / -1;">
            <label class="form-label">Judul Pertemuan</label>
            <input type="text" wire:model="meetingTitle" class="form-input">
            @error('meetingTitle') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    @endif

    <div>
        <label class="form-label" style="display:flex;align-items:center;justify-content:space-between;">
            <span>Pemateri <span style="color:var(--danger-text);">*</span></span>
            @if ($this->canCreateTrainer)
                <button type="button" wire:click="openCreateTrainerModal" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:11px;padding:0;">
                    <i class="fa-solid fa-plus"></i> Tambah Pemateri
                </button>
            @endif
        </label>
        {{-- wire:key ikut jumlah opsi supaya elemen ini remount (Tom Select re-init dgn opsi
        terbaru) begitu pemateri baru ditambahkan lewat modal — wire:ignore normalnya bikin
        Livewire tidak menyentuh isi <select> lagi setelah render pertama. --}}
        <div wire:ignore wire:key="'trainer-select-'.{{ $this->trainerOptions->count() }}">
            {{-- wire:ignore = Livewire tidak lagi sinkron .value lewat wire:model setelah render
            pertama, jadi opsi terpilih HARUS lewat atribut `selected` di HTML-nya sendiri
            (dibaca Tom Select saat init), bukan mengandalkan binding JS. --}}
            <select wire:model="meetingTrainerId" data-tom-select data-placeholder="-- Pilih Pemateri --" class="form-input">
                <option value="">-- Pilih Pemateri --</option>
                @foreach ($this->trainerOptions as $id => $name)
                    <option value="{{ $id }}" @selected((string) $id === (string) $meetingTrainerId)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        @error('meetingTrainerId') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
    </div>

    @if ($showCreateTrainerModal)
        <div class="modal-overlay" wire:click="closeCreateTrainerModal" style="grid-column:1 / -1;">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Tambah Pemateri</h3>

                <div class="grid grid-cols-1 md:grid-cols-2" style="gap:12px;">
                    <div>
                        <label class="form-label">Nama *</label>
                        <input type="text" class="form-input" wire:model="trainerName">
                        @error('trainerName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Tipe *</label>
                        <select class="form-input" wire:model="trainerType">
                            <option value="external">External</option>
                            <option value="internal">Internal</option>
                        </select>
                        @error('trainerType') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="form-label">Karyawan</label>
                        <select data-tom-select data-placeholder="-- Isi kalau pemateri ini karyawan sendiri --" class="form-input" wire:model="trainerUserId">
                            <option value="">-- Isi kalau pemateri ini karyawan sendiri --</option>
                            @foreach ($this->employeeOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('trainerUserId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Keahlian</label>
                        <input type="text" class="form-input" wire:model="trainerExpertise">
                        @error('trainerExpertise') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Perusahaan Asal</label>
                        <input type="text" class="form-input" wire:model="trainerCompany">
                        @error('trainerCompany') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" wire:model="trainerEmail">
                        @error('trainerEmail') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Telepon</label>
                        <input type="text" class="form-input" wire:model="trainerPhone">
                        @error('trainerPhone') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="grid-column:span 2;">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-input" rows="2" wire:model="trainerAddress"></textarea>
                        @error('trainerAddress') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeCreateTrainerModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveTrainer" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    @if (! $this->isSingleDayCourse)
        <div>
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" wire:model="meetingStartDate" class="form-input">
            @error('meetingStartDate') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Tanggal Selesai</label>
            <input type="date" wire:model="meetingEndDate" class="form-input">
            @error('meetingEndDate') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    @endif

    <div>
        <label class="form-label">Jam Mulai <span style="color:var(--danger-text);">*</span></label>
        <input type="time" wire:model="meetingStartTime" class="form-input">
        @error('meetingStartTime') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Jam Selesai <span style="color:var(--danger-text);">*</span></label>
        <input type="time" wire:model="meetingEndTime" class="form-input">
        @error('meetingEndTime') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
    </div>

    <div style="grid-column:1 / -1;">
        <label class="form-label">Deskripsi / Materi (opsional)</label>
        <textarea wire:model="meetingDescription" class="form-input" rows="3"></textarea>
    </div>
</div>
