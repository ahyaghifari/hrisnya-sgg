import './bootstrap';
import { apiFetch } from './api';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

window.apiFetch = apiFetch;

// Auto-init select[data-tom-select] — dipakai untuk filter kategori Pelatihan, dst.
// wire:ignore di elemen pembungkus (lihat blade) mencegah Livewire morphing DOM
// yang disuntik Tom Select; sinkronisasi balik ke wire:model tetap jalan lewat
// event "change" native yang di-dispatch Tom Select ke <select> aslinya.
function initTomSelects() {
    document.querySelectorAll('select[data-tom-select]').forEach((el) => {
        if (el.tomselect) {
            return;
        }

        new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            placeholder: el.dataset.placeholder || '',
        });
    });
}

document.addEventListener('DOMContentLoaded', initTomSelects);
document.addEventListener('livewire:navigated', initTomSelects);
