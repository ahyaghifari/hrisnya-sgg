import "./bootstrap";
import { apiFetch } from "./api";
import TomSelect from "tom-select";
import "tom-select/dist/css/tom-select.css";
import List from "list.js";
import Cropper from "cropperjs";
import "cropperjs/dist/cropper.css";
import { Chart, registerables } from "chart.js";
import Sortable from "sortablejs";
import confetti from "canvas-confetti";

// Dipicu server (TaskListPage::moveTaskStatus) setelah tugas berhasil dipindah ke Selesai.
window.addEventListener("task-completed", () => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    confetti({ particleCount: 40, spread: 70, startVelocity: 35, scalar: 0.8, origin: { y: 0.7 }, zIndex: 10001 });
});

Chart.register(...registerables);

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
            // Livewire morph.updated nembak pas opsi <select> yang mendasarinya berubah (mis.
            // dropdown karyawan/coach yang reaktif ke company terpilih) - TomSelect TIDAK
            // otomatis baca ulang opsi barunya sendiri, widget-nya nyangkut kosong/lama kalau
            // tidak di-sync manual di sini.
            el.tomselect.sync();
            // sync() re-scan <option> dari DOM tapi TIDAK menyamakan item TERPILIH balik ke
            // el.value - Livewire nyetnya lewat property .value langsung (bukan atribut
            // `selected` di HTML), yang tidak dibaca sync(). Tanpa baris ini, pilihan tom-select
            // langsung "kepental" balik ke placeholder tiap kali wire:model.live nge-trigger
            // render ulang (2026-09-25). setValue(..., true) = silent, tidak memicu 'change'
            // lagi (hindari request Livewire berulang).
            el.tomselect.setValue(el.value, true);
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

    // Semua isian SP (Uraian/Kronologi + Berita Acara) dibatasi cuma Bold/Italic/Strike/Link -
    // heading/quote/code/bullet/number/indent/attach/undo/redo disembunyikan (instruksi user
    // 2026-09-25: field SP cukup format teks sederhana, uraian item juga sudah dinomori manual
    // per baris di blade, tombol numbered-list bawaan Trix cuma bikin nomor dobel).
    const isSpTrixField = inputId && /^trix-(item-|edit-item-|report-|dasar-klarifikasi$|kejadian$|subject-clarification$|analysis$|kesimpulan$|followup-plan$)/.test(inputId);
    if (isSpTrixField && editor.toolbarElement) {
        editor.toolbarElement.querySelectorAll(
            ".trix-button-group--block-tools, .trix-button-group--file-tools, .trix-button-group--history-tools"
        ).forEach((group) => group.style.setProperty("display", "none"));
    }
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

// Chart.js — dipasang lewat <canvas data-chart-config='{...}'> (JSON config Chart.js utuh:
// type/data/options), Blade sisi PHP yang susun datanya lewat json_encode(), bukan script
// inline per-widget. TIDAK dibungkus wire:ignore (beda dari versi awal) - beberapa chart
// (mis. ManpowerGapWidget) datanya reaktif ikut filter Livewire, wire:ignore bikin canvas-nya
// beku walau attribute data-chart-config sudah kebaruan dari server. Guard-nya jadi
// bandingkan STRING config yang lagi kepasang (data-chart-config-applied) vs yang baru -
// kalau beda, destroy instance lama & bikin baru; kalau sama, skip (aman dipanggil berkali-
// kali, sama pola dengan TomSelect/List.js di atas). morph.updated dipasang juga (bukan cuma
// morph.added) karena attribute berubah di canvas yang SUDAH ada di DOM, bukan node baru.
function initCharts() {
    document.querySelectorAll("canvas[data-chart-config]").forEach((canvas) => {
        const config = canvas.dataset.chartConfig;

        if (canvas.dataset.chartConfigApplied === config) {
            return;
        }

        const existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }

        new Chart(canvas, JSON.parse(config));
        canvas.dataset.chartConfigApplied = config;
    });
}

document.addEventListener("DOMContentLoaded", initCharts);
document.addEventListener("livewire:navigated", initCharts);
document.addEventListener("livewire:init", () => {
    Livewire.hook("morph.added", () => initCharts());
    Livewire.hook("morph.updated", () => initCharts());
});
document.addEventListener("livewire:init", () => {
    Livewire.on("list-table:refresh", () => {
        refreshListTables();
    });

    // Modal yang dirender kondisional (@if) baru punya elemen <select data-tom-select> setelah
    // Livewire morph — DOMContentLoaded/livewire:navigated saja tidak cukup buat elemen begini.
    // Pakai "morphed" (nembak SEKALI di akhir siklus morph 1 komponen, DOM sudah settle total)
    // — BUKAN "morph.updated" per-node, itu nembak per elemen selagi morphdom masih jalan,
    // beresiko kepanggil sebelum <option> anak-anaknya kelar dipatch (initTomSelects().sync()
    // baca opsi yang masih basi). initTomSelects() sudah guard: elemen baru -> init baru,
    // elemen yang sudah ada instance TomSelect-nya -> sync() baca ulang opsi dari <select> yang
    // sekarang, aman dipanggil berkali-kali.
    Livewire.hook("morphed", () => initTomSelects());

    // Sama seperti tom-select di atas: tabel [data-list-table] yang muncul belakangan lewat
    // @if (mis. tabel karyawan di CreateSchedulePage, baru ada di DOM setelah pilih tipe Shift)
    // tidak pernah lewat DOMContentLoaded/livewire:navigated — initListTables sudah guard
    // listInstances.has(el.id) jadi aman dipanggil berkali-kali.
    Livewire.hook("morph.added", () => initListTables());

    // Board Tugas: drag-and-drop card. Kontainer [data-status-list] pakai wire:ignore.self
    // supaya SortableJS bebas manipulasi DOM kontainer, tapi card di dalamnya tetap di-morph
    // normal - itu yang bikin card "bounce back" kalau moveTaskStatus() nolak drag ilegal.
    Livewire.hook("morphed", () => initTaskBoardSortables());
});
document.addEventListener("livewire:navigated", initTaskBoardSortables);

// Opsi drag kartu yang ramah HP: drag "buatan" SortableJS (bukan HTML5 DnD bawaan browser yang di
// Android susah dipakai, nggak bisa auto-scroll & ghost-nya meleset), ditahan sebentar dulu supaya
// scroll jari nggak ketimpa drag, ghost ditempel di <body> (nggak ikut CSS zoom papan), dan papan
// otomatis ke-scroll ke samping saat kartu didekatkan ke tepi.
const taskSortableTouchOptions = {
    animation: 150,
    forceFallback: true,
    fallbackOnBody: true,
    fallbackTolerance: 4,
    delay: 220,
    delayOnTouchOnly: true,
    touchStartThreshold: 6,
    scroll: true,
    bubbleScroll: true,
    scrollSensitivity: 90,
    scrollSpeed: 18,
    ghostClass: "sortable-ghost",
    chosenClass: "sortable-chosen",
};

function initTaskBoardSortables() {
    // List custom Board - semua anggota boleh drag (aturan siapa boleh mindahin dari List Review,
    // wajib review sebelum Selesai, dst dicek server di TaskService::moveCardsAcrossLists() -
    // drag yang ditolak balik ke posisi asal lewat morph berikutnya). Satu grup per komponen
    // Livewire biar card nggak nyasar antar Board.
    document.querySelectorAll("[data-list-id]").forEach((el) => {
        if (el._sortable) return;

        const wireId = el.closest("[wire\\:id]")?.getAttribute("wire:id") ?? null;
        el._sortable = Sortable.create(el, {
            group: "task-board-lists-" + (wireId || "default"),
            ...taskSortableTouchOptions,
            filter: "button, select, input, a, .task-card-locked",
            preventOnFilter: false,
            onStart() {
                window.taskDragging = true;
            },
            onEnd(evt) {
                window.taskDragging = false;
                if (!wireId) return;

                const cards = (list) => Array.from(list.children).filter((c) => c.dataset.taskId).map((c) => c.dataset.taskId);

                // Drop ke List Selesai wajib konfirmasi - batal = kartu balik ke posisi asal.
                if (evt.to !== evt.from && evt.to.dataset.listFinal === "1" && !confirm("Apakah tugas ini sudah selesai?")) {
                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] ?? null);
                    return;
                }

                // Drop ke List Review wajib konfirmasi juga - batal = kartu balik ke posisi asal.
                if (evt.to !== evt.from && evt.to.dataset.listReview === "1" && !confirm("Ajukan tugas ini untuk direview?")) {
                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] ?? null);
                    return;
                }

                const payload = {};
                payload[evt.from.dataset.listId] = cards(evt.from);
                if (evt.to !== evt.from) {
                    payload[evt.to.dataset.listId] = cards(evt.to);
                }

                Livewire.find(wireId).call("moveCardsAcrossLists", payload, evt.item.dataset.taskId);
            },
        });
    });

    // Drag cuma opsi TAMBAHAN, tombol/dropdown status yang ada tetap dipertahankan. Drag yang
    // melanggar aturan moveStatus() (loncat step, bukan assignee/creator) balik ke kolom asal
    // lewat morph berikutnya - tidak ada revert manual di sini. Sementara baru dipasang di
    // Todo Saya (cuma di sana kontainer-nya dikasih data-status-list).
    document.querySelectorAll("[data-status-list]").forEach((el) => {
        if (el._sortable) return;

        const wireId = el.closest("[wire\\:id]")?.getAttribute("wire:id") ?? null;
        el._sortable = Sortable.create(el, {
            group: "task-board-status-" + (wireId || "default"),
            ...taskSortableTouchOptions,
            // Tombol/dropdown di dalam card tetap bisa diklik tanpa memicu drag.
            filter: "button, select, input, a",
            preventOnFilter: false,
            onStart() {
                window.taskDragging = true;
            },
            onEnd(evt) {
                window.taskDragging = false;
                if (!wireId || evt.from === evt.to) return;

                if (evt.to.dataset.statusList === "review" && !confirm("Ajukan tugas ini untuk direview?")) {
                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] ?? null);
                    return;
                }

                // Pindah ke Selesai wajib konfirmasi - kalau batal, kartu dikembalikan ke posisi asal.
                if (evt.to.dataset.statusList === "complete" && !confirm("Apakah tugas ini sudah selesai?")) {
                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] ?? null);
                    return;
                }

                Livewire.find(wireId).call("moveTaskStatus", evt.item.dataset.taskId, evt.to.dataset.statusList);
            },
        });
    });
}
