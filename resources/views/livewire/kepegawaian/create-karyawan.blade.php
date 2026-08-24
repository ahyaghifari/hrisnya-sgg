@php
    $isGlobal = \App\Helpers\PermissionHelper::hasGlobalRole(auth()->user());
    $isEdit = (bool) $record;
    $steps = [
        1 => ['label' => 'Akun & Peran', 'icon' => 'fa-user-lock', 'color' => 'navy'],
        2 => ['label' => 'Data Pribadi', 'icon' => 'fa-id-card', 'color' => 'green'],
        3 => ['label' => 'Data Pekerjaan', 'icon' => 'fa-briefcase', 'color' => 'teal'],
    ];
@endphp
<div>
<x-page-header :title="$isEdit ? 'Edit Karyawan' : 'Tambah Karyawan'" subtitle="Langkah {{ $step }} dari 3" :back="route('kepegawaian.karyawan')" />

<!-- Indikator step: pola sama .section-nav-btn yang dipakai halaman detail karyawan, cuma
     di sini juga jadi navigasi mundur (klik step yang sudah dilewati). -->
<div class="section-nav">
    @foreach ($steps as $n => $meta)
        <button
            type="button"
            @if ($isEdit || $n < $step) wire:click="goToStep({{ $n }})" @endif
            @if (! $isEdit && $n > $step) disabled @endif
            class="section-nav-btn {{ $meta['color'] }} {{ $n === $step ? 'is-active' : '' }}"
            style="{{ $isEdit || $n < $step ? 'cursor:pointer;' : ($n > $step ? 'opacity:.45;cursor:not-allowed;' : '') }}"
        >
            <i class="fa-solid {{ $meta['icon'] }}"></i>
            <span>{{ $n }}. {{ $meta['label'] }}</span>
        </button>
    @endforeach
</div>

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">

    {{-- STEP 1 — Akun & Peran --}}
    <div @if ($step !== 1) style="display:none;" @endif wire:key="step-1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
            <div style="grid-column:1 / -1;">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" wire:model="name" class="form-input" placeholder="Nama sesuai KTP">
                @error('name') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Email</label>
                <input type="email" wire:model="email" class="form-input" placeholder="nama@perusahaan.com">
                @error('email') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">NIP</label>
                <input type="text" wire:model="nip" class="form-input" placeholder="Kosongkan untuk digenerate otomatis">
                @error('nip') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">{{ $isEdit ? 'Ganti Kata Sandi' : 'Kata Sandi Awal' }}</label>
                <input type="password" wire:model="password" class="form-input" placeholder="{{ $isEdit ? 'Kosongkan jika tidak ingin mengubah' : 'Minimal 8 karakter' }}">
                @error('password') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Tipe Nakes</label>
                <select wire:model="type_nakes" class="form-input">
                    <option value="">Tidak berlaku</option>
                    <option value="nakes">Nakes</option>
                    <option value="non-nakes">Non-Nakes</option>
                    <option value="all">Nakes &amp; Non-Nakes</option>
                </select>
            </div>

            <div>
                <label class="form-label">Perusahaan</label>
                <select wire:model="company_id" class="form-input" @disabled(! auth()->user()->canFilterByCompany())>
                    <option value="">Pilih perusahaan...</option>
                    @foreach ($this->companyOptions as $id => $companyName)
                        <option value="{{ $id }}">{{ $companyName }}</option>
                    @endforeach
                </select>
                @error('company_id') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>

            <div style="display:flex;align-items:center;">
                <label class="toggle-wrapper" style="width:100%;" wire:click="$toggle('is_active')">
                    <span class="toggle {{ $is_active ? 'on' : '' }}"></span>
                    <span style="font-size:13px;font-weight:600;color:var(--text-body);">Karyawan aktif sejak dibuat</span>
                </label>
            </div>

            <div style="grid-column:1 / -1;">
                <label class="form-label">Peran (Role)</label>
                @if (! $company_id)
                    <p style="font-size:12.5px;color:var(--text-muted);">Pilih perusahaan dulu untuk memilih peran.</p>
                @else
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;border:1.5px solid var(--border);border-radius:11px;padding:12px;">
                        @forelse ($this->roleOptions as $id => $roleName)
                            <label style="display:flex;align-items:center;gap:9px;font-size:13px;color:var(--text-body);cursor:pointer;">
                                <span class="custom-checkbox">
                                    <input type="checkbox" wire:model="role_ids" value="{{ $id }}">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </span>
                                {{ $roleName }}
                            </label>
                        @empty
                            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada role untuk perusahaan ini.</p>
                        @endforelse
                    </div>
                @endif
                @error('role_ids') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- STEP 2 — Data Pribadi, Alamat, Kontak --}}
    <div @if ($step !== 2) style="display:none;" @endif wire:key="step-2">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:12px;">Identitas</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:24px;">
            <div>
                <label class="form-label">Nama Panggilan</label>
                <input type="text" wire:model="nickname" class="form-input">
            </div>
            <div>
                <label class="form-label">Email Pribadi</label>
                <input type="email" wire:model="email_pribadi" class="form-input" maxlength="255" placeholder="nama@email.com">
            </div>
            <div>
                <label class="form-label">Jenis Kelamin</label>
                <select wire:model="gender" class="form-input">
                    <option value="">Pilih...</option>
                    <option value="laki_laki">Laki-laki</option>
                    <option value="perempuan">Perempuan</option>
                </select>
            </div>
            <div>
                <label class="form-label">Status Perkawinan</label>
                <input type="text" wire:model="marital_status" class="form-input" maxlength="50">
            </div>
            <div>
                <label class="form-label">Agama</label>
                <select wire:model="religion_id" class="form-input">
                    <option value="">Pilih...</option>
                    @foreach ($this->religionOptions as $id => $religionName)
                        <option value="{{ $id }}">{{ $religionName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Tempat Lahir</label>
                <input type="text" wire:model="place_of_birth" class="form-input">
            </div>
            <div>
                <label class="form-label">Tanggal Lahir</label>
                <input type="date" wire:model="date_of_birth" class="form-input">
            </div>
            <div>
                <label class="form-label">No. NIK</label>
                <input type="text" wire:model="no_nik" class="form-input" maxlength="20">
            </div>
            <div>
                <label class="form-label">No. KK</label>
                <input type="text" wire:model="no_kk" class="form-input" maxlength="20">
            </div>
            <div>
                <label class="form-label">No. BPJS Kesehatan</label>
                <input type="text" wire:model="no_bpjs_kes" class="form-input" maxlength="20">
            </div>
            <div>
                <label class="form-label">No. BPJS Ketenagakerjaan</label>
                <input type="text" wire:model="no_bpjs_ket" class="form-input" maxlength="20">
            </div>
            <div>
                <label class="form-label">NPWP</label>
                <input type="text" wire:model="npwp" class="form-input" maxlength="20">
            </div>
            <div>
                <label class="form-label">Bank</label>
                <input type="text" wire:model="bank_name" class="form-input" maxlength="100" placeholder="Mis. BCA">

            </div>
            <div>
                <label class="form-label">No. Rekening</label>
                <input type="text" wire:model="bank_account_number" class="form-input" maxlength="30">
            </div>
            <div>
                <label class="form-label">Atas Nama Rekening</label>
                <input type="text" wire:model="bank_account_name" class="form-input" maxlength="100">
            </div>
        </div>

        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:12px;">Alamat</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px;">
            <div>
                <label class="form-label">Provinsi</label>
                <input type="text" wire:model="province" class="form-input">
            </div>
            <div>
                <label class="form-label">Kota/Kabupaten</label>
                <input type="text" wire:model="city" class="form-input">
            </div>
            <div>
                <label class="form-label">Kecamatan</label>
                <input type="text" wire:model="district" class="form-input">
            </div>
            <div>
                <label class="form-label">Kelurahan/Desa</label>
                <input type="text" wire:model="village" class="form-input">
            </div>
            <div>
                <label class="form-label">RT</label>
                <input type="text" wire:model="rt" class="form-input" maxlength="5">
            </div>
            <div>
                <label class="form-label">RW</label>
                <input type="text" wire:model="rw" class="form-input" maxlength="5">
            </div>
            <div>
                <label class="form-label">Kode Pos</label>
                <input type="text" wire:model="postal_code" class="form-input" maxlength="10">
            </div>
            <div style="grid-column:1 / -1;">
                <label class="form-label">Alamat Lengkap</label>
                <textarea wire:model="address" class="form-input" rows="3" maxlength="500"></textarea>
            </div>
        </div>

        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:12px;">Kontak</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            <div>
                <label class="form-label">No. Telepon</label>
                <input type="text" wire:model="phone" class="form-input" maxlength="15">
            </div>
            <div>
                <label class="form-label">No. WhatsApp</label>
                <input type="text" wire:model="whatsapp" class="form-input" maxlength="15">
            </div>
        </div>
    </div>

    {{-- STEP 3 — Data Pekerjaan --}}
    <div @if ($step !== 3) style="display:none;" @endif wire:key="step-3">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
            <div>
                <label class="form-label">Departemen</label>
                <select wire:model.live="department_id" class="form-input" @disabled(! $company_id)>
                    <option value="">Pilih...</option>
                    @foreach ($this->departmentOptions as $id => $deptName)
                        <option value="{{ $id }}">{{ $deptName }}</option>
                    @endforeach
                </select>
                @unless ($company_id)
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:5px;">Pilih perusahaan di step 1 dulu.</p>
                @endunless
            </div>
            <div>
                <label class="form-label">Divisi</label>
                <select wire:model.live="division_id" class="form-input" @disabled(! $department_id)>
                    <option value="">Pilih...</option>
                    @foreach ($this->divisionOptions as $id => $divisionName)
                        <option value="{{ $id }}">{{ $divisionName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Sub Divisi</label>
                <select wire:model="sub_division_id" class="form-input" @disabled(! $division_id)>
                    <option value="">Pilih...</option>
                    @foreach ($this->subDivisionOptions as $id => $subDivisionName)
                        <option value="{{ $id }}">{{ $subDivisionName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Jabatan</label>
                <div wire:ignore>
                    <select wire:model="job_position_id" class="form-input" data-tom-select data-placeholder="Cari Jabatan...">
                        <option value="">Pilih...</option>
                        @foreach ($this->jobPositionOptions as $id => $jobPositionName)
                            <option value="{{ $id }}">{{ $jobPositionName }}</option>
                        @endforeach
                    </select>
                </div>
                @error('job_position_id') <p style="color:var(--danger-text);font-size:12px;margin-top:5px;">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Pangkat</label>
                <select wire:model.live="pangkat_id" class="form-input">
                    <option value="">Pilih...</option>
                    @foreach ($this->pangkatOptions as $id => $pangkatName)
                        <option value="{{ $id }}">{{ $pangkatName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Golongan</label>
                <p style="font-size:11.5px;color:var(--text-muted);margin-top:5px;">Otomatis ikut Pangkat yang dipilih.</p>
                <input type="text" value="{{ $this->selectedPangkat?->golongan ?? '-' }}" class="form-input" disabled>
            </div>
            <div>
                <label class="form-label">Kelas</label>
                <select wire:model="kelas_id" class="form-input">
                    <option value="">Pilih...</option>
                    @foreach ($this->kelasOptions as $id => $kelasName)
                        <option value="{{ $id }}">{{ $kelasName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Tipe Karyawan</label>
                <select wire:model="employee_type_id" class="form-input">
                    <option value="">Pilih...</option>
                    @foreach ($this->employeeTypeOptions as $id => $employeeTypeName)
                        <option value="{{ $id }}">{{ $employeeTypeName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Tanggal Bergabung</label>
                <input type="date" wire:model="joining_date" class="form-input">
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;margin-top:28px;padding-top:20px;border-top:1px solid var(--border-soft);">
        <div>
            @if ($step > 1)
                <button type="button" wire:click="prevStep" class="btn-ghost">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </button>
            @endif
        </div>
        <div style="display:flex;gap:10px;">
            @if (! $isEdit && $step < 3)
                <button type="button" wire:click="nextStep" class="btn-primary" wire:loading.attr="disabled" wire:target="nextStep">
                    Lanjut <i class="fa-solid fa-arrow-right"></i>
                </button>
            @else
                {{-- Edit: bebas simpan dari step manapun, tidak perlu sampai step 3 dulu. --}}
                <button type="button" wire:click="save" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <i class="fa-solid fa-check"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Karyawan' }}
                </button>
            @endif
        </div>
    </div>
</div>

</div>
