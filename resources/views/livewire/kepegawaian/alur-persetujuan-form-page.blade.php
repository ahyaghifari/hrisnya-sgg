<div>
<x-page-header :title="$editingFlowId ? 'Edit Alur Persetujuan' : 'Tambah Alur Persetujuan'" :back="route('kepegawaian.alur-persetujuan')" />

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
    <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Informasi Alur</h3>

    <div class="grid grid-cols-1 md:grid-cols-2" style="gap:14px;">
        <div>
            <label class="form-label">Nama Alur Persetujuan</label>
            <input type="text" wire:model="name" class="form-input" placeholder="Mis. Persetujuan Cuti Standar">
            @error('name') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>

        @if ($this->companies->isNotEmpty())
            <div>
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input" @disabled($editingFlowId) style="{{ $editingFlowId ? 'opacity:0.6;cursor:not-allowed;' : '' }}">
                    @foreach ($this->companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
                @if ($editingFlowId)
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Perusahaan tidak bisa diganti setelah alur dibuat.</p>
                @endif
                @error('companyId') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="form-label">Modul</label>
            <select wire:model.live="module" class="form-input">
                <option value="">Pilih modul...</option>
                @foreach ($this->moduleOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('module') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>

        @if ($this->showSubModule)
            <div>
                <label class="form-label">Jenis Pengajuan</label>
                <div wire:key="sub-module-wrap" wire:ignore>
                    <select wire:model="subModule" multiple class="form-input" data-tom-select data-placeholder="Kosongkan = semua jenis...">
                        @foreach ($this->subModuleOptions as $value => $label)
                            <option value="{{ $value }}" @selected(in_array($value, $subModule))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Batasi alur ini ke satu/beberapa jenis pengajuan. Kosong = berlaku semua jenis yang tidak punya alur khusus.</p>
            </div>
        @endif

        <div>
            <label class="form-label">Tipe Alur</label>
            <select wire:model.live="flowType" class="form-input">
                <option value="default">Default (berlaku semua pengaju modul ini)</option>
                <option value="specific">Spesifik (cuma jabatan tertentu)</option>
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:10px;padding-top:22px;">
            <label class="toggle {{ $isActive ? 'on' : '' }}" wire:click="$toggle('isActive')"></label>
            <span style="font-size:12.5px;color:var(--text-body);">{{ $isActive ? 'Aktif' : 'Tidak Aktif' }}</span>
        </div>
    </div>

    @if ($flowType === 'specific')
        <div style="margin-top:14px;">
            <label class="form-label">Berlaku untuk Jabatan</label>
            <div wire:key="job-position-wrap-{{ $companyId }}" wire:ignore>
                <select wire:model="assignmentJobPositions" multiple class="form-input" data-tom-select data-placeholder="Cari nama jabatan...">
                    @foreach ($this->jobPositionOptions as $id => $name)
                        <option value="{{ $id }}" @selected(in_array($id, $assignmentJobPositions))>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Alur ini otomatis diprioritaskan kalau pengaju punya salah satu jabatan di atas.</p>
            @error('assignmentJobPositions') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
        </div>
    @endif
</div>

<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Step Alur Persetujuan</h3>
        <button type="button" wire:click="addStep" class="btn-ghost" style="font-size:11.5px;padding:6px 12px;">
            <i class="fa-solid fa-plus"></i> Tambah Step
        </button>
    </div>
    <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">Urutan step di bawah = urutan approver, step pertama diproses lebih dulu.</p>

    @if (empty($steps))
        <p style="font-size:12px;color:var(--text-faint);text-align:center;padding:16px;">Belum ada step, klik "Tambah Step".</p>
    @endif

    @error('steps') <p style="color:var(--danger-text);font-size:11.5px;margin:0 0 10px;">{{ $message }}</p> @enderror

    <div style="display:flex;flex-direction:column;gap:14px;">
        @foreach ($steps as $i => $step)
            <div wire:key="'step-'.{{ $i }}" style="border:1px solid var(--border-soft);border-radius:12px;padding:14px;background:var(--surface-alt);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:12px;font-weight:700;color:var(--text-heading);">Step {{ $i + 1 }}</span>
                    <button type="button" wire:click="removeStep({{ $i }})" style="background:none;border:none;color:var(--danger-text);cursor:pointer;font-size:12px;">
                        <i class="fa-solid fa-trash"></i> Hapus
                    </button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                    <div>
                        <label class="form-label">Sumber Approver</label>
                        <select wire:model.live="steps.{{ $i }}.approver_type" class="form-input" style="font-size:12px;padding:7px 10px;">
                            <option value="">Pilih...</option>
                            <option value="role">Role</option>
                            <option value="job_position">Jabatan</option>
                            <option value="user">Karyawan Spesifik</option>
                            <option value="supervisor">Atasan Pengaju</option>
                        </select>
                    </div>

                    @if ($step['approver_type'] === 'supervisor')
                        <div>
                            <label class="form-label">Level Atasan</label>
                            <select wire:model.live="steps.{{ $i }}.supervisor_level" class="form-input" style="font-size:12px;padding:7px 10px;">
                                @foreach (range(1, 10) as $lvl)
                                    <option value="{{ $lvl }}">{{ $lvl }}</option>
                                @endforeach
                            </select>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">1 = atasan langsung, 2 = atasan-nya atasan, dst. Dihitung dari jabatan pengaju.</p>
                        </div>
                    @else
                        <div>
                            <label class="form-label">Perusahaan Approver</label>
                            <select wire:model.live="steps.{{ $i }}.source_company" class="form-input" style="font-size:12px;padding:7px 10px;">
                                <option value="same_company">Perusahaan yang Sama</option>
                                <option value="holding_company">Perusahaan Induk / Syifa Global Group</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Approver</label>
                            <div wire:key="approver-wrap-{{ $i }}-{{ $step['approver_type'] }}-{{ $step['source_company'] }}" wire:ignore>
                                <select wire:model="steps.{{ $i }}.approver_ref_id" class="form-input" data-tom-select data-placeholder="Pilih approver..." @disabled(! $step['approver_type'])>
                                    <option value=""></option>
                                    @foreach ($this->approverOptions($step['approver_type'], $step['source_company']) as $id => $name)
                                        <option value="{{ $id }}" @selected($id === $step['approver_ref_id'])>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="form-label">Lingkup</label>
                        <select wire:model.live="steps.{{ $i }}.scope_type" class="form-input" style="font-size:12px;padding:7px 10px;">
                            <option value="same_department">Satu Departemen (dengan pengaju)</option>
                            <option value="specific_department">Departemen Tertentu</option>
                        </select>
                    </div>

                    @if ($step['scope_type'] === 'specific_department')
                        <div>
                            <label class="form-label">Departemen</label>
                            <select wire:model="steps.{{ $i }}.scope_ref_id" class="form-input" style="font-size:12px;padding:7px 10px;">
                                <option value="">Pilih departemen...</option>
                                @foreach ($this->scopeDepartmentOptions as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div style="display:flex;align-items:center;gap:8px;padding-top:22px;">
                        <label class="custom-checkbox">
                            <input type="checkbox" wire:model="steps.{{ $i }}.is_final">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:11.5px;color:var(--text-body);">Step Terakhir</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@php
    $validRecapSteps = collect($steps)
        ->filter(fn ($s) => ! empty($s['approver_type']) && ($s['approver_type'] === 'supervisor' || ! empty($s['approver_ref_id'])));
    $hasSupervisor = $validRecapSteps->contains(fn ($s) => $s['approver_type'] === 'supervisor');
@endphp

@if ($hasSupervisor && $validRecapSteps->isNotEmpty())
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
        <h3 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Rekap Urutan Approver</h3>
        <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Rantai per jabatan pengaju: kiri = pengaju, tiap panah = 1 step approval. Step "Atasan Pengaju" ditentukan otomatis per pengaju; step lain approver-nya tetap.</p>

        @if (empty($assignmentJobPositions))
            <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Pilih dulu "Berlaku untuk Jabatan" di atas untuk melihat rekap.</p>
        @else
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;">
                @foreach ($this->approverFlowPreview() as $row)
                    <li style="font-size:11.5px;color:var(--text-body);line-height:1.7;">
                        {{ $row['base'] }}
                        @foreach ($row['nodes'] as $node)
                            <span style="color:var(--text-muted);">&rarr;</span>
                            <span style="{{ ! $node['ok'] ? 'color:var(--danger-text);' : ($loop->last ? 'font-weight:700;' : 'font-weight:600;') }}">{{ $node['label'] }}</span>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

<div style="display:flex;justify-content:space-between;gap:8px;">
    <a href="{{ route('kepegawaian.alur-persetujuan') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left"></i> Batal</a>
    <button type="button" wire:click="save" class="btn-primary"><i class="fa-solid fa-check"></i> Simpan</button>
</div>

</div>
