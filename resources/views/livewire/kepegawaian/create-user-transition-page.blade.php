<div>
<x-page-header title="Ajukan Perubahan Status Karyawan" subtitle="Promosi, Demosi, Mutasi, PHK, Perubahan Kelas, Pengangkatan Orientasi, atau Perpanjangan Kontrak" :back="route('kepegawaian.mutasi-karyawan')" />

<form wire:submit="submit">
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-id-card" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Informasi Umum</span>
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

            {{-- wire:ignore: Tom Select nyuntik DOM sendiri di luar render Blade - tanpa ini,
            morph Livewire yang dipicu wire:model.live="user_id" (submit apapun di halaman ini)
            bakal hapus DOM suntikan Tom Select, kelihatan kayak field-nya hilang. wire:key
            include companyId+transition_type supaya kalau salah satunya ganti (opsi $this->employees
            juga berubah, lihat getEmployeesProperty()), div ini di-generate ulang dari nol dan
            Tom Select ter-inisialisasi ulang dengan opsi yang benar. --}}
            <div>
                <label class="form-label">Karyawan <span style="color:var(--danger-text);">*</span></label>
                <div wire:key="user-id-wrap-{{ $companyId }}-{{ $transition_type }}" wire:ignore>
                    <select data-tom-select data-placeholder="Pilih karyawan" wire:model.live="user_id" class="form-input" @disabled(! $companyId)>
                        <option value="">Pilih karyawan</option>
                        @foreach ($this->employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->nip }})</option>
                        @endforeach
                    </select>
                </div>
                {{-- @error di luar wire:ignore supaya pesan validasi tetap ter-update oleh morph Livewire. --}}
                @error('user_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Jenis Perubahan <span style="color:var(--danger-text);">*</span></label>
                <select wire:model.live="transition_type" class="form-input">
                    <option value="">Pilih jenis perubahan</option>
                    @foreach (\App\Enum\UserTransitionTypeEnum::assignable() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('transition_type') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    @if (in_array($transition_type, ['promotion', 'demotion']))
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-briefcase" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Perubahan Jabatan</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Jabatan Baru <span style="color:var(--danger-text);">*</span></label>
                    <select wire:model.live="transitionable_id" class="form-input" @disabled(! $user_id)>
                        <option value="">Pilih jabatan baru</option>
                        @foreach ($this->jobPositions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('transitionable_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Tanggal Efektif <span style="color:var(--danger-text);">*</span></label>
                    <input type="date" wire:model="effective_date" class="form-input">
                    @error('effective_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Role Baru</label>
                    <select wire:model="role_id" class="form-input" @disabled(! $user_id)>
                        <option value="">Tidak diubah</option>
                        @foreach ($this->roles as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Otomatis disarankan dari Role Default jabatan yang dipilih.</p>
                </div>
            </div>
        </div>
    @endif

    @if ($transition_type === 'demotion')
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-triangle-exclamation" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Alasan Penilaian</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Alasan <span style="color:var(--danger-text);">*</span></label>
                    <select wire:model.live="reason_option" class="form-input">
                        <option value="">Pilih alasan</option>
                        <option value="poor_performance">Kinerja tidak memenuhi standar</option>
                        <option value="violation">Pelanggaran kebijakan atau etika kerja</option>
                        <option value="restructuring">Restrukturisasi organisasi</option>
                        <option value="personal_request">Permintaan pribadi</option>
                        <option value="other">Lainnya</option>
                    </select>
                    @error('reason_option') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                @if ($reason_option === 'other')
                    <div>
                        <label class="form-label">Alasan Lainnya</label>
                        <textarea wire:model="reason" class="form-input" rows="2" placeholder="Tuliskan alasan lainnya..."></textarea>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if (in_array($transition_type, ['promotion', 'demotion']))
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-chart-bar" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Evaluasi Kinerja</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Pencapaian Target Kerja</label>
                    <select wire:model="target_achievement" class="form-input">
                        <option value="">-</option>
                        <option value="Memenuhi target sepenuhnya">Memenuhi target sepenuhnya</option>
                        <option value="Sebagian besar target terpenuhi">Sebagian besar target terpenuhi</option>
                        <option value="Tidak memenuhi target">Tidak memenuhi target</option>
                    </select>
                    <textarea wire:model="target_comment" class="form-input" rows="2" placeholder="Komentar" style="margin-top:8px;"></textarea>
                </div>
                <div>
                    <label class="form-label">Kualitas Pekerjaan</label>
                    <select wire:model="work_quality" class="form-input">
                        <option value="">-</option>
                        <option value="Sangat Baik">Sangat Baik</option>
                        <option value="Cukup Baik">Cukup Baik</option>
                        <option value="Tidak Memadai">Tidak Memadai</option>
                    </select>
                    <textarea wire:model="work_quality_comment" class="form-input" rows="2" placeholder="Komentar" style="margin-top:8px;"></textarea>
                </div>
                <div>
                    <label class="form-label">Produktivitas</label>
                    <select wire:model="productivity_level" class="form-input">
                        <option value="">-</option>
                        <option value="Tinggi">Tinggi</option>
                        <option value="Sedang">Sedang</option>
                        <option value="Rendah">Rendah</option>
                    </select>
                    <textarea wire:model="productivity_comment" class="form-input" rows="2" placeholder="Komentar" style="margin-top:8px;"></textarea>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-user-group" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Analisis Perilaku</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Kepatuhan Tanggung Jawab</label>
                    <select wire:model="responsibility_adherence" class="form-input">
                        <option value="">-</option>
                        <option value="Mematuhi semua tugas">Mematuhi semua tugas</option>
                        <option value="Pelanggaran kecil">Pelanggaran kecil</option>
                        <option value="Pelanggaran besar">Pelanggaran besar</option>
                    </select>
                    <textarea wire:model="responsibility_comment" class="form-input" rows="2" placeholder="Komentar" style="margin-top:8px;"></textarea>
                </div>
                <div>
                    <label class="form-label">Etika & Sikap Kerja</label>
                    <select wire:model="work_ethic" class="form-input">
                        <option value="">-</option>
                        <option value="Sangat Baik">Sangat Baik</option>
                        <option value="Cukup Baik">Cukup Baik</option>
                        <option value="Bermasalah">Bermasalah</option>
                    </select>
                    <textarea wire:model="work_ethic_comment" class="form-input" rows="2" placeholder="Komentar" style="margin-top:8px;"></textarea>
                </div>
                <div>
                    <label class="form-label">Hubungan Dengan Rekan Kerja</label>
                    <select wire:model="teamwork" class="form-input">
                        <option value="">-</option>
                        <option value="Harmonis">Harmonis</option>
                        <option value="Cukup Baik">Cukup Baik</option>
                        <option value="Terdapat konflik">Terdapat konflik</option>
                    </select>
                    <textarea wire:model="teamwork_comment" class="form-input" rows="2" placeholder="Komentar" style="margin-top:8px;"></textarea>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-lightbulb" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Pertimbangan Tambahan</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Faktor Eksternal (Restrukturisasi, dll)</label>
                    <textarea wire:model="external_factors" class="form-input" rows="2"></textarea>
                </div>
                <div>
                    <label class="form-label">Permintaan Karyawan (jika ada)</label>
                    <textarea wire:model="employee_request" class="form-input" rows="2"></textarea>
                </div>
                <div>
                    <label class="form-label">Komentar Lainnya</label>
                    <textarea wire:model="other_comments" class="form-input" rows="2"></textarea>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-comments" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Komentar Manager/Kepegawaian</span>
            </div>
            <textarea wire:model="manager_comment" class="form-input" rows="3"></textarea>
        </div>
    @endif

    @if ($transition_type === 'kelas_change')
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-arrow-trend-up" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Perubahan Kelas</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Kelas Saat Ini</label>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">{{ $this->currentKelas ?? '-' }}</p>
                </div>
                <div>
                    <label class="form-label">Kelas Baru <span style="color:var(--danger-text);">*</span></label>
                    <select wire:model.live="transitionable_id" class="form-input" @disabled(! $user_id)>
                        <option value="">Pilih kelas baru</option>
                        @foreach ($this->kelasOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('transitionable_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Tanggal Efektif <span style="color:var(--danger-text);">*</span></label>
                    <input type="date" wire:model="effective_date" class="form-input">
                    @error('effective_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Alasan</label>
                    <textarea wire:model="reason" class="form-input" rows="2" placeholder="Alasan perubahan kelas (opsional)"></textarea>
                </div>
            </div>
        </div>
    @endif

    @if ($transition_type === 'employment_status_change')
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-id-card" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Pengangkatan Orientasi</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Status Saat Ini</label>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">{{ $this->currentEmployeeType ?? '-' }}</p>
                </div>
                <div>
                    <label class="form-label">Status Baru <span style="color:var(--danger-text);">*</span></label>
                    <select wire:model.live="transitionable_id" class="form-input" @disabled(! $user_id)>
                        <option value="">Pilih status baru</option>
                        @foreach ($this->employeeTypeOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('transitionable_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kelas otomatis mengikuti kelas minimal dari Pangkat karyawan, tidak perlu dipilih manual.</p>
                </div>
                <div>
                    <label class="form-label">Tanggal Efektif <span style="color:var(--danger-text);">*</span></label>
                    <input type="date" wire:model.live="effective_date" class="form-input">
                    @error('effective_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kalau status barunya Kontrak, tanggal kontrak berakhir otomatis dihitung dari tanggal ini + durasi perpanjangan (Pengaturan Kepegawaian) - tidak perlu diisi manual.</p>
                    @if ($this->contractEndPreview)
                        <p style="font-size:12px;font-weight:600;color:var(--brand);margin-top:4px;">{{ $this->contractEndPreview }}</p>
                    @endif
                </div>
                <div>
                    <label class="form-label">Alasan</label>
                    <textarea wire:model="reason" class="form-input" rows="2" placeholder="Alasan perubahan status (opsional)"></textarea>
                </div>
            </div>
        </div>
    @endif

    @if ($transition_type === 'contract_extension')
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-calendar-days" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Perpanjangan Kontrak</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="form-label">Kontrak Sebelumnya Sampai</label>
                    <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0;">{{ $this->currentContractEndDate ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Tanggal kontrak baru otomatis dihitung dari tanggal ini + durasi perpanjangan (Pengaturan Kepegawaian) begitu disetujui - tidak perlu diisi manual.</p>
                </div>
                <div>
                    <label class="form-label">Alasan</label>
                    <textarea wire:model="reason" class="form-input" rows="2" placeholder="Alasan perpanjangan (opsional)"></textarea>
                </div>
            </div>
        </div>
    @endif

    @if ($transition_type === 'mutation')
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-building" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Mutasi Perusahaan</span>
            </div>
            <div>
                <label class="form-label">Department Baru</label>
                <select wire:model="transitionable_id" class="form-input" @disabled(! $user_id)>
                    <option value="">Pilih department baru</option>
                    @foreach ($this->departments as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    @if ($transition_type === 'termination')
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-triangle-exclamation" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Alasan</span>
            </div>
            <textarea wire:model="reason" class="form-input" rows="3" placeholder="Tuliskan alasan PHK..."></textarea>
            @error('reason') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    @endif

    @if ($transition_type)
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-paperclip" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Lampiran</span>
            </div>
            <input type="file" wire:model="attachments" multiple class="form-input">
            @error('attachments.*') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            <div wire:loading wire:target="attachments" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
        </div>
    @endif

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan</button>
    </div>
</form>
</div>
