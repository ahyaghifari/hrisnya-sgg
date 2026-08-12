<div>
    <x-page-header title="Hak Akses" />

    <div
        class="mt-4"
        x-data="roleAccessTree({
            tree: @js($this->tree),
            checked: @entangle('checked'),
            locked: @js($this->isSuperAdminRole),
            roleGranted: @js($this->roleGrantedPermissions),
        })"
        style="display:flex;flex-direction:column;gap:16px;"
    >
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:flex;gap:8px;">
                <button type="button" wire:click="$set('mode', 'role')" class="{{ $mode === 'role' ? 'btn-primary' : 'btn-secondary' }}" style="padding:8px 16px;font-size:13px;">
                    <i class="fa-solid fa-user-group"></i> Lewat Role
                </button>
                <button type="button" wire:click="$set('mode', 'user')" class="{{ $mode === 'user' ? 'btn-primary' : 'btn-secondary' }}" style="padding:8px 16px;font-size:13px;">
                    <i class="fa-solid fa-user"></i> Langsung ke User
                </button>
            </div>

            @if ($mode === 'user')
                <div style="background:rgba(4,44,108,0.07);border:1px solid rgba(4,44,108,0.2);border-radius:10px;padding:12px 16px;font-size:13px;color:#042c6c;">
                    Ini nambahan izin di LUAR izin dari role user ini — tidak bisa mencabut/override izin yang sudah didapat dari role-nya.
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                @if (auth()->user()->canFilterByCompany())
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Perusahaan</label>
                        <select wire:model.live="companyId" class="form-input">
                            @foreach ($this->companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($mode === 'role')
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Role</label>
                        <select wire:model.live="roleId" class="form-input">
                            @forelse ($this->roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @empty
                                <option value="">Belum ada role</option>
                            @endforelse
                        </select>
                    </div>
                @else
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Cari User</label>
                        <input type="search" wire:model.live.debounce.300ms="userSearch" class="form-input" placeholder="Nama atau NIP...">
                    </div>
                @endif
            </div>

            @if ($mode === 'user')
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">User</label>
                    <select wire:model.live="userId" class="form-input">
                        @forelse ($this->users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->nip }})</option>
                        @empty
                            <option value="">Tidak ada user cocok</option>
                        @endforelse
                    </select>
                    @if ($this->selectedUser)
                        <p style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">
                            Role saat ini: {{ $this->selectedUser->roles->pluck('name')->join(', ') ?: '-' }}
                        </p>
                    @endif
                </div>
            @endif

            <div style="display:flex;gap:8px;">
                <button type="button" class="btn-secondary" @click="expandAll()">Buka Semua</button>
                <button type="button" class="btn-secondary" @click="collapseAll()">Tutup Semua</button>
            </div>

            @if ($this->isSuperAdminRole)
                <div style="background:rgba(4,44,108,0.07);border:1px solid rgba(4,44,108,0.2);border-radius:10px;padding:14px 16px;font-size:15px;color:#042c6c;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <span>Role ini otomatis punya semua hak akses — tidak bisa diatur satu-satu di sini. Kalau ada permission baru (mis. dari resource baru) yang belum tercentang, klik tombol ini buat sinkronkan tanpa perlu command server.</span>
                    <button type="button" class="btn-primary" wire:click="syncSuperAdminPermissions" wire:confirm="Sinkronkan role ini ke SEMUA permission yang ada saat ini?" style="flex-shrink:0;">Sinkronkan Semua Hak Akses</button>
                </div>
            @endif

            {{-- Tree kedalamannya TETAP (Container > Subject > Permission, lihat
            PermissionTreeBuilder) — makanya 3 level di-hardcode langsung di sini pakai
            x-for bersarang, BUKAN partial Blade recursive. @include Blade jalan di server
            (dieksekusi sekali saat render, tidak tahu kapan "leaf" tercapai secara runtime
            Alpine) — partial yang meng-include dirinya sendiri bikin Blade include tanpa
            henti sampai OOM (pernah kejadian, lihat riwayat commit).

            Kotak ini sengaja dibatasi max-height 90vh + overflow-y:auto (permintaan user) -
            tree bisa ratusan leaf, tanpa scroll-container sendiri halaman jadi kepanjangan. --}}
            <div class="card-surface" style="max-height:90vh;overflow-y:auto;">
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <template x-for="root in tree" :key="root.id">
                        <div>
                            <div
                                style="display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:8px;cursor:pointer;"
                                :style="isChecked(root) ? 'background:rgba(4,44,108,0.08);' : ''"
                                @click="toggleExpand(root)"
                            >
                                <i class="fa-solid" :class="isExpanded(root) ? 'fa-square-minus' : 'fa-square-plus'" style="color:#64748b;width:14px;font-size:13px;"></i>
                                <label class="custom-checkbox" @click.stop.prevent="toggle(root)">
                                    <input type="checkbox" x-effect="$el.checked = isChecked(root); $el.indeterminate = isIndeterminate(root)" :disabled="locked || isRoleGranted(root)" @click.prevent>
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                                <i class="fa-solid fa-folder" style="color:#94a3b8;font-size:14px;"></i>
                                <span x-text="root.label" style="font-size:14.5px;font-weight:700;"></span>
                                <span x-show="isRoleGranted(root)" style="font-size:10px;font-weight:700;color:#0369a1;background:rgba(3,105,161,0.1);padding:2px 8px;border-radius:99px;">Dari Role</span>
                            </div>

                            <div x-show="isExpanded(root)" style="margin-left:24px;">
                                <template x-for="mid in root.children" :key="mid.id">
                                    <div>
                                        <div
                                            style="display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:8px;cursor:pointer;"
                                            :style="isChecked(mid) ? 'background:rgba(4,44,108,0.06);' : ''"
                                            @click="toggleExpand(mid)"
                                        >
                                            <i class="fa-solid" :class="isExpanded(mid) ? 'fa-square-minus' : 'fa-square-plus'" style="color:#94a3b8;width:13px;font-size:12px;"></i>
                                            <label class="custom-checkbox" @click.stop.prevent="toggle(mid)">
                                                <input type="checkbox" x-effect="$el.checked = isChecked(mid); $el.indeterminate = isIndeterminate(mid)" :disabled="locked || isRoleGranted(mid)" @click.prevent>
                                                <span class="box"><i class="fa-solid fa-check"></i></span>
                                            </label>
                                            <i class="fa-solid fa-folder-open" style="color:#94a3b8;font-size:13px;"></i>
                                            <span x-text="mid.label" style="font-size:13.5px;font-weight:600;"></span>
                                            <span x-show="isRoleGranted(mid)" style="font-size:10px;font-weight:700;color:#0369a1;background:rgba(3,105,161,0.1);padding:2px 8px;border-radius:99px;">Dari Role</span>
                                        </div>

                                        <div x-show="isExpanded(mid)" style="margin-left:24px;">
                                            <template x-for="leaf in mid.children" :key="leaf.id">
                                                <div
                                                    style="display:flex;align-items:center;gap:9px;padding:6px 10px;border-radius:8px;cursor:pointer;"
                                                    @click="toggle(leaf)"
                                                >
                                                    <span style="width:13px;"></span>
                                                    <label class="custom-checkbox" @click.stop.prevent="toggle(leaf)">
                                                        <input type="checkbox" x-effect="$el.checked = isChecked(leaf)" :disabled="locked || isRoleGranted(leaf)" @click.prevent>
                                                        <span class="box"><i class="fa-solid fa-check"></i></span>
                                                    </label>
                                                    <i class="fa-solid fa-file-lines" style="color:#94a3b8;font-size:11px;"></i>
                                                    <span x-text="leaf.label" style="font-size:13px;"></span>
                                                    <span x-show="isRoleGranted(leaf)" style="font-size:10px;font-weight:700;color:#0369a1;background:rgba(3,105,161,0.1);padding:2px 8px;border-radius:99px;">Dari Role</span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            @unless ($this->isSuperAdminRole)
                <div>
                    <button type="button" class="btn-primary" @click="save()">Simpan Hak Akses</button>
                </div>
            @endunless

        </div>
    </div>

    @script
    <script>
        Alpine.data('roleAccessTree', ({ tree, checked, locked, roleGranted }) => ({
            tree,
            checked,
            locked,
            roleGranted,
            expanded: {},

            leafIds(node) {
                if (!node.children || node.children.length === 0) return [node.id];
                return node.children.flatMap((child) => this.leafIds(child));
            },

            isLeaf(node) {
                return !node.children || node.children.length === 0;
            },

            // Tercentang lewat direct permission ATAU sudah didapat dari role (mode User) -
            // dua-duanya dianggap "granted" buat tampilan, cuma yang direct yang bisa dicabut.
            isChecked(node) {
                const ids = this.leafIds(node);
                return ids.length > 0 && ids.every((id) => this.checked.includes(id) || this.roleGranted.includes(id));
            },

            isIndeterminate(node) {
                if (this.isChecked(node)) return false;
                const ids = this.leafIds(node);
                return ids.some((id) => this.checked.includes(id) || this.roleGranted.includes(id));
            },

            // Semua leaf di node ini sudah didapat dari role -> checkbox-nya dikunci + dikasih
            // badge "Dari Role" (lihat markup), tidak ada yang perlu/bisa ditoggle dari sini.
            isRoleGranted(node) {
                const ids = this.leafIds(node);
                return ids.length > 0 && ids.every((id) => this.roleGranted.includes(id));
            },

            toggle(node) {
                if (this.locked || this.isRoleGranted(node)) return;

                // Leaf yang sudah dari role dibiarkan (tidak ikut ditoggle) - node campuran
                // (sebagian dari role, sebagian belum) tetap bisa diklik buat yang belum saja.
                const ids = this.leafIds(node).filter((id) => !this.roleGranted.includes(id));
                if (ids.length === 0) return;

                const willCheck = !ids.every((id) => this.checked.includes(id));

                if (willCheck) {
                    this.checked = [...new Set([...this.checked, ...ids])];
                } else {
                    this.checked = this.checked.filter((id) => !ids.includes(id));
                }
            },

            isExpanded(node) {
                return this.expanded[node.id] ?? true;
            },

            toggleExpand(node) {
                this.expanded[node.id] = !this.isExpanded(node);
            },

            expandAll() {
                this.walk(this.tree, (node) => { this.expanded[node.id] = true; });
            },

            collapseAll() {
                this.walk(this.tree, (node) => { this.expanded[node.id] = false; });
            },

            walk(nodes, fn) {
                nodes.forEach((node) => {
                    if (!this.isLeaf(node)) {
                        fn(node);
                        this.walk(node.children, fn);
                    }
                });
            },

            save() {
                $wire.save(this.checked);
            },
        }));
    </script>
    @endscript
</div>
