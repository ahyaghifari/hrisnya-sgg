<div class="p-4 md:p-6">
    <x-page-header title="Pengaturan Sikartap" subtitle="Wajib Ide Inovasi & wajib MCU - beda-beda per perusahaan" />

    <div class="mt-4">
        <div class="doc-type-layout">
            @if (auth()->user()->canFilterByCompany())
            <div class="doc-type-nav">
                @foreach ($this->companies as $company)
                    <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn doc-type-btn-sky {{ $companyId === $company->id ? 'is-active' : '' }}">
                        <i class="fa-solid fa-building"></i>
                        <span>{{ $company->name }}</span>
                    </button>
                @endforeach
            </div>
            @endif

            <div style="flex:1;min-width:0;">
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    <div style="display:flex;flex-direction:column;">
                        @foreach ($this->settings as $setting)
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 20px;padding:14px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                                <div style="flex:1;min-width:220px;">
                                    <label class="form-label" style="margin:0;display:inline-flex;align-items:center;gap:6px;">
                                        {{ $setting->label() }}
                                        <button type="button" wire:click="openAuditLog('{{ $setting->value }}')" title="Riwayat" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:11px;padding:0;">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                    </label>
                                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $setting->helperText() }}</p>
                                </div>
                                @if ($this->canEditSettings)
                                    <label class="custom-checkbox" style="--accent:#7c3aed;flex-shrink:0;">
                                        <input type="checkbox" wire:model="values.{{ $setting->value }}" value="1" @checked(($values[$setting->value] ?? '0') == '1')>
                                        <span class="box"><i class="fa-solid fa-check"></i></span>
                                    </label>
                                @else
                                    <span class="badge {{ ($values[$setting->value] ?? '0') == '1' ? 'badge-approved' : 'badge-cancelled' }}">
                                        {{ ($values[$setting->value] ?? '0') == '1' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($this->canEditSettings)
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="justify-content:center;align-self:flex-start;padding-left:20px;padding-right:20px;margin-top:18px;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan
                        </button>
                    @endif
                </div>

                @if (($values[\App\Enum\Sikartap\SikartapSettingEnum::WAJIB_IDE_INOVASI->value] ?? '0') == '1')
                    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:20px;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">Reviewer Atasan Ide Inovasi</p>
                        <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">Tunjuk 1 reviewer per Departemen — berlaku ke semua karyawan di bawah departemen itu. Semua Departemen wajib terisi sebelum bisa disimpan.</p>

                        @forelse ($this->orgDepartments as $departemen)
                            <div style="padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <p style="font-size:12.5px;font-weight:600;color:var(--text-body);margin:0;min-width:160px;">
                                    {{ $departemen->name }}
                                </p>

                                @if ($this->canEditSettings)
                                    <div wire:ignore wire:key="reviewer-select-{{ $companyId }}-{{ $departemen->id }}" style="min-width:220px;">
                                        <select data-tom-select data-placeholder="-- Pilih reviewer --" wire:model="reviewerUserIds.{{ $departemen->id }}">
                                            <option value="">-- Pilih reviewer --</option>
                                            @foreach ($this->companyUsers as $companyUser)
                                                <option value="{{ $companyUser->id }}">{{ $companyUser->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">
                                        {{ $this->companyUsers->firstWhere('id', $reviewerUserIds[$departemen->id] ?? null)?->name ?? 'Belum ditunjuk' }}
                                    </span>
                                @endif
                            </div>
                        @empty
                            <p style="font-size:12px;color:var(--text-muted);">Belum ada Departemen di org struktur perusahaan ini.</p>
                        @endforelse

                        @if ($this->canEditSettings && $this->orgDepartments->isNotEmpty())
                            <button type="button" wire:click="saveReviewers" wire:loading.attr="disabled" wire:target="saveReviewers" class="btn-primary" style="margin-top:16px;">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Reviewer
                            </button>
                        @endif
                    </div>
                @endif

                {{-- Penilai Orientasi - jabatan-based, TIDAK bergantung toggle "wajib Ide Inovasi" --}}
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:20px;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">Penilai Orientasi</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">Jabatan yang menilai orientasi karyawan per Departemen. Penilai = pemegang aktif jabatan itu; kalau kosong, sistem naik ke Departemen induk.</p>

                    @if (! $companyId)
                        <p style="font-size:12px;color:#b45309;margin:0;">Pilih perusahaan dulu.</p>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:12px;">
                            <thead>
                                <tr style="border-bottom:1.5px solid var(--border);">
                                    <th style="text-align:left;padding:8px 10px;">Departemen</th>
                                    <th style="text-align:left;padding:8px 10px;">Jabatan Penilai</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->orientationReviewers as $rev)
                                    <tr wire:key="orev-{{ $rev->id }}" style="border-bottom:1px solid var(--border-soft);">
                                        <td style="padding:8px 10px;">{{ $rev->orgStructure?->name ?? '-' }}</td>
                                        <td style="padding:8px 10px;">{{ $rev->jobPosition?->name ?? '-' }}</td>
                                        <td style="padding:8px 10px;text-align:right;">
                                            @if ($this->canEditSettings)
                                                <button type="button" wire:click="removeOrientationReviewer('{{ $rev->id }}')" wire:confirm="Hapus penilai ini?" class="btn-ghost" style="color:#dc2626;padding:5px 10px;"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="padding:14px 10px;color:var(--text-muted);">Belum ada penilai orientasi. Tambahkan di bawah.</td></tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if ($this->canEditSettings)
                            <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                <div style="flex:1;min-width:180px;">
                                    <label class="form-label">Departemen</label>
                                    <select wire:model="orientationReviewerDeptId" class="form-input">
                                        <option value="">-- Pilih Departemen --</option>
                                        @foreach ($this->orgDepartments as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('orientationReviewerDeptId') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                                </div>
                                <div style="flex:1;min-width:180px;">
                                    <label class="form-label">Jabatan Penilai</label>
                                    <select wire:model="orientationReviewerJobPositionId" class="form-input">
                                        <option value="">-- Pilih Jabatan --</option>
                                        @foreach ($this->companyJobPositions as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('orientationReviewerJobPositionId') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="addOrientationReviewer" class="btn-primary" style="margin-top:22px;"><i class="fa-solid fa-plus"></i> Tambah</button>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Penilai Perpanjangan Kontrak - jabatan-based, sama pola Penilai Orientasi --}}
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:20px;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">Penilai Perpanjangan Kontrak</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">Jabatan yang menilai pengajuan perpanjangan kontrak per Departemen. Kalau kosong, sistem naik ke Departemen induk.</p>

                    @if (! $companyId)
                        <p style="font-size:12px;color:#b45309;margin:0;">Pilih perusahaan dulu.</p>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:12px;">
                            <thead>
                                <tr style="border-bottom:1.5px solid var(--border);">
                                    <th style="text-align:left;padding:8px 10px;">Departemen</th>
                                    <th style="text-align:left;padding:8px 10px;">Jabatan Penilai</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->contractReviewers as $rev)
                                    <tr wire:key="crev-{{ $rev->id }}" style="border-bottom:1px solid var(--border-soft);">
                                        <td style="padding:8px 10px;">{{ $rev->orgStructure?->name ?? '-' }}</td>
                                        <td style="padding:8px 10px;">{{ $rev->jobPosition?->name ?? '-' }}</td>
                                        <td style="padding:8px 10px;text-align:right;">
                                            @if ($this->canEditSettings)
                                                <button type="button" wire:click="removeContractReviewer('{{ $rev->id }}')" wire:confirm="Hapus penilai ini?" class="btn-ghost" style="color:#dc2626;padding:5px 10px;"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="padding:14px 10px;color:var(--text-muted);">Belum ada penilai perpanjangan kontrak. Tambahkan di bawah.</td></tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if ($this->canEditSettings)
                            <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                <div style="flex:1;min-width:180px;">
                                    <label class="form-label">Departemen</label>
                                    <select wire:model="contractReviewerDeptId" class="form-input">
                                        <option value="">-- Pilih Departemen --</option>
                                        @foreach ($this->orgDepartments as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('contractReviewerDeptId') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                                </div>
                                <div style="flex:1;min-width:180px;">
                                    <label class="form-label">Jabatan Penilai</label>
                                    <select wire:model="contractReviewerJobPositionId" class="form-input">
                                        <option value="">-- Pilih Jabatan --</option>
                                        @foreach ($this->companyJobPositions as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('contractReviewerJobPositionId') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="addContractReviewer" class="btn-primary" style="margin-top:22px;"><i class="fa-solid fa-plus"></i> Tambah</button>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Dokumen Perpanjangan Kontrak - daftar dokumen yang wajib/opsional diunggah pengaju --}}
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:20px;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">Dokumen Perpanjangan Kontrak</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">Dokumen yang harus diunggah karyawan saat mengajukan perpanjangan kontrak. Centang "wajib" untuk yang tidak boleh kosong.</p>

                    @if (! $companyId)
                        <p style="font-size:12px;color:#b45309;margin:0;">Pilih perusahaan dulu.</p>
                    @else
                        <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px;">
                            @foreach ($contractDocRows as $i => $row)
                                <div wire:key="cdoc-{{ $i }}" style="display:flex;gap:8px;align-items:center;">
                                    <input type="text" wire:model="contractDocRows.{{ $i }}.label" class="form-input" style="flex:1;" placeholder="Nama dokumen" @disabled(! $this->canEditSettings)>
                                    <label style="display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--text-muted);white-space:nowrap;">
                                        <input type="checkbox" wire:model="contractDocRows.{{ $i }}.required" @disabled(! $this->canEditSettings)> wajib
                                    </label>
                                    @if ($this->canEditSettings)
                                        <button type="button" wire:click="moveContractDocUp({{ $i }})" class="btn-ghost" style="padding:5px 8px;">▲</button>
                                        <button type="button" wire:click="moveContractDocDown({{ $i }})" class="btn-ghost" style="padding:5px 8px;">▼</button>
                                        <button type="button" wire:click="removeContractDoc({{ $i }})" class="btn-ghost" style="color:#dc2626;padding:5px 8px;"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
                                    @endif
                                </div>
                                @error("contractDocRows.$i.label") <p style="font-size:11px;color:#dc2626;">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                        @if ($this->canEditSettings)
                            <div style="display:flex;gap:8px;">
                                <button type="button" wire:click="addContractDoc" class="btn-ghost"><i class="fa-solid fa-plus"></i> Tambah Dokumen</button>
                                <button type="button" wire:click="saveContractDocs" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        @include('livewire.partials.audit-log-modal')
    </div>
</div>
