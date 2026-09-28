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
                                    @can('view', $role)
                                        <button type="button" wire:click="openUsersModal('{{ $role->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-users"></i> Lihat User
                                        </button>
                                    @endcan
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

    @if ($showUsersModal)
        <div class="modal-overlay" wire:click="closeUsersModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    User dengan Role "{{ $usersModalRoleName }}" ({{ count($usersModalUsers) }})
                </h3>

                <div style="max-height:280px;overflow-y:auto;">
                    @forelse ($usersModalUsers as $user)
                        <div wire:key="'usersmodal-'.{{ $user['id'] }}" style="padding:8px 0;border-bottom:1px solid var(--border-soft);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <div>
                                <div style="font-weight:600;font-size:13px;color:var(--text-heading);">{{ $user['name'] }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted);">{{ $user['email'] }} @if($user['nip']) &middot; NIP {{ $user['nip'] }} @endif</div>
                            </div>
                            @if ($this->canManageUsersModal)
                                <button type="button" wire:click="removeUserFromRole('{{ $user['id'] }}')" wire:confirm="Lepas role ini dari {{ $user['name'] }}?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;white-space:nowrap;">
                                    <i class="fa-solid fa-user-minus"></i> Lepas
                                </button>
                            @endif
                        </div>
                    @empty
                        <p style="padding:16px 0;text-align:center;color:var(--text-faint);font-size:12.5px;">Belum ada user dengan role ini.</p>
                    @endforelse
                </div>

                @if ($this->canManageUsersModal)
                    <div style="margin-top:16px;padding-top:14px;border-top:1.5px solid var(--border);">
                        <h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Tambah User ke Role Ini</h4>

                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                            <input type="text" class="form-input" style="flex:1;min-width:160px;" wire:model.live.debounce.400ms="addUserSearch" placeholder="Cari nama / NIP...">

                            <select data-tom-select data-placeholder="Semua Divisi" class="form-input" style="min-width:150px;" wire:model.live="addUserDivisionId">
                                <option value="">Semua Divisi</option>
                                @foreach ($this->addUserDivisions as $division)
                                    <option value="{{ $division->id }}">{{ $division->name }}</option>
                                @endforeach
                            </select>

                            <select data-tom-select data-placeholder="Semua Jabatan" class="form-input" style="min-width:150px;" wire:model.live="addUserJobPositionId">
                                <option value="">Semua Jabatan</option>
                                @foreach ($this->addUserJobPositions as $jobPosition)
                                    <option value="{{ $jobPosition->id }}">{{ $jobPosition->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div style="max-height:220px;overflow-y:auto;">
                            @forelse ($this->addUserCandidates as $candidate)
                                <div wire:key="'addcandidate-'.{{ $candidate->id }}" style="padding:8px 0;border-bottom:1px solid var(--border-soft);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                                    <div>
                                        <div style="font-weight:600;font-size:13px;color:var(--text-heading);">{{ $candidate->name }}</div>
                                        <div style="font-size:11.5px;color:var(--text-muted);">{{ $candidate->email }} @if($candidate->nip) &middot; NIP {{ $candidate->nip }} @endif</div>
                                    </div>
                                    <button type="button" wire:click="addUserToRole('{{ $candidate->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#0d7a3d;white-space:nowrap;">
                                        <i class="fa-solid fa-user-plus"></i> Tambah
                                    </button>
                                </div>
                            @empty
                                <p style="padding:12px 0;text-align:center;color:var(--text-faint);font-size:12.5px;">Tidak ada user yang cocok.</p>
                            @endforelse
                        </div>
                    </div>
                @endif

                <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                    <button type="button" wire:click="closeUsersModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
