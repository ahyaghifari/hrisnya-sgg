<div>
    <x-page-header title="Hak Akses" />

    <div
        class="mt-4"
        x-data="roleAccessTree({
            tree: @js($this->tree),
            checked: @entangle('checked'),
            locked: @js($this->isSuperAdminRole),
        })"J
        style="display:flex;flex-direction:column;gap:16px;"
    >
        <div style="display:flex;flex-direction:column;gap:14px;">
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
            </div>

            <div style="display:flex;gap:8px;">
                <button type="button" class="btn-secondary" @click="expandAll()">Buka Semua</button>
                <button type="button" class="btn-secondary" @click="collapseAll()">Tutup Semua</button>
            </div>

            @if ($this->isSuperAdminRole)
                <div style="background:rgba(4,44,108,0.07);border:1px solid rgba(4,44,108,0.2);border-radius:10px;padding:14px 16px;font-size:15px;color:#042c6c;">
                    Role ini otomatis punya semua hak akses — tidak bisa diatur satu-satu di sini.
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
                                <label class="custom-checkbox" @click.stop="toggle(root)">
                                    <input type="checkbox" :checked="isChecked(root)" x-effect="$el.indeterminate = isIndeterminate(root)" :disabled="locked" @click.prevent>
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                                <i class="fa-solid fa-folder" style="color:#94a3b8;font-size:14px;"></i>
                                <span x-text="root.label" style="font-size:14.5px;font-weight:700;"></span>
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
                                            <label class="custom-checkbox" @click.stop="toggle(mid)">
                                                <input type="checkbox" :checked="isChecked(mid)" x-effect="$el.indeterminate = isIndeterminate(mid)" :disabled="locked" @click.prevent>
                                                <span class="box"><i class="fa-solid fa-check"></i></span>
                                            </label>
                                            <i class="fa-solid fa-folder-open" style="color:#94a3b8;font-size:13px;"></i>
                                            <span x-text="mid.label" style="font-size:13.5px;font-weight:600;"></span>
                                        </div>

                                        <div x-show="isExpanded(mid)" style="margin-left:24px;">
                                            <template x-for="leaf in mid.children" :key="leaf.id">
                                                <div
                                                    style="display:flex;align-items:center;gap:9px;padding:6px 10px;border-radius:8px;cursor:pointer;"
                                                    @click="toggle(leaf)"
                                                >
                                                    <span style="width:13px;"></span>
                                                    <label class="custom-checkbox" @click.stop="toggle(leaf)">
                                                        <input type="checkbox" :checked="isChecked(leaf)" :disabled="locked" @click.prevent>
                                                        <span class="box"><i class="fa-solid fa-check"></i></span>
                                                    </label>
                                                    <i class="fa-solid fa-file-lines" style="color:#94a3b8;font-size:11px;"></i>
                                                    <span x-text="leaf.label" style="font-size:13px;"></span>
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
        Alpine.data('roleAccessTree', ({ tree, checked, locked }) => ({
            tree,
            checked,
            locked,
            expanded: {},

            leafIds(node) {
                if (!node.children || node.children.length === 0) return [node.id];
                return node.children.flatMap((child) => this.leafIds(child));
            },

            isLeaf(node) {
                return !node.children || node.children.length === 0;
            },

            isChecked(node) {
                const ids = this.leafIds(node);
                return ids.length > 0 && ids.every((id) => this.checked.includes(id));
            },

            isIndeterminate(node) {
                if (this.isChecked(node)) return false;
                const ids = this.leafIds(node);
                return ids.some((id) => this.checked.includes(id));
            },

            toggle(node) {
                if (this.locked) return;

                const ids = this.leafIds(node);
                const willCheck = !this.isChecked(node);

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
