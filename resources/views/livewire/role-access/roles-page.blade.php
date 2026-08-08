<div>
    <x-page-header title="Role" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
            <x-company-filter :companies="$this->companies" wire:model.live="companyId" />

            @if ($this->canCreate)
                <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Role
                </button>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Role</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah User</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Permission</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->roles as $role)
                        <tr wire:key="'role-'.{{ $role->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $role->name }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $role->users_count }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $role->permissions_count }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    @if (! in_array($role->name, ['super_admin', 'Super Admin'], true))
                                        @can('update', $role)
                                            <button type="button" wire:click="openEditModal('{{ $role->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </button>
                                        @endcan
                                        @can('delete', $role)
                                            @if ($role->users_count === 0)
                                                <button type="button" wire:click="removeRole('{{ $role->id }}')" wire:confirm="Hapus role {{ $role->name }}? Aksi ini tidak bisa dibatalkan." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                    <i class="fa-solid fa-trash"></i> Hapus
                                                </button>
                                            @endif
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada role untuk perusahaan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Role' : 'Tambah Role' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nama Role</label>
                        <input type="text" class="form-input" wire:model="formName" placeholder="Mis. Supervisor">
                        @error('formName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if (auth()->user()->canFilterByCompany())
                        <div>
                            <label class="form-label">Perusahaan</label>
                            <select class="form-input" wire:model="formCompanyId">
                                <option value="">Global (Syifa Global Group)</option>
                                @foreach ($this->companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            @error('formCompanyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <p style="font-size:11px;color:var(--text-muted);margin-top:14px;">
                    Buat izin akses role ini di halaman <a href="{{ route('hak-akses') }}" wire:navigate style="color:#042c6c;font-weight:600;">Hak Akses</a> setelah disimpan.
                </p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
