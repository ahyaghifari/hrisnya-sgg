<?php

/**
 * Daftar jabatan (job position): id + nama + perusahaan.
 * Jalankan: php artisan tinker scripts/list_job_positions.php
 *
 * Filter perusahaan: isi $filter di bawah dengan sebagian nama / kode / id perusahaan
 * (case-insensitive). Kosongkan ('') untuk semua perusahaan.
 */

use App\Models\Master\JobPositions;

$filter = '';

$applyFilter = fn ($q) => $q->when($filter !== '', fn ($q) => $q->whereHas('company', fn ($c) => $c
    ->where('name', 'like', "%{$filter}%")
    ->orWhere('code', 'like', "%{$filter}%")
    ->orWhere('id', $filter)));

$applyFilter(JobPositions::with('company:id,name,code'))
    ->orderBy('company_id')
    ->orderBy('name')
    ->get(['id', 'name', 'company_id'])
    ->each(fn ($jp) => print($jp->id . "\t" . ($jp->company?->name ?? '-') . "\t" . $jp->name . PHP_EOL));

echo '-- total: ' . $applyFilter(JobPositions::query())->count() . ' jabatan'
    . ($filter !== '' ? " (filter: \"{$filter}\")" : '') . PHP_EOL;
