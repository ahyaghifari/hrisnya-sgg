<?php

namespace App\Livewire\Concerns;

use App\Enum\CustomPermissionEnum;
use Illuminate\Support\Facades\Auth;

/**
 * Dipakai halaman portal yang punya filter company (property $companyId) - satu tempat buat
 * logic "kunci companyId ke company sendiri kalau user tidak diizinkan filter lintas company
 * untuk sistem ini" yang sebelumnya diulang manual (if/elseif sama persis) di tiap mount()/
 * updatedCompanyId() halaman. Baca User::canFilterByCompanyForSystem() untuk aturan lengkapnya
 * (permission lihat_perusahaan_lain + config CompanyFilterAccess per sistem).
 */
trait GatesCompanyFilterBySystem
{
    /** $property default 'companyId' - beberapa halaman lama pakai nama properti beda
     * (mis. CreateKaryawan::$company_id), makanya bisa dioper eksplisit. */
    protected function lockCompanyIdUnlessAllowed(CustomPermissionEnum $system, string $property = 'companyId'): void
    {
        if (! Auth::user()->canFilterByCompanyForSystem($system)) {
            $this->{$property} = Auth::user()->company_id;
        }
    }
}
