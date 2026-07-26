import "./bootstrap";
import { apiFetch } from "./api";
import TomSelect from "tom-select";
import "tom-select/dist/css/tom-select.css";
import List from "list.js";
import Cropper from "cropperjs";
import "cropperjs/dist/cropper.css";

window.apiFetch = apiFetch;

// Fade transisi antar-halaman wire:navigate — class ditaruh di <html> (lihat komentar CSS
// .lw-navigating di app.css), bukan di .page-content, supaya tidak ikut ke-morph hilang saat
// konten baru masuk. "livewire:navigate" nembak pas navigasi MULAI (fade out), "livewire:navigated"
// nembak pas morph SELESAI & halaman baru siap (fade in).
document.addEventListener("livewire:navigate", () => {
    document.documentElement.classList.add("lw-navigating");
});
document.addEventListener("livewire:navigated", () => {
    document.documentElement.classList.remove("lw-navigating");
});

// Alpine component dipakai x-photo-upload-cropper.blade.php — crop foto profil (rasio 1:1)
// SEBELUM di-upload ke Livewire, dipanggil lewat $wire.upload() manual (bukan wire:model di
// <input> aslinya, biar file yang dikirim adalah hasil crop, bukan file mentah).
window.photoCropper = function (wireModel) {
    return {
        cropping: false,
        uploading: false,
        previewUrl: null,
        cropper: null,
        selectedFileName: "photo.jpg",

        onFileSelected(e) {
            const file = e.target.files[0];
            if (!file) return;

            this.selectedFileName = file.name || "photo.jpg";

            const reader = new FileReader();
            reader.onload = (ev) => {
                this.cropping = true;
                this.$nextTick(() => {
                    this.$refs.cropImage.src = ev.target.result;
                    if (this.cropper) this.cropper.destroy();
                    this.cropper = new Cropper(this.$refs.cropImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        background: false,
                        autoCropArea: 1,
                    });
                });
            };
            reader.readAsDataURL(file);
        },

        cancelCrop() {
            this.cropping = false;
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            this.$refs.fileInput.value = "";
        },

        confirmCrop() {
            if (!this.cropper) return;

            this.cropper.getCroppedCanvas({ width: 512, height: 512 }).toBlob((blob) => {
                if (!blob) return;

                const baseName = this.selectedFileName.replace(/\.[^.]+$/, "") || "photo";
                const file = new File([blob], `${baseName}.jpg`, { type: "image/jpeg" });

                this.previewUrl = URL.createObjectURL(blob);
                this.uploading = true;
                this.cropping = false;
                this.cropper.destroy();
                this.cropper = null;
                this.$refs.fileInput.value = "";

                this.$wire.upload(
                    wireModel,
                    file,
                    () => { this.uploading = false; },
                    () => { this.uploading = false; },
                );
            }, "image/jpeg", 0.9);
        },
    };
};

function initTomSelects() {
    document.querySelectorAll("select[data-tom-select]").forEach((el) => {
        if (el.tomselect) {
            return;
        }

        new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            placeholder: el.dataset.placeholder || "",
            plugins: el.multiple ? ["remove_button"] : [],
        });
    });
}

document.addEventListener("DOMContentLoaded", initTomSelects);
document.addEventListener("livewire:navigated", initTomSelects);

// Trix rich editor (CDN, lihat layout.blade.php) — dipakai form Bank Soal LMS.
// Pola: <trix-editor input="trix-{id}"> + <input type="hidden" id="trix-{id}" wire:model="...">.
//
// Trix otomatis sync ke <input hidden> pada event "trix-change", TAPI Livewire tidak otomatis
// tangkap perubahan value <input hidden> yang di-set via JS — wire:model dengarkan "input"
// event yang naik ke elemen itu. Re-dispatch "input" manual ke hidden input saat trix-change.
//
// Container trix-editor dibungkus <div wire:ignore> di blade supaya Livewire tidak morph
// toolbar Trix (yang di-generate oleh Trix sendiri, bukan Livewire) — tanpa wire:ignore,
// morph.updated nembak terus dan menghancurkan toolbar.
//
// Tracking pakai WeakSet (bukan attribute data-*), supaya tidak ada DOM mutation yang memicu
// morph loop Livewire.
const trixWired = new WeakSet();

function wireTrixEditor(editor) {
    if (trixWired.has(editor)) return;
    trixWired.add(editor);

    const inputId = editor.getAttribute("input");
    const hiddenInput = inputId ? document.getElementById(inputId) : null;

    // Isi Trix dari hidden input kalau hidden input sudah punya value (mis. modal edit dibuka
    // setelah Livewire morph isi wire:model). Trix render duluan, value datang belakanggan.
    if (hiddenInput && hiddenInput.value && editor.editor) {
        editor.editor.loadHTML(hiddenInput.value);
    }

    editor.addEventListener("trix-change", () => {
        if (hiddenInput) {
            hiddenInput.value = editor.value;
            hiddenInput.dispatchEvent(new Event("input", { bubbles: true }));
        }
    });

    // Bank Soal tidak butuh gambar inline — tolak attachment file, biarkan cuma teks formatted.
    editor.addEventListener("trix-attachment-add", (event) => {
        if (event.attachment && event.attachment.file) {
            event.attachment.remove();
        }
    });
}

function initTrixEditors() {
    // Trix script (CDN) mungkin belum selesai load pada DOMContentLoaded pertama — cek dulu.
    if (typeof window.Trix === "undefined") return;

    document.querySelectorAll("trix-editor").forEach(wireTrixEditor);
}

document.addEventListener("DOMContentLoaded", initTrixEditors);
document.addEventListener("livewire:navigated", initTrixEditors);
document.addEventListener("trix-initialize", (e) => {
    // Trix nembak "trix-initialize" tiap editor baru di-create — pastikan langsung di-wire.
    if (e.target && e.target.tagName === "TRIX-EDITOR") {
        wireTrixEditor(e.target);
    }
});
document.addEventListener("livewire:init", () => {
    Livewire.hook("morph.added", () => initTrixEditors());
});

// Tabel cari/sortir/pagination instan di browser (List.js, headless - tidak maksa style).
// Company/period tetap difilter lewat Livewire (butuh query server), tapi cari/sortir/ganti
// halaman tidak roundtrip lagi begitu barisnya sudah di layar.
const listInstances = new Map();

function initListTables() {
    document.querySelectorAll("[data-list-table]").forEach((el) => {
        if (listInstances.has(el.id)) {
            return;
        }

        const valueNames = (el.dataset.listValueNames || "")
            .split(",")
            .map((s) => s.trim())
            .filter(Boolean);
        const pageSize = parseInt(el.dataset.listPageSize || "0", 10);

        const list = new List(el.id, {
            valueNames,
            page: pageSize || 100000,
            pagination: pageSize > 0,
        });

        listInstances.set(el.id, list);
        wirePrevNextButtons(el, list);
        wireDeclarativeFilters(el, list);
        wireEmptyState(el, list);
    });
}

// Tampilkan pesan "tidak ada hasil" (elemen class list-empty-state di dalam container) kalau
// search/filter bikin 0 baris cocok — List.js sendiri cuma sembunyiin baris, tidak pernah
// kasih tau "kosong" secara visual.
function wireEmptyState(container, list) {
    const emptyEl = container.querySelector(".list-empty-state");

    if (!emptyEl) {
        return;
    }

    // .table-wrap = pembungkus visual (card/shadow) kalau .list cuma <tbody> di dalam <table>
    // (mis. MyStaff) - kalau tidak ada, .list sendiri yang jadi blok visualnya (mis. grid card
    // Pelatihan), langsung disembunyikan.
    const visualWrap = container.querySelector(".table-wrap") || container.querySelector(".list");

    const toggle = () => {
        const isEmpty = list.matchingItems.length === 0;
        emptyEl.style.display = isEmpty ? "" : "none";
        visualWrap.style.display = isEmpty ? "none" : "";
    };

    list.on("updated", toggle);
}

// Filter tambahan selain search (mis. kategori, status) - instan di browser, tanpa nyentuh
// query List.js bawaan (yang cuma soal search/sort/page). Konvensi dipakai bareng:
//   - Tiap card/baris data kasih `data-filter-<key>="value"` (mis. data-filter-category="123").
//   - Kontrol filter: <select data-list-filter-key="category"> (baca .value tiap "change"), atau
//     sekumpulan <button data-list-filter-key="category" data-list-filter-value="123"> (klik
//     toggle `is-active`, kosongkan data-list-filter-value buat tombol "semua").
// Semua kontrol dengan key yang sama di-AND-kan lewat list.filter() combined predicate.
function wireDeclarativeFilters(container, list) {
    const selects = container.querySelectorAll("select[data-list-filter-key]");
    const buttonGroups = new Map();

    container.querySelectorAll("button[data-list-filter-key]").forEach((btn) => {
        const key = btn.dataset.listFilterKey;
        if (!buttonGroups.has(key)) buttonGroups.set(key, []);
        buttonGroups.get(key).push(btn);
    });

    if (selects.length === 0 && buttonGroups.size === 0) {
        return;
    }

    const activeValues = new Map();

    const applyFilter = () => {
        list.filter((item) =>
            Array.from(activeValues.entries()).every(([key, value]) => {
                if (!value) return true;
                return item.elm.dataset["filter" + key.charAt(0).toUpperCase() + key.slice(1)] === value;
            })
        );
    };

    selects.forEach((select) => {
        select.addEventListener("change", () => {
            activeValues.set(select.dataset.listFilterKey, select.value);
            applyFilter();
        });
    });

    buttonGroups.forEach((buttons) => {
        buttons.forEach((btn) => {
            btn.addEventListener("click", () => {
                buttons.forEach((b) => b.classList.remove("is-active"));
                btn.classList.add("is-active");
                activeValues.set(btn.dataset.listFilterKey, btn.dataset.listFilterValue || "");
                applyFilter();
            });
        });
    });
}

// List.js bawaannya cuma render nomor halaman (lihat node_modules/list.js/src/pagination.js) -
// tidak ada tombol sebelumnya/selanjutnya sama sekali. Ditambah manual di sini lewat API
// list.show(startIndex, pageSize) yang sudah List.js sediakan.
function wirePrevNextButtons(container, list) {
    const prevBtn = container.querySelector(".list-prev");
    const nextBtn = container.querySelector(".list-next");

    if (!prevBtn && !nextBtn) {
        return;
    }

    const updateButtonState = () => {
        const totalPages = Math.ceil(list.matchingItems.length / list.page) || 1;
        const currentPage = Math.ceil(list.i / list.page);

        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
    };

    prevBtn?.addEventListener("click", () => {
        const newStart = Math.max(1, list.i - list.page);
        list.show(newStart, list.page);
    });

    nextBtn?.addEventListener("click", () => {
        const totalPages = Math.ceil(list.matchingItems.length / list.page) || 1;
        const newStart = Math.min((totalPages - 1) * list.page + 1, list.i + list.page);
        list.show(newStart, list.page);
    });

    list.on("updated", updateButtonState);
    updateButtonState();
}

function refreshListTables() {
    listInstances.forEach((list) => {
        // Kosongkan pencarian juga - data di belakangnya barusan ganti (mis. ganti perusahaan),
        // kata kunci lama gampang bikin baris baru kelihatan "hilang" padahal cuma kesaring.
        list.search("");
        const searchInput = list.listContainer.querySelector("input.search");
        if (searchInput) {
            searchInput.value = "";
        }

        list.reIndex();
        list.update();
    });
}

// Tooltip ".rincian-tooltip" (halaman Tambah Jadwal) posisinya position:fixed, dihitung dari
// getBoundingClientRect() tombolnya - CSS position:absolute biasa akan ke-clip oleh pembungkus
// tabel yang overflow-x:auto (perlu discroll di layar sempit). Delegated listener di document
// (bukan per-elemen) supaya tidak perlu di-rebind tiap Livewire morph baris tabelnya.
document.addEventListener("mouseover", (e) => {
    const wrap = e.target.closest(".rincian-tooltip-wrap");
    if (!wrap) {
        return;
    }

    const tooltip = wrap.querySelector(".rincian-tooltip");
    if (!tooltip) {
        return;
    }

    const rect = wrap.getBoundingClientRect();
    tooltip.style.top = rect.top + rect.height / 2 + "px";
    tooltip.style.left = rect.left - 10 + "px";
});

document.addEventListener("DOMContentLoaded", initListTables);
document.addEventListener("livewire:navigated", () => {
    listInstances.clear();
    initListTables();
});
document.addEventListener("livewire:init", () => {
    Livewire.on("list-table:refresh", () => {
        refreshListTables();
    });

    // Modal yang dirender kondisional (@if) baru punya elemen <select data-tom-select> setelah
    // Livewire morph — DOMContentLoaded/livewire:navigated saja tidak cukup buat elemen begini.
    // "morph.added" nembak buat node yang baru masuk DOM (kasus modal @if), "morph.updated"
    // buat node yang sudah ada tapi di-patch — pasang keduanya (initTomSelects sudah guard
    // el.tomselect jadi aman dipanggil berkali-kali).
    Livewire.hook("morph.added", () => initTomSelects());
    Livewire.hook("morph.updated", () => initTomSelects());

    // Sama seperti tom-select di atas: tabel [data-list-table] yang muncul belakangan lewat
    // @if (mis. tabel karyawan di CreateSchedulePage, baru ada di DOM setelah pilih tipe Shift)
    // tidak pernah lewat DOMContentLoaded/livewire:navigated — initListTables sudah guard
    // listInstances.has(el.id) jadi aman dipanggil berkali-kali.
    Livewire.hook("morph.added", () => initListTables());
});
