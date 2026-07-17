import "./bootstrap";
import { apiFetch } from "./api";
import TomSelect from "tom-select";
import "tom-select/dist/css/tom-select.css";
import List from "list.js";

window.apiFetch = apiFetch;

function initTomSelects() {
    document.querySelectorAll("select[data-tom-select]").forEach((el) => {
        if (el.tomselect) {
            return;
        }

        new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            placeholder: el.dataset.placeholder || "",
        });
    });
}

document.addEventListener("DOMContentLoaded", initTomSelects);
document.addEventListener("livewire:navigated", initTomSelects);

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
});
