<?php

namespace App\Filament\Rekrutmen\Concerns;

use App\Models\Rekrutmen\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Selektor perusahaan halaman pipeline Rekrutmen (Livewire) dikunci ke company user —
 * kecuali pegang LIHAT_PERUSAHAAN_LAIN (User::canFilterByCompany()) yang boleh pilih bebas.
 * Tanpa permission: $companyId di-set otomatis ke perusahaan rekrutmen milik company user
 * (dipetakan lewat m_companies.code == companies.hris_code) dan dropdown-nya di-disable.
 */
trait ScopedToCompany
{
    /**
     * Livewire memanggil booted<Trait> tiap request (mount maupun update) — dipakai supaya
     * user tanpa izin tetap terkunci ke company-nya walau payload $companyId dioprek dari client.
     */
    public function bootedScopedToCompany(): void
    {
        if (! $this->canFilterByCompany()) {
            $this->companyId = $this->ownCompanyId();
        }
    }

    public function canFilterByCompany(): bool
    {
        return (bool) filament()->auth()->user()?->canFilterByCompany();
    }

    /** Perusahaan rekrutmen milik company user, atau null kalau tak ada padanannya. */
    public function ownCompanyId(): ?string
    {
        return Company::idForHrisCompany(filament()->auth()->user()?->company_id);
    }

    /** Query dasar daftar perusahaan — override kalau perlu filter tambahan (mis. requires_mcu). */
    protected function companiesQuery(): Builder
    {
        return Company::query()->orderBy('name');
    }

    public function getCompanies(): Collection
    {
        return $this->companiesQuery()
            ->when(! $this->canFilterByCompany(), fn (Builder $q) => $q->whereKey($this->ownCompanyId()))
            ->get(['id', 'name']);
    }
}
